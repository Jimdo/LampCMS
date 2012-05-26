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


namespace Lampcms\Category;

/**
 * Class for saving Submitted category data
 * This data comes from the Category Editor page (or API)
 * The purpose of this class is to
 * insert new category or update data for existing one
 *
 * @author Dmitri Snytkine
 *
 */
class Editor
{
    /**
     * Name of collection that holds
     * categories data
     *
     * @var string
     */
    const COLLECTION = 'CATEGORY';

    protected $Registry;

    protected $aCategories = array();

    /**
     * Array of sub-categories
     * this array category id as key
     * and value is array of its sub-categories
     * order of items in array is important,
     * it is set in the order submitted from
     * the nested sortable editor
     *
     * @var array
     */
    protected $aSubs = array();

    protected $canEdit = true;

    public function __construct(\Lampcms\Registry $Registry)
    {
        $this->Registry = $Registry;
        /**
         * Need to check permission here for extra security
         * Check permission of Registry->Viewer to be able to edit_category
         */
        $role = $Registry->Viewer->getRoleId();

        if (!$this->Registry->Viewer->isAdmin() && !$this->Registry->Acl->isAllowed($role, null, 'edit_category')) {
            $this->canEdit = false;
        }

        $this->Registry->Cache->__unset('categories');
    }

    /**
     *
     * Save Category data in database
     * either as new category or update existing category
     *
     * @param Submitted $Category
     * @param bool $saveData if false then do not actually save into DB
     * Using this option makes possible to simulate saving data but not
     * really saving to DB. This is useful for making the demo of the category
     * editor
     *
     * @throws \Lampcms\Exception
     * @throws MongoException
     */
    public function saveCategory(Submitted $Category, $saveData = false)
    {

        $id = $Category->getId();
        $data = array(
            'title' => \filter_var(\trim($Category->getTitle()), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW),
            'slug' => \trim(\mb_strtolower(preg_replace('/[\s]+/', '_', $Category->getSlug()))),
            'desc' => \filter_var(\trim($Category->getDescription()), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW),
            'b_active' => (bool)$Category->isActive(),
            'b_catonly' => (bool)$Category->isCategory()
        );

        /**
         * If $saveData was not passed here
         * then don't actually do any "Saving" of data
         *
         */
        if (!$this->canEdit || !$saveData) {
            if (empty($id)) {
                $id = microtime(true);
            }
            $data['id'] = $id;

            return $data;
        }

        $Coll = $this->Registry->Mongo->{self::COLLECTION};
        /**
         * Need to ensure that slug is unique index
         * since url of category will be looked up by value of slug
         * filter_var($val, FILTER_SANITIZE_STRING); //, FILTER_FLAG_STRIP_LOW
         */
        $Coll->ensureIndex(array('slug' => 1), array('unique' => true));
        $Coll->ensureIndex(array('id' => 1), array('unique' => true));

        d('data: ' . print_r($data, 1));

        if (empty($id)) {
            $data['id'] = $id = $this->Registry->Incrementor->nextValue(self::COLLECTION);
            /**
             * Every newly added category
             * is assigned a parent id of 0, making
             * it a top level category by default
             * and the order of 1, making it appear at top
             * of categories list
             *
             */
            $data['i_parent'] = 0;
            $data['i_weight'] = 1;
            $data['i_qcount'] = 0;
            $data['i_acount'] = 0;
            $data['i_level'] = 0;
            $data['a_latest'] = null;
            $data['i_ts'] = 0;
            d('inserting category data: ' . print_r($data, 1));
            try {
                $Coll->insert($data, array('fsync' => true));
            } catch (\MongoException $e) {
                $code = $e->getCode();
                $err = $e->getMessage();
                d('MongoException caught Code: ' . $code . ' error: ' . $err);
                if ('11000' == $code || strstr($err, 'E11000')) {
                    throw new \Lampcms\Exception($this->Registry->Tr->get('Duplicate value of Category Url') . ' ' . $data['slug']);
                } else {
                    throw $e;
                }
            }
        } else {
            /**
             * Here must be very careful. Cannot just use update() on Collection
             * instead need to use $set operation, otherwise if Category
             * had a a_sub element, it will be lost because it was
             * not passed in the form. We must update only the values
             * that have been passed and not the entire record
             */
            $id = (int)$id;
            d('Updating category id: ' . $id);
            $Coll->update(array('id' => $id), array('$set' => $data), array('fsync' => true));

        }


        /**
         * Post event to categories
         * data is removed from cache
         */
        $this->Registry->Dispatcher->post($this, 'onCategoryUpdate', $data);

        $ret = $Coll->findOne(array('id' => $id),
            array(
                'id' => true,
                'title' => true,
                'desc' => true,
                'slug' => true,
                'b_active' => true,
                'b_catonly' => true,
                '_id' => false)
        );

        return $ret;
    }

