<?php
/**
 * Bundles plugin for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bundles\services;

use webdna\commerce\bundles\Bundles;
use webdna\commerce\bundles\elements\Bundle;

use Craft;
use craft\base\Component;
use craft\events\SiteEvent;
use craft\queue\jobs\ResaveElements;
use craft\db\Query;

use craft\commerce\Plugin as Commerce;
use craft\commerce\models\LineItem;

/**
 * @author   webdna
 * @package   Bundles
 * @since     2.0.0
 */
class BundlesService extends Component
{


    public function getBundleById(int $id, $siteId = null): ?Bundle
    {
        return Craft::$app->getElements()->getElementById($id, Bundle::class, $siteId);
    }

    public function afterSaveSiteHandler(SiteEvent $event) : void
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
                    'status' => null,
                    'enabledForSite' => false
                ]
            ]));
        }
    }

    /*public function getBundleStock($bundleId)
    {

        $stock = [];

        $rows = $this->_createBundleProductsQuery()
            ->where(['bundleId' => $bundleId])
            ->all();

        foreach($rows as $row) {
            $purchasable = Commerce::getInstance()->getVariants()->getVariantById($row['purchasableId']);
            $stock[] = $purchasable->hasUnlimitedStock ? 'unlimited' : floor($purchasable->stock/$row['qty']);
        }

        if (array_unique($stock) === array('unlimited')) {
            return "unlimited";
        }

        $stockValueArray = array_diff($stock,["unlimited"]);
        $minStock = min($stockValueArray);

        return $minStock;

    }*/

    public function isBundle(LineItem $lineItem): bool
    {
        return (bool)(get_class($lineItem->purchasable) === Bundle::class);
    }

	private function _createBundleProductsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'bundleId',
                'purchasableId',
                'purchasableType',
                'qty',
            ])
            ->from(['{{%bundles_purchasables}}']);
    }

}
