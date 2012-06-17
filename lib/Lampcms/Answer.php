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
 *
 * Class represents one item
 * from the ANSWERS collection
 *
 * @author Dmitri Snytkine
 *
 */
class Answer extends \Lampcms\Mongo\Doc implements Interfaces\Answer, Interfaces\UpDownRatable, Interfaces\CommentedResource
{

    public function __construct(Registry $Registry, array $a = null)
    {
        $a = ($a) ? $a : array();
        parent::__construct($Registry, 'ANSWERS', $a);
    }


    /**
     * (non-PHPdoc)
     * @see LampcmsResourceInterface::getResourceTypeId()
     * @return string
     */
    public function getResourceTypeId()
    {

        return 'ANSWER';
    }

    /**
     * Set value of 'accepted' to true
     * and update i_lm_ts
     *
     * @return object $this
     */
    public function setAccepted()
    {
        parent::offsetSet('accepted', true);

        return $this;
    }

    /**
     * Set value of 'accepted' to false
     * and update i_lm_ts
     *
     * @return object $this
     */
    public function unsetAccepted()
    {
        parent::offsetSet('accepted', false);

        return $this;
    }


    /**
     * (non-PHPdoc)
     * @see ResourceInterface::getResourceId()
     * @return int
     */
    public function getResourceId()
    {

        return $this->offsetGet('_id');
    }


    /**
     * (non-PHPdoc)
     * @see LampcmsResourceInterface::getDeletedTime()
     * @return int (0 if this answer is not deleted)
     */
    public function getDeletedTime()
    {

        return $this->offsetGet('i_del_ts');
    }


    /**
     * Get username of answerer
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->offsetGet('username');
    }

    /**
     *
     * Mark this item as deleted but only
     * if not already marked as deleted
     *
     *
     * @param \Lampcms\User $user
     * @param string        $reason optional reason for delete
     *
     * @internal param \Lampcms\User $object $user user marking this
     *           item as deleted
     *
     * @return object $this
     */
    public function setDeleted(User $user, $reason = null)
    {
        if (0 === $this->getDeletedTime()) {
            if ($reason) {
                $reason = \strip_tags((string)$reason);
            }

            parent::offsetSet('i_del_ts', time());
            parent::offsetSet('a_deleted',
                array(
                    'username' => $user->getDisplayName(),
                    'i_uid' => $user->getUid(),
                    'av' => $user->getAvatarSrc(),
                    'reason' => $reason,
                    'hts' => date('F j, Y g:i a T')
                )
            );
        }

        return $this;
    }


    /**
     *
     * Adds a_edited array of data to Question
     *
     * @param User $user
     * @param string $reason reason for editing
     *
     * @return object $this
     */
    public function setEdited(User $user, $reason = '')
    {
        if (!empty($reason)) {
            $reason = \strip_tags((string)$reason);
        }

        $aEdited = $this->offsetGet('a_edited');
        if (empty($aEdited) || !is_array($aEdited)) {
            $aEdited = array();
        }

        $aEdited[] = array(
            'username' => $user->getDisplayName(),
            'i_uid' => $user->getUid(),
            'av' => $user->getAvatarSrc(),
            'reason' => $reason,
            'hts' => date('F j, Y g:i a T'));

        parent::offsetSet('a_edited', $aEdited);

        return $this;
    }


    /**
     * (non-PHPdoc)
     * @see LampcmsResourceInterface::getOwnerId()
     * @return int id of user who is the author of this answer
     */
    public function getOwnerId()
    {

        return $this->offsetGet('i_uid');
    }


    /**
     * (non-PHPdoc)
     * @see LampcmsResourceInterface::getLastModified()
     * @return int last modified timestamp
     */
    public function getLastModified()
    {

        return $this->offsetGet('i_lm_ts');
    }


    /**
     * Updates last modified timestamp
     * A replacement for updateLastModified() method
     *
     * @return object $this
     */
    public function touch()
    {
        $this->offsetSet('i_lm_ts', time());

        return $this;
    }


    /**
     * (non-PHPdoc)
     *
     * @see UpDownRatable::addUpVote()
     *
     * @param int $inc
     *
     * @throws
     * @return \Lampcms\Answer
     */
    public function addUpVote($inc = 1)
    {

        if (1 !== $inc && (-1 !== $inc)) {
            throw \InvalidArgumentException('$inc can only be 1 or -1. Was: ' . $inc);
        }

        $tmp = (int)$this->offsetGet('i_up');
        $score = (int)$this->offsetGet('i_votes');
        $total = ($score + $inc);

        parent::offsetSet('i_up', max(0, ($tmp + $inc)));
        parent::offsetSet('i_votes', $total);

        /**
         * Plural extension handling
         */
        $v_s = (1 === abs($total)) ? '' : 's';
        parent::offsetSet('v_s', $v_s);

        return $this;
    }


