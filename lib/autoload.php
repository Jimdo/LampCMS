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
 * Autoloader for vtemplates
 *
 * @param $className
 *
 * @internal param string $classname
 * @return bool
 */
function templateLoader($className)
{


    /**
     * This is important
     * This autoloader will be the first
     * one in the __autoload stack (we pass true as 3rd arg
     * to spl_autoload_register())
     *
     * Since this autoload can only
     * handle template files, any file
     * not starting with 'tpl' is not
     * the responsibility of this loader
     * and we must return false to save further
     * pointless processing.
     */
    if (0 !== strpos($className, 'tpl')) {

        return false;
    }

    $styleId = (defined('STYLE_ID')) ? STYLE_ID : '1';
    $dir = (defined('VTEMPLATES_DIR')) ? VTEMPLATES_DIR : 'www';

    $file = LAMPCMS_WWW_DIR . 'style' . DIRECTORY_SEPARATOR . $styleId . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $className . '.php';


    /**
     * Smart fallback to www dir
     * if template does not exist in mobile version
     * But if template file also does not exist in www
     * and in mobile dir, then it will raise an error
     * beause we using require this time instead in include  && ('www' !== $dir)
     */
    if ((false === @include($file)) && ('www' !== $dir)) {

        require LAMPCMS_WWW_DIR . 'style' . DIRECTORY_SEPARATOR . $styleId . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . $className . '.php';
    }

    return true;
}

require 'Lampcms'.DIRECTORY_SEPARATOR.'SplClassLoader.php';
$oLoader = new Lampcms\SplClassLoader();
$oLoader->register();
spl_autoload_register('templateLoader', false, true);
