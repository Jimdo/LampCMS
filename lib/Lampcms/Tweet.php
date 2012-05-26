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


namespace Lampcms;

/**
 *
 * Class for posting Question
 * or Answer to Twitter
 *
 * @author Dmitri Snytkine
 *
 */
class Tweet
{

    /**
     * Post the title of Question or Answer to Twitter
     * Usually this method is called as a shutdown_function
     * @todo if space allows add "prefixes" to Tweets
     * Prefixes will be strings, in translation...
     *
     * @todo if space allows also add "via @ourname" to tweet if the
     * value of TWITTER_USERNAME exists if setting
     *
     * @param \Lampcms\Twitter $oTwitter
     * @param \Lampcms\Bitly $oBitly
     * @param object $Resource object of type Question or Answer
     * @return mixed null if exception was caught or array returned
     * by Twitter API
     */
    public function post(\Lampcms\Twitter $oTwitter, \Lampcms\Bitly $oBitly, $Resource)
    {
        d('cp');
        if (!($Resource instanceof \Lampcms\Question) && !($Resource instanceof \Lampcms\Answer)) {
            e('Resource not Question and not Answer');

            return;
        }

        $ret = null;

        /**
         * $title is already guaranteed to be
         * in utf-8
         */
        $title = $Resource['title'];

        /**
         * Short url from bit.ly is guaranteed
         * to be in utf-8
         */
        $short = $oBitly->getShortUrl($Resource->getUrl());

        /**
         * Our own url is in utf8 unless...
         * Unless this site is on some weird international
         * domain name that includes non-utf8 chars
         * This is super unlikely
         * We can assume that all components of
         * the tweet is already in utf-8
         */
        $url = ($short) ? $short : $Resource->getUrl(true);

        /**
         * Test what the length of tweet will be
         * if we concatinate title + space + url
         *
         * @var int
         */
        $testLength = \mb_strlen($url . ' ' . $title, 'utf-8');
        if ($testLength > 140) {
            d('need to shorten title');
            $title = Utf8String::factory($title, 'utf-8', true)->truncate(139 - \mb_strlen($url, 'utf-8'))->valueOf();
            $text = $title . ' ' . $url;
        } else {
            $text = $title . ' ' . $url;
        }

        d('going to tweet this text: ' . $text);
        try {
            $ret = $oTwitter->postMessage($text);
        } catch (\Exception $e) {
            e('Tweet not sent because of exception: ' . $e->getMessage() . ' in file: ' . $e->getFile() . ' on line: ' . $e->getLine());
        }

        return $ret;
    }
}
