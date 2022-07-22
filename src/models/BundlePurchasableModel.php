<?php
/**
 * Bundles plugin for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bundles\models;

use webdna\commerce\bundles\Bundles;

use Craft;
use craft\base\Model;

/**
 * @author   webdna
 * @package   Bundles
 * @since     2.0.0
 */
class BundlePurchasableModel extends Model
{

    /**
     * @var int|null ID
     */
    public ?int $id = null;
    public ?int $bundleId;
	public ?int $purchasableId;
	public ?string $purchasableType;
    public ?int $qty;

    // Public Methods
    // =========================================================================

    /**
     * @return array
     */
    public function rules() : array
    {
        $rules = [
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
