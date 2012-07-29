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

use \Lampcms\Forms\Form;
use \Lampcms\Cookie;

/**
 * This abstract class is responsible for generating
 * an output page.
 *
 * This is an abstract class and must be extended by the
 * actual controller class
 *
 * The controller class must implement the main() method which
 * will be called by this class automatically
 * after some important pre-processing and initialization
 * have taken place: for example that required variables have
 * all been submitted (if any)
 * Also if controller has a specific $permission
 * set as instance variable
 * this class will check that the current user
 * (represented as Viewer object)
 * has required access level to perform this 'permission'
 * an ACL is used for permission check and is based on user group membership
 * which is called 'role' in ACL jargon.
 *
 * @author Dmitri Snytkine
 *
 */
abstract class WebPage extends Base
{

    /**
     * HTTP Response code to send
     * the value of 200 is default
     * This is useful only when handling certain
     * exceptions that may indicate a '404 not found' error
     * in which case we pass the 404 as error code
     * of exception and then can set the http 404 response
     *
     * @var int
     */
    protected $httpCode = 200;


    /**
     * Array of required GET or POST
     * parameters.
     * These must not be empty
     *
     * @var array
     */
    protected $aRequired = array();


    /**
     * Object representing Array of QUERY_STRING params
     * this is GET or POST array
     *
     * @var object of type Request
     */
    protected $Request;


    /**
     * Flag indicates that
     * the page being rendered for a mobile
     * device. This means content should
     * be in a short format - titles only
     * for a list of articles, etc.
     *
     * @var bool
     */
    protected $isMobile = false;


    /**
     * Array holds
     * links generated by pager class
     *
     * @var
     */
    protected $arrPagerLinks;


    /**
     * Links generated by Paginator
     *
     * @var string html to show pagination links
     */
    protected $pagerLinks = '';


    /**
     * Flag indicates that REQUEST_METHOD
     * MUST be POST
     *
     * @var bool
     */
    protected $bRequirePost = false;


    /**
     * extra javascript(s) to add to this page
     * if class extending this class has this property,
     * then value will be added to the bottom
     * of the page as value of script tag
     *
     * @var mixed string or array of strings
     */
    protected $lastJs = array();


    /**
     * Extra css files to add for the page
     *
     * @var mixed string (path to .css file) or
     * array of such strings
     */
    protected $extraCss = array();


    /**
     * Flag indicates that
     * we require to validate a form token
     *
     * @var bool
     */
    protected $requireToken = false;


    /**
     * Name of template dir
     * for mobile output it should
     * be dynamically changed to 'mobile'
     * it can also be changed to 'tablet'
     * for tablet screens
     *
     * @var string
     */
    protected $tplDir = 'www';


    /**
     *
     * This is for skinning/styling support
     * right now we only have 1 style,
     * it has id = 1
     *
     * @var mixed int | numeric string
     */
    protected $styleID = '1';


    /**
     *
     * layoutID 1 means 1-pane page: no nav div, just one main div
     * layoutID 2 means 2-pane page: main div and nav div
     *
     * @var mixed int | numeric string
     */
    protected $layoutID = '2';


    /**
     * Array of replacement vars
     * for the tplMain template
     *
     * @var array
     */
    protected $aPageVars;


    /**
     * Controller may override this
     * to skip initPageVars() method
     * This is helpful when controller is called by Ajax only
     * so int's not necessary to initialize page vars
     *
     * @var bool
     */
    protected $bInitPageVars = true;

    /**
     * Translator object
     *
     * @var object of type \I18n\Translator
     */
    protected $Tr;


    protected $action;

    /**
     * @var \Lampcms\Uri\Router object
     */
    protected $Router;


