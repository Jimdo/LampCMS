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
 * 	  the page that lists the recent questions (usually home page) must include
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


namespace Lampcms\Controllers;


use Lampcms\WebPage;
use Lampcms\Paginator;
use Lampcms\Template\Urhere;

/**
 * Controller for generating questions view
 * The Home page uses it, the Unanswered page controller
 * extends it, the Tagged page controller extends it
 *
 * @author Dmitri Snytkine
 *
 */
class Viewquestions extends WebPage
{

	protected $pagerPath = '/questions';

	/**
	 * Indicates the current tab
	 *
	 * @var string
	 */
	protected $qtab = 'questions';

	/**
	 * Object MongoCursor
	 * @var Object MongoCursor
	 */
	protected $Cursor = null;

	protected $pageID = 1;

	protected $title = '';

	protected $tagsDiv = '';

	protected $typeDiv = '';

	protected $pageHead = '';

	protected $aTplVars = array();

	/**
	 * Indicates the number of matched items like
	 * 123 Questions with no answers
	 *
	 * @var int
	 */
	protected $count;

	protected $PER_PAGE = 20;

	protected $counterTaggedText = '';

	/**
	 * Exclude these fields from
	 * select for effeciency
	 *
	 * @var array
	 */
	protected $aFields = array(
		'a_title' => 0,
		'sim_q' => 0,
		'a_comments' => 0
	);

	/**
	 * Pagination links on the page
	 * will not be handled by Ajax
	 *
	 * @var bool
	 */
	protected $notAjaxPaginatable = true;

	protected function main(){

		$this->pageID = (int)$this->Request->get('pageID', 'i', 1);

		$this->getCursor()
		->paginate()
		->sendCacheHeaders();

		$this->aPageVars['title'] = $this->title;
		$this->aPageVars['description'] = $this->_('My cool site');
		$this->makeTopTabs()
		->makeQlistHeader()
		->makeCounterBlock()
		->makeQlistBody()
		->makeFollowedTags()
		->makeRecentTags();

	}


	/**
	 * Select items according to conditions passed in GET
	 * Conditions can be == 'unanswered', 'hot', 'recent' (default)
	 */
	protected function getCursor(){
		$this->PER_PAGE = $this->Registry->Ini->PER_PAGE_QUESTIONS;


		$cond = $this->Request->get('cond', 's', 'recent');
		d('cond: '.$cond);
		$where = array();
		/**
		 * Default sort is by timestamp Descending
		 * meaning most recent should be on top
		 *
		 */
		$sort = array('i_sticky' => -1, 'i_lm_ts' => -1);

		$this->title;

		switch($cond){
			/**
			 * Hot is strictly per views
			 */
			case 'hot':
				
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
			case 'active':
				$this->title = $this->_('Active Questions');
				$this->pagerPath = '/active';
				$this->typeDiv = Urhere::factory($this->Registry)->get('tplQtypesdiv', 'active');
				$where = array('i_ts' => array('$gt' => (time() - 604800)));
				$sort = array('i_ans' => -1);
				break;
				/**
				 * Most votes but still
				 * creation/last activity date must be
				 * within reasonable time like not older than 30 days
				 * Cache Tags for one day only
				 * and uncache when new votes comes in
				 * onQuestionVote
				 */
			case 'voted':
				$this->pagerPath = '/voted';
				d('cp');
				$this->title = $this->_('Questions with highest votes in past 7 days');
				$this->typeDiv = Urhere::factory($this->Registry)->get('tplQtypesdiv', 'voted');
				$where = array('i_ts' => array('$gt' => (time() - 604800)));
				$sort = array('i_votes' => -1);
				break;

				/**
				 * Default is all questions
				 * Tags are qrecent
				 * uncache qrecent onNewQuestion only!
				 */
			default:
				$this->title = $this->_('All questions');
				$this->typeDiv = Urhere::factory($this->Registry)->get('tplQtypesdiv', 'newest');
		}

		/**
		 * Exclude deleted items
		 */
		$where['i_del_ts'] = null;


		/**
		 * For effecienty explicitely specify which
		 * doc fields to select or at least tell
		 * which NOT to select, for example we don't need
		 * a_edited and a_title
		 *
		 */
		$this->Cursor = $this->Registry->Mongo->QUESTIONS->find($where, $this->aFields);
		d('$this->Cursor: '.gettype($this->Cursor));
		$this->Cursor->sort($sort);

		return $this;
	}


