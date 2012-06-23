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


namespace Lampcms\Controllers;


use \Lampcms\WebPage;
use \Lampcms\Request;
use \Lampcms\Responder;
use \Lampcms\FollowManager;

/**
 * This controller is responsible
 * for processing the Follow request
 *
 * Follow request can be for Tag, User or Question
 *
 * @author Dmitri Snytkine
 *
 */
class Follow extends WebPage
{
    protected $requireToken = true;

    protected $bRequirePost = true;

    protected $aRequired = array('f', 'ftype', 'follow');

    protected $FollowManager;

    protected function main()
    {

        $this->FollowManager = new FollowManager($this->Registry);

        $this->processFollow()
            ->returnResult();
    }


    protected function processFollow()
    {
        $type = $this->Request['ftype'];
        $follow = $this->Request['follow'];
        $f = $this->Request['f'];

        switch (true) {

            case ('q' === $type):
                if ('off' === $follow) {
                    $this->FollowManager->unfollowQuestion($this->Registry->Viewer, (int)$f);
                } else {
                    $this->FollowManager->followQuestion($this->Registry->Viewer, (int)$f);
                }

                break;

            case ('t' === $type):
                if ('off' === $follow) {
                    $this->FollowManager->unfollowTag($this->Registry->Viewer, $f);
                } else {
                    $this->FollowManager->followTag($this->Registry->Viewer, $f);
                }

                break;

            case ('u' === $type):
                if ('off' === $follow) {
                    $this->FollowManager->unfollowUser($this->Registry->Viewer, (int)$f);
                } else {
                    d('following user ' . $f);
                    $this->FollowManager->followUser($this->Registry->Viewer, (int)$f);
                }

                break;
        }

        return $this;
    }


    /**
     * Return empty array via Ajax
     * this way UI will not have to do anything
     *
     *
     */
    protected function returnResult()
    {
        if (Request::isAjax()) {
            Responder::sendJSON(array());
        }

        Responder::redirectToPage();
    }
}
