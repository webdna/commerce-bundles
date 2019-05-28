<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Bundles plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\bundles\variables;

use kuriousagency\commerce\bundles\Bundles;
use kuriousagency\commerce\bundles\elements\Bundle;
use kuriousagency\commerce\bundles\elements\db\BundleQuery;

use Craft;
use yii\base\Behavior;

/**
 * @author    Kurious Agency
 * @package   Bundles
 * @since     1.0.0
 */
class BundlesVariable extends Behavior
{
    // Public Methods
    // =========================================================================

	 
	 /**
     * @var Plugin
     */
	public $bundles;

    public function init()
    {
        parent::init();

        // Point `craft.bundles` to the kuriousagency\bundles\Bundles instance
		$this->bundles = Bundles::$plugin;

	}	
	
	/**
     * Returns a new CreditQuery instance.
     *
     * @param mixed $criteria
     * @return BundleQuery
     */
    public function bundleProducts($criteria = null): BundleQuery
    {
        $query = Bundle::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
	} 
	


}
