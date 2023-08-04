<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Bundles plugin for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2019 webdna
 */

namespace webdna\commerce\bundles\services;

use webdna\commerce\bundles\Bundles;
use webdna\commerce\bundles\elements\Bundle;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\events\SiteEvent;
use craft\queue\jobs\ResaveElements;
use craft\db\Query;

use craft\commerce\Plugin as Commerce;
use craft\commerce\models\LineItem;

/**
 * @author    webdna
 * @package   Bundles
 * @since     1.0.0
 */
class BundlesService extends Component
{
    // Public Methods
    // =========================================================================

    public function getBundleById(int $id, $siteId = null): ?ElementInterface
    {
        return Craft::$app->getElements()->getElementById($id, Bundle::class, $siteId);
    }

    public function afterSaveSiteHandler(SiteEvent $event): void
    {
        $queue = Craft::$app->getQueue();
        $siteId = $event->oldPrimarySiteId;
        $elementTypes = [
            Bundle::class,
        ];

        foreach ($elementTypes as $elementType) {
            $queue->push(new ResaveElements([
                'elementType' => $elementType,
                'criteria' => [
                    'siteId' => $siteId,
                    'status' => null
                ]
            ]));
        }
    }

    public function isBundle(LineItem $lineItem): bool
    {
        return (bool)(get_class($lineItem->purchasable) === Bundle::class);
    }

}
