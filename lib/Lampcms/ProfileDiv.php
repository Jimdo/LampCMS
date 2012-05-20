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
 * Class for rendering <div> with user profile
 * This class is used for generating div on page
 * /user/$id/
 *
 * @author Dmitri Snytkine
 *
 */
class ProfileDiv extends LampcmsObject
{
	/**
	 * Flag indicates that oauth extension
	 * is available
	 *
	 * @var bool
	 */
	protected $hasOauth = false;


	/**
	 *
	 * User whose profile page being created
	 * @var object of type User
	 */
	protected $User;


	/**
	 * Constructor
	 *
	 * @param Registry $Registry
	 */
	public function __construct(Registry $Registry){
		$this->Registry = $Registry;
		$this->hasOauth = \extension_loaded('oauth');
	}


	/**
	 * Setter for $this->User
	 *
	 * @param User $User
	 */
	public function setUser(User $User){
		$this->User = $User;

		return $this;
	}


	public function getHtml(){
		$edit = '';
		$lastActive = $this->User['i_lm_ts'];
		$lastActive = (!empty($lastActive)) ? $lastActive : $this->User['i_reg_ts'];
		$rep = $this->User->getReputation();

		d('rep: '.$rep);
		$uid = $this->User->getUid();
		$isSameUser = ($this->Registry->Viewer->getUid() === $uid);
		if($isSameUser || $this->Registry->Viewer->isModerator()){
			$edit = '<div class="fl middle"><span class="icoc key">&nbsp;</span><a href="/editprofile/'.$uid.'" class="edit middle">Edit profile</a></div>';
		}

		$desc = \trim($this->User['description']);
		$desc = (empty($desc)) ? '' : Utf8String::factory($desc, 'utf-8', true)->linkify()->valueOf();

		$vars = array(
			'editLink' => $edit,
			'username' => $this->User->username,
			'avatar' => $this->User->getAvatarImgSrc(),
			'reputation' => $rep,
			'name' => $this->User->getDisplayName(),
			'genderLabel' => 'Gender',
			'gender' => $this->getGender(),
			'since' => date('F j, Y', $this->User->i_reg_ts),
			'lastActivity' => TimeAgo::format(new \DateTime(date('r', $lastActive))),
			'website' => $this->User->getUrl(),
			'twitter' => '<div id="my_tw">'.$this->getTwitterAccount($isSameUser).'</div>',
			'age' => $this->User->getAge(),
			'facebook' => '<div id="my_fb">'.$this->getFacebookAccount($isSameUser).'</div>',
			'tumblr' => '<div id="my_tm">'.$this->getTumblrAccount($isSameUser).'</div>',
			'blogger' => '<div id="my_bg">'.$this->getBloggerAccount($isSameUser).'</div>',
			'linkedin' => '<div id="my_li">'.$this->getLinkedInAccount($isSameUser).'</div>',
			'location' => $this->User->getLocation(),
			'description' => \wordwrap($desc, 50), // @todo this is not unicode-safe! Must update it!
			'editRole' => Usertools::getHtml($this->Registry, $this->User),
			'followButton' => $this->makeFollowButton(),
			'followers' => ShowFollowers::factory($this->Registry)->getUserFollowers($this->User),
			'following' => ShowFollowers::factory($this->Registry)->getUserFollowing($this->User)
		);

		return \tplUserInfo::parse($vars);
	}


	/**
	 * Get either the link to @username of Twitter account if user has one
	 * OR html for the button to connect Twitter account
	 * to Existing Account
	 *
	 *
	 * @param object $Registry
	 * @param object $User
	 *
	 * @return string html html of Connect button
	 * or just plain text string with @username
	 *
	 */
	public function getTwitterAccount($isSameUser){

		$t = $this->User->getTwitterUrl();
		if(!empty($t)){
			return $t;
		}

		if($this->hasOauth && $isSameUser){
			$aTwitter = $this->Registry->Ini->getSection('TWITTER');
			if(!empty($aTwitter['TWITTER_OAUTH_KEY']) && !empty($aTwitter['TWITTER_OAUTH_SECRET'])){
				/**
				 * @todo
				 * Translate String
				 */
				return '<div id="connect_twtr" class="twsignin ajax ttt btn_connect rounded4" title="Connect Twitter Account"><img src="/images/tw-user.png" width="16" height="16"><span class="_bg_tw">Connect Twitter</span></div>';
			}
		}

		return '';
	}


	/**
	 * Get either the link to Tumblr blog
	 * if user has one
	 * OR html for the button to connect Tumblr account
	 * to Existing Account
	 *
	 *
	 * @param object $Registry
	 * @param object $User
	 *
	 * @return string html of Connect button
	 * or link to user's Tumblr blog
	 *
	 */
	public function getTumblrAccount($isSameUser){

		$t = $this->User->getTumblrBlogLink();
		d('tumblr blog url: '.$t);
		if(!empty($t)){
			return $t;
		}

		if($this->hasOauth && $isSameUser){
			$a = $this->Registry->Ini->getSection('TUMBLR');
			if(!empty($a) && !empty($a['OAUTH_KEY']) && !empty($a['OAUTH_SECRET'])){
				/**
				 * @todo
				 * Translate string
				 */
				return '<div id="connect_tumblr" class="add_tumblr ajax ttt btn_connect rounded4" title="Connect Tumblr Blog"><img src="/images/tumblr_16.png" width="16" height="16"><span class="_bg_tw">Connect Tumblr Blog</span></div>';
			}
		}

		return '';
	}


