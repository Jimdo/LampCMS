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


namespace Lampcms;

/**
 *
 * Helper class for presenting
 * the block with user's answers
 * on the profile page
 *
 * This class can also be used when we need to see all
 * user answers like on administation page
 *
 *
 * @author Dmitri Snytkine
 *
 */
class UserAnswers extends LampcmsObject
{

	const PER_PAGE = 10;

	public static function get(Registry $Registry, User $User){

		$uid = $User->getUid();
		if(0 === $uid){
			d('not registered user');

			return '';
		}

		$pagerLinks = '';
		/**
		 * Default pager path
		 */
		$pagerPath = '/tab/a/'.$uid.'/oldest';

		$cond = $Registry->Request->get('sort', 's', 'recent');
		switch($cond){
			case 'oldest':
				$sort = array('_id' => 1);
				$pagerPath = '/tab/a/'.$uid.'/recent';
				break;

			case 'voted':
				$sort = array('i_votes' => -1);
				$pagerPath = '/tab/a/'.$uid.'/voted';
				break;

			case 'updated':
				$sort = array('i_lm_ts' => -1);
				$pagerPath = '/tab/a/'.$uid.'/updated';
				break;

				
				case 'best':
				$sort = array('accepted' => -1);
				$pagerPath = '/tab/a/'.$uid.'/best';
				break;
			default:
				$sort = array('_id' => -1);
				$pagerPath = '/tab/a/'.$uid.'/oldest';
				break;

		}

		$cursor = self::getCursor($Registry, $uid, $sort);
		$count = $cursor->count(true);
		d('$count: '.$count);

		/**
		 * If this user does not have any answers then return
		 * empty string, skip any unnecessary template parsing
		 */
		if(0 == $count){
			d('no user answers');
			return '';
		}

		$pageID = $Registry->Request->get('pageID', 'i', 1);

		if($count > self::PER_PAGE || $pageID > 1){
			$oPaginator = Paginator::factory($Registry);
			$oPaginator->paginate($cursor, self::PER_PAGE,
			array('path' => $pagerPath));

			$pagerLinks = $oPaginator->getLinks();
			d('$pagerPath: '.$pagerPath. ' pagerLinks: '.$pagerLinks);
		}

		$answers = \tplUanswers::loop($cursor);
		d('$answers: '.$answers);

		$vals = array(
		'count' => $count,
		'answers' => $answers,
		'pagination' => $pagerLinks);

		return \tplUserAnswers::parse($vals);

	}

	/**
	 *
	 * Get result of selectin answers from ANSWERS collection
	 *
	 * @param Registry $Registry
	 * @param int $uid
	 *
	 * @return object MongoCursor
	 *
	 * @todo use different 'sort' params based on
	 * what's passed in Request: by votes,
	 * or by creation date
	 */
	protected static function getCursor(Registry $Registry, $uid, array $sort)
	{
		$where = array('i_uid' => $uid);
		/**
		 * Exclude deleted items unless viewer
		 * is a moderator
		 */
		if(!$Registry->Viewer->isModerator()){
			$where['i_del_ts'] = null;
		}

		$cursor = $Registry->Mongo->ANSWERS->find($where)->sort($sort);

		return $cursor;
	}
}
