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


namespace Lampcms\Cache;

use \Lampcms\ArrayDefaults;
use \Lampcms\Registry;
use \Lampcms\DevException;


/**
 *
 * @author Dmitri Snytkine
 *
 */
class Cache extends \Lampcms\Event\Observer
{

    /**
     * arrayObject used for storing results
     * of found values when using __isset() method
     *
     * @var object of type ArrayDefaults
     */
    protected $Tmp;

    /**
     * arrayObject where key is cache key
     * and value is integer number of seconds to cache that key
     * when adding to cache.
     *
     * @var object of type ArrayDefaults
     */
    protected $oTtl;

    /**
     * If set to true the Cache will NOT be checked
     * when looking for keys and no values will be put in cache
     * This can be used for debugging purposes only
     * Never set this to true on a production server because this would
     * defeat the purpose of using this class (cache will not be used)
     *
     * @var bool
     */
    protected $skipCache = false;


    /**
     * array of extra params
     * it is used in getKeyValue function
     *
     * @var array
     */
    protected $arrExtra = array();

    /**
     * Arrays of keys for which values were not found
     * using get()
     * Values for these keys will be recreated
     * and set in cache
     *
     * @var array
     */
    protected $aMissingKeys = array();

    /**
     * Array of value to be returned
     * to client from get() method
     *
     * @var array
     */
    protected $aReturnVals = array();


    protected $CacheInterface;

    /**
     * Array of identifying tags for cache
     *
     * @var array
     */
    protected $aTags = null;

    /**
     * @param Registry $Registry
     */
    public function __construct(Registry $Registry)
    {
        d('starting Cache');
        parent::__construct($Registry);
        $this->oTtl      = new ArrayDefaults(array(), 0);
        $this->Tmp       = new ArrayDefaults(array());
        $this->skipCache = $Registry->Ini->SKIP_CACHE;
        d('cp');
        if (!$this->skipCache) {
            d('cp');
            $this->setCacheEngine(Mongo::factory($Registry));
            $Registry->Dispatcher->attach($this);
        }
    }


    /**
     * Since this is a singleton object
     * we should disallow cloning
     *
     * @return void
     * @throws \Lampcms\DevException
     */
    public function __clone()
    {
        throw new \Lampcms\DevException('Cloning this object is not allowed.');
    }


    public function __toString()
    {

        return 'object Lampcms\Cache\Cache';
    }


    /**
     * Get value for key or array of keys
     *
     * @param                                       $key
     *
     * @param Callback|\Lampcms\Cache\Callback|null $callback
     * @param array                                 $arrExtra array of extra parameters. Some functions
     *                                                        need certain extra parameters
     *
     * @throws \Lampcms\DevException
     * @return mixed usually a date for a requested key but could be null
     * in case of some problems
     */
    public function get($key, Callback $callback = null, array $arrExtra = array())
    {

        d('$key: ' . $key);
        if (!empty($arrExtra)) {
            d(' $arrExtra: ' . print_r(array_keys($arrExtra), 1));

            $this->arrExtra = $arrExtra;
        }

        if (\is_string($key)) {
            d('cp');
            $res = $this->getFromCache($key);
            if (false === $res) {
                $res = $this->getKeyValue($key, $callback);

                $this->setValues($key, $res);
            }

            return $res;

        } elseif (is_array($key)) {

            $arrRequestKeys = $key;

            $this->aReturnVals  = array();
            $this->aMissingKeys = array();

            /**
             * Requesting several keys at once in an array
             * will try to get them all at once
             * but if some could not be found, then
             * request missing keys one at a time
             */
            $this->aReturnVals = $this->getFromCache($arrRequestKeys);

            $this->aReturnVals = (false === $this->aReturnVals) ? array() : $this->aReturnVals;

            $arrRequestKeys = \array_flip($arrRequestKeys);

            d('$arrRequestKeys: ' . \print_r($arrRequestKeys, 1));

            $this->aMissingKeys = \array_diff_key($arrRequestKeys, $this->aReturnVals);
            d('$this->aMissingKeys: ' . \print_r($this->aMissingKeys, 1));

            /**
             * If we did not get any of the requested keys from cache
             * we need to get it one by one and then add it to $arrValues
             */
            if (!empty($this->aMissingKeys)) {
                $this->getMissingKeys();
            }

            return $this->aReturnVals;
        }

        throw new DevException('Requested key can only be a string or array. Supplied value was of type: ' . gettype($key));
    }


