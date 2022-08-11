<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Bundles plugin for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2019 webdna
 */

namespace webdna\commerce\bundles\models;

use webdna\commerce\bundles\Bundles;

use Craft;
use craft\base\Model;

/**
 * @author    webdna
 * @package   Bundles
 * @since     1.0.0
 */
class BundlePurchasableModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public int $id;
    public int $bundleId;
    public int $purchasableId;
    public string $purchasableType;
    public int $qty;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [
                [
                    'bundleId',
                    'purchasableId',
                    'purchasableType',
                ], 'required'
            ],
            [['qty'], 'integer', 'min' => 1],
        ];

    }
}

?>
