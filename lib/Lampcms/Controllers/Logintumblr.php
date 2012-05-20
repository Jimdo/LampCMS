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
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
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

use \Lampcms\WebPage;
use \Lampcms\Request;
use \Lampcms\Responder;


/**
 * Class for generating a popup page that starts the oauth dance
 * and this also serves as a callback url
 * to which Tumblr Oauth redirects after authorization
 *
 * Dependency is pecl OAuth extension!
 * 
 * @todo add selectBlog() method to process submitted
 * blog select form instead of using separate tumblrselect
 * controller!
 * 
 * Then can add special form to post first entry on the
 * closePage() method!
 *
 * @author Dmitri Snytkine
 *
 */
class Logintumblr extends WebPage
{

	const REQUEST_TOKEN_URL = 'http://www.tumblr.com/oauth/request_token';

	const ACCESS_TOKEN_URL = 'http://www.tumblr.com/oauth/access_token';

	const AUTHORIZE_URL = 'http://www.tumblr.com/oauth/authorize';

	const ACCOUNT_DATA_URL = 'http://www.tumblr.com/api/authenticate';

	/**
	 * Array of Tumblr's
	 * oauth_token and oauth_token_secret
	 *
	 * @var array
	 */
	protected $aAccessToken = array();


	/**
	 * Object php OAuth
	 *
	 * @var object of type php OAuth
	 * must have oauth extension for this
	 */
	protected $oAuth;


	protected $bInitPageDoc = false;


	/**
	 * Configuration of Tumblr API
	 * this is array of values TUMBLR section
	 * in !config.ini
	 *
	 * @var array
	 */
	protected $aTM = array();


	/**
	 * Array of User's Tumblr blogs
	 * User can have more than one blog on Tumblr
	 *
	 * @var array
	 */
	protected $aBlogs;


