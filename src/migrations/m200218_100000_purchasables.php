<?php

namespace webdna\commerce\bundles\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;

/**
 * m200218_100000_purchasables migration.
 */
class m200218_100000_purchasables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%bundles_purchasables}}')) {
            Db::renameTable('{{%bundles_products}}', '{{%bundles_purchasables}}');
        }

        if (!$this->db->columnExists('{{%bundles_purchasables}}', 'purchasableType')) {
            $this->addColumn('{{%bundles_purchasables}}', 'purchasableType', $this->string()->notNull());
        }

        Db::dropForeignKeyIfExists('{{%bundles_purchasables}}', ['purchasableId']);
        $this->addForeignKey($this->db->getForeignKeyName('{{%bundles_purchasables}}', 'purchasableId'), '{{%bundles_purchasables}}', 'purchasableId', '{{%commerce_purchasables}}', 'id', 'CASCADE', null);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        return true;
    }
}
