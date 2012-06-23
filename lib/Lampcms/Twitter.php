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


namespace Lampcms;

use Lampcms\Interfaces\TwitterUser;

/**
 * Base class for interacting with Twitter API
 *
 * @author Dmitri Snytkine
 *
 */
class Twitter extends LampcmsObject
{

    const URL_VERIFY_CREDENTIALS = 'http://api.twitter.com/1/account/verify_credentials.json';

    const URL_STATUS = 'http://api.twitter.com/1/statuses/update.json';

    const URL_FAVORITE = 'http://api.twitter.com/1/favorites/%s/%s.json';

    const URL_FOLLOW = 'http://api.twitter.com/1/friendships/create/%s.json';


    /**
     * TWITTER section of !config.inc
     * @var array
     */
    protected $aTwitterConfig = array();

    /**
     * Object of php oAuth class
     *
     * @var object
     */
    protected $oAuth;

    /**
     * Instance of TwitterUserInterface which is also User object
     * @var object User
     */
    protected $User;

    /**
     * URL of Twitter API where to post stuff
     *
     * @var string
     */
    protected $url = null;

    /**
     * Constructor
     *
     * @param Registry $Registry
     * @return void
     */
    public function __construct(Registry $Registry)
    {
        if (!extension_loaded('oauth')) {
            throw new \Lampcms\Exception('Cannot use this class because php extension "oauth" is not loaded');
        }

        $this->Registry = $Registry;
        $oViewer = $Registry->Viewer;
        if ($oViewer instanceof TwitterUser) {

            $this->User = $oViewer;
            //d(' $this->User: '.print_r($this->User, 1));
        }
        d('cp');

        $this->aTwitterConfig = $Registry->Ini->offsetGet('TWITTER');
        d('$this->aTwitterConfig: ' . print_r($this->aTwitterConfig, 1));
        if (empty($this->aTwitterConfig) || empty($this->aTwitterConfig['TWITTER_OAUTH_KEY']) || empty($this->aTwitterConfig['TWITTER_OAUTH_SECRET'])) {
            throw new DevException('Missing configuration parameters for TWITTER API');
        }

        try {
            d('cp');
            $this->oAuth = new \OAuth($this->aTwitterConfig['TWITTER_OAUTH_KEY'], $this->aTwitterConfig['TWITTER_OAUTH_SECRET'], OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
            $this->oAuth->enableDebug(); // This will generate debug output in your error_log
            d('cp');
        } catch (\OAuthException $e) {
            e('OAuthException: ' . $e->getMessage() . ' ' . print_r($e, 1));

            throw new Exception('Something went wrong during Twitter authorization. This has nothing to do with your account. Please try again later' . $e->getMessage());
        }
    }


    /**
     * Setter for $this->User
     *
     * @param User $User
     * @return object $this
     */
    public function setUser(\Lampcms\TwitterUser $User)
    {
        $this->User = $User;

        return $this;
    }


    /**
     * Getter for this->oAuth object
     * @return object of type php oAuth
     */
    public function getOAuth()
    {
        return $this->oAuth;
    }

    /**
     * Factory method
     * @param Registry $Registry
     * @param User $User
     * @return object of this class
     */
    /*public static function factory(Registry $Registry, TwitterUser $User = null)
      {
         $o = new self($Registry);
         if(null !== $User){
         $o->setUser($User);
         }

         return $o;
         }*/

    /**
     * Verify user oAuth credentials
     * token/secret
     * This will confirm or deny that user is
     * authorizing us to use Twitter account
     *
     * @param mixed $token string oauth token or object of type User
     * @param string $secret
     * @return array of user profile on success
     * Nothing is ever returned on failure because failure
     * causes an exception to be thrown, so be ready to either
     * catch exception explicitely or let the WebPage to catch it
     * and generate web page or send json
     *
     * @throws \Lampcms\TwitterAuthException in case verification fails
     * This is helpful because this would cause this exception to be caught
     * in WebPage and returned in json array (in case request is by ajax)
     * and in turn it would present nice modal on page asking user
     * to start oAuth dance again.
     *
     *
     */
    public function verifyCredentials($token = null, $secret = null)
    {
        if (null !== $token && !is_string($token) && !is_object($token)) {
            throw new DevException('$token is not a string and not an object');
        }

        $User = null;

        if (is_string($token) && is_string($secret)) {
            $sToken = $token;
            $sSecret = $secret;
        }

        if (null === $token) {
            if (!isset($this->User)) {
                throw new DevException('$token is null and $this->User is not set');
            }

            $token = $this->User;
        }

        if (is_object($token)) {
            if (!($token instanceof TwitterUser)) {
                throw new DevException('param $token is not a string and not instance of User ' . get_class($token));
            }

            $User = $token;
            $sToken = $User->getTwitterToken();
            $sSecret = $User->getTwitterSecret();
        }

        if (empty($sToken) || empty($sSecret)) {

            throw new TwitterAuthException('empty_token');
        }

        try {
            $this->oAuth->setToken($sToken, $sSecret);
            $this->oAuth->fetch(self::URL_VERIFY_CREDENTIALS);

            return $this->getResponse($User);

        } catch (\OAuthException $e) {
            e('LampcmsError OAuthException: ' . $e->getMessage() . ' ' . print_r($e, 1));
            /**
             * Should NOT throw LampcmsTwitterAuthException because
             * we are not sure it was actually due to authorization
             * or many Twitter was bugged down or something else
             */
            throw new Exception('Something went wrong during authorization. Please try again later' . $e->getMessage());
        }
    }


    /**
     * Post message (update) to Twitter
     * using oAuth credentials from User
     *
     * If post is successfull then we update the User
     * with the latest profile data we get from Twitter
     * and call save(), so that our record will be updated
     * if profile data has changed
     *
     * @param string $sMessage message to post
     *
     * @param object $User
     *
     * @param int $inReplyToId twitter status id
     * Optional. The ID of an existing status that the update is in reply to.
     * Note: This parameter will be ignored
     * unless the author of the tweet this parameter references is mentioned
     * within the status text.
     * Therefore, you must include @username,
     * where username is the author of the referenced tweet, within the update.
     *
     * @throws LampcmsTwitterException or LampcmsTwitterAuthException on failure
     * @return array of data returned by Twitter
     */
    public function postMessage($sMessage, $inReplyToId = null)
    {
        d('cp');
        $this->url = self::URL_STATUS;
        $args = array('status' => $sMessage);
        if (null !== $inReplyToId) {
            $args['in_reply_to_status_id'] = $inReplyToId;
        }

        return $this->apiPost($args);
    }


    /**
     * Save Twitter status ID, userID and timestamp
     * into TWEETS collection
     * This is just for basic statistics to know
     * which user twitted how many times and when
     * and then can possibly get the full tweets
     * from Twitter by _id if necessary
     *
     * Currently this method is not used anywhere
     *
     *
     * @param mixed $tweet array in case of success
     * or any possible format returned by Twitter API otherwise
     *
     * @return object $this
     */
    protected function saveTweet($tweet)
    {
        if (empty($tweet)
            || !is_array($tweet)
            || empty($tweet['http_code'])
            || ('200' != $tweet['http_code'])
            || empty($tweet['id_str'])
        ) {
            d('Not successful tweet. Nothing to save');

            return $this;
        }

        try {
            $coll = $this->Registry->Mongo->TWEETS;
            $coll->ensureIndex(array('i_uid' => 1));

            $aData = array('_id' => $tweet['id_str'], 'i_uid' => $this->User->getUid(), 'i_ts' => time());
            $coll->save($aData);
        } catch (\Exception $e) {
            e('Unable to save data to TWEETS collection because of ' . $e->getMessage() . ' in file: ' . $e->getFile() . ' on line: ' . $e->getLine());
        }

        return $this;
    }


    /**
     * Prepare the raw string before posting to Twitter
     * It will convert string to guaranteed utf8,
     * then strip html tags then truncate to 140 chars
     *
     * @param string $sMessage
     * @param string $inReplyToId
     *
     * @return array of response data from Twitter API
     */
    public function prepareAndPost(Utf8String $Message, $inReplyToId = null)
    {
        $body = $Message->htmlspecialchars()->truncate(140)->valueOf();

        return $this->postMessage($body, $inReplyToId);
    }


    /**
     * Add or delete status from user's favorites
     * @param TwitterUserInterface $intStatusId
     * @param $User
     * @param $isDelete
     * @return unknown_type
     */
    public function updateFavorites($statusId, $isDelete = false)
    {

        d('$intStatusId: ' . $intStatusId);
        $action = ($isDelete) ? 'destroy' : 'create';
        $args = array('id' => $intStatusId);
        $this->url = sprintf(self::URL_FAVORITE, $action, $statusId);

        return $this->postApi($args);
    }


    /**
     * Set the authenticated user to follow
     * another user (usually to follow our own account)
     *
     * @param string $twitterUserId username or twitter user ID
     * if not set then we will use the one from !config.inc
     * TWITTER -> TWITTER_USERNAME
     */
    public function followUser($twitterUserId = null)
    {
        if (empty($twitterUserId)) {
            $twitterUserId = $this->aTwitterConfig['TWITTER_USERNAME'];
        }

        if (empty($twitterUserId)) {
            throw new DevException('No Twitter user to follow');
        }

        $this->url = sprintf(self::URL_FOLLOW, $twitterUserId);

        return $this->apiPost();
    }


    /**
     * Get oAuth token, secret from User and set
     * the values in this oAuth object
     *
     * @param TwitterUserInterface $User
     * @return object $this
     */
    public function setOAuthTokens($token = null, $secret = null)
    {
        d('this->User: ' . print_r($this->User->getArrayCopy(), 1));
        $token = (!empty($token)) ? $token : $this->User->getTwitterToken();
        $secret = (!empty($secret)) ? $secret : $this->User->getTwitterSecret();

        d('setting $token: ' . $token . ' secret: ' . $secret);

        $this->oAuth->setToken($token, $secret);

        return $this;
    }


    /**
     * Get the array of data that was
     * sent in response to Twitter API request
     * This will examine the value of http header
     * and will throw exception if header is 401 meaning
     * authorization failed
     *
     * @return mixed array of data returned by Twitter on success
     * nothing is returned on failure because an exception is thrown instead
     */
    protected function getResponse()
    {
        $aData = json_decode($this->oAuth->getLastResponse(), 1);

        $aDebug = $this->oAuth->getLastResponseInfo();
        d('debug: ' . print_r($aDebug, 1));
        if ('200' == $aDebug['http_code']) {
            $aData['http_code'] = $aDebug['http_code'];

            return $aData;

        } elseif ('401' == $aDebug['http_code']) {
            d('Twitter oauth failed with 401 http code. Data: ' . print_r($aData, 1));
            /**
             * If this method was passed User
             * then null the tokens
             * and save the data to table
             * so that next time we will know
             * that this User does not have tokens
             */
            if (is_object($this->User)) {
                d('Going to revoke access tokens for user object');
                $this->User->revokeOauthToken();
                /**
                 * Important to post this update
                 * so that user object will be removed from cache
                 */
                $this->Registry->Dispatcher->post($this->User, 'onTwitterUserUpdate');
            }

            /**
             * This exception should be caught all the way in WebPage and it will
             * cause the ajax message with special key=>value which will
             * trigger the popup to be shown to user with link
             * to signing with Twitter
             */
            throw new TwitterAuthException('twitter_credentials_failed');

        } else {
            e('verifyCredentials failed http code was: ' . $aDebug['http_code'] . ' full debug: ' . print_r($aDebug, 1) . ' response: ' . print_r($aData, 1));

            throw new TwitterException('twitter_auth_failed', array(), $aDebug['http_code']);
        }
    }


    /**
     * Makes a POST request to Twitter API
     * @param mixed $aData
     *
     * @return array with response data from Twitter API
     */
    protected function apiPost($aData = null)
    {

        if (!is_object($this->User)) {
            throw new TwitterException('Object of type UserTwitter was not set');
        }

        d('this->url: ' . $this->url);

        try {
            /**
             * For posting a Twitter update we must set
             * the authType to OAUTH_AUTH_TYPE_FORM
             * so that we will be using the POST method
             * when sending data to Twitter API
             */
            $authType = constant('OAUTH_AUTH_TYPE_FORM');
            d('$authType: ' . $authType);

            $this->oAuth->setAuthType($authType);
            $this->setOAuthTokens();
            d('fetching: ' . $this->url . ' data: ' . print_r($aData, 1));
            $this->oAuth->fetch($this->url, $aData);

        } catch (\OAuthException $e) {
            $aDebug = $this->oAuth->getLastResponseInfo();
            d('debug: ' . print_r($aDebug, 1));

            e('OAuthException: ' . $e->getMessage() . ' e: ' . print_r($e, 1));
            /**
             * Should NOT throw TwitterException because
             * we are not sure it was actually due to authorization
             * or maby Twitter was bugged down or something else
             */
            throw new TwitterException('Something went wrong during connection with Twitter. Please try again later' . $e->getMessage());
        }

        $aResponse = $this->getResponse();
        d('Twitter returned data: ' . print_r($aResponse, 1));

        /**
         * Now we should update user object since we just got back
         * the fresh profile data
         */
        if (!empty($aResponse) && !empty($aResponse['user'])) {
            /**
             * @todo
             * This does not look right. It may override
             * the avatar_external from facebook. We should
             * not really do this unless we store separate external
             * avatars per service like twitter, facebook, etc.
             */
            //$this->User['avatar_external'] = $aResponse['user']['profile_image_url'];
            //$this->User->saveIfChanged();

        }

        return $aResponse;
    }


}