	/**
	 * Create a paginator object
	 * and paginate results of select
	 *
	 * @return object $this
	 */
	protected function paginate(){
		d('paginating with $this->pagerPath: '.$this->pagerPath);
		$Paginator = Paginator::factory($this->Registry);
		$Paginator->paginate($this->Cursor, $this->PER_PAGE,
		array('path' => $this->pagerPath));

		$this->pagerLinks = $Paginator->getLinks();

		d('$this->pagerLinks: '.$this->pagerLinks);

		return $this;
	}


	/**
	 * Generates html of the "recent tags"
	 * block
	 *
	 * If user is logged in AND
	 * has 'followed tags' then don't use
	 * cache and instead do this:
	 * get array of recent tags, sort in a way
	 * than user's tags are on top and then render
	 * This way user's tags will always be on top
	 * at a cost of couple of milliseconds we get
	 * a nice personalization that does increase
	 * the click rate!
	 *
	 * @return object $this
	 */
	protected function makeRecentTags(){

		$aUserTags = $this->Registry->Viewer['a_f_t'];
		if(!empty($aUserTags)){
			$s = $this->getSortedRecentTags($aUserTags);
		} else {
			$s = $this->Registry->Cache->get('qrecent');
		}

		$tags = \tplBoxrecent::parse(array('tags' => $s, 'title' => $this->_('Recent Tags')));
		d('cp');
		$this->aPageVars['tags'] = $tags;

		return $this;
	}


	/**
	 * @todo finish this
	 *
	 * Must peek at last item in cursor and get
	 * its' timestamp, then rewind cursor back
	 * Use usual uid, usergroup, pageID, lang for etag
	 * maybe also use reputation score now
	 */
	protected function sendCacheHeaders(){


		return $this;
	}


	protected function makeTopTabs(){

		$tabs = Urhere::factory($this->Registry)->get('tplToptabs', $this->qtab);
		$this->aPageVars['topTabs'] = $tabs;

		return $this;
	}


	protected function makeQlistHeader(){

		$this->aPageVars['qheader'] = '<h1>'.$this->title.'</h1>';

		return $this;
	}


	protected function makeQlistBody(){

		$uid = $this->Registry->Viewer->getUid();
		$in = '';
		$categories = null;
		$aUserTags 		= $this->Registry->Viewer['a_f_t'];
		$showDeleted 	= $this->Registry->Viewer->isModerator();
		$contributed	= $this->_('You have contributed to this question');
		$following		= $this->_('You are following this question');
		$asked			= $this->_('Asked');
		$latestBy		= $this->_('Latest answer by');
		$toggle			= $this->_('Toggle Unread/Read Status');
		
		if($this->Registry->Ini->CATEGORIES > 0){
			$categories = $this->Registry->Cache->categories;
			$in = $this->_('In');
		}
			
		$func = function(&$a) use($uid, $aUserTags, $showDeleted, $following, $contributed, $asked, $latestBy, $toggle, $categories, $in){

			if($uid == $a['i_uid'] || (!empty($a['a_uids']) && in_array($uid, $a['a_uids'])) ){
				$a['dot'] = '<div class="fr pad2"><span class="ico person ttt" title="'.$contributed.'">&nbsp;</span></div>';
			}

			if(!empty($a['a_flwrs']) && in_array($uid, $a['a_flwrs']) ){
				
				$a['following_q'] = '<div class="fr pad2"><span class="icoc check ttt" title="'.$following.'">&nbsp;</span></div>';
			}
				
			if($categories && !empty($a['i_cat'])  && !empty($categories[$a['i_cat']]) ){
				$a['category'] = '<br><span class="categ">'.$in.' <a href="/category/'.$categories[$a['i_cat']]['slug'].'">'.$categories[$a['i_cat']]['title'].'</a></span>';
			}

			/**
			 * Add special flag if user following
			 * at least one of the tag of this question.
			 */
			if(count(array_intersect($a['a_tags'], $aUserTags)) > 0){
				$a['following_tag'] = '  followed_tag';
			}

			$a['asked'] = $asked;
			$a['toggle'] = $toggle;
			$a['latest_by'] = $latestBy;
		};


		$sQdivs = \tplQrecent::loop($this->Cursor, true, $func);

		$sQlist = \tplQlist::parse(array($this->typeDiv, $sQdivs, $this->pagerLinks, $this->notAjaxPaginatable), false);
		$this->aPageVars['body'] = $sQlist;
		d('cp');
		/**
		 * In case of Ajax can just send out sQlist as result
		 */
		return $this;
	}


