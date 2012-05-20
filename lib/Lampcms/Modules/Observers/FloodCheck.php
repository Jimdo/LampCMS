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


namespace Lampcms\Modules\Observers;

/**
 * Posting flood check filter
 * This filter is implemented as
 * the Observer
 * 
 * @author Dmitri Snytkine
 *
 */
class FloodCheck extends \Lampcms\Event\Observer
{
	/**
	 * @todo later we should check the time
	 * offset based on user reputation score
	 *
	 */
	protected $minutesToWait = 1;


	public function main(){
		
		if(LAMPCMS_DEBUG){
			d('flood check not performed in debug mode');
			
			return;
		}
		
		$this->minutesToWait = (int)$this->Registry->Ini->FLOOD_CHECK_TIME;
		/**
		 * Do not apply this filter
		 * to moderators (and admins)
		 * Moderators can post as much as they want
		 * as fast as they want
		 *
		 */
		if(!$this->Registry->Viewer->isModerator()){
			switch ($this->eventName){
				case 'onBeforeNewQuestion':
					$this->byUser('QUESTIONS')->byIp('QUESTIONS');
					break;

				case 'onBeforeNewAnswer':
					$this->byUser('ANSWERS')->byIp('ANSWERS');
					break;

				case 'onBeforeNewComment':
					$this->checkComment();
					break;

			}
		}
	}


	/**
	 * Perfomes flood check for comments
	 *
	 * @throws \Lampcms\Exception is comment is
	 * posted too soon after posting another comment
	 */
	protected function checkComment(){
		d('cp');
		if(!$this->Registry->Viewer->isModerator()){
			d('cp');
			$uid = $this->Registry->Viewer->getUid();
			$timeout = (int)$this->Registry->Ini->COMMENTS_FLOOD_TIME;
			d('timeout: '.$timeout);
			$since = time() - $timeout;
			$where = array('i_uid' => $uid, 'i_ts' => array('$gt' => $since));
				
			$a = $this->Registry->Mongo->COMMENTS->findOne($where);

			if(!empty($a)){
				/**
				 * @todo
				 * Translate string
				 */
				throw new \Lampcms\AccessException('You are posting too fast.<br>You must wait '.$timeout.' seconds between comments');
			}
		}
	}


	/**
	 * @todo
	 * Translate exception
	 *
	 * @throws QuestionParserException
	 */
	protected function byIp($collName){
		d('$collName: '.$collName);
		if(!$this->Registry->Viewer->isModerator()){
			$since = time() - ($this->minutesToWait * 60);
			$byIP = $this->Registry->Mongo
			->getCollection($collName)
			->findOne(array('ip' => $this->obj['ip'], 'i_ts' => array('$gt' => $since)), array('i_ts', 'hts'));


			if(!empty($byIP)){
				throw new \Lampcms\FilterException('You are posting too fast. Please wait '.$this->minutesToWait.' minutes between posting');
			}
		}

		return $this;
	}

	
	protected function byUser($collName){
		if(!$this->Registry->Viewer->isModerator()){
			$since = time() - ($this->minutesToWait * 60);
			$byUid = $this->Registry->Mongo
			->getCollection($collName)
			->findOne(array('i_uid' => $this->obj['i_uid'], 'i_ts' => array('$gt' => $since)), array('i_ts', 'hts'));

			if(!empty($byUid)){
				throw new \Lampcms\FilterException('You are posting too fast. Please wait '.$this->minutesToWait.' minutes between posting');
			}
		}

		return $this;
	}
}
