<?php
/**
 * Bundles plugin for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bundles\assetbundles\indexcpsection;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author   webdna
 * @package   Bundles
 * @since     2.0.0
 */
class IndexCPSectionAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init() : void
    {
        $this->sourcePath = "@webdna/commerce-bundles/assetbundles/indexcpsection/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Index.js',
        ];

        $this->css = [
            'css/Index.css',
        ];

        parent::init();
    }
}
