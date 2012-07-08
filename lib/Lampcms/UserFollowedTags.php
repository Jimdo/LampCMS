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


namespace Lampcms;

/**
 *
 * Class for rendering block with
 * tags that user is following
 * and also adding another block
 * of "you both follow these tags"
 * if Viewer following some of the same tags
 *
 * @author Dmitri Snytkine
 *
 */
class UserFollowedTags extends LampcmsObject
{
    /**
     * Maximum tags to show in user tags block
     * A very active user can have hundreds of tags, we
     * want to show only the 60
     * most popular tags for this user
     *
     *
     * @var int
     */
    const MAX_TO_SHOW = 40;


    /**
     *
     * Get block with links to tags that User is following
     * and also possibly a block on tags that both Viewer and User
     * are following
     *
     * @param Registry $Registry
     * @param User $User
     *
     * @return string html
     */
    public static function get(Registry $Registry, User $User)
    {
        $aUserTags = $User['a_f_t'];
        if (empty($aUserTags)) {
            return '';
        }

        if (count($aUserTags) > self::MAX_TO_SHOW) {
            $aUserTags = \array_slice($aUserTags, 0, self::MAX_TO_SHOW);
        }

        /**
         * @todo Translate string
         */
        $blockTitle = $User->getDisplayName() . ' is following these tags';
        $tags = $commonTags = '';

        $tags = \tplTagLink::loop($aUserTags, false);


        /**
         * If Viewer is not the same user as user whose profile
         * being viewer then attempt to get the 'common' tags
         * that both viewer and user are following
         */
        if ($User->getUid() !== $Registry->Viewer->getUid()) {
            $commonTags = self::getCommonTags($aUserTags, $Registry->Viewer['a_f_t']);
        }

        $vals = array('count' => $blockTitle, 'label' => 'tag', 'tags' => $tags);

        $ret = \tplUserTags::parse($vals);

        return $ret . $commonTags;
    }


    /**
     * Get array of tags that both User and Viewer following
     * and return parsed block 'You both follow'
     *
     * @param array $userTags
     * @param array $viewerTags
     *
     * @return string html block or empty String if there are
     * no common tags
     */
    protected static function getCommonTags(array $userTags, array $viewerTags)
    {

        $aCommon = \array_intersect($userTags, $viewerTags);
        if (empty($aCommon)) {
            return '';
        }

        $tags = \tplTagLink::loop(array_values($aCommon), false);
        /**
         * @todo translate string 'You both follow'
         *
         */
        $vals = array('count' => '@@You both follow@@', 'label' => 'tag', 'tags' => $tags);

        return \tplUserTags::parse($vals);
    }
}
