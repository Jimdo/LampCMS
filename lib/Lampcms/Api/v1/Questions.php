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


namespace Lampcms\Api\v1;

use Lampcms\Api\Api;

/**
 * Possible request options:
 * sort by "last_modify" asc/desc
 * include type: all/unanswered/noanswer
 * tags: all (default) or with list of tags
 * list of tags must be urlencoded and comma-separated
 * startwith = first ID
 * limit = how many with max of 100;
 *
 * Include options: will NOT include comments...
 * will not include contributors or followers
 * will not include some other things.
 * will not include a_title
 * will not include sim_q, tumblr, blogger, twitter ids
 * will include a_tags
 * will not include body but will include intro.
 * this will be configurable to include body too.
 * will include userid and username and avtr
 *
 *
 * Will not include h_ts, only timestapm i_ts and i_lm_ts
 *
 * Optional: some fields may be optional like geo.
 * Enter description here ...
 * @author admin
 *
 */
class Questions extends Api
{

    /**
     * Min value of _id from which
     * to include results
     *
     * @var int
     */
    protected $startId;

    protected $maxId;

    /**
     * If this value is set,
     * only questions by this user
     * will be returned
     *
     * @var int
     */
    protected $userId;

    /**
     * Type of questions to include
     * in result
     * This param is set via type param in url
     * can be one of these:
     * 'unans' (no answers),
     * 'answrd' (has answers but not an accepted answer),
     * 'accptd' (has accepted answer)
     *
     * @var string
     */
    protected $type = null;

    /**
     * Array of tags
     * If this array is set then
     * returned questions will
     * have these tags.
     *
     * @var array
     */
    protected $aTags = null;


    /**
     * How to match the array of tags
     * if match=any then this is set to '$in'
     * if match=all then this is set to '$all'
     * this value is then used when querying Mongo
     *
     *
     * @var string
     */
    protected $tagsMatch = '$in';


    /**
     * Number of found results in cursor
     *
     * @var int
     */
    protected $count = 0;

    /**
     * Fields to exclude from result
     *
     * @var array
     */
    protected $aFields = array(
        'a_title' => 0,
        'i_views' => 0,
        'i_favs' => 0,
        'i_flwrs' => 0,
        'ulink' => 0,
        'b' => 0,
        'tags_html' => 0,
        'hts' => 0,
        'credits' => 0,
        'ans_s' => 0,
        'v_s' => 0,
        'f_s' => 0,
        'a_uids' => 0,
        'a_flwrs' => 0,
        'sim_q' => 0,
        'vw_s' => 0,
        'a_comments' => 0,
        'a_latest' => 0,
        'i_del_ts' => 0,
        'a_deleted' => 0);

    /**
     * Allowed values of the 'sort' param
     *
     * @var array
     */
    protected $allowedSortBy = array('i_lm_ts', '_id', 'i_ans', 'i_votes');


    protected function main()
    {
        $this->setQuestionType()
            ->setTags()
            ->setTagsCondition()
            ->setSortBy()
            ->setSortOrder()
            ->setStartId()
            ->setMaxId()
            ->setUserId()
            ->setStartTime()
        //->setEndTime()
            ->setLimit()
            ->getCursor()
            ->setOutput();
    }


    protected function getCursor()
    {

        if (true === $this->Request->get('comments', 'b')) {
            unset($this->aFields['a_comments']);
        }

        if (true === $this->Request->get('body', 'b')) {
            unset($this->aFields['b']);
        }

        $where = array('i_del_ts' => null);

        if ($this->type) {
            $where['status'] = $this->type;
        }

        if (!empty($this->aTags)) {
            $match[$this->tagsMatch] = $this->aTags;
            $where['a_tags'] = $match;
        }

        /*if($this->endTime){
              $where['i_lm_ts'] = array('$lt' => (int)$this->endTime);
              }*/

        if ($this->startTime) {
            $where['i_lm_ts'] = array('$gt' => (int)$this->startTime);
        }

        if ($this->startId) {
            $where['_id'] = array('$gt' => (int)$this->startId);
        }


        if ($this->maxId) {
            $where['_id'] = array('$lt' => (int)$this->maxId);
        }

        if (isset($this->userId)) {

            $where['i_uid'] = $this->userId;
        }

        d('$where: ' . print_r($where, 1));


        $sort[$this->sortBy] = $this->sortOrder;

        $offset = (($this->pageID - 1) * $this->limit);
        d('offset: ' . $offset);

        $this->cursor = $this->Registry->Mongo->QUESTIONS->find($where, $this->aFields)
            ->sort($sort)
            ->limit($this->limit)
            ->skip($offset);

        $this->count = $this->cursor->count();
        d('count: ' . $this->count);

        if (0 === $this->count) {
            d('No results found for this query: ' . print_r($where, 1));

            throw new \Lampcms\HttpResponseCodeException('No matches for your request', 404);
        }

        return $this;
    }


