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
 *       the page that lists the recent questions (usually home page) must include
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


namespace Lampcms\I18n;

use \Lampcms\Cache\Callback;

/**
 * Simple translator class
 * for getting translated messages
 * with option to use fallback value
 * and option to use placeholders in translation strings
 * and have these placeholders replaced at time
 * of returning translated string
 *
 * This class is serializable so the object
 * can be stored in cache or in session for very
 * efficient and fast object creation
 *
 * @author Dmitri Snytkine
 *
 */
class Translator implements \Serializable, \ArrayAccess, \Lampcms\Interfaces\Translator
{

    /**
     *
     * Array of messages
     * in the form of associative array
     * where keys are strings
     * and values are the translations of the string
     *
     * @var array
     */
    protected $aMessages = array();


    /**
     * Name of locate
     * This object holds array of messages
     * for this locale
     *
     * @var string
     */
    protected $locale;

    /**
     * Callback function to translate
     * placeholder strings to their translated values
     * This function is used in the output buffer
     *
     * @var function
     */
    protected $callback;


    /**
     * Factory method
     * This is a preferred method for
     * instantiating this object
     * as it will return object that contains
     * catalog that includes current locale merged
     * with fallback language locale merged
     * with default catalog
     *
     * @param \Lampcms\Cache\Cache  $Cache
     * @param  string               $locale
     *
     * @throws \Lampcms\DevException
     * @internal param \Lampcms\Registry $Registry
     * @return \Lampcms\I18n\Translator
     */
    public static function factory(\Lampcms\Cache\Cache $Cache, $locale)
    {
        if (!\is_string($locale)) {
            throw new \Lampcms\DevException('Param $locale must be a string. Was: ' . gettype($locale));
        }

        $fallback = null;

        if (\strlen($locale) > 3) {
            d('going to also use lang fallback for $locale: ' . $locale);

            $fallback = \substr($locale, 0, -\strlen(\strrchr($locale, '_')));
        }

        $default = LAMPCMS_DEFAULT_LOCALE;
        d('$default: ' . $default . ' $locale: ' . $locale . ' $fallback: ' . $fallback);

        $o = new static();
        $o->setLocale($locale);

        /**
         * Get the XLIFF object for this locale,
         * one for fallback if different from this locale
         * one for default if different
         * from $locale and from $fallback
         */
        $o->addCatalog($Cache->get('xliff_' . $locale));

        if (!empty($fallback) && ($fallback !== $locale)) {
            $o->addCatalog($Cache->get('xliff_' . $fallback));
        }

        if (!empty($default) && ($default !== $fallback) && ($default !== $locale)) {
            $o->addCatalog($Cache->get('xliff_' . $default));
        }

        return $o;
    }


    /**
     * Setter for $this->locale value
     *
     * @param string $locale
     *
     * @throws \Lampcms\DevException
     * @return \Lampcms\I18n\Translator
     */
    public function setLocale($locale)
    {
        if (!\is_string($locale)) {
            throw new \Lampcms\DevException('Param $locale must be a string Was: ' . gettype($locale));
        }


        /*if(2 !== strlen($locale) && (!preg_match('/[a-z]{2}_[A-Z]{2}/', $locale))){
              throw new \InvalidArgumentException('Param $locale is invalid. Must be in a form on "en_US" format (2 letter lang followed by underscore followed by 2-letter country');
          }*/

        $this->locale = $locale;

        return $this;
    }


    /**
     * Getter for $this->locale string
     *
     * @see Lampcms\Interfaces.Translator::getLocale()
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }


    /**
     *
     * @see Lampcms\Interfaces.Translator::translate()
     *
     * @param            $string
     * @param array|null $vars
     * @param null       $default
     *
     * @return null|string
     */
    public function get($string, array $vars = null, $default = null)
    {
        $str = (!empty($this->aMessages[$string])) ? $this->aMessages[$string] : (\is_string($default) ? $default : $string);

        return (!$vars) ? $str : \strtr($str, $vars);
    }


    /**
     * Getter of the callback function
     *
     *
     * @return function
     */
    public function getCallback()
    {
        if (!isset($this->callback)) {
            $search = $replace = array();
            foreach ($this->aMessages as $k => $v) {
                $search[]  = '@@' . $k . '@@';
                $replace[] = $v;
            }

            $this->callback = function($s) use ($search, $replace)
            {
                $output = \str_replace($search, $replace, $s);

                /**
                 * @todo if LAMPCMS_DEBUG then collect all untranslated strings
                 *       and email to admin or at least log
                 *       May also automatically add to XLIFF file, at least to default lang
                 *       But for this to work the XLIFF file must be writable, which is not always a good idea
                 */
                /**
                 * Any placeholders that have not been replaced with
                 * values from URI_PARTS or from ROUTES
                 * AND has not been translated with $translator
                 *
                 * will be replaced with their placeholder names
                 * with this single call
                 */
                //return \preg_replace('/@@([a-zA-Z0-9_\-!?\().,\'\/\s]+)@@/', '\\1', $output);

                return \str_replace('@@', '', $output);

            };

        }

        return $this->callback;
    }


    /**
     *
     * Add array of messages to current
     * $this->aMessages array
     * New array is merged with $this->aMessages
     * will override existing values
     * for same keys as $this->aMessages
     *
     * @param array $a
     *
     * @return \Lampcms\I18n\Translator
     */
    public function addArray(array $a)
    {
        $this->aMessages = \array_merge($this->aMessages, $a);

        return $this;
    }


    /**
     * @param \Lampcms\Interfaces\Translator $o
     *
     * @return \Lampcms\I18n\Translator
     */
    public function addCatalog(\Lampcms\Interfaces\Translator $o)
    {
        $a = $o->getMessages();

        $this->addArray($a);

        return $this;
    }


    /**
     * (non-PHPdoc)
     *
     * @see Serializable::serialize()
     * @return string json encoded string
     */
    public function serialize()
    {
        return \json_encode(array(
                'aMessages' => $this->aMessages,
                'locale'    => $this->locale)
        );
    }


    /**
     * (non-PHPdoc)
     *
     * @see Serializable::unserialize()
     *
     * @param $serialized
     */
    public function unserialize($serialized)
    {
        $a               = \json_decode($serialized, true);
        $this->aMessages = $a['aMessages'];
        $this->locale    = $a['locale'];
    }


    /**
     * (non-PHPdoc)
     *
     * @see Lampcms\Interfaces.Translator::getMessages()
     * @return array
     */
    public function getMessages()
    {
        return $this->aMessages;
    }


    /**
     * (non-PHPdoc)
     *
     * @see Lampcms\Interfaces.Translator::has()
     *
     * @param $string
     *
     * @return bool
     */
    public function has($string)
    {
        return \array_key_exists($string, $this->aMessages);
    }

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }


    public function offsetGet($offset)
    {
        return $this->get($offset, null, $offset);
    }


    public function offsetUnset($offset)
    {

    }

    public function offsetSet($offset, $value)
    {
        $this->aMessages[$offset] = $value;
    }

}
