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



namespace Lampcms;


/**
 * Parser of tags of one question
 *
 * @author Dmitri Snytkine
 *
 */
class TagsTokenizer extends \Lampcms\String\Tokenizer
{


	/**
	 * Factory method. This class should be instantiated
	 * ONLY through this method
	 *
	 * @param object of type Utf8string $Tags
	 * @return object of this class
	 */
	public static function factory(Utf8String $Tags){

		$str = $Tags->toLowerCase()->trim()->valueOf();

		return new self($str);
	}


	/**
	 * Parse title of the question by
	 * tokenizing it
	 * Overrides parent's parse and users mb_split
	 * instead of preg_split to be UTF-8 Safe
	 * because title can be a UTF-8 string
	 *
	 * @return array tokens;
	 */
	public function parse(){
		if(empty($this->origString)){
			return array();
		}

		/**
		 * Remove <> brackets and forward slash. 
		 * These are really bad news and will cause
		 * alot of headache later, especially the /
		 * which will break the url rewrite rules
		 * This is important because values of tags are 
		 * used in urls
		 * 
		 * These and should not make into the array
		 * str_replace is UTF-8 safe and faster than regex
		 * 
		 * 
		 */
		// 1st param used to be this array: array('<', '>', '/')
		$tokens = str_replace('/', '', $this->origString);
		
		\mb_regex_encoding('UTF-8');
		$aTokens = \mb_split('[\s,]+', $tokens);
		d('$aTokens: '.print_r($aTokens, 1));
		$aTokens = \array_unique($aTokens);
		$aStopwords = \Lampcms\getStopwords();

		/**
		 * Tags are stored with htmlspecialchars() encoded!
		 * This means if searching for tags we need
		 * to also run search params through htmlspecialchars()
		 * But no extra effort is needed to display tags on html page
		 * they will always display as html tags not part of html!
		 * this is safe even for using values
		 * of tags in xml feed! Very important!
		 * 
		 */
		array_walk($aTokens, function(&$val) use($aStopwords){
			$val = trim($val);
			$val = ((strlen($val) > 1) && !in_array($val, $aStopwords)) ? \htmlspecialchars($val, ENT_QUOTES, 'UTF-8') : false;
		});
		
		
		/**
		 * Since tags that match bad words are removed from array
		 * we need to filter empty values one more time
		 * just to be 100% sure to Remove empty values
		 * because empty values in tags are really
		 * bad news for many other functions
		 * of this program
		 * 
		 *
		 */
		$aTokens = \array_filter($aTokens);
		\sort($aTokens, SORT_STRING);
		d('$aTokens: '.print_r($aTokens, 1));

		return $aTokens;
	}

}
