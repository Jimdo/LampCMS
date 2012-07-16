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
 * Class for creating
 * array of time zones
 */
class TimeZone extends \DateTimeZone
{

    /**
     * Generates array suitable
     * for using it in QuickForm
     * to create drop-down menu of
     * time zones.
     *
     * @return array where keys
     * are time zone names and values
     * are strings indicating GMT offset
     * followed by timezone name.
     * This array is sorted by keys
     * (by timezone names)
     *
     * @deprecated
     */
    public static function getSelectArray()
    {
        $arrResult = array();
        $arr = self::listAbbreviations();

        foreach ($arr as $abbr) {
            foreach ($abbr as $aTz) {

                $sign = ($aTz['offset'] < 0) ? '-' : (($aTz['offset'] > 0) ? '+' : '');
                $gmt = abs($aTz['offset']) / 3600;
                $hh = $gmt; //floor($gmt);
                $mm = $gmt - $hh;
                $mm = ($mm * 60); //floor($mm * 60);
                $key = $aTz['timezone_id'];
                $val = '(GMT' . $sign . sprintf("%02s", $hh) . ':' . sprintf("%02s", $mm) . ') ' . $aTz['timezone_id'];
                if (!empty($key) && !empty($val)) {
                    $arrResult[$key] = $aTz['offset'] . ' ' . $val;
                }
            }
        }

        ksort($arrResult);

        return $arrResult;
    }


    /**
     *
     * Get HTML for the select menu options (without the <select></select> tags)
     * to select
     * a timezone
     *
     * @param string $current current timezone to be set as "selected" in the menu
     *
     * @return string html code for the <option> element
     */
    public static function getMenu($current = null)
    {

        $a = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL ^ \DateTimeZone::UTC);
        $res = '';
        foreach ($a as $zone) {

            $selected = ($zone === $current) ? " selected" : '';
            $val = str_replace('_', ' ', $zone);
            $res .= "\n<option value=\"$zone\"$selected>$val</option>";
        }

        return $res;
    }


    /**
     * Extracts value of timezone_offset from a timezone string.
     *
     * @param string $strTimezone timezone string
     * @param array $arrTimezones array of timezones
     * @return integer number of seconds, can be a negative number
     */
    public static function getTimezoneOffset($strTimezone, $arrTimezones)
    {
        $res = '0';
        preg_match('/\(GMT(([\-+]{1})([0-9]{2}):([0-9]{2}))\)/', $arrTimezones[$strTimezone], $matches);

        if (isset($matches[2]) && isset($matches[3]) && isset($matches[4])) {
            $intSeconds = ($matches[3] * 60 * 60) + ($matches[4] * 60);
            $prefix = ($matches[2] == '-') ? '-' : '';
            $res = trim($prefix . $intSeconds);
        }

        return (int)$res;
    }


    /**
     * Get the first available timezone name
     * that matches the offset value (in seconds)
     *
     *
     * @param $intOffset
     *
     * @internal param number $offset of seconds from GMT
     *
     * @return first matching timezone name
     */
    public static function getTZbyoffset($intOffset)
    {
        $tza = \DateTimeZone::listAbbreviations();
        foreach ($tza as $abbr) {
            foreach ($abbr as $zone) {
                if ($zone['offset'] === (int)$intOffset) {
                    return $zone['timezone_id'];
                }
            }
        }

        return '';
    }

}
