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
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\Template;


/**
 * Class for parsing vsprintf based templates
 *
 * @todo Template could have static function loopFilter()
 * and will be applied inside the loop()
 * this way it can possibly splice the array or
 * even modify the Iterator (cursor) before starting the loop
 * Could be helpful to limit the number of items to be looped
 *
 * @todo recursively check if value is_array() and if yes
 * the parse it first with loop() and then replace the value
 * of that key with the parsed version.
 *
 * This could be used for nested replies, should even
 * work with unlimited nesting levels
 *
 * @author Dmitri Snytkine
 *
 */
class Fast
{
	/**
	 * Can override this static method in concrete template
	 * It accepts array of $vars by reference
	 * This method can modify actual values
	 * or template variables
	 * before the variables are actually used
	 * in template
	 *
	 * @param array $vars
	 */
	protected static function func(&$vars){}

	/**
	 * Flag indicates that
	 * template should skip
	 * calling the $func function
	 *
	 *
	 * @var bool
	 */
	protected static $skip = false;
	
	/**
	 * 
	 * Flag indicates that in debug mode
	 * an extra html comments will be added
	 * before and after the template
	 * Some templates may want to skip adding debug code
	 * even when in debug mode. Usually these will
	 * be the templates that created html blocks that 
	 * are stored with questions or in cache.
	 * 
	 * 
	 * @var bool
	 */
	protected static $debug = true;


	protected static function translate($s, array $vars = null){
		if(isset($_SESSION) && !empty($_SESSION['Translator'])){
			return $_SESSION['Translator']->get($s, $vars, $s);
		}

		return $s;
	}

	/**
	 * Parse template, using input $aVars array of replacement
	 * variables to be used in the vsprintf() function
	 *
	 * @param array $aVars
	 *
	 * @param bool $merge if true will apply default values
	 * as well as making sure the elements or input
	 * array are in the correct order. This is very important
	 * if you not sure that array of values you passing has
	 * named elements in correct order.
	 * If you are 100% sure that elements are in correct order
	 * then set this to false to save function call
	 *
	 * @param Closure $func callback function to be applied
	 * to input array. The callback anonymous function MUST
	 * accept input array by reference and perform some
	 * operations on the actual array.
	 */
	public static function parse(array $aVars, $merge = true, $func = null){

		/**
		 * ORDER IS IMPORTANT:
		 * Closure functions applied first, then static function "func"
		 *
		 * Apply callback to array if
		 * callback was passed here
		 * callback MUST accept array by reference
		 * so that it can modify actual values in aVars
		 *
		 * The callback should be applied first,
		 * so that in case the template also has a function,
		 * it would be possible to use callback to add
		 * elements to the result array.
		 *
		 * This is useful when we have a cursor - a result
		 * of database select but also need to inject
		 * extra element(s) to the array of item which
		 * are not present in the database
		 */
		if(null !== $func){

			$func($aVars);
		}

		/**
		 * A template may contain hard coded static property $func
		 *
		 * If it does then input array will be run through
		 * that $func function
		 * it MUST accept array by reference
		 * and modify actual array value
		 *
		 */
		if( false === static::$skip ){

			static::func($aVars);
		}
		
		if($merge){

			$aVars = \array_merge(static::$vars, $aVars);
		}

		$begin = $end = $t = '';

		if (true === LAMPCMS_DEBUG && static::$debug) {
			$t = '  ';
			$templateName = get_called_class();
			$templateName = LAMPCMS_WWW_DIR.'style'.DIRECTORY_SEPARATOR.STYLE_ID.DIRECTORY_SEPARATOR.VTEMPLATES_DIR.DIRECTORY_SEPARATOR.$templateName;
			$begin = sprintf("\n$t<!-- Template %s -->\n", $templateName);
			$end = sprintf("\n$t<!-- // Template %s -->\n", $templateName);
		}

		$ret = static::replace($aVars);

		return $begin.$t.$ret.$end;
	}
	
	
	/**
	 * Here the placeholders are replaced
	 * by actual value
	 * 
	 * Sub-class may implement own way of replacing variables
	 * 
	 * @param array $aVars
	 * @return string parsed template
	 */
	protected static function replace( array $aVars ){
		return \vsprintf(static::$tpl, $aVars);
	}

	
	/**
	 * @todo template may contain $loop static
	 * function, if it does then use it on
	 * passed array
	 *
	 * @param mixed $a could be array or object of type Iterator
	 * @param bool $merge
	 * @param Closure $func if passed, this callback function
	 * will be passed to each element's parse() function
	 *
	 * @throws InvalidArgumentException if $a is not array and not Iterator
	 */
	public static function loop($a, $merge = true, $func = null){
		$begin = $end = '';
		/**
		 * Throw exception if Iterator is not
		 * an array and not instance of iterator
		 */
		if(!is_array($a) && (!is_object($a) || !($a instanceof \Iterator)) ){
			$err = 'Param $a (first param passed to loop() must be array of object instance of Iterator was: '.gettype($a);

			throw new \InvalidArgumentException($err);
		}



		/**
		 * Cannot just declare this $s as static inside the
		 * method
		 * because then it remembers this value
		 * even between parsing multiple times
		 * Instead we must recursively pass $s to itself
		 */
		$s = '';
		foreach($a as $aVars){
			if(is_string($aVars)){
				$vars = array($aVars);
				//d('aVars now: '.print_r($vars, 1));
			}else {
				$vars = $aVars;
			}
			$s .= static::parse($vars, $merge, $func);
		}

		if (true === LAMPCMS_DEBUG && static::$debug) {
			$templateName = get_called_class();
			$begin = sprintf("\n<!-- BEGIN LOOP in template: %s -->\n", $templateName);
			$end = sprintf("\n<!-- // END  LOOP in template: %s -->\n", $templateName);
		}

		return $begin.$s.$end;
	}


	/**
	 * Static getter for vars array
	 *
	 * @asKeys bool if true then return array of
	 * placeholder names. This type of array is useful for
	 * passing it to mongocollection->find() 2nd param
	 * to hint which field we need to select.
	 *
	 * @return array of $vars from template OR if
	 * asKeys is true then returns array of placeholder names
	 */
	public static function getVars($asKeys = false){
		if(isset(static::$vars)){
			$ret = static::$vars;
			if($asKeys){
				return array_keys($ret);
			}

			return $ret;
		}

		return array();
	}


	/**
	 * Static getter for $tpl
	 *
	 * @return string $tpl template
	 */
	public static function getTemplate(){
		return static::$tpl;
	}


	/**
	 * Get array of unparsed template
	 * and default vars
	 *
	 * It may be very helpful in case of Ajax to load
	 * the template into browser and do the
	 * vsprinf(tpl, vars) in javascript
	 * using the sprintf for javascript package
	 *
	 * @see http://www.diveintojavascript.com/projects/javascript-sprintf
	 *
	 * @return array with keys 'tpl' and 'vars' representing
	 * the template on which this was called
	 */
	public static function get(){
		return array('tpl' => static::$tpl, 'vars' => static::$vars);
	}

}
