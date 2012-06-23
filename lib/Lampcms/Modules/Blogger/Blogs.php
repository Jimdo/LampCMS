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


namespace Lampcms\Modules\Blogger;

/**
 *
 * Class for parsing (extracting) array
 * of all blogs that user has with Blogger
 * The input xml file is returned from Blogger API
 * as result to call to this url: https://www.blogger.com/feeds/default/blogs
 * by an authenticated user
 * We usually call this url during the "connect blogger" process
 * right after the user just authorized blogger account via OAuth
 *
 * @author Dmitri Snytkine
 *
 */
class Blogs
{

    /**
     *
     * Parse $xml and return array of blogs
     * each element in that array is an array
     * with keys: 'id', 'title' and 'url'
     *
     * @param string $xml xml returned by Blogger API
     * in response to request https://www.blogger.com/feeds/default/blogs
     * by an authenticated user (using OAuth credentials)
     *
     * @throws \Exception
     */
    public function getBlogs($xml)
    {

        $aBlogs = array();
        $XML = new \Lampcms\Dom\Document();
        if (false === $XML->loadXML($xml)) {
            $err = 'Unexpected Error parsing response XML';
            throw new \Exception($err);
        }

        $xp = new \DOMXPath($XML);
        $xp->registerNamespace('atom', "http://www.w3.org/2005/Atom");

        $aParsed = $XML->getElementsByTagName('entry');

        if (0 === $aParsed->length) {
            e('Looks like user does not have any blogs: $xml: ' . $xml);

            $err = ('Looks like you have Blogger account but do not have any blogs setup yet');
            throw new \Exception($err);
        }

        foreach ($aParsed as $blog) {
            $aBlog = array();
            $aBlog['id'] = $this->getId($blog);
            $aBlog['title'] = $blog->getElementsByTagName('title')->item(0)->nodeValue;
            $r = $xp->query('atom:link[@type = "text/html"]/@href', $blog);
            $aBlog['url'] = ($r->length > 0) ? $r->item(0)->nodeValue : null;
            if (!empty($aBlog['url']) && !empty($aBlog['id'])) {
                $aBlogs[] = $aBlog;
            }
        }

        return $aBlogs;
    }


    /**
     * Extract value of actual blog ID
     * from the 'id' tag
     * the value looks like this:
     * <id>tag:blogger.com,1999:user-850590157766.blog-4083976222769752292</id>
     * we need only the numeric value after the id-
     *
     * @param \DOMElement $el
     * @return mixed null if not found | string numeric value
     */
    public function getId(\DOMElement $el)
    {
        $a = $el->getElementsByTagName('id');
        if (0 === $a->length) {
            return null;
        }

        $val = $a->item(0)->nodeValue;
        $pos = \strrpos($val, '-');

        return (!$pos) ? null : \trim(\substr($val, ($pos + 1)));
    }
}