    /**
     * Constructor
     *
     * @return \Lampcms\WebPage
     *
     * @param \Lampcms\Registry|object     $Registry
     * @param \Lampcms\Request|null|object $Request
     */
    public function __construct(Registry $Registry, Request $Request = null)
    {

        parent::__construct($Registry);

        $this->Request = (null !== $Request) ? $Request : $Registry->Request;
        $this->Router  = $this->Registry->Router;
        $this->action  = $this->Request['a'];

        $this->initParams()
            ->setTemplateDir()
            ->setLocale()
            ->loginByFacebookCookie()
            ->loginBySid()
            ->setTimeZone()
            ->initPageVars()
            ->addJoinForm()
            ->addLangForm()
            ->addJsTranslations();

        Cookie::sendFirstVisitCookie();
        d('cp');
        try {
            $this->checkLoginStatus()
                ->checkAccessPermission()
                ->main();
        } catch ( Exception $e ) {
            /**
             * Only Exceptions in the Lampcms Namespace
             * (the ones that extend Lampcms\Exception)
             * will be handled here, others bubble up to
             * index.php
             */
            $this->handleException($e);
        }

        d('cp');

        /**
         * Observer will be able to
         * record access of current Viewer to
         * the current page for the purpose of
         * recording of who's online and at what url
         */

        $this->Registry->Dispatcher->post($this, 'onPageView', $this->aPageVars);

        //\Lampcms\Log::dump();
    }


    /**
     * Set default timezone
     * Only if Viewer is not logged in because for logged in user
     * the timezone is automatically set when Viewer is created
     * in Registry
     *
     * @return object $this
     *
     * @throws DevException
     */
    protected function setTimeZone()
    {
        if (!$this->isLoggedIn()) {
            if (false !== $tzn = Cookie::get('tzn')) {
                $timezone = $tzn;
            } else {
                $timezone = $this->Registry->Ini->SERVER_TIMEZONE;
            }

            d('Setting timezone for not logged in user to: ' . $timezone);

            if (false === \date_default_timezone_set($this->Registry->Ini->SERVER_TIMEZONE)) {

                if (isset($tzn)) {
                    $err = 'Invalid name of  timezone: ' . $tzn;
                    Cookie::delete('tzn');
                } else {
                    $err = 'Invalid name of  timezone: ' . $timezone . ' Check "SERVER_TIMEZONE" in !config.ini constant. The list of valid timezone names can be found here: http://us.php.net/manual/en/timezones.php';
                }

                throw new \Lampcms\DevException($err);
            }
        }

        return $this;
    }


    protected function setLocale()
    {
        $this->Registry->Locale->setLocale();
        $this->Tr = $this->Registry->Tr;

        return $this;
    }


    /**
     * Translator method
     * It's customary in many projects to
     * use the single underscore
     * symbol for translation function.
     *
     * @param string $string string to translate
     *
     * @param array  $vars   optional array of replacement vars for
     *                       translation
     *
     * @return string translated string
     */
    protected function _($string, array $vars = null)
    {

        return $this->Tr->get($string, $vars);
    }


    /**
     * Magic method
     * Must use try/catch here
     * because php turns uncaught exception in __toString
     * into a nasty error message
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->getResult();
        } catch ( \Exception $e ) {
            e('Exception in ' . __METHOD__ . ' ' . $e->getMessage());
            return \Lampcms\Responder::makeErrorPage(Exception::formatException($e));
        }
    }


    /**
     * Must be implemented in sub-class
     * this should contain the main
     * logic of controller class.
     *
     * The purpose of this method is for a controller
     * class to populate
     * the $this->aPageVars array
     *
     * @return
     */
    abstract protected function main();


    /**
     * Check Request object for required params
     * as well as for required form token
     *
     * @throws Exception
     * @return object $this
     */
    protected function initParams()
    {

        if ($this->bRequirePost && ('POST' !== Request::getRequestMethod())) {
            throw new Exception('POST method required');
        }

        $this->Request->setRequired($this->aRequired)->checkRequired();

        if (true === $this->requireToken) {
            \Lampcms\Forms\Form::validateToken($this->Registry);
        }

        d('cp');

        return $this;
    }


