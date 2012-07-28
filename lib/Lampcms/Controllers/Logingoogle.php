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

use \Lampcms\WebPage;
use \Lampcms\Request;
use \Lampcms\Cookie;
use \Lampcms\Responder;
use \Lampcms\Mongo\Doc as MongoDoc;
use \Lampcms\Mongo\Schema\User as Schema;
use \Lampcms\Acl\Role;
use \Lampcms\TimeZone;
use \Lampcms\String;
use \Lampcms\Utf8String;
use \Lampcms\User;


/**
 * Controller processes the Login with Google
 * This page is usually shown in a popup window
 * The Google Oauth authorization is shown to user in that window
 * then upon authorizing this site the browser redirects back
 * to this controller.
 *
 * Controller creates a new account or log in the user if record
 * of user if found by email address
 *
 */
class Logingoogle extends Register
{

    /**
     * Name of session key to store value of state
     * which is a random string used for verification
     * of API redirect calls
     *
     * @var string
     */
    const STATE_KEY = 'google_api_state';

    /**
     * Template for the url of GOOGLE authorization API
     * // &approval_prompt=force
     *
     * @var string
     */
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/auth?response_type=code&redirect_uri={redirect}&client_id={client_id}&scope={scope}&access_type=offline{prompt}&state={state}&v=3.0';

    /**
     * Template of the URL of google API where we can get userinfo array
     * by calling it with a valid access_token
     *
     * @var string
     */
    const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=%s';

    /**
     * Url of GOOGLE API where we make POST request
     * to exchange token
     *
     * @var string
     */
    const OAUTH2_TOKEN_URI = 'https://accounts.google.com/o/oauth2/token';

    protected $ApiClient;

    protected $Service;

    protected $configSection;


    /**
     * Array of user data received from Google API
     *
     * @var array
     */
    protected $userInfo;

    /**
     * Oauth2 access token received from API
     * We will store it with user data
     *
     * @var string this is a json string (must run json_decode to get values)
     */
    protected $token;

    /**
     * Decoded $token json
     *
     * @var \stdClass object
     */
    protected $tokenObject;

    /**
     * Email address of user in lower case
     *
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $tempPassword;


    /**
     * These are default scopes
     * and will be overridden with values
     * from [GOOGLE_API] SCOPE array
     *
     * @var array
     */
    private $scopes = array(
        'https://www.googleapis.com/auth/userinfo.profile',
        'https://www.googleapis.com/auth/userinfo.email'
    );

    protected $redirectUri;


    /**
     * Handle creation of new account
     * OR logging in existing user
     *
     * @return void
     */
    protected function main()
    {

        if (is_array($_GET) && !empty($_GET['error'])) {
            d('Received error response from Google API: ' . $_GET['error']);

            $this->closeWindow('');
        }

        $this->configSection = $this->Registry->Ini->getSection('GOOGLE_API');
        $tplRedirect         = '{_WEB_ROOT_}/{_logingoogle_}/';
        $uriMapper           = $this->Router->getCallback();
        $this->redirectUri   = $uriMapper($tplRedirect, true);
        $this->scopes        = $this->configSection['SCOPE'];


        if (!isset($_GET['code'])) {
            Responder::redirectToPage($this->makeAuthUrl());
        } else {
            $this->validateState();
            $this->getToken();
            $this->getUserInfo();
            $this->createOrUpdate();
        }
    }


    /**
     * Validate value of 'state' we get from GOOGLE
     * and it must match the value we stored in SESSION
     * before making this request
     *
     * @return object $this
     * @throws \Lampcms\DevException
     */
    protected function validateState()
    {
        if (!isset($_GET['state'])) {
            throw new \Lampcms\DevException('Security token not passed from Google API');
        }

        if (!isset($_SESSION[self::STATE_KEY])) {
            throw new \Lampcms\DevException('$_SESSION[self::STATE_KEY] value not set');
        }

        if ($_GET['state'] !== $_SESSION[self::STATE_KEY]) {
            throw new \Lampcms\DevException('Invalid value of security token passed from Google API');
        }

        unset($_SESSION[self::STATE_KEY]);

        return $this;
    }


