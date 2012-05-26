<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
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
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
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


/**
 * Class responsible for
 * increasing/decreasing count
 * of tags in UNANSWERED_TAGS collection
 *
 * @todo this can be in Registry as singleton
 *
 * @author Dmitri Snytkine
 *
 */
class UnansweredTags extends LampcmsObject
{

    /**
     * Mongo collection UNANSWERED_TAGS
     *
     * @var object of type MongoCollection representing UNANSWERED_TAGS
     */
    protected $coll;

    public function __construct(Registry $Registry)
    {
        $this->Registry = $Registry;
        $this->coll = $Registry->Mongo->UNANSWERED_TAGS;

        $this->coll->ensureIndex(array('tag' => 1), array('unique' => true));
        $this->coll->ensureIndex(array('i_ts' => 1));
    }

    /**
     * Increases count for each tag
     * in tags from supplied question
     *
     * @param object $Question
     */
    public function set(Question $Question)
    {
        $aTags = $Question->offsetGet('a_tags');


        if (!is_array($aTags) || empty($aTags)) {

            return $this;
        }

        foreach ($aTags as $tag) {
            try {
                $this->coll->update(array("tag" => $tag), array('$inc' => array("i_count" => 1), '$set' => array('i_ts' => time(), 'hts' => date('F j, Y, g:i a T'))), array("upsert" => true));
            } catch (\MongoException $e) {
                e('unable to upsert UNANSWERED_TAGS: ' . $e->getMessage());
            }
        }
    }


    /**
     * Decrseases count of tag for given question
     * or completely removes that tag if count
     * is already at 1 because it does not make
     * sense to keep tag with a count of 0
     *
     * Every time a question is set as answered
     * this object/method should be invoked and pass
     * the question object to it
     *
     * @param Question $Question
     */
    public function remove($Question)
    {

        if (!is_array($Question) && (!($Question instanceof \Lampcms\Question))) {
            throw new \InvalidArgumentException('$Question must be array OR instance of Question. was: ' . gettype($Question));
        }

        $aTags = (is_array($Question)) ? $Question : $Question['a_tags'];


        if (empty($aTags) || !is_array($aTags)) {

            return;
        }

        /**
         * If tag exists in collection and > 0 then decrsease count,
         * otherwise remove it
         *
         */
        foreach ($aTags as $tag) {
            $aItem = $this->coll->findOne(array('tag' => $tag));
            if (null === $aItem) {
                //d('No record for tag: '.$tag.' in UNANSWERED_TAGS');
                continue;
            }

            if (1 === $aItem['i_count']) {
                //d('removing tag: '.$tag.' from UNANSWERED_TAGS');
                $this->coll->remove(array('tag' => $tag));

            } else {
                //d('decreasing count for tag: '.$tag.' in UNANSWERED_TAGS');
                try {
                    $this->coll->update(array("tag" => $tag), array('$inc' => array("i_count" => -1)));
                } catch (\MongoException $e) {
                    //e('unable to update UNANSWERED_TAGS collection: '.$e->getMessage());
                }
            }
        }

        /**
         * Post onAcceptedAnswer
         * the tagsUnanswered have to be unset from cache
         * as well as posssibly some other cached items
         */
        $this->Registry->Dispatcher->post($this, 'onRemovedUnansweredTags', $aTags);
    }
}
