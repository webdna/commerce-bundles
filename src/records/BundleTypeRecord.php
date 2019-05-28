<?php
namespace kuriousagency\commerce\bundles\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;

use yii\db\ActiveQueryInterface;

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