    /**
     * Get user data from GOOGLE API
     *
     * @throws \Lampcms\AlertException in case of problems during CURL call
     * or if response json string could not be decoded or
     * if decoded response does not contain expected elements
     *
     * @return object $this
     */
    protected function getToken()
    {
        $vars = array(
            'code'          => $_GET['code'],
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->redirectUri,
            'client_id'     => $this->configSection['CLIENT_ID'],
            'client_secret' => $this->configSection['CLIENT_SECRET']
        );

        $Curl = new \Lampcms\Curl;
        try {
            $Response = $Curl->post(self::OAUTH2_TOKEN_URI, $vars);
            d('received response from API. HTTP CODE: ' . $Response->getCode() . ' body: ' . $Response->getBody());
        } catch ( \Exception $e ) {
            e('Unable to get Token from ' . self::OAUTH2_TOKEN_URI . '. Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on ' . $e->getLine());
            throw new \Lampcms\AlertException('Unable to authenticate with Google at this time');
        }

        d('Response code: ' . $Response->getCode());
        $this->token = $Response->getBody();

        if (null === $this->tokenObject = \json_decode($this->token)) {
            e('Unable to decode json $token: ' . $this->token);
            throw new \Lampcms\AlertException('Unexpected response received from Google API');
        }

        if (!\property_exists($this->tokenObject, 'access_token') || !isset($this->tokenObject->access_token)) {
            e('Decoded $this->tokenObject does not contain "access_token" property. json: ' . $this->token);
            throw new \Lampcms\AlertException('Unexpected response format received from Google API');
        }

        return $this;

    }


    /**
     * Get user info data from GOOGLE API using access_token
     * Sets  $this->userInfo array
     *
     * @return object $this
     *
     * @throws \Lampcms\AlertException if something goes wrong during CURL call
     * or if unable to json_decode the response
     */
    protected function getUserInfo()
    {
        $url  = \sprintf(self::USERINFO_URL, $this->tokenObject->access_token);
        $Curl = new \Lampcms\Curl;

        try {
            d('Calling userinfo url: ' . $url);
            $Response = $Curl->get($url);
            $body     = $Response->getBody();
            d('received response from API. HTTP CODE: ' . $Response->getCode() . ' body: ' . $body);
        } catch ( \Exception $e ) {
            e('Unable to get userinfo from ' . $url . ' Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on ' . $e->getLine());
            throw new \Lampcms\AlertException('Unexpected response received from Google API');
        }


        if (null === $this->userInfo = \json_decode($body, true)) {
            e('Unable to json_decode response body: ' . $body);
            throw new \Lampcms\AlertException('Unexpected response format received from Google API');
        }

        return $this;
    }


    /**
     * Based on value of email address in the data received
     * from Google API
     * Login existing user or create a new account
     * and login the new user
     *
     */
    protected function createOrUpdate()
    {
        $User        = null;
        $this->email = \mb_strtolower($this->userInfo['email']);
        $res         = $this->Registry->Mongo->EMAILS->findOne(array(Schema::EMAIL => $this->email), array('i_uid' => true));
        if (!empty($res) && !empty($res['i_uid'])) {
            d('found user id by email address. uid: ' . $res['i_uid']);

            $aUser = $this->Registry->Mongo->USERS->findOne(array(Schema::PRIMARY => $res['i_uid']));
            $User  = User::factory($this->Registry, $aUser);
            $this->updateUser($User);
        }

        if (null === $User) {
            $a = $this->Registry->Mongo->USERS->findOne(array(Schema::EMAIL => $this->email));
            if (!empty($a)) {
                d('found user id by email address. uid: ' . $a['_id']);
                $User = User::factory($this->Registry, $a);
                $this->updateUser($User);
            }
        }

        if (null === $User) {
            $User = $this->createUser();
        }

        try {
            $this->processLogin($User);
            $this->Registry->Dispatcher->post($this, 'onGoogleLogin');
            $this->closeWindow();
        } catch ( \Lampcms\LoginException $e ) {
            /**
             * re-throw as regular exception
             * so that it can be caught and shown in popup window
             */
            e('Unable to process login: ' . $e->getMessage());

            exit(\Lampcms\Responder::makeErrorPage($e->getMessage()));
        }

    }


