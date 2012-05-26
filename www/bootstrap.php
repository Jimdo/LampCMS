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
 * IMPORTANT
 * You don't have to edit anything in this file
 * if you want to keep the default installation
 * directory structure.
 *
 */

/**
 * If you want to run multi-site installation of Lampcms
 * and want to reuse the single instance of the Lampcms library
 * you must uncomment the line below to define the full
 * path to your 'lib' directory of the lampcms (without trailing slash)
 *
 * The lib directory must contain the Lampcms and Pear folders
 * (same folders that are included in the Lampcms distribution)
 * for example something like this "/var/lampcms/lib" on Linux
 * or something like this 'C:\lampcms\lib' on Windows
 */
define('LAMPCMS_LIB_DIR', 'C:\Lampcms\git\lib'); //C:\eclipse\workspace\QA\lib


/**
 * Define full path to where the config directory is located
 * Important: do not include a slash at the end
 * for example "/var/config" or "c:\myconfig"
 *
 * or leave commented out if config directory is
 * in default location (next to www dir)
 *
 */
define('LAMPCMS_CONFIG_DIR', 'C:\Lampcms\git\config'); //C:\eclipse\workspace\QA\config


/**
 * DO NOT REMOVE OR EDIT
 * ANY OF THE LINES BELOW!
 *
 */
if(!defined('LAMPCMS_LIB_DIR')){
    define('LAMPCMS_LIB_DIR', realpath(dirname(__DIR__)).DIRECTORY_SEPARATOR.'lib');
}

define('LAMPCMS_WWW_DIR', realpath(__DIR__).DIRECTORY_SEPARATOR);

if(!defined('LAMPCMS_CONFIG_DIR')){
    define('LAMPCMS_CONFIG_DIR', realpath(dirname(__DIR__)).DIRECTORY_SEPARATOR.'config');
}
