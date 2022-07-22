<?php
/**
 * Bundles plugin for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bundles\assetbundles\bundles;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author   webdna
 * @package   Bundles
 * @since     2.0.0
 */
class BundlesAsset extends AssetBundle
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		$this->sourcePath =
			"@webdna/commerce/bundles/assetbundles/bundles/dist";

		$this->depends = [CpAsset::class];

		$this->js = ['js/Bundles.js'];

		$this->css = ['css/Bundles.css'];

		parent::init();
	}
}
