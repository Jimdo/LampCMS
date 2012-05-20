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
 * This class renders extra "Check boxes" on
 * the "Ask" and "Answer" forms
 * To ask user to also post to Twitter, Facebook, etc..
 *
 * @author Dmitri Snytkine
 *
 */
class SocialCheckboxes
{

	/**
	 * Make html with divs containint checkboxes
	 * and labels for the extra "post to Twitter" etc.
	 * checkboxes
	 * a bunch of divs with checkboxes to
	 * post content to Twitter, Facebook etc., depending
	 * on settings in !config.ini
	 * and depending if these extra modules are enabled
	 *
	 * @return string html fragment
	 */
	public static function get(Registry $Registry){

		d('cp');
		$oViewer = $Registry->Viewer;
		if($oViewer->isGuest()){
			d('User is guest, no social checkboxes to be added for guest');
			return '';
		}

		$ret = '';
		$aFilters = $Registry->Ini->getSection('INPUT_FILTERS');
		//d('$aFilters: '.print_r($aFilters, 1));
		/**
		 * @todo Translate String
		 */
		$tpl = 'Post to %s<br><strong>+%s</strong> reputation points';

		/**
		 * If has twitter observer module
		 */
		if(array_key_exists('twitter', $aFilters)){
			d('cp');
			/**
			 * The state of checkbox remembered from
			 * the previous user action + must not have Twitter access revoked
			 * Enter description here ...
			 * @var unknown_type
			 */
			$isConnected = ('' !== (string)$oViewer->getTwitterSecret());
			$checked = ( $isConnected && (true === $oViewer['b_tw'])) ? ' checked' : '';
			d('$checked: '.$checked);
			$label = \sprintf($tpl, 'Twitter', $Registry->Ini->POINTS->SHARED_CONTENT);
			$vars = array('tweet', $label, $checked);
			$ret .= \tplSocialPost::parse($vars, false);
		}

		/**
		 * Is has facebook observer module
		 */
		if(array_key_exists('facebook', $aFilters)){
			$isFbConnected = (1 < \strlen((string)$oViewer->getFacebookToken()));
			$checked = ($isFbConnected && true === $oViewer['b_fb']) ? ' checked' : '';
			$label = \sprintf($tpl, 'Facebook', $Registry->Ini->POINTS->SHARED_CONTENT);
			$vars = array('facebook', $label, $checked);
			$ret .= \tplSocialPost::parse($vars, false);
		}

		/**
		 * Is has tumblr observer module
		 */
		if(array_key_exists('tumblr', $aFilters)){
			$isTmConnected = (null !== $oViewer->getTumblrToken());
			$checked = ($isTmConnected && true === $oViewer['b_tm']) ? ' checked' : '';
			$label = \sprintf($tpl, 'Tumblr', $Registry->Ini->POINTS->SHARED_CONTENT);
			$vars = array('tumblr', $label, $checked);
			$ret .= \tplSocialPost::parse($vars, false);
		}

		/**
		 * Is has blogger observer module
		 */
		if(array_key_exists('blogger', $aFilters)){
			$isBConnected = (null !== $oViewer->getBloggerToken());
			$checked = ($isBConnected && true === $oViewer['b_bg']) ? ' checked' : '';
			$label = \sprintf($tpl, 'Blogger', $Registry->Ini->POINTS->SHARED_CONTENT);
			$vars = array('blogger', $label, $checked);
			$ret .= \tplSocialPost::parse($vars, false);
		}


		/**
		 * Is has LinkedIn observer module
		 */
		if(array_key_exists('linkedin', $aFilters)){
			$isLConnected = (null !== $oViewer->getLinkedInToken());
			$checked = ($isLConnected && true === $oViewer['b_li']) ? ' checked' : '';
			$label = \sprintf($tpl, 'LinkedIn', $Registry->Ini->POINTS->SHARED_CONTENT);
			$vars = array('linkedin', $label, $checked);
			$ret .= \tplSocialPost::parse($vars, false);
		}


		d('ret: '.$ret);

		return $ret;
	}
}