    /**
     * Update User object if necessary:
     * Always add the google_token value (json token string)
     *
     * if user does not have avatar_external set one from Google Info
     * If user does not have locale set from info
     * If user does not have fn or ln set from Google Info
     * If user does not have gender set from Google Info
     * If User does not have url set from Google Info profile url
     *
     * @param \Lampcms\User $User
     *
     * @return object $this
     */
    protected function updateUser(\Lampcms\User $User)
    {

        $User['google_id'] = (string)$this->userInfo['id'];

        /**
         * Update the following field ONLY
         * if they DON'T already exists in this user's record!
         *
         * This means that if record exists and is an empty
         * string - don't update this because it usually means
         * that user did have this field before and then removed
         * the value by editing profile.
         */
        if (null === $User[Schema::EXTERNAL_AVATAR] && !empty($this->userInfo['picture'])) {
            $User[Schema::EXTERNAL_AVATAR] = $this->userInfo['picture'] . '?sz=50';
        }

        if (null === $User[Schema::FIRST_NAME] && !empty($this->userInfo['given_name'])) {
            $User[Schema::FIRST_NAME] = $this->userInfo['given_name'];
        }

        if (null === $User[Schema::LAST_NAME] && !empty($this->userInfo['family_name'])) {
            $User[Schema::LAST_NAME] = $this->userInfo['family_name'];
        }

        if (null === $User[Schema::GENDER] && !empty($this->userInfo['gender'])) {
            $User[Schema::GENDER] = ('male' === $this->userInfo['gender']) ? 'M' : 'F';
        }

        if (null === $User[Schema::URL] && !empty($this->userInfo['link'])) {
            $User[Schema::URL] = $this->userInfo['link'];
        }

        $User->save();

        return $this;
    }


