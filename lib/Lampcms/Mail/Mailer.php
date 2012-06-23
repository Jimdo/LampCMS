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


namespace Lampcms\Mail;

use \Lampcms\DevException;

/**
 * Class for sending out emails
 *
 * @author Dmitri Snytkine
 *
 */
class Mailer
{

    protected $adminEmail;

    protected $siteName;

    protected $from;

    /**
     * Ini object
     *
     * @var object \Lampcms\Ini
     */
    protected $Ini;

    public function __construct(\Lampcms\Config\Ini $Ini)
    {
        $this->Ini = $Ini;
        $this->adminEmail = $Ini->EMAIL_ADMIN;
        $this->siteName = $Ini->SITE_NAME;
        $this->from = \Lampcms\String::prepareEmail($this->adminEmail, $this->siteName);
    }


    /**
     * This is a main mail function to send
     * email to individual user or to array or receipients
     * or even by passing Iterator object to it such
     * as MongoCursor
     *
     * The better more fine-tuned method mailFromCursor
     * should be used when sending mass email and when we know
     * that cursor contains several hundreds of records
     *
     * Sends our emails using the mail()
     * but can be sent from inside the runLater
     * This way function returns immediately
     *
     * Also accept Iteractor so we can use the
     * cursor in place of $to
     *
     * Has the ability to pass in
     * callback function and that function
     * would return email address or false
     * if email should not be sent
     * This will be used if $to is iterator or array
     * and contains fields like e_ft = null or does not exist
     * So we can check if (empty($a['e_ft']){
     *  return flase // skip this one
     * } else {
     *  return $a['email'];
     * }
     *
     * @todo for best result we should actually fork another php
     *script as regular cgi script (not fastcgi, just cgi php)
     *and just pass arguments to it and let it run as long as necessary,
     *looping over the array of recepients and sending out emails
     *The problem is that we cannot just pass cursor or even an array
     *of receipients to a cgi like this, we need to somehow pass data
     *differently like not the actual data but the conditions and then
     *let the cgi find that data and use it. We can do this via a cache
     *just put array into cache and pass cache key to cgi script
     *that would work for up to 4MB of data
     *And then let cgi delete that key and just in case set the expiration
     *on that key too so it will be deleted eventually even if cgi
     *dies for some reason
     *
     * @param mixed $to
     * @param string $subject
     * @param string $body
     *
     * @param bool $sendLater if true (default) then will run
     * via the runLater, otherwise
     * will run immediately. So if this method itself is
     * invoked from some registered shutdown function then it
     * makes sense to not use the 'later' feature.
     *
     * @param function $func callback function to be applied
     * to each record of the array.
     *
     * @throws DevException
     */
    public function mail($to, $subject, $body, $func = null, $sendLater = true)
    {

        if (!is_string($to) && !is_array($to) && (!is_object($to) || !($to instanceof \Iterator))) {

            $class = (is_object($to)) ? get_class($to) : 'not an object';

            throw new DevException('$to can be only string or array or object implementing Iterator. Was: ' . gettype($to) . ' class: ' . $class);
        }

        $aTo = (is_string($to)) ? (array)$to : $to;

        $aHeaders = array();
        $aHeaders['From'] = $this->from;
        $aHeaders['Reply-To'] = $this->adminEmail;
        $headers = \Lampcms\prepareHeaders($aHeaders);

        d('$subject: ' . $subject . ' body: ' . $body . ' headers: ' . $headers . ' $aTo: ' . print_r($aTo, 1));

        $callable = function() use ($subject, $body, $headers, $aTo, $func)
        {

            $total = (is_array($aTo)) ? count($aTo) : $aTo->count();

            /**
             * @todo deal with breaking up
             * the long array/cursor into
             * smaller chunks and send emails
             * in groups on N then wait N seconds
             * This is handled differently for cursors and for arrays
             */
            if ($total > 0) {
                foreach ($aTo as $to) {
                    /**
                     * Deal with format of array when array
                     * is result of iterator_to_array($cur)
                     * where $cur is MongoCursor - result of
                     * find()
                     */
                    if (is_array($to)) {
                        /**
                         * If callback function is passed to mail()
                         * then it must accept array or
                         * one user record as argument and must
                         * return either email address (string)
                         * or false if record should be skipped
                         * For example, if array contains something like this
                         * 'email' => user@email.com,
                         * 'e_ft' => null
                         *
                         * Which indicates that user does not want
                         * to receive emails on followed tag
                         *
                         * This is a way to pass callback to serve
                         * as a filter - to users who opted out
                         * or receiving emails on specific events
                         * will not receive them
                         *
                         * Since each opt-out flag is different, the
                         * each callback for specific type of mailing
                         * will also be different.
                         *
                         */
                        if (is_callable($func)) {
                            $to = $func($to);
                        } elseif (!empty($to['email'])) {
                            $to = $to['email'];
                        }
                    }

                    /**
                     * Now it is possible that callback
                     * function returned null or false so we
                     * must now check that $to is not
                     * empty
                     * Also if array did not contain
                     * the 'email' key then nothing will
                     * be sent at this point because
                     * the $to will be !is_string() at
                     * this time - it will still be array
                     */
                    if (empty($to) || !is_string($to)) {

                        continue;
                    }


                    $ER = error_reporting(0);
                    if (true !== @\mail($to, $subject, $body, $headers)) {
                        // was unable to send out email
                        if (function_exists('e')) {
                            e('unable to send email to ' . $to . ' subject: ' . $subj . ' body: ' . $body);
                        }
                    }
                    error_reporting($ER);
                }
            }

        };

        if ($sendLater) {
            \Lampcms\runLater($callable);
        } else {
            $callable();
        }

        return true;
    }


