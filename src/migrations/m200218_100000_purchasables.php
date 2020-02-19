<?php

namespace kuriousagency\commerce\bundles\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m200218_100000_purchasables migration.
 */
class m200218_100000_purchasables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
		if (!$this->db->tableExists('{{%bundles_purchasables}}')) {
			MigrationHelper::renameTable('{{%bundles_products}}', '{{%bundles_purchasables}}', $this);
		}

		if (!$this->db->columnExists('{{%bundles_purchasables}}', 'purchasableType')) {
			$this->addColumn('{{%bundles_purchasables}}', 'purchasableType', $this->string()->notNull());
		}
		
		MigrationHelper::dropForeignKeyIfExists('{{%bundles_purchasables}}', ['purchasableId'], $this);
		$this->addForeignKey($this->db->getForeignKeyName('{{%bundles_purchasables}}', 'purchasableId'), '{{%bundles_purchasables}}', 'purchasableId', '{{%commerce_purchasables}}', 'id', 'CASCADE', null);

		return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        return true;
    }
}
