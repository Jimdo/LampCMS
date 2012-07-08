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


namespace Lampcms\Controllers;

use \Lampcms\Utf8String;
use \Lampcms\WebPage;
use \Lampcms\Request;
use \Lampcms\Responder;
use \Lampcms\CommentParser;
use \Lampcms\SubmittedCommentWWW;


/**
 * Controller is responsible for
 * parsing user generated comment
 * to question or answer
 *
 * @todo add support for replyid
 * comment may have replyid in which
 * case it will be a reply to existing comment
 * in case of reply an commentor (user) should
 * be notified when someone replied to their comment
 *
 * @author Dmitri Snytkine
 *
 */
class Addcomment extends WebPage
{
    protected $membersOnly = true;

    protected $aRequired = array('rid', 'com_body');

    protected $requireToken = true;

    protected $bRequirePost = true;

    /**
     * Resource for which this comment
     * is being processed
     * this will be either \Lampcms\Answer
     * or \Lampcms\Question
     * object
     *
     * @var object of type Lampcms\Answer
     * or \Lampcms\Question but will implement Lampcms\LampcmsObject
     */
    protected $Resource;


    protected $CommentParser;

    protected function main()
    {
        $this->Registry->registerObservers('INPUT_FILTERS');

        $this->getResource()
            ->checkPermission()
            ->add()
            ->returnResult();
    }


    /**
     * Use Comments class to add
     * comment to COMMENTS collection
     * and to push to the actual resource
     *
     * @return object $this
     */
    protected function add()
    {

        $this->CommentParser = new CommentParser($this->Registry);
        $this->CommentParser->add(new SubmittedCommentWWW($this->Registry, $this->Resource));

        return $this;
    }


    /**
     * Create object of type Question or Answer
     *
     * @throws \Lampcms\Exception
     * @return object $this
     */
    protected function getResource()
    {

        $a = $this->Registry->Mongo->RESOURCE->findOne(array('_id' => $this->Request['rid']));
        d('a: ' . print_r($a, 1));
        $collection = 'QUESTIONS';
        if (empty($a)) {
            throw new \Lampcms\Exception('RESOURCE NOT FOUND by id ' . $this->Request['rid']);
        }

        if (!empty($a['res_type']) && ('ANSWER' === $a['res_type'])) {
            $collection = 'ANSWERS';
        }

        $rid = (int)$this->Request['rid'];
        d('$collection: ' . $collection . ' $rid: ' . $rid);

        $coll = $this->Registry->Mongo->getCollection($collection);
        $a = $coll->findOne(array('_id' => $rid));

        if (empty($a)) {

            throw new \Lampcms\Exception('@@Item not found@@');
        }

        $class = ('QUESTIONS' === $collection) ? '\\Lampcms\\Question' : '\\Lampcms\\Answer';

        $this->Resource = new $class($this->Registry, $a);

        return $this;
    }


    /**
     * Who can comment?
     * Usually it's owner of resource
     * OR owner of question for which this resource is
     * an answer
     * OR someone with enough reputation
     *
     *
     * @throws \Exception
     * @throws \Lampcms\Exception
     * @return object $this
     */
    protected function checkPermission()
    {
        $viewerID = $this->Registry->Viewer->getUid();

        /**
         * If NOT question owner AND NOT Resource owner
         * AND Reputation below required
         * THEN must have 'comment' permission
         *
         * This means in order to comment Viewer
         * must be owner of Question OR owner of Answer
         * OR have enough reputation
         * OR have special 'comment' permission
         */
        if (
            ($this->Resource->getQuestionOwnerId() !== $viewerID) &&
            ($this->Resource->getOwnerId() !== $viewerID) &&
            ($this->Registry->Viewer->getReputation() < $this->Registry->Ini->POINTS->COMMENT)
        ) {
            try {
                $this->checkAccessPermission('comment');
            } catch (\Exception $e) {

                /**
                 * If this is an AuthException then it means
                 * user does not have 'comment' permission in the ACL
                 * which also means that user does not have
                 * the required reputation score.
                 * We will show a nice message then.
                 *
                 * In case it's some other type of exception just re-throw it
                 */
                if ($e instanceof \Lampcms\AccessException) {

                    /**
                     * @todo
                     * translate string
                     *
                     */
                    throw new \Lampcms\Exception('A minimum reputation score of ' . $this->Registry->Ini->POINTS->COMMENT .
                        ' is required to comment on someone else\'s question or answer.
					Your current reputation score is ' . $this->Registry->Viewer->getReputation());
                } else {
                    throw $e;
                }
            }
        }

        return $this;
    }


    /**
     * Return array of resourceID, type (A or Q)
     * and parsed div with comment
     *
     *
     */
    protected function returnResult()
    {
        $aComment = $this->CommentParser->getArrayCopy();

        /**
         * Add edit and delete tools because
         * Viewer already owns this comment and is
         * allowed to edit or delete it right away.
         * Javascript that usually dynamically adds these tools
         * is not going to be fired, so these tools
         * must already be included in the returned html
         *
         */
        $aComment['edit_delete'] = '  <span class="ico del ajax" title="@@delete@@">@@delete@@</span> <span class="ico edit ajax" title="@@edit@@">@@edit@@</span>';

        /**
         * Important to add owner_id key
         * because it's not in the comment array
         * It is used when creating the 'reply' link
         * in the tplComment
         * That ID is then used when figuring out if
         * viewer has permission to add comment.
         * Users with low reputation still always have
         * permission to add comments to own resources.
         *
         */
        $aComment['owner_id'] = $this->Resource->getOwnerId();

        $aRet = array(
            'comment' => array('id' => $aComment['_id'],
                'res' => $aComment['i_res'],
                'parent' => $aComment['i_prnt'],
                'html' => \tplComment::parse($aComment))
        );

        Responder::sendJSON($aRet);

    }
}
