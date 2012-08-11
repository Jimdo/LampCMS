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

/**
 * Class for handling OUR Incoming HTTP Request
 * including headers and query string
 *
 * uses memoization. When value has been
 * filtered just put it into cache array so requests
 * for the same var will not have to go through
 * the same process of resolving  and filtering
 * Must first examine to possibilities of when user
 * sets the value of Request var by simply
 * doing something like $Request['myvar'] = 'new value'
 * It will just set the value into the underlying array object
 * but no way we can also put it into cache. This means if
 * user does this and then requests this var again, the cached
 * version will be returned and not the one user just added
 * to the object. This is super not-cool. Easy solution
 * is to just add to this->aFiltered from offsetSet()
 *
 * @author Dmitri Snytkine
 *
 */
class Request extends LampcmsArray implements Interfaces\LampcmsObject
{

    /**
     * Array of required query string params
     *
     * @var array
     */
    protected $aRequired = array();

    /**
     * Array of filtered params
     *
     * @var array but initially set to null
     */
    protected $aFiltered = array();

    protected static $ajax = null;

    /**
     * Array of UTF8 objects
     *
     * @var array of objects of type Utf8String
     */
    protected $aUTF8 = array();

    /**
     * @var object Uri\Router
     */
    protected $router;

    /**
     * @var object Config\Ini
     */
    protected $ini;

    /**
     * Constructor
     *
     * @param Uri\Router $router
     * @param Config\Ini $ini
     * @param array|null $a
     */
    public function __construct(\Lampcms\Uri\Router $router, \Lampcms\Config\Ini $ini, array $a = null)
    {

        $this->router = $router;
        $this->ini    = $ini;
        //$a            = (null === $a) ? array() : $a;
        if(!$a){
            if(self::isPost()){
                $a = $_POST;
            } else {
                /**
                 *
                 */
                $a = &$_GET;
            }
        }

        parent::__construct($a);
    }


    /**
     * @param bool $useRouter by default will use Uri\Router class
     *                        for all get requests.
     *                        This may not be desirable for the API calls, so can pass false to
     *                        keep using this class
     *
     * @return string name of controller based on the
     * request, always falls back to default controller
     * defined in !config.ini in [ROUTES] section as _DEFAULT_
     */
    public function getController($useRouter = true)
    {
        if (!$useRouter || self::isPost()) {
            return $this->get('a');
        }

        return $this->router->getController();
    }


    /**
     *
     * Mimics the HttpQueryString::get() method
     *
     * @param string $name
     * @param string $type    's' for string, 'i' for 'int', 'b' for bool
     *                        will accept other values but only understands these 3 and will default
     *                        to 's' (string) if unknown value is used
     *
     * @param string $default default value to return in
     *                        case param $name is not found
     *
     * @return bool|int|mixed|null|string
     */
    public function get($name, $type = 's', $default = null)
    {

        $val = (!$this->offsetExists($name)) ? $default : $this->offsetGet($name);

        switch ($type) {

            case 'b':
                $val = (bool)$val;
                break;

            case 'i':
                $val = (int)$val;
                break;

            default:
                $val = (string)$val;
        }

        return $val;
    }


    /**
     * Set array of params that are required
     * to be in request
     *
     * @param array $aRequired array of param names
     *
     * @return \Lampcms\Request
     */
    public function setRequired(array $aRequired = array())
    {
        /**
         * Since started using Url\Router it is problematic
         * to set required parameters for get requests
         * will keep using this only for post requests
         * The handling of required GET parameters will
         * now be done in individual controllers (GET parameters are now value if $this->Registry->Router->getUriSegments())
         */
        if (self::isPost()) {
            $this->aRequired = $aRequired;
        }

        return $this;
    }


    /**
     *
     * Getter for $this->aRequired
     *
     * @return array
     */
    public function getRequired()
    {
        return $this->aRequired;
    }


    /**
     * Return array of query params
     * values run through filter first
     * and result is also memoized
     *
     * @param bool $resetFiltered
     *
     * @return array $this->aFiltered
     */
    public function getArray($resetFiltered = true)
    {

        $a = $this->getArrayCopy();
        //d('raw request array: '.print_r($a, 1));
        foreach ($a as $key => $val) {
            if (!array_key_exists($key, $this->aFiltered)) {
                $this->aFiltered[$key] = $this->offsetGet($key);
            }
        }

        return $this->aFiltered;
    }


