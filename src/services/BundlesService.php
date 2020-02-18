<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Bundles plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\bundles\services;

use kuriousagency\commerce\bundles\Bundles;
use kuriousagency\commerce\bundles\elements\Bundle;

use Craft;
use craft\base\Component;
use craft\events\SiteEvent;
use craft\queue\jobs\ResaveElements;
use craft\db\Query;

use craft\commerce\Plugin as Commerce;

/**
 * @author    Kurious Agency
 * @package   Bundles
 * @since     1.0.0
 */
class BundlesService extends Component
{
    // Public Methods
	// =========================================================================
	
	public function getBundleById(int $id, $siteId = null)
    {
        return Craft::$app->getElements()->getElementById($id, Bundle::class, $siteId);
    }

	public function afterSaveSiteHandler(SiteEvent $event)
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