    /**
     * Create record of new user
     *
     * @return \Lampcms\User object User object
     */
    protected function createUser()
    {

        $sid = (false === ($sid = Cookie::getSidCookie())) ? String::makeSid() : $sid;

        if (false !== $tzn = Cookie::get('tzn')) {
            $timezone = $tzn;
        } else {
            $timezone = $this->Registry->Ini->SERVER_TIMEZONE;
        }

        $aUser                                 = array();
        $aUser[Schema::EMAIL]                  = $this->email;
        $aUser[Schema::REPUTATION]             = 1;
        $aUser[Schema::REGISTRATION_TIMESTAMP] = time();
        $aUser[Schema::REGISTRATION_TIME]      = date('r');
        $aUser[Schema::FIRST_VISIT_TIMESTAMP]  = (false !== $intFv = Cookie::getSidCookie(true)) ? $intFv : time();
        $aUser[Schema::SID]                    = $sid;
        $aUser['google_id']                    = (string)$this->userInfo['id'];
        $aUser['google_token']                 = $this->token;

        if (!empty($this->userInfo['given_name'])) {
            $aUser[Schema::FIRST_NAME] = $this->userInfo['given_name'];
        }

        if (!empty($this->userInfo['family_name'])) {
            $aUser[Schema::LAST_NAME] = $this->userInfo['family_name'];
        }

        if (!empty($this->userInfo['locale'])) {
            $aUser[Schema::LOCALE] = $this->userInfo['locale'];
        }

        if (!empty($this->userInfo['link'])) {
            $aUser[Schema::URL] = $this->userInfo['link'];
        }

        if (!empty($this->userInfo['gender'])) {
            $aUser[Schema::GENDER] = ('male' === $this->userInfo['gender']) ? 'M' : 'F';
        }

        if (!empty($this->userInfo['name'])) {
            $username = $this->userInfo['name'];
        } elseif (!empty($this->userInfo['family_name'])) {
            $username = (!empty($this->userInfo['family_name']));
            if (!empty($this->userInfo['family_name'])) {
                $username = ' ' . $this->userInfo['family_name'];
            }
        }


        $oEA      = \Lampcms\ExternalAuth::factory($this->Registry);
        $username = $oEA->makeUsername($username);

        $aUser[Schema::USERNAME]           = $username;
        $aUser[Schema::USERNAME_LOWERCASE] = \mb_strtolower($username);
        $aUser[Schema::ROLE]               = Role::EXTERNAL_USER;
        $aUser[Schema::TIMEZONE]           = $timezone;
        $aUser[Schema::EXTERNAL_AVATAR]    = $this->userInfo['picture'] . '?sz=50';

        $aUser = \array_merge($this->Registry->Geo->Location->data, $aUser);

        d('creating new googlge aUser: ' . \json_encode($aUser));

        $User = User::factory($this->Registry, $aUser);
        $User->save();
        d('new user _id: ' . $User['_id']);

        \Lampcms\PostRegistration::createReferrerRecord($this->Registry, $User);

        try {
            $this->createEmailRecord($User['_id']);
        } catch ( \Lampcms\DevException $e ) {
            e('Unable to create email record: ' . $e->getMessage());
        }

        $this->addContacts($User->getUid());
        $this->Registry->Dispatcher->post($User, 'onNewUser');

        return $User;
    }


    /**
     * Import user's contacts from Google account
     * We will be able to use contacts to match new users
     * with existing members they may already know
     *
     * @param int $uid _id of newly created user
     */
    protected function addContacts($uid)
    {
        if (\in_array('https://www.google.com/m8/feeds/', $this->scopes)) {
            $Curl          = new \Lampcms\Curl;
            $ContactParser = new \Lampcms\Modules\Google\Contacts($this->Registry->Mongo, $Curl);
            $accessToken = $this->tokenObject->access_token;

            $func = function() use ($ContactParser, $uid, $accessToken){
                $ContactParser->import($uid, $accessToken);
            };

            \Lampcms\runLater($func);
        }
    }


    /**
     * Prepare the url of Google authorization call
     *
     * @return string
     */
    protected function makeAuthUrl()
    {
        $state                     = \Lampcms\String::makeRandomString(16);
        $_SESSION[self::STATE_KEY] = $state;

        $vars = array(
            '{prompt}'    => (LAMPCMS_DEBUG) ? '&approval_prompt=force' : '',
            '{redirect}'  => $this->redirectUri,
            '{client_id}' => $this->configSection['CLIENT_ID'],
            '{scope}'     => \urlencode(\implode(' ', $this->configSection['SCOPE'])),
            '{state}'     => $state
        );

        $res = \strtr(self::AUTH_URL, $vars);

        return $res;
    }


    protected function closeWindow($text = '<h2>@@You have successfully logged in. You should close this window now@@</h2>')
    {
        $script = '
		var myclose = function(){
		window.close();
		}
		if(window.opener){
		setTimeout(myclose, 300); // give opener window time to process login and cancell intervals
		}else{
			//alert("not a popup window or opener window gone away");
			setTimeout(myclose, 300);
		}';
        d('cp');


        $s = Responder::PAGE_OPEN . Responder::JS_OPEN .
            $script .
            Responder::JS_CLOSE . $text .
            Responder::PAGE_CLOSE;
        d('cp s: ' . $s);
        echo $s;
        fastcgi_finish_request();
        exit;
    }

}
