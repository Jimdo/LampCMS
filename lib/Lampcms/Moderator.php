<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is licensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 *       the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attributes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2012 (or current year) Dmitri Snytkine
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;

use \Lampcms\IndexerFactory;
use \Lampcms\Mongo\Schema\Resource as ResourceSchema;
use \Lampcms\Mongo\Schema\User as UserSchema;

/**
 * Helper class for approving
 * a pending question and answer by a moderator
 * This is not a controller, this class is
 * called from a controller or from an api controller
 *
 */
class Moderator extends Base
{

    /**
     * @var object Question or Answer
     */
    protected $Resource;

    /**
     * @var object Question when approving an Answer
     * we need a parent Question object to post it to event dispatcher
     */
    protected $Question;


    /**
     *
     * Set the value of $this->Resource, set resource i_status
     * to value of 2 (posted), adds the name and uid of moderator
     * who approved the item,
     * update the user account of item author to increase their
     * count of approved items
     *
     * @param int       $id
     * @param string    $type emun 'q', 'a'
     *
     * @throws \Lampcms\DevException if $id is not numeric or $type is not enum 'q', 'a'
     * @throws \Lampcms\Exception if question/answer is deleted or not found by $id
     * @return object $this
     */
    public function approveResource($id, $type = 'q')
    {
        /**
         * First check that Viewer is a moderator
         * only moderator can approve a pending resource
         * If Viewer does not have permission then AccessException will be thrown
         */
        $this->checkAccessPermission('approve_pending');

        if (!is_numeric($id)) {
            throw new DevException('Value of $id was not numeric. Passed value was: ' . $id);
        }

        if ($type !== 'q' && $type !== 'a') {
            throw new DevException('Unknown resource type. Can only be "a" or "q". Passed value was: ' . $type);
        }

        if ('q' === $type) {
            $a = $this->Registry->Mongo->QUESTIONS->findOne(array(ResourceSchema::PRIMARY => (int)$id));
        } else {
            $a = $this->Registry->Mongo->ANSWERS->findOne(array(ResourceSchema::PRIMARY => (int)$id));
        }

        if (empty($a)) {
            throw new \Lampcms\Exception('@@Question not found@@');
        }

        if (!empty($a[ResourceSchema::DELETED_TIMESTAMP])) {

            throw new \Lampcms\Exception('@@This item was deleted on@@ ' . date('r', $a[ResourceSchema::DELETED_TIMESTAMP]));
        }

        if ('q' === $type) {
            $this->Resource = new Question($this->Registry, $a);
        } else {
            $this->Resource = new Answer($this->Registry, $a);
        }

        if (true === $res = $this->Resource->setApprovedStatus($this->Registry->Viewer)) {
            $this->updatePoster();
            if ('q' === $type) {
                try {
                    IndexerFactory::factory($this->Registry)->indexQuestion($this->Resource);
                } catch ( \Exception $e ) {
                    $err = ('Exception: ' . get_class($e) . ' Unable to add question to search index because: ' . $e->getMessage() . ' Error Code: ' . $e->getCode() . ' trace: ' . $e->getTraceAsString());
                    d($err);
                }
                $this->Registry->Dispatcher->post($this->Resource, 'onApprovedQuestion');
            } else {
                $this->Registry->Dispatcher->post($this->Resource, 'onApprovedAnswer', array('question' => $this->getQuestion($this->Resource->getQuestionId())));
            }

            d('Approval complete for resource type ' . $type . ' id: ' . $id);
        } else {
            d('Item was already approved');
        }


        return $this;
    }


    /**
     * Getter for $this->Question
     *
     *
     * @param int $qid
     *
     * @throws Exception
     * @return object of type Question representing the Question
     * for which we parsing the answer
     */
    protected function getQuestion($qid)
    {
        if (!isset($this->Question)) {
            $a = $this->Registry->Mongo->QUESTIONS->findOne(array(ResourceSchema::PRIMARY => $qid));

            if (empty($a)) {
                e('Cannot find question with _id: ' . $qid);

                throw new Exception('@@Unable to find parent question for this answer@@');
            }

            $this->Question = new Question($this->Registry, $a);
        }

        return $this->Question;
    }

    /**
     * Increase count of approved posts for Resource author
     *
     * @return object $this
     */
    protected function updatePoster()
    {
        $uid = $this->Resource->getOwnerId();

        d('Before incrementing approved items count for user uid: ' . $uid);

        try {
            $res = $this->Registry->Mongo->USERS->update(array(ResourceSchema::PRIMARY => $uid), array('$inc' => array(UserSchema::APPROVED_COUNTER => 1)));
            d('Result of updating poster account: ' . $res);
        } catch ( \MongoException $e ) {
            e('Unable to increase user\'s approved items count ' . $e->getMessage());
        }

        return $this;
    }

}