    /**
     * Initial check to see if request contains
     * all required parameterns
     *
     *
     * @throws \LogicException if at least
     * one required param is missing
     * @return object $this
     */
    public function checkRequired()
    {
        if (!empty($this->aRequired)) {
            foreach ($this->aRequired as $var) {
                if (!$this->offsetExists($var)) {
                    throw new \LogicException('Missing required query param: ' . $var);
                }
            }
        }

        return $this;
    }


    /**
     * Changing offsetGet does not affect get()
     *
     * @todo if requesting 'a' then use getController()
     * which will use Router for GET request OR will
     * use getFiltered('a')
     * do the same for pageID
     *
     * @param string $offset
     *
     * @throws DevException
     * @return int|mixed|string
     */
    public function offsetGet($offset)
    {

        /**
         * Offset (param in url or in post)
         * can only be ASCII char
         */
        $offset = \filter_var($offset, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

        /**
         * For 'a' and 'pageID' return
         * default values and don't go through
         * getFiltered() if values don't exist
         *
         */
        if (!$this->offsetExists($offset)) {
            if ('a' === $offset) {

                return  $this->ini->DEFAULT_CONTROLLER;

            } elseif ('pageID' === $offset) {
                return 1;
            }

            throw new \Lampcms\DevException('Request param ' . $offset . ' does not exist');
        }

        return $this->getFiltered($offset);
    }

    /**
     * Get value of page id extracted from request
     * if request is GET then will use Uri\Router::getPageID method
     *
     * @param bool $useRouter
     *
     * @return int value of page id (defaults to 1 is pageID not present in request)
     */
    public function getPageID($useRouter = true)
    {
        if (!$useRouter || self::isPost()) {
            return $this->get('pageID');
        }

        return $this->router->getPageID();
    }


    /**
     * This overrides the ArrayObject's own
     * method so that if something is added
     * to this object by using
     * $Request['var'] = $val then it is
     * automatically also added to aFiltered array
     *
     * @param $key
     * @param $val
     */
    public function offsetSet($key, $val)
    {

        parent::offsetSet($key, $val);
        $this->aFiltered[$key] = $val;
    }


    /**
     * Get filtered value of query string
     * param. Use $this->aFiltered as storage
     * for cached resolved values. This way multiple
     * requests for the same $name will only go
     * through filter once and then resolved filtered value
     * will be reused
     *
     * @param string $name name of query string param
     *
     * @throws \InvalidArgumentException
     * @return mixed string|bool|int depending on param type
     */
    protected function getFiltered($name)
    {

        d('getting filtered for ' . $name);

        if (!\array_key_exists($name, $this->aFiltered)) {
            d('cp not yet in $this->aFiltered');

            $val = parent::offsetGet($name);

            if ('a' === $name && !empty($val)) {

                $ret = self::getCleanControllerName($val);

            } elseif (
                ('i_' === \substr(\strtolower($name), 0, 2)) ||
                ('id' === \substr(\strtolower($name), -2, 2))
            ) {

                /**
                 * FILTER_VALIDATE_INT
                 * does not seem to accept 0 as a valid int!
                 * this sucks, so instead going to use is_numeric
                 */
                if ('' !== $val && !\is_numeric($val) || ($val < 0) || ($val > 99999999999)) {
                    throw new \InvalidArgumentException('Invalid value of "' . $name . '". It can only be a number between 0 and 99999999999 was: ' . \htmlentities($val));
                }

                $ret = (int)$val;

            } elseif ('_hex' === substr(\strtolower($name), -4, 4)) {
                $expression = '/^[0-9A-F]{6}$/';
                if (!filter_var($val, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $expression)))) {
                    throw new \InvalidArgumentException('Invalid value of ' . $name . ' it can only be a hex number. Was: ' . \htmlentities($val));
                }

                $ret = $val;

            } elseif ('flag' === \substr(\strtolower($name), -4, 4)) {

                /**
                 * FILTER_VALIDATE_BOOLEAN will not work here
                 * because it does not accept 0 as valid option,
                 * only 1, true, on, yes
                 * it just does not accept any values for 'false'
                 */
                if ($val != 1) {
                    throw new \InvalidArgumentException('Invalid value of ' . $name . ' It can only be an integer and not greater than 1, it was: ' . gettype($val) . ' val: ' . \htmlentities($val));
                }

                $ret = (bool)$val;

            } elseif ('token' === $name) {
                $ret = filter_var($val, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            } else {
                /**
                 * Do NOT use FILTER_STRIP_LOW, it may look like a good idea but
                 * it removes all line breaks in text!
                 */
                $ret = $val; //filter_var($val, FILTER_SANITIZE_STRING); //, FILTER_FLAG_STRIP_LOW

            }

            $this->aFiltered[$name] = $ret;
        }

        return $this->aFiltered[$name];
    }


