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


namespace Lampcms\Api\v1;

use \Lampcms\Api\Api;
use \Lampcms\AnswerParser;
use \Lampcms\String\HTMLString;
use \Lampcms\Question;

/**
 * Controller for adding new Answer via
 * API POST
 *
 * @author Dmitri Snytkine
 *
 */
class Addanswer extends Api
{
    protected $permission = 'answer';

    protected $bRequirePost = true;

    protected $membersOnly = true;

    /**
     * Submitted Answer object
     *
     * @var object of type SubmittedAnswer
     */
    protected $Submitted;


    protected $aRequired = array('qbody', 'qid');

    /**
     * Object of newly created Answer
     *
     * @var object of type \Lampcms\Answer
     */
    protected $Answer;

    /**
     * Question object represents the
     * question for which this answer is being parsed
     *
     *
     * @var object of type \Lampcms\Question
     */
    protected $Question;


    protected function main()
    {
        $this->Submitted = new SubmittedAnswer($this->Registry);

        $this->getQuestion()
            ->validateBody()
            ->process()
            ->setOutput();
    }


    /**
     * Get data for Question based on qid Request param
     *
     * @throws \Lampcms\HttpResponseCodeException in case
     * Question with passed "qid" is not found OR if it is
     * marked as deleted
     *
     * @return object $this
     */
    protected function getQuestion()
    {

        $aQuestion = $this->Registry->Mongo->QUESTIONS->findOne(array('_id' => (int)$this->Request['qid']));

        /**
         * @todo Translate string
         */
        if (empty($aQuestion)) {
            throw new \Lampcms\HttpResponseCodeException('Question not found', 404);
        }

        if (!empty($aQuestion['i_del_ts'])) {

            throw new \Lampcms\HttpResponseCodeException('This question was deleted on ' . date('r', $aQuestion['i_del_ts']), 404);
        }

        $this->Question = new Question($this->Registry, $aQuestion);


        return $this;
    }


    /**
     * Validate minimum length and min required
     * word count of body
     *
     * @throws \Lampcms\HttpResponseCodeException
     *
     * @return object $this
     */
    protected function validateBody()
    {
        $minChars = $this->Registry->Ini->MIN_ANSWER_CHARS;
        $minWords = $this->Registry->Ini->MIN_ANSWER_WORDS;
        $body = $this->Submitted->getBody();
        $oHtmlString = HTMLString::stringFactory($body);
        $wordCount = $oHtmlString->getWordsCount();
        $len = $oHtmlString->length();

        if ($len < $minChars) {
            throw new \Lampcms\HttpResponseCodeException('Answer must contain at least ' . $minChars . ' letters', 400);
        }

        if ($wordCount < $minWords) {
            throw new \Lampcms\HttpResponseCodeException('Answer must contain at least ' . $minWords . ' words', 400);
        }

        return $this;
    }


    /**
     * Process submitted Answer using AnswerParser class
     *
     * @throws \Lampcms\HttpResponseCodeException
     */
    protected function process()
    {
        $oAdapter = new AnswerParser($this->Registry);
        try {
            $this->Answer = $oAdapter->parse($this->Submitted);
            d('cp created new answer ' . $this->Answer->getResourceId());

        } catch (\Lampcms\QuestionParserException $e) {
            $err = $e->getMessage();
            d('$err: ' . $err);

            throw new \Lampcms\HttpResponseCodeException('Unable to add this answer: ' . $err, 400);
        }

        return $this;
    }


    /**
     * Entire Answer data will be returned
     * in request
     *
     * @return object $this
     */
    protected function setOutput()
    {

        /*$d = __METHOD__.' '.__LINE__;
          exit($d);*/

        $a = $this->Answer->getArrayCopy();
        /**
         * @todo maybe use special template
         * for 'app' instead of default template?
         *
         */
        $a['edit_delete'] = ' <span class="ico del ajax" title="Delete">delete</span>  <span class="ico edit ajax" title="Edit">edit</span>';
        $a ['html'] = \tplAnswer::parse($a);
        d('before sending out $a: ' . print_r($a, 1));

        $this->Output->setData($a);

        return $this;
    }

}
