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

namespace Lampcms\Controllers;

use Lampcms\WebPage;
use Lampcms\String;

//use Lampcms\Mailer;

/**
 * Class responsible for
 * displaying the forgot password
 * form, processing the form,
 * generating a new ramdom password
 * for user
 * and emailing it to user
 */
class Remindpwd extends WebPage
{
    const EMAIL_BODY = 'Hi there, %1$s

You have requested to reset your password on %2$s because you have forgotten your password. 
If you did not request this, please ignore it. 

This link expire and become useless in 24 hours time.

To reset your password, please visit the following page:

%3$s

When you visit that page, your password will be reset, and the new password will be emailed to you.

Your username is: %1$s


All the best,
%2$s

	';

    const TPL_SUCCESS = '<div class="frm1">Your username is <b>%1$s</b>
Please write it down now!
<br/><br/>
Your password reset instructions have been emailed<br/> to the address
associated with your account.</div>';

    const SUBJECT = 'Your password request at %s';

    protected $layoutID = 1;

    /**
     * @var object of type Form
     */
    protected $Form;

    protected $aAllowedVars = array('login');


    /**
     *
     * Username or email address
     * submitted in forgotten password form
     * @var string
     */
    protected $login;

    /**
     * User ID of user who forgot password
     *
     * @var int
     */
    protected $uid;


    /**
     * The user was found by email
     * and not by username
     *
     * @var bool
     */
    protected $byEmail = false;


    /**
     *
     * Email address of user
     * @var string
     */
    protected $emailAddress;

    /**
     *
     * Secret string will be used in reset
     * password link, sent to user via email
     * @var string
     */
    protected $randomString;


    /**
     * Remders and processes the form
     *
     * @return string page with html form
     * @param array $arrParams array of GET or POST params
     */
    protected function main()
    {
        /**
         * @todo Translate String
         *
         */
        $this->title = 'Password help';

        $this->Form = new \Lampcms\Forms\Pwd($this->Registry);
        $this->aPageVars['title'] = $this->title;


        if ($this->Form->isSubmitted() && $this->Form->validate() && $this->validateUser()) {
            d('cp');
            $this->generateCode()->emailCode();
            $this->aPageVars['body'] = sprintf(self::TPL_SUCCESS, $this->login);
        } else {
            $this->aPageVars['body'] = $this->Form->getForm();
        }
    }


    /**
     * Validation to check that user
     * with this username or email address
     * exists in the database
     * If user exists, set the $this->forgottenUid
     * to the value of this user's id
     *
     * @return bool true if user found, otherwise false
     * and in case of false also sets form errors
     * so that user will see the form with errors
     */
    protected function validateUser()
    {
        $this->login = \mb_strtolower($this->Form->getSubmittedValue('login'));
        d('$this->login: ' . $this->login);
        if (false !== \filter_var($this->login, FILTER_VALIDATE_EMAIL)) {
            d('cp');
            $this->byEmail = true;
            $aEmail = $this->Registry->Mongo->EMAILS->findOne(array('email' => $this->login));
            if (empty($aEmail)) {
                $this->Form->setError('login', 'No user with this email address');

                return false;
            }

            d('$aEmail: ' . print_r($aEmail, 1));
            $aResult = $this->Registry->Mongo->USERS->findOne(array('_id' => (int)$aEmail['i_uid']));

        } else {
            if (false === \Lampcms\Validate::username($this->login)) {
                d('cp');
                $this->Form->setError('login', 'This username is invalid');

                return false;
            }

            $aResult = $this->Registry->Mongo->USERS->findOne(array('username_lc' => $this->login));
        }

        if (empty($aResult)) {
            d('cp');

            $this->Form->setError('login', 'User Not found');

            return false;
        }


        /**
         * @todo
         *
         * if 'usertype' == 'email'
         * then user does not have login
         * Just test and then throw an exception?
         * Actually maybe it's better if user could just login
         * then edit profile and become regular user...
         *
         * But how would we do that? We would bacially activate
         * a user on first login.
         */
        d('$aResult: ' . \print_r($aResult, 1));

        /**
         * If username exists but email does not
         * such as the case when user is external user who has
         * not yet provided email address
         *
         */

        if (empty($aResult['email'])) {
            throw new \Lampcms\Exception('This is an external account and you have not provided a valid email address for it');
        }

        /**
         * @todo if user does not have username
         * then we should use email address instead
         * user should be able to login using email address!
         *
         */
        $this->uid = $aResult['_id'];
        $this->login = (!empty($aResult['username'])) ? $aResult['username'] : $aResult['email'];
        $this->emailAddress = $aResult['email'];

        return true;
    }


    /**
     * Generates a random string
     * to be use in password reset url
     * It checks to make sure this string does not already exist
     * in the PASSWORD_CHANGE table
     *
     * @return object $this
     *
     * @throws LampcmsException in case a unique string
     * could not be generated
     */
    protected function generateCode()
    {
        d('cp');
        $counter = 0;
        $done = false;

        do {
            $counter++;
            $aData = array();
            $aData['_id'] = \strtolower(\Lampcms\String::makeRandomString(12));
            $aData['i_ts'] = time();
            $aData['i_uid'] = $this->uid;

            /**
             * @todo
             * Don't use _id for string,
             * instead use unique index on string + 'y'/'n' value of 'used'
             * This way string can be duplicate as long as no same
             * string is used
             */
            try {
                $coll = $this->Registry->Mongo->PASSWORD_CHANGE;
                $coll->insert($aData, array('fsync' => true));
                $done = true;
                d('cp');
            } catch (\MongoException $e) {
                d('code already exists, trying again...');
            }


        } while (!$done && ($counter < 50));

        if (!$done) {
            throw new \Lampcms\Exception('Error: Unable to generate random string at this time, please try again in 30 seconds');
        }

        $this->randomString = $aData['_id'];

        return $this;
    }


    /**
     * Prepares the body, subject and from
     * and email to user
     *
     * @todo translate strings instead of using constants
     * of this class
     *
     * @return object $this
     */
    protected function emailCode()
    {
        $link = $this->Registry->Ini->SITE_URL . '/index.php?a=resetpwd&uid=' . $this->uid . '&r=' . $this->randomString;
        $body = vsprintf(self::EMAIL_BODY, array($this->login, $this->Registry->Ini->SITE_NAME, $link));
        $subject = sprintf(self::SUBJECT, $this->Registry->Ini->SITE_NAME);

        //Mailer::factory($this->Registry)->mail($this->emailAddress, $subject, $body);
        $this->Registry->Mailer->mail($this->emailAddress, $subject, $body);

        return $this;
    }

}