    public function setCacheEngine(\Lampcms\Interfaces\Cache $Cache = null)
    {
        $this->CacheInterface = $Cache;

        return $this;
    }


    /**
     * Remove all data from cache
     *
     * @return object $this
     */
    public function flush()
    {
        if (!$this->skipCache) {
            $this->CacheInterface->flush();
        }

        return $this;
    }


    protected function getMissingKeys()
    {
        d('Could not get all keys from cache' . print_r($this->aMissingKeys, 1));
        $arrFoundKey = array();

        foreach ($this->aMissingKeys as $key => $val) {
            $arrFoundKey        = $this->getKeyValue($key);
            $arrFoundKeys[$key] = $arrFoundKey;
            $this->setValues($key, $arrFoundKey);
        }

        $this->aReturnVals = array_merge($this->aReturnVals, $arrFoundKeys);
    }


    /**
     * Finds the method that is responsible
     * for retrieving data for a requested key
     * and calls on that method
     *
     * @param                                     $key
     * @param \Lampcms\Cache\Callback|null|object $callback object of type Lampcms\Cache\Callback
     *                                                      if this object is passed it contains method
     *                                                      for getting the value of requested key - it will be
     *                                                      used if the $key is not present in cache.
     *                                                      The run() method of $callback object will be called with
     * 2 parameters: Registry and $key
     *
     * If this param is
     * not passed that this object will use the method defined in this class
     * for computing value for the $key if such method has been defined.
     *
     * @return mixed a data returned for the requested key or false
     */
    protected function getKeyValue($key, Callback $callback = null)
    {

        if ($callback) {
            d('$callback object is passed');
            $res = $callback->run($this->Registry, $key);

            return $res;

        } else {
            $aRes = explode('_', $key, 2);
            $arg  = (array_key_exists(1, $aRes)) ? $aRes[1] : null;

            /**
             * Check that method exists
             */
            if (method_exists($this, $aRes[0])) {
                $method = $aRes[0];
                d('Looking for key: ' . $key . ' Going to use method: ' . $method);
                $res = \call_user_func(array($this, $method), $arg);

                return $res;
            }

            d('method ' . $aRes[0] . ' does not exist in this object');
        }

        return false;
    }


    /**
     * Generate value of key and set it in cache
     *
     * @param $key
     *
     * @param $ttl optional number of seconds to keep this
     *             key in cache. Default null will result in no expiration for value
     *
     * @return object $this
     */
    protected function resetKey($key, $ttl = null)
    {
        $ttl = (is_numeric($ttl)) ? $ttl : $this->oTtl[$key];

        $this->setValues($this->getKeyValue($key), $ttl);

        return $this;
    }


    /**
     * Tries to get value of $key in cache object
     * but if value does not exist, does not
     * attempt to recreate that value
     *
     * @param $key
     *
     * @return mixed value for $key if it exists in cache
     * or false or null
     */
    public function tryKey($key)
    {

        return $this->getFromCache($key);
    }


    /**
     * Getter method enables to request a single key from cache
     * like this: $hdlCache->keyName;
     *
     * @param string $key
     *
     * @return mixed a value of the requested cache key
     *
     * @throws DevException if requested key is not a string.
     */
    public function __get($key)
    {
        if (!is_string($key)) {
            throw new DevException('Cache key must be a string');
        }
        d('looking for ' . $key);

        return $this->get($key);
    }


    /**
     * Magic method to set a single cache key by
     * using a string like this:
     * $this->hdlCache->mykey = 'some val';
     * this will set the cache key 'mykey' with
     * the value 'my val'
     * Value can be anything - a string, array or object (just not a resource
     * and NOT a database connection object)
     *
     * @param string $strKey
     *
     * @param mixed  $val
     *
     * @throws DevException if value is empty, so basically a string like this:
     * $this->hdlCache->somekey = ''; is not allowed. setting value to null or
     * using an empty array as value will also cause this exception.
     */
    public function __set($strKey, $val)
    {

        if (!is_string($strKey)) {
            throw new DevException('Cache key must be a string');
        }

        if (is_resource($val)) {
            throw new DevException('Value cannot be a resource');
        }

        $this->setValues($strKey, $val);

    }