    /**
     * Delete category by value of id
     * also update the a_sub of parent category and
     * remove the $id from a_sub array of parent
     * if this category has any parent category.
     *
     * @param int $id
     *
     * @return bool
     */
    public function delete($id)
    {
        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('Value of param $id must be numeric');
        }

        if (!$this->canEdit) {
            return;
        }

        $id = (int)$id;
        $coll = $this->Registry->Mongo->{self::COLLECTION};

        $a = $coll->findOne(array('id' => $id), array('i_parent'));
        d('cp');
        if ($a && !empty($a['i_parent'])) {
            d('cp');
            $coll->update(array('id' => $a['i_parent']), array('$pull' => array('a_sub' => $id)));
        }

        $ret = $coll->remove(array('id' => $id), array('fsync' => true));

        /**
         * Post event to categories
         * data is removed from cache
         */
        $this->Registry->Dispatcher->post($this, 'onCategoryUpdate', $data);

    }


    /**
     * Setup array of categories
     * They will be sorted by parent id (no parent id first)
     * and then by i_weight - lowest weight first
     * index them by id
     *
     * @return object $this
     */
    protected function setCategories()
    {
        $cur = $this->Registry->Mongo->{self::COLLECTION}->find(array())->sort(array('i_parent' => 1, 'i_weight' => -1));
        /**
         * Rekey the array so that array keys
         * are category id
         */
        foreach ($cur as $item) {
            $this->aCategories[$item['id']] = $item;
        }


        return $this;
    }

    /**
     * Save the order
     * and nesting order of categories
     * as they were submitted from
     * the nestedSortable
     *
     * @param array $categories
     */
    public function saveOrder(array $categories)
    {

        if (!$this->canEdit) {
            return;
        }

        $this->setCategories();
        $i = 1;
        foreach ($categories as $id => $parent_id) {
            /**
             * Very important to case
             * id and parent_id to integer
             * because Mongo is type sensitive
             * and expects ints for these values
             */
            $id = (int)$id;
            $parent_id = (int)$parent_id;

            /**
             * Extra sanity check to make
             * sure category with this id exists
             * It must exist, otherwise it could not be sorted
             */
            if (array_key_exists($id, $this->aCategories)) {

                $this->aCategories[$id]['i_level'] = $this->getLevel($id);
                /**
                 * Add value of 'i_weight' wich is the order
                 * in which the category was sorted by sortable
                 * The lower weight means higher up in the order
                 */
                $this->aCategories[$id]['i_weight'] = $i;
                /**
                 * If this category was sorted
                 * such that it has a parent, then
                 * add to $this->aSubs array for the parent category
                 * and also set the value of i_parent of this category
                 */
                if ('root' !== $parent_id && 0 !== $parent_id) {
                    $this->aCategories[$id]['i_parent'] = $parent_id;
                    if (!array_key_exists($parent_id, $this->aSubs)) {
                        $this->aSubs[$parent_id] = array();
                    }
                    $this->aSubs[$parent_id][] = $id;
                }
            }

            $i += 1;
        }

        /**
         * Now add a_subs to
         * their parents
         */
        if (!empty($this->aSubs)) {
            foreach ($this->aSubs as $id => $subs) {

                $this->aCategories[$id]['a_sub'] = $subs;
            }
        }

        d('Saving sorted categories: ' . print_r($this->aCategories, 1));
        $coll = $this->Registry->Mongo->{self::COLLECTION};
        $j = 0;
        foreach ($this->aCategories as $data) {

            $coll->save($data);
            $j += 1;
        }
        /**
         * Commit mongo data to disk
         */
        $this->Registry->Mongo->flush();

        /**
         * Post event to categories
         * data is removed from cache
         */
        $this->Registry->Dispatcher->post($this, 'onCategoryUpdate', $data);

        return $j;
    }

    /**
     * Get level of nesting of this category id
     * based on submitted $_POST['cat'] array
     *
     * @param int $id category id for which we getting result
     * @param int $prev used for recursion, do not pass anything here yourself!
     *
     * @return int level of nesting 0 means this category is top-level
     */
    protected function getLevel($id, $prev = 0)
    {
        $c = $_POST['cat'];

        if (empty($c[$id]) || 'root' == $c[$id]) {

            return (0 + $prev);
        }

        return $this->getLevel($c[$id], $prev + 1);

    }
}
