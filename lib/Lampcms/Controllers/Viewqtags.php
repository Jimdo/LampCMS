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
 *    the website's Questions/Answers functionality is powered by lampcms.com
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

use Lampcms\WebPage;
use Lampcms\Paginator;
use Lampcms\Template\Urhere;
use Lampcms\Request;
use Lampcms\Responder;

/**
 * Controller for displaying page with
 * all available tags for all questions
 * List can be sorted by name, by count and by latest question with
 * this tag
 *
 * Results are paginated
 * A search form will also be on the page to allow to find specific tag
 * Later the search will be ajaxified
 */
class Viewqtags extends Viewquestions
{

    /**
     * Indicates the current tab
     *
     * @var string
     */
    protected $qtab = 'tags';

    protected $pagerPath = '{_viewqtags_}/{_SORT_TAG_NAME_}';

    protected $PER_PAGE = 60;

    /**
     * Pagination links on the page
     * will not be handled by Ajax
     *
     * @var bool
     */
    protected $notAjaxPaginatable = false;


    /**
     * Select items according to conditions passed in GET
     * Conditions can be == 'unanswered', 'hot', 'recent' (default)
     *
     * @return \Lampcms\Controllers\Viewqtags
     */
    protected function getCursor()
    {
        $uriParts = $this->Registry->Ini->getSection('URI_PARTS');
        $aFields  = array('tag', 'i_count', 'hts');

        $cond = $this->Router->getSegment(1, 's', $uriParts['SORT_POPULAR']);
        d('cond: ' . $cond);

        $this->title = '@@Tags@@';

        switch ( $cond ) {
            /**
             * Hot is strictly per views
             */
            case $uriParts['SORT_POPULAR']:
                $sort            = array('i_count' => -1);
                $this->title     = '@@Popular tags@@';
                $this->pagerPath = '{_viewqtags_}/{_SORT_POPULAR_}';
                d('cp');
                break;


            /**
             * Most answers/comments/views
             * There is an activity counter
             * 1 point per view, 10 point per comment,
             * 50 points per answer
             * but still limit to 30 days
             * Cache Tags for 1 day only
             * uncache onQuestionVote, onQuestionComment
             */
            case $uriParts['SORT_RECENT']:
                $this->title     = '@@Recent tags@@';
                $sort            = array('i_ts' => -1);
                $this->pagerPath = '{_viewqtags_}/{_SORT_RECENT_}';
                d('cp');
                break;


            /**
             * Default is all questions
             * Tags are qrecent
             * uncache qrecent onNewQuestion only!
             */
            default:
                $sort = array('tag' => 1);
        }

        $this->typeDiv = Urhere::factory($this->Registry)->get('tplTagsort', $cond);

        $this->Cursor = $this->Registry->Mongo->QUESTION_TAGS->find(array('i_count' => array('$gt' => 0)), $aFields);
        $this->count  = $this->Cursor->count(true);
        d('$this->Cursor: ' . gettype($this->Cursor) . ' $this->count: ' . $this->count);
        $this->Cursor->sort($sort);

        return $this;
    }


    /**
     * No cache headers for this page but
     * if this is an ajax request then return
     * the html we have so far as it does not make
     * sense to process any further methods
     *
     * @see wwwViewquestions::sendCacheHeaders()
     * @return \Lampcms\Controllers\Viewqtags
     */
    protected function sendCacheHeaders()
    {

        if (Request::isAjax()) {
            $sQdivs = \tplTagslist::loop($this->Cursor);
            Responder::sendJSON(array('paginated' => '<div class="tags_wrap">' . $sQdivs . $this->pagerLinks . '</div>'));
        }

        return $this;
    }


    /**
     * Make html block for
     * the top-right position
     * For this type of page it
     * does not actually contain any count, just
     * an informative text message
     *
     * (non-PHPdoc)
     *
     * @see Lampcms\Controllers.Viewquestions::makeCounterBlock()
     * @return \Lampcms\Controllers\Viewqtags
     */
    protected function makeCounterBlock()
    {

        $text = '@@Unique Tags@@';

        $description = 'A tag is a keyword or label that categorizes your question with other, similar questions. Using the right tags makes it easier for others to find and answer your question.';

        $this->aPageVars['topRight'] = \tplCounterblock::parse(array($this->count, $text, $description), false);

        return $this;
    }


    /**
     * (non-PHPdoc)
     *
     * @see Lampcms\Controllers.Viewquestions::makeQlistBody()
     * @return \Lampcms\Controllers\Viewqtags
     */
    protected function makeQlistBody()
    {

        $sQdivs                  = \tplTagslist::loop($this->Cursor);
        $sQlist                  = \tplQlist::parse(array($this->typeDiv, $sQdivs . $this->pagerLinks, '', $this->notAjaxPaginatable), false);
        $this->aPageVars['body'] = $sQlist;
        /**
         * In case of Ajax can just send out sQlist as result
         */
        return $this;
    }
}