    /**
     * Checks if cache should be used
     * if yes, then requests value of $key from cache
     * The calling method already checked that $key is array or string,
     * so we are sure that if $key is not a string then its an array
     *
     * @param $key
     *
     * @return mixed whatever is returned from $Cache object
     */
    protected function getFromCache($key)
    {

        if (true === $this->skipCache || null === $this->CacheInterface) {
            d('cp');
            return false;
        }

        if (is_string($key)) {
            d('cp');
            return $this->CacheInterface->get($key);
        }

        return $this->CacheInterface->getMulti($key);
    }


    /**
     * First checks whether or not cache should be used
     * if yes, then adds value to Cache object under the $key
     * or if $key is array, sets multiple items into cache
     * if $key is array, it must be an associative array of $key => $val
     *
     * @param mixed    $key   string or associative array
     *
     * @param string   $val
     * @param array    $aTags optionally assign these tags to this key
     *
     * @return bool
     */
    public function setValues($key, $val = '', array $aTags = null)
    {
        if (!$this->skipCache) {
            if (\is_string($key)) {

                $tags = (!empty($aTags)) ? $aTags : $this->aTags;

                /**
                 * @todo must ensure $val is utf-8 by
                 *       running it through Utf8String::stringFactory()!
                 *       or better yet make setValue() that requires
                 *       Utf8string as value!
                 *
                 */
                /**
                 * must have a way to
                 * set an empty result into cache
                 * this is important if
                 * we found that a message does not have any
                 * replies, this makes thread array empty
                 * We must set it into cache otherwise
                 * we will keep doing the same select looking
                 * for the thread array.
                 */

                if (!empty($val) || (0 === $val)) {
                    d('going to set key ' . $key . ' val: ' . var_export($val, 1));

                    return $this->CacheInterface->set($key, $val, $this->oTtl[$key], $tags);
                }
            } elseif (!empty($key)) {

                return $this->CacheInterface->setMulti($key);
            }
        }

        return false;
    }


    /**
     * magic method to check if key exists in Cache
     * But it does more that just check - it will add the value
     * of key to $this->Tmp object
     * so that if we need the value of this key, it will be in the object.
     * This is memoization
     *
     * @param string $key
     *
     * @return boolean
     *
     * @throws DevException is $key is not a string
     */
    public function __isset($key)
    {
        if (!is_string($key)) {
            throw new DevException('$key can only be a string. Supplied argument was of type: ' . gettype($key));
        }

        $this->Tmp[$key] = $this->getFromCache($key);
        if ((null !== $this->Tmp[$key]) && (false !== $this->Tmp[$key])) {

            return true;
        }

        return false;
    }


    /**
     * Magic method to delete cache key
     * using the unset($this->key)
     *
     * @param $key
     *
     * @return mixed whatever is returned by cache object
     * which is usually true on success or false on failure
     *
     * @throws DevException is $key is not a string
     */
    public function __unset($key)
    {
        if (!is_string($key)) {

            throw new DevException('$key can only be a string. Supplied argument was of type: ' . gettype($key));
        }

        d('Deleting from cache key: ' . $key);

        if (!$this->skipCache) {
            $ret = $this->CacheInterface->delete($key);
            d('ret: ' . $ret);

            return $ret;
        }
    }


    /**
     * Handle events
     * (non-PHPdoc)
     *
     * @todo should write extra function that will
     * register the shutdown function to unset specific key
     * This way the unsetting of key will be done after
     * other shutdown functions have completed
     * The reason is that some functions are run via runLater()
     * so it's possible that we unset the key before all the shutdown
     * functions have finished running. This will result in
     * unsetting cache keys before the new values are available
     *
     * @see Lampcms.Observer::main()
     */
    protected function main()
    {
        switch ( $this->eventName ) {
            case 'onNewQuestions':
            case 'onNewQuestion':
            case 'onResourceDelete':
            case 'onApprovedQuestion':
                $this->__unset('qunanswered');
                $this->__unset('qrecent');
                break;

            case 'onNewAnswer':
            case 'onAcceptAnswer':
            case 'onApprovedAnswer':
                $this->__unset('qunanswered');
                break;

            case 'onCategoryUpdate':
                $this->__unset('categories');
                break;
        }
    }