	/**
	 *
	 *
	 */
	protected function makeCounterBlock(){
		$this->aPageVars['topRight'] = \tplCounterblock::parse(array($this->Cursor->count(), $this->_('Number of Questions'), ''), false);

		return $this;
	}


	/**
	 * Creates div with tags user follows
	 * The values from the tag links in that div
	 * are also used by Javascript to highlight
	 * the divs with questions that contain these tags
	 *
	 * This block is only added if user follows
	 * at least one tag
	 *
	 * @return object $this
	 */
	protected function makeFollowedTags(){

		$aFollowed = $this->Registry->Viewer['a_f_t'];
		d('$aFollowed: '.print_r($aFollowed, 1));
		if(!empty($aFollowed)){

			$this->aPageVars['side'] = '<div id="usrtags" class="fl cb w90 pl10 mb10"><div class="pad8 lg cb fr rounded3 w90"><h4>'.
			$this->_('Tags you follow').'</h4>'.
			\tplFollowedTags::loop($aFollowed, false).'</div></div>';

		}

		return $this;
	}


	/**
	 * Creates html for the recent tags block
	 * but user's followed tags will always be
	 * on top
	 *
	 *
	 * @param array $aUserTags array of tags user follows
	 *
	 * @return string html with parsed tags links
	 */
	protected function getSortedRecentTags(array $aUserTags, $type = 'recent'){

		$limit = 30;
		if('unanswered' === $type){
			$cur = $this->Registry->Mongo->UNANSWERED_TAGS->find(array(), array('tag', 'i_count'))->sort(array('i_ts' => -1))->limit($limit);
		} else {
			$cur = $this->Registry->Mongo->QUESTION_TAGS->find(array('i_count' => array('$gt' => 0)), array('tag', 'i_count'))->sort(array('i_ts' => -1))->limit($limit);
		}

		d('got '.$cur->count(true).' tag results');
		$aTags = iterator_to_array($cur);

		d('aTags: '.print_r($aTags, 1));
		/**
		 * $aTags now looks like array of
		 * elements like this one:
		 * [4d84c3693630000000003820] => Array
		 (
		 [_id] => MongoId Object
		 (
		 [$id] => 4d84c3693630000000003820
		 )

		 [i_count] => 1
		 [tag] => pop
		 )
		 */

		if(!empty($aTags)){
			usort($aTags, function($a, $b) use ($aUserTags){
				return (in_array($a['tag'], $aUserTags)) ? -1 : 1;
			});
		};

		d('$aTags now: '.print_r($aTags, 1));
		$html = ('unanswered' === $type) ? \tplUnanstags::loop($aTags) : \tplLinktag::loop($aTags);

		d('html recent tags: '.$html);

		return '<div class="tags-list">'.$html.'</div>';
	}

}
