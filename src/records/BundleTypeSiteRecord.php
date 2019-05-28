<?php
namespace kuriousagency\commerce\bundles\records;

use craft\db\ActiveRecord;
use craft\records\Site;

use yii\db\ActiveQueryInterface;

class BundleTypeSiteRecord extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%bundles_bundletypes_sites}}';
    }

    public function getBundleType(): ActiveQueryInterface
    {
        return $this->hasOne(BundleTypeRecord::class, ['id', 'bundleTypeId']);
    }

    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id', 'siteId']);
    }
}
