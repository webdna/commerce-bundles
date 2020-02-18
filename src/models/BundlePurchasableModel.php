<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Bundles plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\bundles\models;

use kuriousagency\commerce\bundles\Bundles;

use Craft;
use craft\base\Model;

/**
 * @author    Kurious Agency
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
    public $id;
    public $bundleId;
	public $purchasableId;
	public $purchasableType;
    public $qty;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
     /**
     * @return array
     */
    public function rules()
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

?>