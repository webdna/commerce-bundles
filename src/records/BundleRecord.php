<?php
/**
 * Bundles plugin for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bundles\records;

use webdna\commerce\bundles\Bundles;


use craft\db\ActiveRecord;
use craft\records\Element;

use craft\commerce\records\TaxCategory;
use craft\commerce\records\ShippingCategory;

use yii\db\ActiveQueryInterface;

/**
 * @author   webdna
 * @package   Bundles
 * @since     2.0.0
 *
 * @property-read ActiveQueryInterface $shippingCategory
 * @property-read ActiveQueryInterface $type
 * @property-read ActiveQueryInterface $element
 * @property-read ActiveQueryInterface $taxCategory
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
