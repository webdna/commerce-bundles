<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Bundles plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\bundles\elements;

use kuriousagency\commerce\bundles\Bundles;
use kuriousagency\commerce\bundles\elements\db\BundleQuery;
use kuriousagency\commerce\bundles\events\CustomizeBundleSnapshotDataEvent;
use kuriousagency\commerce\bundles\events\CustomizeBundleSnapshotFieldsEvent;
use kuriousagency\commerce\bundles\events\CompleteBundleOrderEvent;
use kuriousagency\commerce\bundles\models\BundleTypeModel;
use kuriousagency\commerce\bundles\records\BundleRecord;
use kuriousagency\commerce\bundles\records\BundlePurchasableRecord;

use Craft;
use craft\elements\db\ElementQueryInterface;
use craft\db\Query;
use craft\elements\actions\Delete;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\validators\DateTimeValidator;

use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\TaxCategory;
use craft\commerce\models\ShippingCategory;
use craft\commerce\Plugin as Commerce;

use craft\digitalproducts\Plugin as DigitalProducts;

use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\Expression;

/**
 * @author    Kurious Agency
 * @package   Bundles
 * @since     1.0.0
 */
class Bundle extends Purchasable
{
	
	 // Constants
    // =========================================================================

    const STATUS_LIVE = 'live';
    const STATUS_PENDING = 'pending';
	const STATUS_EXPIRED = 'expired';	
	
	const EVENT_BEFORE_CAPTURE_BUNDLE_SNAPSHOT = 'beforeCaptureBundleSnapshot';
	const EVENT_AFTER_CAPTURE_BUNDLE_SNAPSHOT = 'afterCaptureBundleSnapshot';
	
	const EVENT_AFTER_COMPLETE_BUNDLE_ORDER = 'afterCompleteBundleOrder';
	
	
	// Public Properties
    // =========================================================================

    public $id;
    public $typeId;
    public $taxCategoryId;
    public $shippingCategoryId;
    public $postDate;
    public $expiryDate;
    public $sku;
	public $price;