    /**
     * Setup initial
     * $this->aPageVars variables.
     * This variables are used by tplMain
     * - a main web page templates, regardless of
     * which controller is called - these vars are
     * always added to the page
     *
     *
     * @return object $this
     */
    protected function initPageVars()
    {
        if (!$this->bInitPageVars
            || Request::isAjax()
            || 'logout' === $this->action
            || 'login' === $this->action
        ) {
            d('special case: ' . $this->action);

            return $this;
        }

        $Viewer          = $this->Registry->Viewer;
        $this->aPageVars = \tplMain::getVars();

        $Ini                                     = $this->Registry->Ini;
        $this->aPageVars['site_title']           = $Ini->SITE_TITLE;
        $this->aPageVars['site_url']             = $Ini->SITE_URL;
        $this->aPageVars['description']          = $this->aPageVars['site_description'] = $Ini->SITE_NAME;
        $this->aPageVars['show_comments']        = $Ini->SHOW_COMMENTS;
        $this->aPageVars['max_comments']         = $Ini->MAX_COMMENTS;
        $this->aPageVars['comments_timeout']     = $Ini->COMMENT_EDIT_TIME;
        $this->aPageVars['layoutID']             = $this->layoutID;
        $this->aPageVars['DISABLE_AUTOCOMPLETE'] = $Ini->DISABLE_AUTOCOMPLETE;
        $this->aPageVars['VERSION_ID']           = VERSION_ID;
        $this->aPageVars['home']                 = $this->_('Home');

        /**
         * @todo later can change to something like
         * $this->Registrty->Viewer->getStyleID()
         *       To load style selected by user
         *
         */
        $css                         = (true === LAMPCMS_DEBUG || \strstr(VERSION_ID, 'package_version')) ? '/_main.css?t=' . time() : '/main.css';
        $this->aPageVars['main_css'] = $Ini->CSS_SITE . '{_DIR_}/style/' . STYLE_ID . '/' . VTEMPLATES_DIR . $css;

        $aFacebookConf = $Ini->getSection('FACEBOOK');

        if (!empty($aFacebookConf)) {
            if (!empty($aFacebookConf['APP_ID'])) {
                $this->addMetaTag('fbappid', $aFacebookConf['APP_ID']);
                $this->addFacebookJs($aFacebookConf['APP_ID']);

                if (!empty($aFacebookConf['EXTENDED_PERMS'])) {
                    $this->addMetaTag('fbperms', $aFacebookConf['EXTENDED_PERMS']);
                }
            }
        }

        $this->aPageVars['session_uid'] = $Viewer->getUid();
        $this->aPageVars['role']        = $Viewer->getRoleId();
        $this->aPageVars['rep']         = $Viewer->getReputation();
        $this->aPageVars['version_id']  = Form::generateToken();
        /**
         * meta 'tw' will be set to string "1" if user has conneted Twitter
         */
        $this->addMetaTag('tw', ('' !== (string)$Viewer->getTwitterSecret()));
        /**
         * meta 'tw' will be set to string "1" if user has conneted Facebook
         */
        $this->addMetaTag('fb', ('' !== (string)$Viewer->getFacebookToken()));

        $js = (true === LAMPCMS_DEBUG || \strstr(VERSION_ID, 'package_version')) ? '/qa.js?t=' . time() : '/min/qa_' . VERSION_ID . '.js';

        $src = $Ini->JS_SITE . '{_DIR_}/js' . $js;

        $this->aPageVars['JS'] = $src;
        /**
         * @todo
         *  also add twitter id or username or just 'yes'
         *  of viewer so that we know viewer has twitter account
         *  and is capable of using twitter from our API
         *  Also we can ask use to add Twitter account
         *  if we know he does not have one connected yet
         */

        return $this;
    }


    /**
     *
     * Add JavaScript for Facebook UI to the page
     *
     * @param string $appId value from !config.ini 'FACEBOOK' -> 'APP_ID'
     *
     * @return \Lampcms\WebPage (this object)
     */
    protected function addFacebookJs($appId)
    {
        /**
         * Do NOT add Facebook JS
         * to the page with logout flag
         * in order to prevent FB from adding
         * fb cookie again
         */
        $this->aPageVars['fb_js'] = \tplFbJs::parse(array($appId), false);


        return $this;
    }