	/**
	 * Get either the link to Tumblr blog
	 * if user has one
	 * OR html for the button to connect Tumblr account
	 * to Existing Account
	 *
	 *
	 * @param object $Registry
	 * @param object $User
	 *
	 * @return string html of Connect button
	 * or link to user's Tumblr blog
	 *
	 */
	public function getBloggerAccount($isSameUser){

		$t = $this->User->getBloggerBlogLink();
		d('blojgger blog url: '.$t);
		if(!empty($t)){
			return $t;
		}

		if($this->hasOauth && $isSameUser){
			$a = $this->Registry->Ini->getSection('BLOGGER');
			if(!empty($a) && !empty($a['OAUTH_KEY']) && !empty($a['OAUTH_SECRET'])){
				/**
				 * @todo
				 * Translate string
				 */
				return '<div id="connect_blogger" class="add_blogger ajax ttt btn_connect rounded4" title="Connect Blogger.com Blog"><img src="/images/blogger_16.png" width="16" height="16"><span class="_bg_tw">Connect Blogger Blog</span></div>';
			}
		}

		return '';
	}



	/**
	 * Get either the link to Tumblr blog
	 * if user has one
	 * OR html for the button to connect Tumblr account
	 * to Existing Account
	 *
	 *
	 * @param object $Registry
	 * @param object $User
	 *
	 * @return string html of Connect button
	 * or link to user's Tumblr blog
	 *
	 */
	public function getLinkedInAccount($isSameUser){

		$t = $this->User->getLinkedinLink();
		d('linkedIn url: '.$t);
		if(!empty($t)){
			return $t;
		}

		if($this->hasOauth && $isSameUser){
			$a = $this->Registry->Ini->getSection('LINKEDIN');
			if(!empty($a) && !empty($a['OAUTH_KEY']) && !empty($a['OAUTH_SECRET'])){
				/**
				 * @todo
				 * Translate string
				 */
				return '<div id="connect_linkedin" class="add_linkedin ajax ttt btn_connect rounded4" title="Connect LinkedIn Account"><img src="/images/linkedin_16.png" width="16" height="16"><span class="_bg_tw">Connect LinkedIn</span></div>';
			}
		}

		return '';
	}


	/**
	 * Get either the link to Facebook profile
	 * if user has one
	 * OR html for the button to connect Facebook account
	 * to Existing Account
	 *
	 *
	 * @param object $Registry
	 * @param object $User
	 *
	 * @return string html of Connect button
	 * or link to Facebook profile page
	 *
	 */
	public function getFacebookAccount($isSameUser){

		$f = $this->User->getFacebookUrl();
		if(!empty($f)){
			return $f;
		}

		if($this->hasOauth && $isSameUser){
			$aFB = $this->Registry->Ini->getSection('FACEBOOK');
			if(!empty($aFB) && !empty($aFB['APP_ID'])){
				/**
				 * @todo
				 * Translate string
				 */
				return '<div id="connect_fb" class="fbsignup ajax ttt btn_connect rounded4" title="Connect Facebook Account"><img src="/images/facebook_16.png" width="16" height="16"><span class="_bg_tw">Connect Facebook</span></div>';
			}
		}

		return '';
	}


	/**
	 * Get textual value of "Gender" M/F
	 *
	 * @todo translate string Male/Female
	 * @param string $gender
	 * @return string
	 */
	protected function getGender(){
		$gender = $this->User['gender'];
		switch($gender){
			case 'M':
				/**
				 * @todo
				 * Translate string
				 *
				 */
				$ret = 'Male';
				break;

			case 'F':
				/**
				 * @todo
				 * Translate string
				 *
				 */
				$ret = 'Female';
				break;

			default:
				$ret = '';
		}

		return $ret;
	}


	/**
	 * Make a follow user button
	 * checks that user is not the same
	 * as viewer, otherwise just returns
	 * empty string
	 *
	 *
	 * @param Registry $Registry
	 * @param User $User use who to follow
	 * @return string html of "Follow" button
	 * of empty string if Viewer is same as User
	 *
	 */
	public function makeFollowButton(){

		$button = '';
		$oViewer = $this->Registry->Viewer;
		$uid = $this->User->getUid();

		if($uid !== $oViewer->getUid()){
			/**
			 * @todo
			 * Translate strings
			 */
			$aVars = array(
			'id' => $uid,
			'icon' => 'cplus',
			'label' => 'Follow',
			'class' => 'follow',
			'type' => 'u',
			'title' => 'Follow this user'
			);
			/**
			 * @todo
			 * Translate strings
			 */
			if(in_array($uid, $oViewer['a_f_u'])){
				$aVars['label'] = 'Following';
				$aVars['class'] = 'following';
				$aVars['icon'] = 'check';
				$aVars['title'] = 'You are following this user';
			}

			$button = '<div class="fl mt10 mb10"><div class="follow_wrap">'.\tplFollowButton::parse($aVars, false).'</div></div>';
		}

		return $button;
	}

}
