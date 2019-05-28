<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Bundles plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\bundles\records;

use kuriousagency\commerce\bundles\Bundles;

use craft\db\ActiveRecord;
use craft\records\Element;

use craft\commerce\records\TaxCategory;
use craft\commerce\records\ShippingCategory;

use yii\db\ActiveQueryInterface;

/**
 * @author    Kurious Agency
 * @package   Bundles
 * @since     1.0.0
 */
class BundleRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bundles_bundles}}';
    }

    public function getType(): ActiveQueryInterface
    {
        return $this->hasOne(BundleTypeRecord::class, ['id' => 'typeId']);
    }

    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id' => 'taxCategoryId']);
    }

    public function getShippingCategory(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingCategory::class, ['id' => 'shippingCategoryId']);
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}