    /**
     * Add extra meta tag to the page
     *
     * @param string $tag name of tag
     * @param string $val value of tag
     *
     * @return object $this
     */
    protected function addMetaTag($tag, $val)
    {
        $meta = CRLF . \sprintf('<meta name="%s" content="%s">', $tag, $val);

        $this->aPageVars['extra_metas'] .= $meta;

        return $this;
    }


    /**
     * If user session did not
     * contain data that allowed to
     * treat user as logged in, then
     * try to login user by uid/sid cookies
     * This will work if user has previously
     * logged in and selected the 'remember me'
     * check box.
     *
     * @return object $this OR redirects back
     * to the same page but with SESSION setup
     * with user data, so user will be detected as logged-in
     * after the redirect
     */
    protected function loginBySid()
    {

        if ($this->isLoggedIn() || 'logout' === $this->action || 'login' === $this->action) {
            d('cp');
            return $this;
        }

        if (!isset($_COOKIE) || !isset($_COOKIE['uid']) || !isset($_COOKIE['sid'])) {
            d('uid or sid cooke not set');

            return $this;
        }

        try {
            $oCheckLogin = new CookieAuth($this->Registry);
            $User        = $oCheckLogin->authByCookie();
            d('aResult: ' . \print_r($User->getArrayCopy(), 1));

        } catch ( CookieAuthException $e ) {
            d('LampcmsError: login by sid failed with message: ' . $e->getMessage());
            Cookie::delete(array('uid'));

            return $this;
        }

        /**
         * Login OK
         * used to also
         * ->setUserTimezone($this->oViewer)
         * but its not necessary because user
         *  will be redirected anyway
         */
        $this->processLogin($User);
        $this->Registry->Dispatcher->post($this, 'onCookieLogin');

        return $this;
    }


