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
ini_set('session.use_trans_sid', false);
ini_set('session.use_only_cookies', true);

define('INIT_TIMESTAMP', microtime());


include '../!inc.php';

require($lampcmsClasses.'Base.php');
require($lampcmsClasses.'WebPage.php');
require($lampcmsClasses.'Forms'.DIRECTORY_SEPARATOR.'Form.php');
require($lampcmsClasses.'Cookie.php');
require($lampcmsClasses.'LoginForm.php');


if (true !== session_start()) {
	/**
	 * @todo
	 * Translate String
	 */
	echo ('Unable to start the program due to the session start error');
} else {

	try {

		if(empty($_SESSION['viewer'])){
			d('No Viewer is $_SESSION');
			\Lampcms\Cookie::sendRefferrerCookie();
		}

		$Request = $Registry->Request;
		$a = $Request['a'];

		$controller = ucfirst($a);
		include($lampcmsClasses.'Controllers'.DIRECTORY_SEPARATOR.$controller.'.php');
		$class = '\Lampcms\\Controllers\\'.$controller;

		header('Content-Type: text/html; charset=utf-8');
		echo new $class($Registry);
		/**
		 *
		 * Commenting out the session_write_close()
		 * may improve performance since all session writes
		 * will be done after the browser connection
		 * is closed.
		 * The downside is that if any of the registered shutdown
		 * functions cause fatal error the session
		 * may never be saved. It's worth trying commenting this out
		 * and running the site for awhile. If noticing any problems
		 * with sessions (like user suddenly logged out
		 * while browsing beteen pages) then uncomment this
		 */
		// session_write_close();
		fastcgi_finish_request();

	} catch(\OutOfBoundsException $e){
		
		//session_write_close();
		/**
		 * Special case is OutOfBoundsException which
		 * is our special way of saying exit(); but do it
		 * gracefully - let it be caught here and then do nothing
		 * This is better than using exit() because on some servers
		 * exit may terminate the whole fastcgi process instead of just
		 * stopping this one script
		 */
		$errMessage = trim($e->getMessage());

		if(!empty($errMessage)){
			echo '<div class="exit_error">'.$errMessage.'</div>';
			d('Got exit signal from '.$e->getTraceAsString());
		}
		fastcgi_finish_request();

	} catch(\Exception $e) {
		
		$code = $e->getCode();
		session_write_close();
		header("HTTP/1.0 500 Exception");
		try {
			$extra = (isset($_SERVER)) ? ' $_SERVER: '.print_r($_SERVER, 1) : ' no server';
			$extra .= 'Exception in file: '.$e->getFile(). "\n line: ".$e->getLine()."\n trace: ".$e->getTraceAsString();
			/**
			 * @mail must be here before the Lampcms\Exception::formatException
			 * because Lampcms\Exception::formatException in case of ajax request will
			 * send out ajax and then throw \OutOfBoundsException in order to finish request (better than exit())
			 */
			if( ($code >=0) && defined('LAMPCMS_DEVELOPER_EMAIL') && strlen(trim(constant('LAMPCMS_DEVELOPER_EMAIL'))) > 7){
				@mail(LAMPCMS_DEVELOPER_EMAIL, '500 Error in index.php', $extra);
			}
			$html = \Lampcms\Responder::makeErrorPage('<strong>Error:</strong> '.Lampcms\Exception::formatException($e));

			echo nl2br($html);

		} catch (\OutOfBoundsException $e2){
			// do nothing, this was a way to exit() from Responder::sendJSON()
		} catch(\Exception $e2) {
			$code = $e->getCode();
			$sHtml = \Lampcms\Responder::makeErrorPage('<strong>Exception:</strong> '.strip_tags($e2->getMessage())."\nIn file:".$e2->getFile()."\nLine: ".$e2->getLine());
			$extra = (isset($_SERVER)) ? ' $_SERVER: '.print_r($_SERVER, 1) : ' no extra';
			if(($code >=0) && defined('LAMPCMS_DEVELOPER_EMAIL') && strlen(trim(constant('LAMPCMS_DEVELOPER_EMAIL'))) > 7){

				@mail(LAMPCMS_DEVELOPER_EMAIL, 'Error in index.php on line '.__LINE__, $sHtml.$extra);

			}
			echo nl2br($sHtml);
		}

		fastcgi_finish_request();
	}
}