	/**
	 * The main purpose of this class is to
	 * generate the oAuth token
	 * and then redirect browser to twitter url with
	 * this unique token
	 *
	 * No actual page generation will take place
	 *
	 * @see classes/WebPage#main()
	 */
	protected function main(){

		if(!extension_loaded('oauth')){
			throw new \Exception('Unable to use Tumblr API because OAuth extension is not available');
		}

		$this->aTm = $this->Registry->Ini['TUMBLR'];

		try {
			$this->oAuth = new \OAuth($this->aTm['OAUTH_KEY'], $this->aTm['OAUTH_SECRET'], OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
			$this->oAuth->enableDebug();  
		} catch(\OAuthException $e) {
			e('OAuthException: '.$e->getMessage());

			throw new \Exception('Something went wrong during authorization. Please try again later'.$e->getMessage());
		}


		/**
		 * If this is start of dance then
		 * generate token, secret and store them
		 * in session and redirect to tumblr authorization page
		 */
		if(empty($_SESSION['tumblr_oauth']) || empty($this->Request['oauth_token'])){
			/**
			 * Currently Tumblr does not handle "Deny" response of user
			 * too well - they just redirect back to this url
			 * without any clue that user declined to authorize
			 * our application.
			 */
			$this->step1();
		} else {
			$this->step2();
		}
	}


	/**
	 * Generate oAuth request token
	 * and redirect to tumblr for authentication
	 *
	 * @return object $this
	 *
	 * @throws Exception in case something goes wrong during
	 * this stage
	 */
	protected function step1(){

		try {
			// State 0 - Generate request token and redirect user to tumblr to authorize
			$_SESSION['tumblr_oauth'] = $this->oAuth->getRequestToken(self::REQUEST_TOKEN_URL);

			d('$_SESSION[\'tumblr_oauth\']: '.print_r($_SESSION['tumblr_oauth'], 1));
			if(!empty($_SESSION['tumblr_oauth']) && !empty($_SESSION['tumblr_oauth']['oauth_token'])){

				/**
				 * A more advanced way is to NOT use Location header
				 * but instead generate the HTML that contains the onBlur = focus()
				 * and then redirect with javascript
				 * This is to prevent from popup window going out of focus
				 * in case user clicks outsize the popup somehow
				 */
				$this->redirectToTumblr(self::AUTHORIZE_URL.'?oauth_token='.$_SESSION['tumblr_oauth']['oauth_token']);
			} else {
				/**
				 * Here throw regular Exception, not Lampcms\Exception
				 * so that it will be caught ONLY by the index.php and formatted
				 * on a clean page, without any template
				 */

				throw new \Exception("Failed fetching request token, response was: " . $this->oAuth->getLastResponse());
			}
		} catch(\OAuthException $e) {
			e('OAuthException: '.$e->getMessage().' '.print_r($e, 1));

			throw new \Exception('Something went wrong during authorization. Please try again later'.$e->getMessage());
		}

		return $this;
	}


	/**
	 * Step 2 in oAuth process
	 * this is when tumblr redirected the user back
	 * to our callback url, which calls this controller
	 * @return object $this
	 *
	 * @throws Exception in case something goes wrong with oAuth class
	 */
	protected function step2(){

		try {
			/**
			 * This is a callback (redirected back from tumblr page
			 * after user authorized us)
			 * In this case we must: create account or update account
			 * in USER table
			 * Re-create oViewer object
			 * send cookie to remember user
			 * and then send out HTML with js instruction to close the popup window
			 */
			d('Looks like we are at step 2 of authentication. Request: '.print_r($_REQUEST, 1));

			/**
			 * @todo check first to make sure we do have oauth_token
			 * on REQUEST, else close the window
			 */
			$this->oAuth->setToken($this->Request['oauth_token'], $_SESSION['tumblr_oauth']['oauth_token_secret']);
			$this->aAccessToken = $this->oAuth->getAccessToken(self::ACCESS_TOKEN_URL);
			d('$this->aAccessToken: '.print_r($this->aAccessToken, 1));

			unset($_SESSION['tumblr_oauth']);

			$this->oAuth->setToken($this->aAccessToken['oauth_token'], $this->aAccessToken['oauth_token_secret']);

			/**
			 * Now getUserBlogs
			 * Then if user has more than one blog
			 * display a form with "select blog"
			 * + description about it
			 *
			 * Make sure to run connect() first so that oViewer['tumblr']
			 * element will be created and will have all user blogs
			 *
			 *
			 * Else - user has just one blog then close Window!
			 *
			 */
			d('cp');
			$this->getUserBlogs()->connect();
			d('cp');
			
			/**
			 * If user has more than one blog
			 * then show special form
			 */
			if(count($this->aBlogs) > 1){
				d('User has more than one blog, generating "select blog" form');
				$form = $this->makeBlogSelectionForm();
				d('$form: '.$form);
				exit(Responder::makeErrorPage($form));
			} else {
				d('User has one tumblr blog, using it now');
				/**
				 * Set flag to session indicating that user just
				 * connected tumblr Account
				 */
				$this->Registry->Viewer['b_tm'] = true;
				$this->closeWindow();
			}

		} catch(\OAuthException $e) {
			e('OAuthException: '.$e->getMessage().' '.print_r($e, 1));

			$err = 'Something went wrong during authorization. Please try again later'.$e->getMessage();
			throw new \Exception($err);
		}

		return $this;
	}


	protected function makeBlogSelectionForm(){
		/**
		 * @todo Translate string
		 */
		$label = 'You have more than one blog on Tumblr.<br>
			 Please select one blog that will be connected to this account.<br>
			 <br>When you select the "Post to Tumblr" option, your<br>
			 Question or Answer will be posted to this blog.';

		/**
		 * @todo Translate string
		 */
		$save = 'Save';
		$token = \Lampcms\Forms\Form::generateToken();
		$options = '';
		$tpl = '<option value="blog%s">%s</option>';
		foreach($this->aBlogs as $id => $blog){
			$options .= sprintf($tpl, $id, $blog['title']);
		}

		$vars = array('token' => $token, 'options' => $options, 'label' => $label, 'save' => $save);

		return \tplTumblrblogs::parse($vars);
	}


	/**
	 * Fetch xml from Tumblr, parse it
	 * and generate array of $this->aBlogs
	 *
	 *
	 * @throws \Exception if something does not work
	 * as expected
	 *
	 * @return object $this
	 */
	protected function getUserBlogs(){

		d('fetchind data from: '.self::ACCOUNT_DATA_URL);
		$this->oAuth->fetch(self::ACCOUNT_DATA_URL);
		$res = $this->oAuth->getLastResponse();
		d('res: '.$res);
			
		$aDebug = $this->oAuth->getLastResponseInfo();
		/**
		 * Always check for response code first!
		 * it must be 201 or it's no good!
		 *
		 * Also check the 'url' part of it
		 * if it does not match url you used
		 * in request then it was redirected!
		 */
		d('debug: '.print_r($aDebug, 1));

		if(empty($res) || empty($aDebug['http_code']) || '200' != $aDebug['http_code']){
			$err = 'Unexpected Error parsing API response';

			throw new \Exception($err);
		}

		/**
		 * $res is xml like this (can have multiple 'tumblelog' elements):
		 *
		 * <?xml version="1.0" encoding="UTF-8"?>
		 * <tumblr version="1.0">
		 * <user default-post-format="html" can-upload-audio="1" can-upload-aiff="1" can-ask-question="1" can-upload-video="1" max-video-bytes-uploaded="26214400" liked-post-count="0"/>
		 * <tumblelog title="Snytkine" is-admin="1" posts="1" twitter-enabled="0" draft-count="0" messages-count="0" queue-count="" name="snytkine" url="http://snytkine.tumblr.com/" type="public" followers="0" avatar-url="http://assets.tumblr.com/images/default_avatar_128.gif" is-primary="yes" backup-post-limit="30000"/>
		 * </tumblr>
		 *
		 */
		$XML = new \DOMDocument('1.0', 'utf-8');
		if(false === $XML->loadXML($res)){
			$err = 'Unexpected Error parsing response XML';
			throw new \Exception($err);
		}

		$aParsed = $XML->getElementsByTagName('tumblelog');
		d('Blogs count: '.$aParsed->length);

		if(0 === $aParsed->length){
			e('Looks like user does not have any blogs: $xml: '.$res);

			$err = ('Looks like you have Tumblr account but do not have any blogs on Tumblr');
			throw new \Exception($err);
		}

		foreach($aParsed as $blog){

			$aBlog = array('url' => null);
			$aBlog['title'] = $blog->getAttribute('title');
			$type = $blog->getAttribute('type');
			$aBlog['type'] = $type;

			if($blog->hasAttribute('is-primary')){
				$aBlog['is-primary'] = $blog->getAttribute('is-primary');
			}
	
			if('public' === $type){
				if($blog->hasAttribute('url')){
					$aBlog['url'] = $blog->getAttribute('url');
				}
				if($blog->hasAttribute('name')){
					$aBlog['name'] = $blog->getAttribute('name');
				}
			} else {

				if($blog->hasAttribute('private-id')){
					$aBlog['private-id'] = $blog->getAttribute('private-id');
				}
			}

			$this->aBlogs[] = $aBlog;
		}

		d('aBlogs: '.print_r($this->aBlogs, 1));

		return $this;
	}


	/**
	 * Add element [tumblr] to Viewer object
	 * this element is array with 2 keys: tokens
	 * and blogs - both are also arrays
	 *
	 * @return object $this
	 */
	protected function connect(){

		$this->Registry->Viewer['tumblr'] = array('tokens' => $this->aAccessToken, 'blogs' => $this->aBlogs);
		$this->Registry->Viewer->save();

		return $this;
	}


	/**
	 * Return html that contains JS window.close code and nothing else
	 *
	 * @return unknown_type
	 */
	protected function closeWindow(array $a = array()){
		d('cp a: '.print_r($a, 1));
		$js = '';

		$tpl = '
		var myclose = function(){
		window.close();
		}
		if(window.opener){
		%s
		setTimeout(myclose, 100); // give opener window time to process login and cancell intervals
		}else{
			alert("This is not a popup window or opener window gone away");
		}';
		d('cp');

		$script = \sprintf($tpl, $js);

		$s = Responder::PAGE_OPEN. Responder::JS_OPEN.
		$script.
		Responder::JS_CLOSE.
		'<h2>You have successfully connected your Tumblr Blog. You should close this window now</h2>'.

		Responder::PAGE_CLOSE;
		d('cp s: '.$s);
		echo $s;
		fastcgi_finish_request();
		exit;
	}


	/**
	 * @todo add YUI Event lib
	 * and some JS to subscribe to blur event
	 * so that onBlur runs not just the first onBlur time
	 * but all the time
	 *
	 * @param string $url of tumblr oauth, including request token
	 * @return void
	 */
	protected function redirectToTumblr($url){
		d('tumblr redirect url: '.$url);
		/**
		 * @todo translate this string
		 *
		 */
		$s = Responder::PAGE_OPEN. Responder::JS_OPEN.
		'setTZOCookie = (function() {
		getTZO = function() {
		var tzo, nd = new Date();
		tzo = (0 - (nd.getTimezoneOffset() * 60));
		return tzo;
	    }
		var tzo = getTZO();
		document.cookie = "tzo="+tzo+";path=/";
		})();
		
		
		var myredirect = function(){
			window.location.assign("'.$url.'");
		};
			setTimeout(myredirect, 300);
			'.
		Responder::JS_CLOSE.
		'<div class="centered"><a href="'.$url.'">If you are not redirected in 2 seconds, click here to authenticate with tumblr</a></div>'.
		Responder::PAGE_CLOSE;

		d('exiting with this $s: '.$s);

		exit($s);
	}
}