    /**
     * (non-PHPdoc)
     *
     * @see UpDownRatable::addDownVote()
     *
     * @param int $inc
     *
     * @throws
     * @return \Lampcms\Answer
     */
    public function addDownVote($inc = 1)
    {

        if (1 !== $inc && (-1 !== $inc)) {
            throw \InvalidArgumentException('$inc can only be 1 or -1. Was: ' . $inc);
        }

        $tmp = (int)$this->offsetGet('i_down');
        $score = (int)$this->offsetGet('i_votes');
        $total = ($score - $inc);

        parent::offsetSet('i_down', max(0, ($tmp + $inc)));
        parent::offsetSet('i_votes', $total);

        /**
         * Plural extension handling
         */
        $v_s = (1 === abs($total)) ? '' : 's';
        parent::offsetSet('v_s', $v_s);

        return $this;
    }


    /**
     * (non-PHPdoc)
     * @see UpDownRatable::getVotesArray()
     * @return array
     */
    public function getVotesArray()
    {

        $a = array(
            'up' => $this->offsetGet('i_up'),
            'down' => $this->offsetGet('i_down'),
            'score' => $this->offsetGet('i_votes'));

        return $a;
    }


    /**
     * (non-PHPdoc)
     * @see UpDownRatable::getScore()
     * @return array|bool|int|mixed|null
     */
    public function getScore()
    {

        return $this->offsetGet('i_votes');
    }


    /**
     * Get full (absolute) url for this question,
     * including the http and our domain
     * add the #answer to the url, but this has challenges
     * with pagination and some challenges with url rewrite rules
     *
     * For example if this answer is not of the first page of the question
     * then the # anchor will not point to valid answer.
     * It's not easy to determine on which page this answer is (currently)
     *
     * @param bool $short
     *
     * @return string url for this question
     */
    public function getUrl($short = false)
    {
        $url = '{_WEB_ROOT_}/{_viewquestion_}/{_QID_PREFIX_}'.$this->offsetGet('_id'). '/#ans' . $this->offsetGet('_id');

        $url = $this->getRegistry()->Ini->SITE_URL.$url;
        $callback = $this->Registry->Router->getCallback();


        $ret = $callback($url);

        return $ret;
    }


    /**
     * (non-PHPdoc)
     * @see Lampcms\Interfaces.Post::getBody()
     * @return string body of the answer (may contain html)
     */
    public function getBody()
    {
        return $this->offsetGet('b');
    }


    /**
     * (non-PHPdoc)
     * @see Lampcms\Interfaces.Post::getTitle()
     * @return string
     */
    public function getTitle()
    {
        return $this->offsetGet('title');
    }


    /**
     * (non-PHPdoc)
     * @see Lampcms\Interfaces.Post::getSeoUrl()
     * @return string
     */
    public function getSeoUrl()
    {
        return '';
    }


    /**
     * (non-PHPdoc)
     *
     * @see Lampcms\Interfaces.CommentedResource::addComment()
     *
     * @param \Lampcms\CommentParser $Comment
     *
     * @return \Lampcms\Answer (this object)
     */
    public function addComment(CommentParser $Comment)
    {
        $aKeys = array(
            '_id',
            'i_uid',
            'i_prnt',
            'username',
            'avtr',
            'b_owner',
            'inreplyto',
            's_inreply',
            'b',
            't',
            'ts',
            'cc',
            'cn',
            'reg',
            'city',
            'zip',
            'lat',
            'lon');

        $aComments = $this->getComments();
        d('aComments: ' . print_r($aComments, 1));

        /**
         * Only keep the keys that we need
         * get rid of keys like hash, i_res
         * because we don't need them here
         */
        $aComment = $Comment->getArrayCopy();
        $aComment = \array_intersect_key($aComment, array_flip($aKeys));

        $aComments[] = $aComment;

        $this->setComments($aComments);

        return $this;
    }


    /**
     * (non-PHPdoc)
     * @see Lampcms\Interfaces.CommentedResource::getCommentsCount()
     * @return int
     */
    public function getCommentsCount()
    {
        $aComments = $this->getComments();

        return count($aComments);
    }


