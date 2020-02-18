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

use yii\db\ActiveQueryInterface;

/**
 * @author    Kurious Agency
 * @package   Bundles
 * @since     1.0.0
 */
class BundlePurchasableRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bundles_purchasables}}';
    }
}
