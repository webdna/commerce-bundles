<?php
/**
 * Bundles plugin for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bundles\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use yii\db\ActiveQueryInterface;

/**
 *
 * @property-read ActiveQueryInterface $fieldLayout
 */
class BundleTypeRecord extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%bundles_bundletypes}}';
    }

    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}
