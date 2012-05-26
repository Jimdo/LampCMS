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
 * @copyright  2005-2012 (or current year) Dmitri Snytkine
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;

class Responder
{
    const PAGE_OPEN = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<title></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link type="text/css" rel="stylesheet" href="/style/1/www/_main.css">
</head>
<body>';

    const PAGE_CLOSE = '</body></html>';

    const JS_OPEN = '<script type="text/javascript">';

    const JS_CLOSE = '</script>';

    const CSS_OPEN = '<style type="text/css">';

    const CSS_CLOSE = '</style>';


    /**
     * Flag indicates that
     * request came to the iframe
     * This usually happends when form
     * is submitted via hijaxForm javascript
     * The puprose of this flag is to allow
     * the sending of response json to be sent
     * out via sendJSON in a special way
     *
     * @see sendJSON()
     *
     *
     * @var bool
     */
    protected static $bIframe = false;


    /**
     * Send the string to a browser and exit
     *
     * @param array $arrJSON
     * @param int httpCode default is 200
     * @param array $headers
     * @param boolean $addJSTags if true, then
     * string will be sent as an HTML page that contains
     * javascript
     * Javascript will pass the json object to
     * the parent window's function fParseResponse
     * but only if parent object exists and contains
     * fParseQfJson script
     */
    public static function sendJSON(array $aJSON, array $headers = null, $httpCode = 200, $addJSTags = false)
    {

        if (Request::isIframe() || !empty($addJSTags)) {
            self::sendJsonPage($aJSON);
            session_write_close();
            throw new \OutOfBoundsException;
        }

        $contentType = "Content-Type: text/json; charset=UTF-8";
        $res = json_encode($aJSON);

        d('Sending json: ' . $res);
        header("HTTP/1.1 " . $httpCode . " OK");
        header($contentType);
        echo($res);
        session_write_close();
        fastcgi_finish_request();
        //exit();
        throw new \OutOfBoundsException;
    }


    /**
     * Send out response to JSONP Request
     * by sending out application/javascript Content-Type header
     * and then string: callbackfunction with
     * JSON-encoded data as callback's argument
     * and then calling fastcgi_finish_request();
     * and exit;
     *
     * @param array $aJSON
     * @param string $callback
     * @throws \InvalidArgumentException
     */
    public static function sendJSONP(array $aJSON, $callback)
    {
        if (!is_string($callback)) {
            throw new \InvalidArgumentException('$callback must be a string. Was: ' . gettype($callback));
        }

        header("HTTP/1.1 200 OK");
        header("Content-Type: application/javascript; charset=UTF-8");

        echo $callback . '(' . json_encode($aJSON) . ')';
        session_write_close();
        fastcgi_finish_request();
        throw new \OutOfBoundsException;
    }


    /**
     * Outputs html page
     * that includes ONLY javascript
     * that contains json encoded array or data
     *
     * @param string $aJson
     * @return
     */
    public static function sendJsonPage(array $aJson)
    {
        $header = "Content-Type: text/html";
        $json = json_encode($aJson);

        $result = self::PAGE_OPEN . self::JS_OPEN . '
		if(parent && parent.oSL && (parent.oSL.oFrm && parent.oSL.oFrm.fParseResponce) ){
		parent.oSL.oFrm.fParseResponce(' . $json . ');
		}
        else{
			alert("parent page does not have required oSL.oFrm.fParseResponce");
        }
		' . self::JS_CLOSE . self::PAGE_CLOSE;

        header($header);

        echo $result;
        session_write_close();
        fastcgi_finish_request();
        throw new \OutOfBoundsException;
    }


    /**
     * Generates a simple
     * html page with contents of
     * an error message inside a special
     * <div>
     *
     * @param string $sError
     * @return string Error message to put inside
     * the page
     */
    public static function makeErrorPage($sError)
    {
        return self::PAGE_OPEN . "\n" . '<div id="excsl"><div id="tools">' . $sError . '</div></div>' . "\n" . self::PAGE_CLOSE;
    }


    /**
     * Redirecting browser to a new url
     * using the header "Location" value
     *
     * @param string $url url where to redirect. Default is '/' meaning to www root
     */
    public static function redirectToPage($url = null)
    {

        if (null === $url) {
            $url = (empty($_SERVER['HTTP_REFERER'])) ? '/' : $_SERVER['HTTP_REFERER'];
        }

        if (Request::isAjax()) {
            self::sendJSON(array('redirect' => $url));
        }

        session_write_close();
        header("Location: " . $url);
        fastcgi_finish_request();
        throw new \OutOfBoundsException;
    }


    /**
     * Make a url from values in
     * $_SESSION['LOCATION'] array
     *
     * @return string a assembled url
     */
    public static function makeUrlFromLocation()
    {
        $sUrl = null;
        if (isset($_SESSION) &&
            !empty($_SESSION['LOCATION']) &&
            !empty($_SESSION['LOCATION']['H']) &&
            !empty($_SESSION['LOCATION']['a'])
        ) {
            $sUrl = self::makeUri('', array_merge($_SESSION['LOCATION']['H'], array('a' => $_SESSION['LOCATION']['a'])));
        }

        return $sUrl;
    }

}