    /**
     * Authenticate user if user has fbc_ cookie
     * This cookie is set right after user clicks
     * on login with facebook button
     *
     * @return object $this
     */
    protected function loginByFacebookCookie()
    {

        $action = $this->action;

        if ($this->isLoggedIn()
            || 'logout' === $action
            || 'connectfb' === $action
            || 'login' === $action
        ) {
            d('skipping loginByFacebookCookie');
            return $this;
        }

        try {
            $oViewer = $this->Registry->Facebook->getFacebookUser();

            $this->processLogin($oViewer);
            d('logged in facebook user: ' . $this->Registry->Viewer->getUid());
            $this->Registry->Dispatcher->post($this, 'onFacebookLogin');

        } catch ( FacebookAuthException $e ) {
            d('Facebook login failed. ' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
        }

        return $this;
    }

    /**
     *
     * Set User object as a currently logged in
     * User, aka Viewer
     * Set this Viewer as a value of $_SESSION['viewer']
     * array
     *
     * @param User $User
     * @param bool $bResetSession
     *
     * @return \Lampcms\WebPage
     * @throws LoginException
     */
    protected function processLogin(User $User, $bResetSession = false)
    {

        /**
         * This little thing is not
         * necessary for web-page type of request
         * but just in case request was made by
         * some other means like by email
         *
         */
        if (!isset($_SESSION)) {
            $_SESSION = array();
        }

        /**
         * This give a change for some sort of filter to examine twitter id, twitter name
         * and possibly disallow the login
         *
         */
        if (false === $this->Registry->Dispatcher->post($User, 'onBeforeUserLogin')) {
            d('onBeforeUserLogin returned false');
            throw new LoginException('Access denied');
        }

        if ($bResetSession) {
            session_regenerate_id();
        }

        $this->Registry->Viewer = $User;
        $_SESSION['viewer']     = array('id'    => $User->getUid(),
                                        'class' => $User->getClass());

        /**
         * This is important otherwise
         * the old stale value is used
         * when checking isLoggedIn()
         */
        if (isset($this->bLoggedIn)) {
            unset($this->bLoggedIn);
        }

        /**
         * Remove navlinks block from
         * session because
         * after user logged-in he suppose to see
         * different links block
         */
        if (!empty($_SESSION)) {
            $_SESSION['navlinks']    = array();
            $_SESSION['login_form']  = null;
            $_SESSION['login_error'] = null;
            $_SESSION['langs']       = null;
        }

        return $this;
    }


    /**
     * Performs the last step in assembling
     * the page
     *
     * @return string parsed tplMail template
     * with the {timer} replaced with
     * page generation time (if present)
     */
    public function getResult()
    {

        if (404 === $this->httpCode) {
            d('setting 404 error code');
            header("HTTP/1.0 404 Not Found");
        }

        d('cp');
        $this->addLoginBlock();
        d('cp');
        $this->addLastJs();
        d('cp');
        $this->addExtraCss();
        d('cp');
        $tpl = \tplMain::parse($this->aPageVars);
        d('cp');

        return $tpl;
    }


    /**
     * Adds (appends) value to last_js element of page
     *
     * @todo check if relative path of src
     *       then also take into account
     *       config option JS
     *
     * @return object $this
     */
    protected function addLastJs()
    {
        /**
         * If browser does not have tzn cookie then include
         * the tz.js script - it sets the timezone cookie.
         *
         */
        if (false === Cookie::get('tzn')) {
            $this->lastJs[] = '{_DIR_}/js/min/tz.js';
        }

        if (!empty($this->lastJs)) {
            foreach ((array)$this->lastJs as $val) {
                $this->aPageVars['last_js'] .= CRLF . '<script type="text/javascript" src="' . $val . '"></script>';
            }
        }

        return $this;
    }


    /**
     *
     * Adds extra stylesheet(s) to the page
     *
     * @return object $this
     */
    protected function addExtraCss()
    {

        try {
            if ($this->Registry->Ini->SHOW_FLAGS) {

                $this->extraCss[] = $this->Registry->Ini->CSS_SITE . '{_DIR_}/css/flags.css';
            }
        } catch ( \Lampcms\IniException $e ) {
            e($e->getMessage());
        }

        if (!empty($this->extraCss)) {
            d('Got extra css to add');
            $this->aPageVars['extra_css'] = '';
            foreach ($this->extraCss as $val) {
                $this->aPageVars['extra_css'] .= CRLF . \sprintf('<link rel="stylesheet" type="text/css" href="%s">', $val);
            }
        }

        return $this;
    }


    /**
     * If ENABLE_CODE_EDITOR is set to true
     * in !config.ini then
     * add additional 2 js and 1 css file to the page
     * This will enable the "Code Editor" and "Code highlighter"
     * widgets in the YUI Editor
     *
     * This method is called from 2 different controllers:
     * Ask and Viewquestion
     * That's why it's here in just one place - so it does
     * not have to be duplicated in each controller
     *
     * @return object $this
     */
    protected function configureEditor()
    {
        $a = $this->Registry->Ini->getSection('EDITOR');
        if ($a['ENABLE_CODE_EDITOR']) {
            d('enabling code highlighter');
            $this->lastJs     = array('{_DIR_}/js/min/shCoreMin.js', '{_DIR_}/js/min/dsBrushes.js');
            $this->extraCss[] = '{_DIR_}/js/min/sh.css';
        }

        if ($a['ENABLE_YOUTUBE']) {
            $this->addMetaTag('btn_yt', '1');
        }

        return $this;
    }


    /**
     * Adds the Login forum or Welcome block
     *
     * @return object $this
     */
    protected function addLoginBlock()
    {
        if ('logout' !== $this->action) {
            $this->aPageVars['header'] = LoginForm::makeWelcomeMenu($this->Registry);
        }

        return $this;
    }


    /**
     * Formats the exception, adding
     * additional exception data like backtrace
     * if running in debug mode
     * then adds the 'error' to a page
     *
     * @return void
     *
     * @param \Lampcms\Exception $le
     *
     * @throws \OutOfBoundsException
     * @throws \Exception|\OutOfBoundsException
     * @internal param object $e Exception object
     */
    public function handleException(\Lampcms\Exception $le)
    {
        try {

            if ($le instanceof RedirectException) {
                \session_write_close();
                $newUrl = $le->getMessage();
                /**
                 * If redirect url contains any URI placeholders
                 * (like {_WEB_ROOT_} for example)
                 * then use mapper callback function
                 * to replace those placeholders
                 */
                if (\strstr($newUrl, '{_')) {
                    $mapper = $this->Router->getCallback();
                    $newUrl = $mapper($newUrl);
                }

                /**
                 * If a relative url then turn into
                 * full url to comply with w3c standard that says
                 * redirect headers must point to complete url
                 */
                if (0 !== \strncasecmp('http', $newUrl, 4)) {
                    $newUrl = $this->Registry->Ini->SITE_URL . $newUrl;
                }

                header("Location: " . $newUrl, true, $le->getCode());
                throw new \OutOfBoundsException;
            }

            if ($le instanceof CaptchaLimitException) {
                d('Captcha limit reached.');
                /**
                 * @todo add ip to CAPTCHA_HACKS collection
                 *
                 */
            }

            /**
             * In case of LampcmsAuthException
             * the value of 'c' attribute in exception
             * element will be set to "login"
             * indicating to template that this
             * is a 'must login' type of exception
             * and to render the login form
             *
             */
            $class = ($le instanceof AuthException) ? 'login' : 'excsl';

            /**
             * Special case:
             * the http error code can be
             * passed in exception as third argument
             * (in case where there are no second arg,
             * the second arg must be passed as null)
             */
            if (201 < $errCode = $le->getCode()) {
                $this->httpCode = (int)$errCode;
            }

            if ($le instanceof Lampcms404Exception) {
                $this->httpCode = 404;
            }

            /**
             * e() will also email error to developer
             * In case of Login and OutOfBoundsException we don't want
             * to email developers
             * We also don't want to email developers
             * in special types of exceptions that have
             * code set to -1
             * which is a way to say
             * "This exception is not severe enough for email to be sent out"
             */
            if (
                ($errCode >= 0) &&
                !($le instanceof Lampcms404Exception) &&
                !($le instanceof AuthException) &&
                !($le instanceof LoginException) &&
                !($le instanceof NoticeException) &&
                !($le instanceof MustLoginException) &&
                !($le instanceof \OutOfBoundsException)
            ) {
                e('Exception caught in: ' . $le->getFile() . ' on line: ' . $le->getLine() . ' ' . $le->getMessage() . ' trace: ' . $le->getTraceAsString());
            }

            /**
             *
             * Exception::formatException will correctly
             * handle sending out JSON and exiting
             * if the request isAjax
             *
             */
            $err = Exception::formatException($le, null, $this->Registry->Tr);
            /**
             * @todo if Login exception then present a login form!
             *
             */
            $this->aPageVars['layoutID'] = 1;
            $this->aPageVars['body']     = \tplException::parse(array('message' => \nl2br($err),
                                                                      'class'   => $class,
                                                                      'title'   => $this->_('Alert')));

        } catch ( \OutOfBoundsException $e ) {
            throw $e;
        } catch ( \Exception $e ) {
            e('Exception object ' . $e->getMessage());
            $err = Responder::makeErrorPage($le->getMessage() . ' in ' . $e->getFile());
            echo $err;
            fastcgi_finish_request();
            throw new \OutOfBoundsException;
        }

    }


    /**
     * This method is called from Login and
     * from wwwOauth, thus its here in one place
     *
     * @return array of user profile and welcome html div
     * if user is logged in, or empty array otherwise
     */
    protected function makeLoginArray()
    {
        $a = array();
        d('cp');
        if ($this->isLoggedIn()) {
            $welcome      = LoginForm::makeWelcomeMenu($this->Registry);
            $a['welcome'] = $welcome;
        }

        return $a;
    }


    /**
     * Validates the value of form token
     * passed in form against the one stored in SESSION
     *
     * @todo validate (store it first) IP address
     *       of request that it must match ip when token is validate
     *       and throw special type of Exception so that a user will
     *       get explanation that IP address has changed
     *
     * @param string $token value as passed in the submitted form
     *
     * @throws TokenException
     * @return true of success
     */
    protected function validateToken($token = null)
    {

        $message = '';
        $token   = ((null === $token) && !empty($this->Request['token'])) ? $this->Request['token'] : $token;

        if (empty($_SESSION['secret'])) {
            d("No token in SESSION ");
            /**
             * @todo
             * Translate String
             */
            $message = 'Form_token_missing';
        } elseif ($_SESSION['secret'] !== $token) {
            d('session token: ' . $_SESSION['secret'] . ' supplied token: ' . $token);
            $message = 'wrong form token';
        }

        if (!empty($message)) {

            if (Request::isAjax()) {
                Responder::sendJSON(array('exception' => $message));
            }

            throw new TokenException($message);
        }

        return true;
    }


    /**
     * Add extra div with "Join" form
     * where we ask to provide email address
     * after user joins with external provider
     *
     * @return object $this
     */
    protected function addJoinForm()
    {
        if (!$this->bInitPageVars || !Request::isAjax() && ('remindpwd' !== $this->action) && ('logout' !== $this->action)) {
            /**
             * If user opted out of continuing
             * registration, the special 'dnd' or "Do not disturb"
             * cookie was set via Javascritp
             * We will respect that and will not show that same
             * nagging prompt again
             *
             * This cookie is deleted on Logout
             *
             * @todo set ttl for this cookie to last only a couple of days
             *       so we can keep nagging user again after awhile until user
             *       finally enters email address
             *       Also do not have to check if user is UserExternal - if user
             *       does not have email address then keep nagging the user
             *       The thing is - only external user can possibly be logged in without
             *       any email address because normal user will not know their password
             *       since temp passwords are sent to email.
             */
            $cookie = Cookie::get('dnd');
            d('dnd: ' . $cookie);
            if (!$cookie) {

                if ($this->Registry->Viewer instanceof UserExternal) {
                    $email = $this->Registry->Viewer->email;
                    d('email: ' . $email);
                    if (empty($email)) {
                        $sHtml = RegBlock::factory($this->Registry)->getBlock();
                        d('$sHtml: ' . $sHtml);
                        $this->aPageVars['extra_html'] = $sHtml;
                    }
                }
            }
        }

        return $this;
    }


    /**
     * Define the location of templates
     * This is usually used for pointing
     * to special 'mobile' directory when
     * we need to serve mobile pages
     *
     * @todo something like this:
     *       Registry->Viewer->getStyleId().DS.$this->tplDir
     *       where getStyleId will return whatever user
     *       has selected with fallback to default '1'
     *
     * @return object $this
     */
    protected function setTemplateDir()
    {

        d('setting template dir');

        \define('STYLE_ID', $this->styleID);
        \define('VTEMPLATES_DIR', $this->tplDir);

        return $this;
    }


    /**
     * Add the drop-down menu for Language selection
     * to the page vars
     *
     * @return $this
     */
    protected function addLangForm()
    {
        d('cp');
        $this->aPageVars['langsForm'] = $this->Registry->Locale->getOptions();

        return $this;
    }


    /**
     * Add Javascript translations object
     * ONLY if the current locale is NOT English
     *
     * @return WebPage
     */
    protected function addJsTranslations()
    {
        if (!isset($_SESSION['locale']) || 0 !== stripos($_SESSION['locale'], 'en')) {
            $this->aPageVars['js_strings'] = \tplTranslations::parse(array(), false);
        }

        return $this;
    }

}
