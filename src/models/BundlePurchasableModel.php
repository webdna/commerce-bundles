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
    public ?int $bundleId = null;
	public ?int $purchasableId = null;
	public ?string $purchasableType = null;
    public ?int $qty = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
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
