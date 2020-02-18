<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Bundles plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\bundles\migrations;

use kuriousagency\commerce\bundles\Bundles;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * @author    Kurious Agency
 * @package   Bundles
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            // $this->insertDefaultData();
        }

        return true;
    }

   /**
     * @inheritdoc
     */
    public function safeDown()
    {
		$this->driver = Craft::$app->getConfig()->getDb()->driver;
		$this->dropForeignKeys();
        $this->dropTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%bundles_bundles}}');
        if ($tableSchema === null) {
			$tablesCreated = true;
			
            $this->createTable('{{%bundles_bundles}}', [
				'id' => $this->primaryKey(),
				'typeId' => $this->integer(),
				'taxCategoryId' => $this->integer()->notNull(),
				'shippingCategoryId' => $this->integer()->notNull(),
				'postDate' => $this->dateTime(),
				'expiryDate' => $this->dateTime(),
				'sku' => $this->string()->notNull(),
				'price' => $this->decimal(12, 2)->notNull(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);

			$this->createTable('{{%bundles_purchasables}}', [
				'id' => $this->primaryKey(),
				'bundleId' => $this->integer()->notNull(),
				'purchasableId' => $this->integer()->notNull(),
				'purchasableType' => $this->string()->notNull(),
				'qty' => $this->integer(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);

			$this->createTable('{{%bundles_bundletypes}}', [
				'id' => $this->primaryKey(),
				'fieldLayoutId' => $this->integer(),
				'name' => $this->string()->notNull(),
				'handle' => $this->string()->notNull(),
				'skuFormat' => $this->string(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);
	
			$this->createTable('{{%bundles_bundletypes_sites}}', [
				'id' => $this->primaryKey(),
				'bundleTypeId' => $this->integer()->notNull(),
				'siteId' => $this->integer()->notNull(),
				'uriFormat' => $this->text(),
				'template' => $this->string(500),
				'hasUrls' => $this->boolean(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);
			
			


        }

        return $tablesCreated;
    }

    /**
     * @return void
     */
    protected function createIndexes()
    {
		$this->createIndex($this->db->getIndexName('{{%bundles_bundles}}', 'sku', true), '{{%bundles_bundles}}', 'sku', true);
        $this->createIndex($this->db->getIndexName('{{%bundles_bundles}}', 'typeId', false), '{{%bundles_bundles}}', 'typeId', false);
        $this->createIndex($this->db->getIndexName('{{%bundles_bundles}}', 'taxCategoryId', false), '{{%bundles_bundles}}', 'taxCategoryId', false);
		$this->createIndex($this->db->getIndexName('{{%bundles_bundles}}', 'shippingCategoryId', false), '{{%bundles_bundles}}', 'shippingCategoryId', false);

		$this->createIndex($this->db->getIndexName('{{%bundles_purchasables}}', 'bundleId', true), '{{%bundles_purchasables}}', 'bundleId', false);
		$this->createIndex($this->db->getIndexName('{{%bundles_purchasables}}', 'purchasableId', true), '{{%bundles_purchasables}}', 'purchasableId', false);

		$this->createIndex($this->db->getIndexName('{{%bundles_bundletypes}}', 'handle', true), '{{%bundles_bundletypes}}', 'handle', true);
        $this->createIndex($this->db->getIndexName('{{%bundles_bundletypes}}', 'fieldLayoutId', false), '{{%bundles_bundletypes}}', 'fieldLayoutId', false);

		$this->createIndex($this->db->getIndexName('{{%bundles_bundletypes_sites}}', ['bundleTypeId', 'siteId'], true), '{{%bundles_bundletypes_sites}}', ['bundleTypeId', 'siteId'], true);
        $this->createIndex($this->db->getIndexName('{{%bundles_bundletypes_sites}}', 'siteId', false), '{{%bundles_bundletypes_sites}}', 'siteId', false);
    }

    /**
     * @return void
     */
    protected function addForeignKeys()
    {
		$this->addForeignKey($this->db->getForeignKeyName('{{%bundles_bundles}}', 'id'), '{{%bundles_bundles}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%bundles_bundles}}', 'shippingCategoryId'), '{{%bundles_bundles}}', 'shippingCategoryId', '{{%commerce_shippingcategories}}', 'id', null, null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%bundles_bundles}}', 'taxCategoryId'), '{{%bundles_bundles}}', 'taxCategoryId', '{{%commerce_taxcategories}}', 'id', null, null);
		$this->addForeignKey($this->db->getForeignKeyName('{{%bundles_bundles}}', 'typeId'), '{{%bundles_bundles}}', 'typeId', '{{%bundles_bundletypes}}', 'id', 'CASCADE', null);

		$this->addForeignKey($this->db->getForeignKeyName('{{%bundles_purchasables}}', 'bundleId'), '{{%bundles_purchasables}}', 'bundleId', '{{%bundles_bundles}}', 'id', 'CASCADE', null);
		$this->addForeignKey($this->db->getForeignKeyName('{{%bundles_purchasables}}', 'purchasableId'), '{{%bundles_purchasables}}', 'purchasableId', '{{%commerce_purchasables}}', 'id', 'CASCADE', null);
        
        $this->addForeignKey($this->db->getForeignKeyName('{{%bundles_bundletypes}}', 'fieldLayoutId'), '{{%bundles_bundletypes}}', 'fieldLayoutId', '{{%fieldlayouts}}', 'id', 'SET NULL', null);
    
        $this->addForeignKey($this->db->getForeignKeyName('{{%bundles_bundletypes_sites}}', 'siteId'), '{{%bundles_bundletypes_sites}}', 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%bundles_bundletypes_sites}}', 'bundleTypeId'), '{{%bundles_bundletypes_sites}}', 'bundleTypeId', '{{%bundles_bundletypes}}', 'id', 'CASCADE', null);
    }

    // /**
    //  * @return void
    //  */
    // protected function insertDefaultData()
    // {
		
		
    // }

    /**
     * @return void
     */
    protected function dropTables()
    {
        $this->dropTableIfExists('{{%bundles_bundles}}');
        $this->dropTableIfExists('{{%bundles_purchasables}}');
        $this->dropTableIfExists('{{%bundles_bundletypes}}');
        $this->dropTableIfExists('{{%bundles_bundletypes_sites}}');
	}
	
	protected function dropForeignKeys()
    {
		MigrationHelper::dropAllForeignKeysOnTable('{{%bundles_bundles}}', $this);
		MigrationHelper::dropAllForeignKeysOnTable('{{%bundles_purchasables}}', $this);
		MigrationHelper::dropAllForeignKeysOnTable('{{%bundles_bundletypes}}', $this);
		MigrationHelper::dropAllForeignKeysOnTable('{{%bundles_bundletypes_sites}}', $this);
	}
}