    private $_bundleType;
	//private $_products;
	private $_purchasables;
	private $_purchasableIds;
    private $_qtys;

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce-bundles', 'Bundle');
	}
	
	public function __toString(): string
    {
        return (string)$this->title;
    }

    public function getName()
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
	}
	
	public static function hasUris(): bool
    {
        return true;
	}
	
	public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }  

	public static function defineSources(string $context = null): array
    {
        if ($context === 'index') {
            $bundleTypes = Bundles::$plugin->bundleTypes->getEditableBundleTypes();
            $editable = true;
        } else {
            $bundleTypes = Bundles::$plugin->bundleTypes->getAllBundleTypes();
            $editable = false;
        }

        $bundleTypeIds = [];

        foreach ($bundleTypes as $bundleType) {
            $bundleTypeIds[] = $bundleType->id;
        }

        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('commerce-bundles', 'All bundles'),
                'criteria' => [
                    'typeId' => $bundleTypeIds,
                    'editable' => $editable
                ],
                'defaultSort' => ['postDate', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('commerce-bundles', 'Bundle Types')];

        foreach ($bundleTypes as $bundleType) {
            $key = 'bundleType:'.$bundleType->id;
            $canEditBundles = Craft::$app->getUser()->checkPermission('commerce-bundles-manageBundleType:' . $bundleType->id);

            $sources[$key] = [
                'key' => $key,
                'label' => $bundleType->name,
                'data' => [
                    'handle' => $bundleType->handle,
                    'editable' => $canEditBundles
                ],
                'criteria' => ['typeId' => $bundleType->id, 'editable' => $editable]
            ];
        }

        return $sources;
	}
	
	protected static function defineActions(string $source = null): array
    {
        $actions = [];

        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('commerce-bundles', 'Are you sure you want to delete the selected bundles?'),
            'successMessage' => Craft::t('commerce-bundles', 'Bundles deleted.'),
        ]);

        return $actions;
	}
	
	public function getStatuses(): array
    {
        return [
            self::STATUS_LIVE => Craft::t('commerce-bundles', 'Live'),
            self::STATUS_PENDING => Craft::t('commerce-bundles', 'Pending'),
            self::STATUS_EXPIRED => Craft::t('commerce-bundles', 'Expired'),
            self::STATUS_DISABLED => Craft::t('commerce-bundles', 'Disabled')
        ];
	}
	
	// public function getEditorHtml(): string
    // {
    //     $viewService = Craft::$app->getView();
    //     $html = $viewService->renderTemplateMacro('bundles/_fields', 'titleField', [$this]);
    //     $html .= parent::getEditorHtml();
    //     $html .= $viewService->renderTemplateMacro('bundles/_fields', 'generalFields', [$this]);
    //     $html .= $viewService->renderTemplateMacro('bundles/_fields', 'pricingFields', [$this]);
    //     $html .= $viewService->renderTemplateMacro('bundles/_fields', 'behavioralMetaFields', [$this]);
    //     $html .= $viewService->renderTemplateMacro('bundles/_fields', 'generalMetaFields', [$this]);

    //     return $html;
	// }

	public function getIsAvailable(): bool
    {
        return $this->getStatus() === static::STATUS_LIVE;
	}
	
	public function getStatus()
    {
        $status = parent::getStatus();

        if ($status === self::STATUS_ENABLED && $this->postDate) {
            $currentTime = DateTimeHelper::currentTimeStamp();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = $this->expiryDate ? $this->expiryDate->getTimestamp() : null;

            if ($postDate <= $currentTime && (!$expiryDate || $expiryDate > $currentTime)) {
                return self::STATUS_LIVE;
            }

            if ($postDate > $currentTime) {
                return self::STATUS_PENDING;
            }

            return self::STATUS_EXPIRED;
        }

        return $status;
	}
	
	public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['typeId', 'sku', 'price','purchasableIds','qtys'], 'required'];
        $rules[] = [['sku'], 'string'];
        $rules[] = [['postDate', 'expiryDate'], DateTimeValidator::class];

        return $rules;
	}
	
	public static function find(): ElementQueryInterface
    {
        return new BundleQuery(static::class);
	}
	
	public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';

        return $attributes;
    }

    public function getIsEditable(): bool
    {
        if ($this->getType()) {
            $id = $this->getType()->id;

            return Craft::$app->getUser()->checkPermission('commerce-bundles-manageBundleType:' . $id);
        }

        return false;
	}
	
	public function getCpEditUrl()
    {
        $bundleType = $this->getType();

        if ($bundleType) {
            return UrlHelper::cpUrl('commerce-bundles/bundles/' . $bundleType->handle . '/' . $this->id);
        }

        return null;
	}
	
	public function getProduct()
    {
        return $this;
    }

    public function getFieldLayout()
    {
        $bundleType = $this->getType();

        return $bundleType ? $bundleType->getBundleFieldLayout() : null;
	}

	public function getUriFormat()
    {
        $bundleTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($bundleTypeSiteSettings[$this->siteId])) {
            throw new InvalidConfigException('Bundle’s type (' . $this->getType()->id . ') is not enabled for site ' . $this->siteId);
        }

        return $bundleTypeSiteSettings[$this->siteId]->uriFormat;
    }
	
	public function getType()
    {
        if ($this->_bundleType) {
            return $this->_bundleType;
        }

        return $this->typeId ? $this->_bundleType = Bundles::$plugin->bundleTypes->getBundleTypeById($this->typeId) : null;
	}
	
	public function getTaxCategory()
    {
        if ($this->taxCategoryId) {
            return Commerce::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
        }

        return null;
	}
	
	public function getShippingCategory()
    {
        if ($this->shippingCategoryId) {
            return Commerce::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
        }

        return null;
    }

    // Events
    // -------------------------------------------------------------------------

	public function beforeSave(bool $isNew): bool
    {
        if ($this->enabled && !$this->postDate) {
            // Default the post date to the current date/time
            $this->postDate = DateTimeHelper::currentUTCDateTime();
        }

        return parent::beforeSave($isNew);
    }

	public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $bundleRecord = BundleRecord::findOne($this->id);

            if (!$bundleRecord) {
                throw new Exception('Invalid bundle id: '.$this->id);
            }
        } else {
            $bundleRecord = new BundleRecord();
            $bundleRecord->id = $this->id;
        }
        
        $bundleRecord->postDate = $this->postDate;
        $bundleRecord->expiryDate = $this->expiryDate;
        $bundleRecord->typeId = $this->typeId;
        // $bundleRecord->promotable = $this->promotable;
        $bundleRecord->taxCategoryId = $this->taxCategoryId;
        $bundleRecord->shippingCategoryId = $this->shippingCategoryId;
        $bundleRecord->price = $this->price;

        // Generate SKU if empty
        if (empty($this->sku)) {
            try {
                $bundleType = Bundles::$plugin->bundleTypes->getBundleTypeById($this->typeId);
                $this->sku = Craft::$app->getView()->renderObjectTemplate($bundleType->skuFormat, $this);
            } catch (\Exception $e) {
                $this->sku = '';
            }
        }

        $bundleRecord->sku = $this->sku;

        $bundleRecord->save(false);

        return parent::afterSave($isNew);
	}
	
	    // Implement Purchasable
    // =========================================================================

    public function getPurchasableId(): int
    {
        return $this->id;
    }
    
    public function getSnapshot(): array
    {
        $data = [];

        $data['type'] = self::class;

        // Default Bundle custom field handles
        $bundleFields = [];
        $bundleFieldsEvent = new CustomizeBundleSnapshotFieldsEvent([
            'bundle' => $this,
            'fields' => $bundleFields,
        ]);

        // Allow plugins to modify fields to be fetched
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_BUNDLE_SNAPSHOT)) {
            $this->trigger(self::EVENT_BEFORE_CAPTURE_BUNDLE_SNAPSHOT, $bundleFieldsEvent);
        }

        // Capture specified Bundle field data
        $bundleFieldData = $this->getSerializedFieldValues($bundleFieldsEvent->fields);
        $bundleDataEvent = new CustomizeBundleSnapshotDataEvent([
            'bundle' => $this,
            'fieldData' => $bundleFieldData,
        ]);

        // Allow plugins to modify captured Bundle data
        if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_BUNDLE_SNAPSHOT)) {
            $this->trigger(self::EVENT_AFTER_CAPTURE_BUNDLE_SNAPSHOT, $bundleDataEvent);
        }

		$data['fields'] = $bundleDataEvent->fieldData;

		//$data['productId'] = $this->id;

        return array_merge($this->getAttributes(), $data);
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getDescription(): string
    {
        return $this->title;
    }

    public function getTaxCategoryId(): int
    {
        return $this->taxCategoryId;
    }

    public function getShippingCategoryId(): int
    {
        return $this->shippingCategoryId;
    }

    public function hasFreeShipping(): bool
    {
        return true;
    }

    public function getIsPromotable(): bool
    {
        return true;
	}

    public function hasStock(): bool
    {
		return $this->getStock() > 0;
	}
	
	public function getStock()
	{
		$stock = [];

		$qtys = $this->getQtys();
		
		foreach($this->getPurchasables() as $purchasable) {	
			$qty = $qtys[$purchasable->id];
			
			if (method_exists($purchasable, 'availableQuantity')) {
				$stock[] = floor($purchasable->availableQuantity() / $qty);
			}elseif (property_exists($purchasable, 'stock')) {
				$stock[] = $purchasable->hasUnlimitedStock ? PHP_INT_MAX : floor($purchasable->stock / $qty);
			}else {
				// assume not stock or quantity means unlimited
				$stock[] = PHP_INT_MAX;
			}
		}
		if (count($stock)) {
			return min($stock);
		}

		return 0;
	}

	public function getProducts()
	{
		Craft::$app->getDeprecator()->log('Bundle::getProducts()', 'Bundle::getProducts() has been deprecated. Use Bundle::getPurchasables() instead');

		return $this->getPurchasables();
	}
	
	public function getPurchasables() {
		if (null === $this->_purchasables) {
			foreach ($this->getPurchasableIds() as $id) {
				$this->_purchasables[] = Craft::$app->getElements()->getElementById($id);
			}
		}

		return $this->_purchasables;
	}

	public function getPurchasableIds(): array
    {
        if (null === $this->_purchasableIds) {

			$purchasableIds = [];

			foreach ($this->_getBundlePurchasables() as $row) {
				$purchasableIds[] = $row['purchasableId'];
			}

			$this->_purchasableIds = $purchasableIds;
        }

        return $this->_purchasableIds;
	}
	
	public function setPurchasableIds(array $purchasableIds)
    {
        $this->_purchasableIds = array_unique($purchasableIds);
    }

	public function getQtys()
	{
		if (null === $this->_qtys) {

			$this->_qtys = [];

			foreach($this->_getBundlePurchasables() as $row) {
				$this->_qtys[$row['purchasableId']] = $row['qty'];
			}
		}

		return $this->_qtys;
	}

	public function setQtys($qtys)
	{		
		$this->_qtys = is_array($qtys) ? $qtys : [];
	}


	public function populateLineItem(LineItem $lineItem)
    {
        $errors = [];
        if ($lineItem->purchasable === $this) {

			//$bundleStock = Bundles::$plugin->bundles->getBundleStock($lineItem->purchasable->id);
			$stock = $this->stock;

			//if($bundleStock != "unlimited") {
				if ($lineItem->qty > $stock) {
					$lineItem->qty = $stock;
					$errors[] = 'You reached the maximum stock of ' . $lineItem->purchasable->getDescription();
				}
			//}
        }
        if ($errors) {
            $cart = Commerce::getInstance()->getCarts()->getCart();
            $cart->addErrors($errors);
            Craft::$app->getSession()->setError(implode(',', $errors));
        }
    }
	
	 /**
     * Updates Stock count from completed order.
     *
     * @inheritdoc
     */
    public function afterOrderComplete(Order $order, LineItem $lineItem)
    {
		$qtys = $this->getQtys();

		foreach($this->getPurchasables() as $purchasable) {

			$item = new LineItem();
			$item->id = $lineItem->id;
			$item->qty = $lineItem->qty * $qtys[$purchasable->id];
			$purchasable->afterOrderComplete($order, $item);

			//handle digital products
			if ($purchasable instanceof \craft\digitalproducts\elements\Product) {
				for ($i = 0; $i < $item->qty; $i++) {
                    DigitalProducts::getInstance()->getLicenses()->licenseProductByOrder($purchasable, $order);
                }
			}

			$event = new CompleteBundleOrderEvent([
				'bundle' => $this,
				'order' => $order,
				'lineItem' => $item,
			]);
			
			// Allow plugins to handle a bundle purchasable
			if ($this->hasEventHandlers(self::EVENT_AFTER_COMPLETE_BUNDLE_ORDER)) {
				$this->trigger(self::EVENT_AFTER_COMPLETE_BUNDLE_ORDER, $event);
			}
		}
	}
	
	// Protected methods
    // =========================================================================

    protected function route()
    {
        // Make sure the bundle type is set to have URLs for this site
        $siteId = Craft::$app->getSites()->currentSite->id;
        $bundleTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($bundleTypeSiteSettings[$siteId]) || !$bundleTypeSiteSettings[$siteId]->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $bundleTypeSiteSettings[$siteId]->template,
                'variables' => [
                    'bundle' => $this,
                ]
            ]
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('commerce-bundles', 'Title')],
            'type' => ['label' => Craft::t('commerce-bundles', 'Type')],
            'slug' => ['label' => Craft::t('commerce-bundles', 'Slug')],
            'sku' => ['label' => Craft::t('commerce-bundles', 'SKU')],
            'price' => ['label' => Craft::t('commerce-bundles', 'Price')],
            'postDate' => ['label' => Craft::t('commerce-bundles', 'Post Date')],
            'expiryDate' => ['label' => Craft::t('commerce-bundles', 'Expiry Date')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        if ($source === '*') {
            $attributes[] = 'type';
        }

        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';

        return $attributes;
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['title'];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        /* @var $bundleType bundleType */
        $bundleType = $this->getType();

        switch ($attribute) {
            case 'type':
                return ($bundleType ? Craft::t('site', $bundleType->name) : '');

            case 'taxCategory':
                $taxCategory = $this->getTaxCategory();

                return ($taxCategory ? Craft::t('site', $taxCategory->name) : '');

            case 'shippingCategory':
                $shippingCategory = $this->getShippingCategory();

                return ($shippingCategory ? Craft::t('site', $shippingCategory->name) : '');

            case 'defaultPrice':
                $code = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

                return Craft::$app->getLocale()->getFormatter()->asCurrency($this->$attribute, strtoupper($code));

            case 'promotable':
                return ($this->$attribute ? '<span data-icon="check" title="'.Craft::t('commerce-bundles', 'Yes').'"></span>' : '');

            default:
                return parent::tableAttributeHtml($attribute);
        }
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('commerce-bundles', 'Title'),
            'postDate' => Craft::t('commerce-bundles', 'Post Date'),
            'expiryDate' => Craft::t('commerce-bundles', 'Expiry Date'),
            'price' => Craft::t('commerce-bundles', 'Price'),
        ];
	}

	private function _getBundlePurchasables()
	{
		return $purchasables = BundlePurchasableRecord::find()
			->where(['bundleId' => $this->id])
			->all();
	}

    // /**
    //  * @inheritdoc
    //  */
    // public function beforeDelete(): bool
    // {
    //     return true;
    // }

    // /**
    //  * @inheritdoc
    //  */
    // public function afterDelete()
    // {
    // }

    // /**
    //  * @inheritdoc
    //  */
    // public function beforeMoveInStructure(int $structureId): bool
    // {
    //     return true;
    // }

    // /**
    //  * @inheritdoc
    //  */
    // public function afterMoveInStructure(int $structureId)
    // {
    // }
}
