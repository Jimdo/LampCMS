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


/**
 * Class
 * methods common to external authentication
 * like Signin with Twitter, Facebook connect, Google friend Connect, etc.
 *
 * All external auth have this in common:
 * check if user already exists with the given external auth type and id
 * (for example with twitter ID for Twitter user, or with GFC id for
 * Google Friend connect user)
 *
 * If exists, then create User object based on given type/id
 * This is specific to the external auth type and defined in sub-class
 * Then addArray of fresh data that we have just received from external
 * source and then save the data if necessary (if there are any changes, then save
 * the USERS table and also to table specific to external auth provider like
 * to USERS_TWITTER or USERS_GFC table
 *
 * Always send the uid cookie so that external-based auth users
 * will always have auto-login set to true.
 *
 * Post onUserUpdate event at the end of process if any changes has been made
 * to user data
 *
 * After creating/updating the oViewer object login the user
 *
 * If used is not found for this provider/id then create new user
 * first create record in USERS table
 * then using the id from USERS create record in specific auth provider table
 * for example in USERS_TWITTER or USERS_GFC
 *
 * After new user is create post onNewUser event, also login the new user
 * and set uid cookie
 *
 * @todo we may add extra method so that IF user is new to us,
 * and is posted by AJAX then we send back special html of json object
 * so that a popup will be generated asking user to complete one last step:
 * provide an email address and possibly pick a username in case we have
 * a name collision or in case username is no provided by auth provider
 * like Facebook or GFC don't seem to provide username at all
 * we will tell user that they need to confirm their membership by
 * following special account activation link that we will email to them.
 * this is standard.
 * That prompt will send data to us via AJAX and will have a real time email
 * validation progress bar so the receiving php script will do the usual sleep(5) 4 times
 * and keep checking bounces.
 *
 * @author Dmitri Snytkine
 *
 */
class ExternalAuth extends LampcmsObject
{

    /**
     * Constructor
     * @param Registry $Registry
     */
    public function __construct(Registry $Registry)
    {

        $this->Registry = $Registry;
    }


    /**
     * Checks in username of twitter user
     * already exists in our regular USERS table
     * and if it does then prepends the @ to the username
     * otherwise returns twitter username
     *
     * The result is that we will use the value of
     * Twitter username as our username OR the @username
     * if username is already taken
     *
     * @todo change this to use MONGO USERS and use something like
     * $any
     *
     * @return string the value of username that will
     * be used as our own username
     *
     */
    public function makeUsername($displayName, $isUtf8 = false)
    {
        d('going to auto_create username based on displayName: ' . $displayName);

        /**
         * Make 100% sure that displayName is in UTF8 encoding
         * Commenting this out for now since it was causing
         * a problem once.
         * So for now we going to trust that Facebook give us results
         * as a valid UTF-8 String
         */
        if (!$isUtf8) {
            $displayName = Utf8String::stringFactory($displayName)->valueOf();
        }

        $coll = $this->Registry->Mongo->USERS;
        $res = null;

        $username = null;
        $aUsernames = array(
            preg_replace('/\s+/', '_', $displayName),
            preg_replace('/\s+/', '', $displayName),
            preg_replace('/\s+/', '.', $displayName),
            preg_replace('/\s+/', '-', $displayName)
        );

        $aUsernames = \array_unique($aUsernames);

        d('$aUsernames: ' . print_r($aUsernames, 1));

        for ($i = 0; $i < count($aUsernames); $i++) {
            $name = \mb_strtolower($aUsernames[$i], 'utf-8');

            $res = $coll->findOne(array('username_lc' => $name));
            d('$res: ' . $res);
            if (empty($res)) {
                $username = $aUsernames[$i];
                break;
            }
        }

        /**
         * If still could not find username then
         * use brute force and try appending numbers
         * to username untill succeed
         */
        if (null === $username) {
            $i = 1;
            do {
                $name = \mb_strtolower($aUsernames[0], 'utf-8') . $i;
                $res = $coll->findOne(array('username_lc' => $name));
                if (empty($res)) {
                    $username = $aUsernames[0] . $i;
                }
                d('$res: ' . $res);
            } while (null === $username);
        }

        return $username;
    }
}