    /**
     *
     * Methods for getting specific keys:
     */


    /**
     * Generated html string
     * of links to recent tags fom QA module
     *

     */
    public function qrecent()
    {
        d('cp');
        $limit = $this->Registry->Ini->MAX_RECENT_TAGS;
        $cur   = $this->Registry->Mongo->QUESTION_TAGS->find(array('i_count' => array('$gt' => 0)), array('tag', 'i_count'))->sort(array('i_ts' => -1))->limit($limit);
        d('got ' . $cur->count(true) . ' tag results');

        $html = \tplLinktag::loop($cur);

        d('html recent tags: ' . $html);
        $this->aTags = array('tags');

        return '<div class="tags-list">' . $html . '</div>';

    }

    /**
     * Get array of categories
     * rekeys by id
     *
     */
    public function categories()
    {
        $aRes = array();
        $cur  = $this->Registry->Mongo->CATEGORY->find(array(), array('_id' => false))
            ->sort(array('i_parent' => 1, 'i_weight' => 1));

        /**
         * Rekey the array so that array keys
         * are category id
         */
        if ($cur && $cur->count() > 0) {
            foreach ($cur as $item) {
                $aRes[(int)$item['id']] = $item;
            }
        }

        return $aRes;
    }


    /**
     * Generated html string
     * of links to unanswered tags fom QA module
     *
     */
    public function qunanswered()
    {
        $limit = $this->Registry->Ini->MAX_RECENT_TAGS;
        $cur   = $this->Registry->Mongo->UNANSWERED_TAGS->find(array(), array('tag', 'i_count'))->sort(array('i_ts' => -1))->limit($limit);
        $count = $cur->count(true);
        d('got ' . $count . ' tag results');

        $html = \tplUnanstags::loop($cur);

        d('html recent tags: ' . $html);

        $ret = '<div class="tags-list">' . $html . '</div>';
        /* if ($count > $limit) {
            $ret .= '<div class="moretags"><a href="{_WEB_ROOT_}/{_viewqtags_}/{_unanswered_}/"><span rel="in">@@All unanswered tags@@</span></a>';
        }*/

        $this->aTags = array('tags');

        return $ret;
    }


    /**
     *
     * @param string $strIp ip address to lookup
     *
     * @throws \Lampcms\DevException
     * @return object of type GeoipLocation
     */
    protected function geo($strIp)
    {
        e('Using Cache to store geo data is deprecated. Use Geo\Ip class now');
        throw new DevException('Deprecated method');
    }


    /**
     * Creates and returns Acl object
     *
     * @return object of type \Lampcms\Acl\Acl
     */
    protected function Acl()
    {
        d('cp');
        $this->aTags = array('acl', 'settings');

        return new \Lampcms\Acl\Acl();
    }


    /**
     * @param $key
     *
     * @internal param string $locale name of locate for this object
     * (for example 'en_CA' for Canada)
     *
     * @return \Lampcms\I18n\XliffCatalog
     */
    protected function xliff($key)
    {
        d('$key: ' . $key);

        $file = LAMPCMS_CONFIG_DIR . DIRECTORY_SEPARATOR . LAMPCMS_TR_DIR . DIRECTORY_SEPARATOR . 'messages.' . $key . '.xlf';
        d('$file: ' . $file);

        $this->aTags = array('tr');

        return new \Lampcms\I18n\XliffCatalog($file, $key);
    }


    /**
     *
     * Method for creating Translation object
     *
     * @param string $locale name of locate for this object
     * (for example 'en_CA' for Canada)
     *
     * @return \Lampcms\I18n\Translator
     */
    protected function tr($locale)
    {
        d('$locale: ' . $locale);
        $this->aTags = array('tr');

        $o = \Lampcms\I18n\Translator::factory($this, $locale);

        d('returning o: ' . get_class($o));

        return $o;
    }

}