    /**
     * Popular the $this->Output object
     * with data
     * Output object will format this data to
     * appropriate format (json or jsonc or xml),
     * depending on type of Output object
     *
     * @return object $this
     */
    protected function setOutput()
    {
        $data = array('total' => $this->count,
            'page' => $this->pageID,
            'perpage' => $this->limit,
            'questions' => \iterator_to_array($this->cursor, false));

        $this->Output->setData($data);

        return $this;
    }


    /**
     * Set value of $this->userId based
     * of uid request param
     *
     * @return object $this
     */
    protected function setUserId()
    {
        $id = $this->Request->get('uid', 'i', null);
        if (!empty($id)) {
            $this->userId = $id;
            d('$this->userId ' . $this->userId);
        }

        return $this;
    }


    /**
     * If tags passed in request
     * then create array of $this->aTags
     *
     * @return object $this
     */
    protected function setTags()
    {
        $tags = $this->Request->get('tags', 's', '');
        //$tags = \urldecode($tags); // server already urldecodes stuff

        if (empty($tags)) {
            d('no tags in url');

            return $this;
        }

        $this->aTags = \explode(' ', $tags);
        $this->aTags = \array_filter($this->aTags);
        d('aTags: ' . \print_r($this->aTags, 1));

        return $this;
    }


    /**
     * Get match condition for tags (any or all)
     * Skips this step if $this->aTags is empty
     * because it's meaningless to use tagsMatch condition
     * if there will not be a search by tags
     *
     * @throws \Lampcms\HttpResponseCodeException
     *
     * @return object $this
     */
    protected function setTagsCondition()
    {
        if (empty($this->aTags)) {

            return $this;
        }

        $cond = $this->Request->get('match', 's', 'any');
        $allowed = array('any', 'all');
        if (!\in_array($cond, $allowed)) {
            throw new \Lampcms\HttpResponseCodeException('Invalid value of "match" param in request. Allowed values are: ' . implode(', ', $allowed) . ' Value was" ' . $cond, 406);
        }

        $this->tagsMatch = ('any' === $cond) ? '$in' : '$all';

        return $this;
    }


    /**
     * Set value of $this->type
     * can be one of unans, answrd or accptd
     *
     * @throws \Lampcms\HttpResponseCodeException
     *
     * @return object $this
     */
    protected function setQuestionType()
    {
        $allowed = array('unans', 'answrd', 'accptd');
        $type = $this->Request->get('type', 's', null);
        d('$type: ' . var_export($type, true));

        if (!empty($type)) {

            if (!\in_array($type, $allowed)) {
                throw new \Lampcms\HttpResponseCodeException('Invalid value of "type" param in request. Allowed values are: ' . implode(', ', $allowed) . ' Value was" ' . $type, 406);
            }

            $this->type = $type;
        }

        return $this;
    }


    /**
     * Set value of startId based on start param
     *
     * @return object $this
     */
    protected function setStartId()
    {
        $id = $this->Request->get('start_id', 'i', null);
        if (!empty($id)) {
            $this->startId = $id;
        }

        return $this;
    }


    /**
     * Set value of startId based on start param
     *
     * @return object $this
     */
    protected function setMaxId()
    {
        $id = $this->Request->get('max_id', 'i', null);
        if (!empty($id)) {
            $this->maxId = $id;
        }

        return $this;
    }

}
