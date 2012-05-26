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


namespace Lampcms;
require_once 'bootstrap.php';


/**
 * Run after MongoTest
 *
 */
class MongoDocTest extends LampcmsUnitTestCase
{
    protected $COLLNAME = 'MY_TEST_COLLECTION';

    protected $oMongoDoc;

    protected $aData = array('one' => 1, 'two' => 2);

    public function setUp()
    {
        $this->Registry = new Registry();
        $this->oMongoDoc = \Lampcms\Mongo\Doc::factory($this->Registry, $this->COLLNAME);

    }

    public function testSetMinAutoIncrement()
    {
        $this->oMongoDoc->setMinAutoIncrement(10);
        $this->assertEquals(10, $this->oMongoDoc->getMinAutoIncrement());
    }

    public function testGetRegistry()
    {

        $this->assertTrue($this->oMongoDoc->getRegistry() instanceof \Lampcms\Registry);
    }

    public function testGetCollectionName()
    {
        $this->assertEquals('MY_TEST_COLLECTION', $this->oMongoDoc->getCollectionName());
    }

    public function testGetNonexistentKey()
    {
        $this->assertEquals('', $this->oMongoDoc['abcdef']);
    }


    public function testAddArray()
    {

        $this->oMongoDoc->addArray($this->aData);
        $this->oMongoDoc->setSaved();
        $this->assertEquals(1, $this->oMongoDoc['one']);
        $this->assertEquals(2, $this->oMongoDoc['two']);
    }


    /**
     *
     * @depends testSetMinAutoIncrement
     */
    public function testSave()
    {

        $this->oMongoDoc->addArray($this->aData);
        $this->oMongoDoc['name'] = 'Johnson';

        $res = $this->oMongoDoc->save();

        $this->assertTrue(is_int($res));

        $data = $this->Registry->Mongo->getCollection($this->COLLNAME)->findOne(array('_id' => $res));
        $this->assertEquals(1, $data['one']);
        $this->assertEquals(2, $data['two']);
        $this->assertEquals('Johnson', $data['name']);
    }


    /**
     * Tests magic ->by
     * and also tests that save() triggets update
     * instead of insert when document already
     * has primary key
     *
     * @depends testSave
     */
    public function testUpdate()
    {
        $oMongoDoc = new \Lampcms\Mongo\Doc($this->Registry, $this->COLLNAME);
        $oMongoDoc->byone(1);

        $this->assertEquals(1, $oMongoDoc['one']);
        $oMongoDoc['two'] = 3;
        $oMongoDoc->save();

        $data = $this->Registry->Mongo->getCollection($this->COLLNAME)->findOne(array('two' => 3));
        $this->assertEquals(1, $data['one']);
        $this->assertEquals(3, $data['two']);
        $this->assertEquals('Johnson', $data['name']);
    }


    /**
     * Tests magic ->by
     * and also tests that save() triggets update
     * instead of insert when document already
     * has primary key
     *
     * @depends testSave
     */
    public function testReload()
    {
        $oMongoDoc = new \Lampcms\Mongo\Doc($this->Registry, $this->COLLNAME);
        $oMongoDoc->byone(1);

        $this->assertEquals(1, $oMongoDoc['one']);

        $this->Registry->Mongo->getCollection($this->COLLNAME)
            ->update(array('one' => 1), array('$set' => array('five' => 5)));

        $oMongoDoc->reload();

        $this->assertEquals(5, $oMongoDoc['five']);
        $this->assertEquals(1, $oMongoDoc['one']);
    }


    /**
     * @depends testSave
     *
     */
    public function testSerialization()
    {
        $oMongoDoc = new \Lampcms\Mongo\Doc($this->Registry, $this->COLLNAME);
        $oMongoDoc->byone(1);
        $md5 = $oMongoDoc->getChecksum();
        $saved = $oMongoDoc->getSavedFlag();
        $s = serialize($oMongoDoc);
        $oNew = unserialize($s);

        $this->assertTrue($this->oMongoDoc->getRegistry() instanceof \Lampcms\Registry);
        $this->assertTrue($oNew instanceof \Lampcms\Mongo\Doc);
        $this->assertEquals($oNew['one'], 1);
        $this->assertEquals($oNew->getChecksum(), $md5);
        $this->assertEquals($saved, $oNew->getSavedFlag());
        $this->assertEquals('Johnson', $oNew['name']);
        $this->assertEquals($oNew->getCollectionName(), $this->COLLNAME);
    }

}