    /**
     *
     * The purpose of this methos is to take
     * a Mongo cursor as a source of email addresses
     * then iterate over them and do create an array
     * of email addresses. May use callback function
     * to filter out addresses of people who
     * opted out of certain types
     * of emails updates
     *
     *
     * In case the cursor contains very large number of records it
     * may use the forkMail() method to form
     * mass mailing in the background
     *
     *
     * @param \MongoCursor $cur
     * @param string $subject
     * @param string $body
     * @param string $func
     */
    public function mailFromCursor(\MongoCursor $cur, $subject, $body, $func = null, $sendLater = false)
    {

        /**
         * Cannot change anything in the cursor
         * Cannot just do the skip() and limit()
         * it too late now! Attempting to modify a cursor
         * now will result in this type of exception: cannot modify cursor after beginning iteration.
         *
         * For a very large website a whole
         * new class should be written that can deal with
         * getting cursort with > 10,000 results then iterating over it,
         * creating array(s) of about 1000 emails, senging them out,
         * waiting couple of minutes and continuing
         *
         * This class can deal with results of up to 10,000 records per
         * one notification and will still be able to
         * handle this task relative well by creating array
         * and then forking a new cgi script in the background
         * to do actual email senting
         *
         * For couple of hundred emails or even a 1000 at a time
         * they can all be sent our from the same (this) process as long
         * as the process is run after the fastcgi_finish_request()
         *
         */

        $cID = spl_object_hash($cur);
        //$body = $body ."\n\n_______\ncid: ".$cID."\n";

        $aEmails = array();
        foreach ($cur as $a) {

            if (is_callable($func)) {
                $email = $func($a);
                if (!empty($email)) {
                    $aEmails[] = $email;
                }
            } else if (!empty($a['email'])) {
                $aEmails[] = $a['email'];
            }

        }

        $aEmails = \array_unique($aEmails);
        /**
         *
         * @todo if count($aEmails) > 100 then formMail,
         * else just \mail()
         *
         * Right now we don't have such cgi-based scripts
         * that can accept the cache key
         * but it will be the next-best-solution
         * for a busy site
         * and the very best solution would be
         * to form the whole EmailNotifier methods
         * from the cgi script
         * and the super-advanced solution for
         * sites that have over 10,000 followers per topic
         * or per some users would be to
         * form the whole process on a remove server
         * This is something that we can code on request
         * as it is not worth the time for just an average site
         */
        $this->mail($aEmails, $subject, $body, null, $sendLater);

    }


    /**
     * This method will take in array of email addresses
     * put then into cache and then form
     * a separate cgi php script and pass
     * that cache key to it as a param.
     * That cgi script will be able to retreive
     * the array, break it up into
     * smaller chunks and send it out in intervals
     * and wait N seconds in between. That will not
     * be using up any of our webserver's php processes
     * so it's OK for such scripts to run several minutes
     *
     * This method by it's natute of forking another process
     * has the effect of passing a job to a separate thread
     * and as such it makes no sense to use it via shutdown
     * function as it is already designed to not add any significant
     * processing time to the main process
     *
     * @todo finish writing it. Must have cgi
     * version of php on the server with mongo
     * extension because array of emails will be passed
     * there via mongo key
     *
     * @param array $aTo simple array of email addresses
     * @param string $subject
     * @param string $body
     * @param bool $sendLater
     */
    public function forkMail(array $aTo, $subject, $body)
    {

    }

}
