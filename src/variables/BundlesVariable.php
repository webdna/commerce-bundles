<?php
/**
 * Bundles plugin for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bundles\variables;

use craft\elements\db\ElementQueryInterface;
use webdna\commerce\bundles\Bundles;
use webdna\commerce\bundles\elements\Bundle;
use webdna\commerce\bundles\elements\db\BundleQuery;

use Craft;
use yii\base\Behavior;

/**
 * @author   webdna
 * @package   Bundles
 * @since     2.0.0
 */
class BundlesVariable extends Behavior
{
    // Public Methods
    // =========================================================================


	 /**
     * @var Plugin
     */
	public $commerceBundles;

    public function init() : void
    {
        parent::init();

        // Point `craft.commerceBundles` to the webdna\bundles\Bundles instance
		$this->commerceBundles = Bundles::$plugin;

	}

	/**
     * Returns a new CreditQuery instance.
     *
     * @param mixed|null $criteria
     * @return ElementQueryInterface
     */
    public function bundles(mixed $criteria = null): ElementQueryInterface
    {
        $query = Bundle::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
	}



}
