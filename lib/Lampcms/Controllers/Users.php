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

use Lampcms\Responder;

use Lampcms\WebPage;
use Lampcms\Paginator;
use Lampcms\Template\Urhere;
use Lampcms\Request;
use \Lampcms\Mongo\Schema\User as Schema;


/**
 * Controller for rendering
 * the "Members" page
 *
 * If request is by Ajax, it returns only the content
 * of the main area, paginated, sorted and with pagination
 * links if necessary
 *
 * @author Dmitri Snytkine
 *
 */
class Users extends WebPage
{

    /**
     * Users to show per page
     *
     * @var int
     */
    protected $perPage = 15;

    /**
     * @var string
     */
    protected $pagerPath = '{_users_}/{_SORT_NEW_}';


    /**
     * Will show this page
     * in 1-column layout, no right side nav
     *
     * @var int
     */
    protected $layoutID = 1;


    /**
     * Indicates the current tab
     *
     * @var string
     */
    protected $qtab = 'users';


    /**
     * Value of the sort $_GET param
     *
     * @var string
     */
    protected $sort;


    /**
     * Condition for MongoCursor sort
     *
     * Defaults to sort by reputation in
     * Descending order
     *
     * @var array
     */
    protected $sortOrder = array('i_rep' => -1);

    /**
     * @var int
     */
    protected $pageID = 1;


    /**
     * Mongo Cursor
     *
     * @var object of type MongoCursor
     */
    protected $Cursor;


    /**
     * Total number of users
     *
     * @var int
     */
    protected $usersCount;

    /**
     * Html block with users
     *
     * @var string html
     */
    protected $usersHtml;


    protected function main()
    {

        $this->pageID = $this->Registry->Router->getPageID();
        $this->init()
            ->getCursor()
            ->paginate()
            ->renderUsersHtml();

        /**
         * In case of Ajax request, just return
         * the content of the usersHtml
         * and don't proceed any further
         */
        if (Request::isAjax()) {
            Responder::sendJSON(array('paginated' => $this->usersHtml));
        }

        $this->setTitle()
            ->makeSortTabs()
            ->makeTopTabs()
            ->setUsers();
    }


    /**
     * Set value for title meta and title on page
     *
     * @return object $this
     */
    protected function setTitle()
    {
        $title                      = $this->Registry->Ini->SITE_TITLE . ' @@Members@@';
        $this->aPageVars['title']   = $title;
        $this->aPageVars['qheader'] = '<h1>' . $title . '</h1>';

        return $this;
    }


    /**
     * Initialize some instance variables
     * based on "sort" request param
     *
     * @throws \InvalidArgumentException if sort param is invalid
     *
     * @return object $this
     */
    protected function init()
    {
        $uriParts = $this->Registry->Ini->getSection('URI_PARTS');
        $this->perPage = $this->Registry->Ini->PER_PAGE_USERS;


       // $this->sort = $this->Registry->Request->get('sort', 's', 'rep');

        $this->sort = $this->Registry->Router->getSegment(1, 's', $uriParts['SORT_REPUTATION']);

        switch ( $this->sort ) {
            case $uriParts['SORT_ACTIVE']:
                $this->sortOrder = array('i_lm_ts' => -1);
                $this->pagerPath = '{_users_}/{_SORT_ACTIVE_}';
                break;

            case $uriParts['SORT_REPUTATION']:
                $this->sortOrder = array(Schema::REPUTATION => -1);
                $this->pagerPath = '{_users_}/{_SORT_REPUTATION_}';
                break;

            case $uriParts['SORT_NEW']:
                $this->sortOrder = array(Schema::PRIMARY => -1);
                $this->pagerPath = '{_users_}/{_SORT_NEW_}';
                break;


            case $uriParts['SORT_OLDEST']:
                $this->sortOrder = array(Schema::PRIMARY => 1);
                $this->pagerPath = '{_users_}/{_SORT_OLDEST_}';
                break;

            default:
                throw new \InvalidArgumentException('@@Invalid value of sort param@@: ' . $this->sort);
        }

        return $this;
    }


    /**
     * Sets top tabs for the page
     * making the "Members" the current active tab
     *
     * @return object $this
     */
    protected function makeTopTabs()
    {
        d('cp');
        $tabs                       = Urhere::factory($this->Registry)->get('tplToptabs', $this->qtab);
        $this->aPageVars['topTabs'] = $tabs;

        return $this;
    }


    /**
     * Paginate the results of cursor
     *
     * @return object $this
     */
    protected function paginate()
    {
        d('paginating');
        $oPaginator = Paginator::factory($this->Registry);
        $oPaginator->paginate($this->Cursor, $this->perPage,
            array('path'        => '{_WEB_ROOT_}/' . $this->pagerPath,
            'currentPage' => $this->pageID));

        $this->pagerLinks = $oPaginator->getLinks();

        return $this;
    }


    /**
     * Sets the value of "sort by" tabs
     *
     * Will not add any tabs if there are fewer than 4 users on the site
     * because there are just 4 "sort by" tabs
     * and there is no need to sort the results
     * of just 4 items
     *
     * @return object $this
     */
    protected function makeSortTabs()
    {

        $tabs = '';

        /**
         * Does not make sense to show
         * any type of 'sort by' when
         * Total number of users is
         * fewer than number of "sort by" tabs
         */
        if ($this->usersCount > 4) {

            $tabs = Urhere::factory($this->Registry)->get('tplUsertypes', $this->sort);
        }

        $aVars = array(
            $this->usersCount,
            (1 === $this->usersCount) ? 'User' : 'Users', $tabs);

        $this->aPageVars['body'] .= \tplUsersheader::parse($aVars, false);

        return $this;
    }


    /**
     * Runs the find() in the USERS collection
     * and sets the $this->Cursor instance variable
     *
     * @return object $this
     */
    protected function getCursor()
    {
        $where = array('role' => array('$ne' => 'deleted'));
        /**
         * Moderators can see deleted viewers
         */
        if ($this->Registry->Viewer->isModerator()) {
            $where = array();
        }

        $this->Cursor = $this->Registry->Mongo->USERS->find($where,
            array(
                '_id',
                'i_rep',
                'username',
                'fn',
                'mn',
                'ln',
                'email',
                'avatar',
                'avatar_external',
                'i_reg_ts',
                'i_lm_ts',
                'role'
            )
        );

        $this->Cursor->sort($this->sortOrder);
        $this->usersCount = $this->Cursor->count();

        return $this;
    }


    /**
     * Renders the content of the members block
     * and sets the $this->usersHtml instance variable
     * but does not yet add them to page
     * The Ajax based request will just
     * grab the result of this variable
     *
     * @return object $this
     */
    protected function renderUsersHtml()
    {
        $func      = null;
        $aGravatar = $this->Registry->Ini->getSection('GRAVATAR');

        if (count($aGravatar) > 0) {
            $func = function(&$a) use ($aGravatar)
            {
                $a['gravatar'] = $aGravatar;
            };
        }

        $this->usersHtml = '<div class="users_wrap">' . \tplU3::loop($this->Cursor, true, $func) . $this->pagerLinks . '</div>';

        return $this;
    }


    /**
     * Adds the content of users block
     * to page body
     *
     * @return object $this
     */
    protected function setUsers()
    {
        $this->aPageVars['body'] .= '<div id="all_users" class="sortable paginated" lampcms:total="' . $this->usersCount . '" lampcms:perpage="' . $this->perPage . '">' . $this->usersHtml . '</div>';

        return $this;
    }

}