    /**
     *
     * Get value of requested param converted
     * to Utf8String object
     *
     * @param string $name
     * @param mixed  $default fallback value in case
     *                        the param $name does not exist if Request
     *
     * @return object of type Utf8String representing the value
     * of requested param
     */
    public function getUTF8($name, $default = null)
    {
        if (empty($this->aUTF8[$name])) {
            $res                = $this->get($name, 's', $default);
            $ret                = Utf8String::stringFactory($res);
            $this->aUTF8[$name] = $ret;
        }

        return $this->aUTF8[$name];
    }


    /**
     * @return request method like "GET" or "POST"
     * and always in UPPER CASE
     *
     */
    public static function getRequestMethod()
    {

        return (isset($_SERVER) && !empty($_SERVER['REQUEST_METHOD'])) ? \strtoupper($_SERVER['REQUEST_METHOD']) : null;
    }


    /**
     * Get value of specific request header
     *
     * @param string $strHeader
     *
     * @return mixed string value of header or null
     * if header not found
     */
    public final static function getHttpHeader($strHeader)
    {

        $strKey = 'HTTP_' . \strtoupper(\str_replace('-', '_', $strHeader));
        if (!empty($_SERVER[$strKey])) {

            return $_SERVER[$strKey];
        }

        /**
         * Fix case of request header, this way the
         * param $strHeader is NOT case sensitive
         *
         */
        if (\function_exists('apache_request_headers')) {
            $strHeader  = (\str_replace(" ", "-", (\ucwords(\str_replace("-", " ", \strtolower($strHeader))))));
            $arrHeaders = \apache_request_headers();

            if (!empty($arrHeaders[$strHeader])) {

                return $arrHeaders[$strHeader];
            }
        }

        return null;
    }


    /**
     *
     * @return bool true if request is via Ajax, false otherwise
     *
     */
    public static final function isAjax()
    {
        if (null !== self::$ajax) {

            return self::$ajax;
        }

        if ((isset($_GET) && !empty($_GET['ajaxid'])) ||
            (isset($_POST) && !empty($_POST['ajaxid']))
        ) {
            self::$ajax = true;
            d('ajaxid true');

            return true;
        }

        self::$ajax = (\strtoupper((string)self::getHttpHeader('X-REQUESTED-WITH')) === 'XMLHTTPREQUEST');

        return self::$ajax;
    }


    /**
     * Check to see if iframeid param exists in query string
     *
     * @return bool true if iframeid is one of the params
     */
    public static function isIframe()
    {

        return !empty($_REQUEST['iframeid']);
    }


    /**
     * Returns ip address of client
     * and falls back to localhost address
     *
     *
     * @return string ip address
     */
    public static function getIP()
    {

        return (isset($_SERVER) && !empty($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.2';
    }


    /**
     * Get useragent of user making a request
     *
     * @return mixed string useragent | null if user agent not present
     *
     */
    public static function getUserAgent()
    {

        $sUserAgent = (isset($_SERVER) && isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : null;

        return $sUserAgent;
    }

    /**
     * Check if request has been submitted
     * via the POST method
     *
     * @return bool true if request came via HTTP POST
     */
    public static function isPost()
    {
        return self::getRequestMethod() === 'POST';
    }


    /**
     * Validation of controller name
     * Controller name can contain only letters
     * and be up to 32 chars long
     *
     * @param $name name of controller
     *
     * @throws \InvalidArgumentException
     * @return string controller name in lower case
     */
    public static function getCleanControllerName($name)
    {
        $expression = '/^[[:alpha:]\-]{1,32}$/';
        /**
         * @todo maybe we can allow non-alpha chars in uri
         * We can try to allow controller names be encoded in utf8?
         */
        if (!\filter_var($name, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $expression)))) {
            throw new \InvalidArgumentException('Invalid value of controller name. It can only contain letters and a hyphen and be limited to 32 characters in total was: ' . \htmlentities($name));
        }

        return \mb_strtolower($name);
    }

}
