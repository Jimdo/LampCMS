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


/**
 * Template for displaying the
 * Options (tabs) for sorting the User answers
 * Added to Userinfo page above the user questions div
 *
 * Used in Userinfo controller
 */
class tplSortuans extends Lampcms\Template\Fast
{
    protected static $vars = array(
        'oldest_c' => '', //1
        'recent_c' => '', //2
        'voted_c' => '', //3
        'updated_c' => '', //4
        'oldest_t' => '@@Older answers first@@', // 5
        'recent_t' => '@@Recent answers first@@', // 6
        'voted_t' => '@@Most voted answers first@@', // 7
        'updated_t' => '@@Answers with recent activity first@@', // 8
        'oldest' => '@@Oldest@@', //9
        'recent' => '@@Recent@@', //10
        'voted' => '@@Most voted@@', //11
        'updated' => '@@Recently active@@', //12
        'uid' => '', //13
        'best_c' => '', //14
        'best' => '@@Best answer@@', //15
        'best_t' => '@@Selected as Best Answer@@' //16
    );

    /**
     * Best to not edit this template!
     * Placeholders will be replaced by the output buffer callback
     * @var string
     */
    protected static $tpl = '
	<div id="qtypes2" class="qtypes cb fl reveal hidden">
	<a href="{_WEB_ROOT_}/{_userinfotab_}/a/%13$s/{_SORT_RECENT_}/{_PAGER_PREFIX_}1{_PAGER_EXT_}" class="ajax sortans ttt2 qtype%2$s" title="%6$s">%10$s</a>
	<a href="{_WEB_ROOT_}/{_userinfotab_}/a/%13$s/{_SORT_OLDEST_}/{_PAGER_PREFIX_}1{_PAGER_EXT_}" class="ajax sortans ttt2 qtype%1$s" title="%5$s">%9$s</a>
	<a href="{_WEB_ROOT_}/{_userinfotab_}/a/%13$s/{_SORT_VOTED_}/{_PAGER_PREFIX_}1{_PAGER_EXT_}" class="ajax sortans ttt2 qtype%3$s" title="%7$s">%11$s</a>
	<a href="{_WEB_ROOT_}/{_userinfotab_}/a/%13$s/{_SORT_UPDATED_}/{_PAGER_PREFIX_}1{_PAGER_EXT_}" class="ajax sortans ttt2 qtype%4$s" title="%8$s">%12$s</a>
	<a href="{_WEB_ROOT_}/{_userinfotab_}/a/%13$s/{_SORT_BEST_}/{_PAGER_PREFIX_}1{_PAGER_EXT_}" class="ajax sortans ttt2 qtype%14$s" title="%16$s">%15$s</a>
	</div>';
}
