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
 * Class responsible for creating
 * block with div with answers for
 * one question. Answers are found
 * in ANSWERS collection, pagination
 * is applied as necessary, sort order
 * will also be applied according to
 * param from Request
 *
 * @author Dmitri Snytkine
 *
 */
class Answers extends LampcmsObject
{

    /**
     * Mongo cursor
     *
     * @var object \MongoCursor
     */
    protected $Cursor;

    protected $pagetPath;


    public function __construct(Registry $Registry)
    {
        $this->Registry = $Registry;

    }


    /**
     * Get html div with answers for this one question,
     * sorted according to param passed in request
     *
     * Enclose result in <div> and add pagination to bottom
     * of div if there is any pagination necessary!
     *
     * @todo add skip() and limit() to cursor
     *       in order to use pagination.
     *
     * @param Question $Question
     *
     * @param string   $result desired format of result. Possible
     *                         options are: html, array or json object
     *
     *
     *
     * @throws Exception
     * @return string html block
     */
    public function getAnswers(Question $Question, $result = 'html')
    {

        $qid    = $Question['_id'];
        $url    = $Question['url'];
        $pageID = $this->Registry->Router->getPageID();
        d('url: ' . $url . ' $pageID: ' . $pageID);


        $this->pagetPath = $Question->getUrl() . '/';

        $urlParts = $this->Registry->Ini->getSection('URI_PARTS');

        $cond = $this->Registry->Router->getSegment(3, 's', $urlParts['SORT_BEST']);

        d('cond: ' . $cond);
        $noComments = (false === (bool)$this->Registry->Ini->MAX_COMMENTS);
        d('no comments: ' . $noComments);
        $aFields = ($noComments || false === (bool)$this->Registry->Ini->SHOW_COMMENTS) ? array('comments' => 0) : array();
        /**
         * Extra security validation,
         * IMPORTANT because we should never use
         * anything in Mongo methods directly from
         * user input
         */
        if (!in_array($cond, array($urlParts['SORT_RECENT'], $urlParts['SORT_BEST'], $urlParts['SORT_OLDEST']))) {
            throw new Exception('Invalid value of param "cond" was: ' . $cond);
        }

        $where = array('i_qid' => $qid);
        if (!$this->Registry->Viewer->isModerator()) {
            d('not moderator');
            $where['i_del_ts'] = null;
        }

        switch ( $cond ) {
            case $urlParts['SORT_RECENT']:
                $sort = array('i_lm_ts' => -1);
                break;

            case $urlParts['SORT_OLDEST']:
                $sort = array('i_ts' => 1);
                break;

            case $urlParts['SORT_BEST']:
            default:
                $sort = array('accepted' => -1,
                              'i_votes'  => -1);
        }

        $cursor = $this->Registry->Mongo->ANSWERS->find($where, $aFields);
        d('$cursor: ' . gettype($cursor));
        $cursor->sort($sort);
        $oPager = Paginator::factory($this->Registry);
        $oPager->paginate($cursor, $this->Registry->Ini->PER_PAGE_ANSWERS,
            array('path'        => $this->pagetPath . $cond,
                  'append'      => false,
                  'currentPage' => $pageID));

        $pagerLinks = $oPager->getLinks();

        $ownerId  = $Question['i_uid'];
        $showLink = (($ownerId > 0) && ($this->Registry->Viewer->isModerator() || $ownerId == $this->Registry->Viewer->getUid()));

        $noComments = ($noComments) ? ' nocomments' : '';

        $func = function(&$a) use ($showLink, $noComments)
        {
            /**
             * Don't show Accept link for
             * already accepted answer
             */
            if (!($a['accepted'])) {
                if ($showLink) {
                    $a['accept_link'] = '<a class="accept ttt" title="@@Click to accept this as best answer@@" href="{_WEB_ROOT_}/{_accept_}/' . $a['_id'] . '">@@Accept@@</a>';
                }
            } else {
                $a['accepted'] = '<img src="{_IMAGE_SITE_}{_DIR_}/images/accepted.png" alt="@@Best answer@@" class="ttt" title="@@Owner of the question accepted this as best answer@@">';
            }

            $a['nocomments'] = $noComments;
            $a['edited']     = '@@Edited@@';
        };

        /**
         * Create div with answers, append pagination links
         * to bottom and return the whole div block
         */
        $answers = \tplAnswer::loop($cursor, true, $func) . $pagerLinks;

        return $answers;

    }
}
