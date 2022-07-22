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
class BundlesModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $someAttribute = 'Some Default';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules() : array
    {
        return [
            ['someAttribute', 'string'],
            ['someAttribute', 'default', 'value' => 'Some Default'],
        ];
    }
}