    /**
     *
     * Increase value of i_comments by 1
     * The i_comments is a counter
     *
     * @param int $count
     *
     * @throws \InvalidArgumentException
     * @return object $this
     */
    public function increaseCommentsCount($count = 1)
    {
        if (!is_int($count)) {
            throw new \InvalidArgumentException('$count must be integer. was: ' . gettype($count));
        }

        /**
         * Now increase comments count
         */
        $commentsCount = $this->getCommentsCount();
        d('$commentsCount ' . $commentsCount);

        /**
         * Must use parent::offsetSet because
         * $this->offsetSet will point back to this
         * method and enter infinite loop untill
         * we run out of memory
         */
        parent::offsetSet('i_comments', ($commentsCount + $count));

        return $this;
    }


    /**
     * Remove one comment from array of comments
     * then re-save the new array of comments
     * the numerical keys of array will be reset
     * Also i_comments value will be updated to the
     * new count of comments
     *
     * (non-PHPdoc)
     *
     * @see Lampcms\Interfaces.CommentedResource::deleteComment()
     *
     * @param $id
     *
     * @return \Lampcms\Answer
     */
    public function deleteComment($id)
    {

        if (0 === $this->getCommentsCount()) {
            e('This question does not have any comments');

            return $this;
        }

        $aComments = $this->offsetGet('a_comments');

        for ($i = 0; $i < count($aComments); $i += 1) {
            if ($id == $aComments[$i]['_id']) {
                d('unsetting comment: ' . $i);
                \array_splice($aComments, $i, 1);
                break;
            }
        }

        $newCount = count($aComments);
        if (0 === $newCount) {
            $this->offsetUnset('a_comments');
        } else {
            $this->setComments($aComments);
        }

        return $this;
    }


    public function getCategoryId()
    {
        return $this->offsetGet('i_cat');
    }

    /**
     * Getter for 'comments' element
     * @return array of comments or empty array if
     * 'comments' element not present in the object
     *
     */
    public function getComments()
    {

        return $this->offsetGet('a_comments');
    }


    /**
     * Get one comment from
     * a_comments array
     *
     * @param int $id comment id
     * @throws DevException if param $id is not an integer
     *
     * @return mixed array of one comment | false if comment not found by $id
     *
     */
    public function getComment($id)
    {
        if (!\is_int($id)) {
            throw new DevException('Param $id must be integer. Was: ' . $id);
        }

        $aComments = $this->getComments();

        for ($i = 0; $i < count($aComments); $i += 1) {
            if ($id == $aComments[$i]['_id']) {
                return $aComments[$i];
            }
        }

        return false;
    }


    /**
     * Sets the 'a_comments' key via parent::offsetSet
     * Using parent because offsetSet of this class
     * will disallow setting a_comments key directly!
     *
     *
     * @param array $aComments comments array
     *
     * @return object $this
     */
    public function setComments(array $aComments)
    {
        parent::offsetSet('a_comments', $aComments);
        parent::offsetSet('i_comments', \count($aComments));

        $this->touch();

        return $this;
    }


    /**
     * Get id of question for this answer
     *
     * @return int id of question for which this is an answer
     */
    public function getQuestionId()
    {
        return (int)$this->offsetGet('i_qid');
    }


    /**
     * Get uid of user who asked the question
     * for which this is the answer
     * This is useful during adding a comment
     * to an answer where we need to know if
     * the comment comes from the original asker.
     *
     * @return int id of question owner
     */
    public function getQuestionOwnerId()
    {
        return (int)$this->offsetGet('i_quid');
    }


    /**
     * This method prevents setting some
     * values directly
     *
     * (non-PHPdoc)
     *
     * @see ArrayObject::offsetSet()
     *
     * @param mixed $index
     * @param mixed $newval
     *
     * @throws DevException
     */
    public function offsetSet($index, $newval)
    {
        switch ($index) {
            case 'accepted':
                throw new DevException('value of accepted cannot be set directly. Use setAccepted() or unsetAccepted() methods');
                break;

            case 'i_comments':
                throw new DevException('value of i_comments cannot be set directly. Use increaseCommentsCount() method');
                break;

            case 'comments':
            case 'a_comments':
                throw new DevException('value of a_comments cannot be set directly. Must use setComments() method for that');
                break;

            case 'i_down':
            case 'i_up':
            case 'i_votes':
                throw new DevException('value of ' . $index . ' keys cannot be set directly. Use addDownVote or addUpVote to add votes');
                break;

            case 'a_deleted':
            case 'i_del_ts':
                throw new DevException('value of ' . $index . ' cannot be set directly. Must use setDeleted() method for that');
                break;

            case 'a_edited':
                throw new DevException('value of a_edited cannot be set directly. Must use setEdited() method for that');
                break;

            default:
                parent::offsetSet($index, $newval);
        }
    }

}
