<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Bundles plugin for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2019 webdna
 */

namespace webdna\commerce\bundles\variables;

use webdna\commerce\bundles\Bundles;
use webdna\commerce\bundles\elements\Bundle;
use webdna\commerce\bundles\elements\db\BundleQuery;

use Craft;
use yii\base\Behavior;

/**
 * @author    webdna
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
    public $commerceBundles;

    public function init(): void
    {
        parent::init();

        // Point `craft.commerceBundles` to the webdna\bundles\Bundles instance
        $this->commerceBundles = Bundles::$plugin;
    }

    /**
     * Returns a new CreditQuery instance.
     *
     * @param mixed $criteria
     * @return BundleQuery
     */
    public function bundles(mixed $criteria = null): BundleQuery
    {
        $query = Bundle::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
    }

}
