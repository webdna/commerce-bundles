<?php
namespace kuriousagency\commerce\bundles\services;

use kuriousagency\commerce\bundles\elements\Bundle;
// use kuriousagency\commerce\bundles\events\BundleTypeEvent;
use kuriousagency\commerce\bundles\models\BundleTypeModel;
use kuriousagency\commerce\bundles\models\BundleTypeSiteModel;
use kuriousagency\commerce\bundles\records\BundleTypeRecord;
use kuriousagency\commerce\bundles\records\BundleTypeSiteRecord;

use Craft;
use craft\db\Query;
use craft\events\SiteEvent;
use craft\helpers\App;
use craft\queue\jobs\ResaveElements;

use yii\base\Component;
use yii\base\Exception;

class BundleTypesService extends Component
{
    // Constants
    // =========================================================================

    // const EVENT_BEFORE_SAVE_BUNDLETYPE = 'beforeSaveBundleType';
    // const EVENT_AFTER_SAVE_BUNDLETYPE = 'afterSaveBundleType';


    // Properties
    // =========================================================================

    private $_fetchedAllBundleTypes = false;
    private $_bundleTypesById;
    private $_bundleTypesByHandle;
    private $_allBundleTypeIds;
    private $_editableBundleTypeIds;
    private $_siteSettingsByBundleId = [];


    // Public Methods
    // =========================================================================

    public function getEditableBundleTypes(): array
    {
        $editableBundleTypeIds = $this->getEditableBundleTypeIds();
        $editableBundleTypes = [];

        foreach ($this->getAllBundleTypes() as $bundleTypes) {
            if (in_array($bundleTypes->id, $editableBundleTypeIds, false)) {
                $editableBundleTypes[] = $bundleTypes;
            }
        }

        return $editableBundleTypes;
    }

    public function getEditableBundleTypeIds(): array
    {
        if (null === $this->_editableBundleTypeIds) {
            $this->_editableBundleTypeIds = [];
            $allBundleTypeIds = $this->getAllBundleTypeIds();

            foreach ($allBundleTypeIds as $bundleTypeId) {
                if (Craft::$app->getUser()->checkPermission('commerce-bundles-manageBundleType:' . $bundleTypeId)) {
                    $this->_editableBundleTypeIds[] = $bundleTypeId;
                }
            }
        }

        return $this->_editableBundleTypeIds;
    }

    public function getAllBundleTypeIds(): array
    {
        if (null === $this->_allBundleTypeIds) {
            $this->_allBundleTypeIds = [];
            $bundleTypes = $this->getAllBundleTypes();

            foreach ($bundleTypes as $bundleType) {
                $this->_allBundleTypeIds[] = $bundleType->id;
            }
        }

        return $this->_allBundleTypeIds;
    }

    public function getAllBundleTypes(): array
    {
        if (!$this->_fetchedAllBundleTypes) {
            $results = $this->_createBundleTypeQuery()->all();

            foreach ($results as $result) {
                $this->_memoizeBundleType(new BundleTypeModel($result));
            }

            $this->_fetchedAllBundleTypes = true;
        }

        return $this->_bundleTypesById ?: [];
    }

    public function getBundleTypeByHandle($handle)
    {
        if (isset($this->_bundleTypesByHandle[$handle])) {
            return $this->_bundleTypesByHandle[$handle];
        }

        if ($this->_fetchedAllBundleTypes) {
            return null;
        }

        $result = $this->_createBundleTypeQuery()
            ->where(['handle' => $handle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeBundleType(new BundleTypeModel($result));

        return $this->_bundleTypesByHandle[$handle];
    }

    public function getBundleTypeSites($bundleTypeId): array
    {
        if (!isset($this->_siteSettingsByBundleId[$bundleTypeId])) {
            $rows = (new Query())
                ->select([
                    'id',
                    'bundleTypeId',
                    'siteId',
                    'uriFormat',
                    'hasUrls',
                    'template'
                ])
                ->from('{{%bundles_bundletypes_sites}}')
                ->where(['bundleTypeId' => $bundleTypeId])
                ->all();

            $this->_siteSettingsByBundleId[$bundleTypeId] = [];

            foreach ($rows as $row) {
                $this->_siteSettingsByBundleId[$bundleTypeId][] = new BundleTypeSiteModel($row);
            }
        }

        return $this->_siteSettingsByBundleId[$bundleTypeId];
    }

    public function saveBundleType(BundleTypeModel $bundleType, bool $runValidation = true): bool
    {
        $isNewBundleType = !$bundleType->id;

        // // Fire a 'beforeSaveBundleType' event
        // if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_BUNDLETYPE)) {
        //     $this->trigger(self::EVENT_BEFORE_SAVE_BUNDLETYPE, new BundleTypeEvent([
        //         'bundleType' => $bundleType,
        //         'isNew' => $isNewBundleType,
        //     ]));
        // }

        if ($runValidation && !$bundleType->validate()) {
            Craft::info('Bundle type not saved due to validation error.', __METHOD__);

            return false;
        }

        if (!$isNewBundleType) {
            $bundleTypeRecord = BundleTypeRecord::findOne($bundleType->id);

            if (!$bundleTypeRecord) {
                throw new Exception("No bundle type exists with the ID '{$bundleType->id}'");
            }

        } else {
            $bundleTypeRecord = new BundleTypeRecord();
        }

        $bundleTypeRecord->name = $bundleType->name;
        $bundleTypeRecord->handle = $bundleType->handle;
        $bundleTypeRecord->skuFormat = $bundleType->skuFormat;

        // Get the site settings
        $allSiteSettings = $bundleType->getSiteSettings();

        // Make sure they're all there
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            if (!isset($allSiteSettings[$siteId])) {
                throw new Exception('Tried to save a bundle type that is missing site settings');
            }
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Bundle Field Layout
            $fieldLayout = $bundleType->getBundleFieldLayout();
            Craft::$app->getFields()->saveLayout($fieldLayout);
            $bundleType->fieldLayoutId = $fieldLayout->id;
            $bundleTypeRecord->fieldLayoutId = $fieldLayout->id;

            // Save the vobundleucher type
            $bundleTypeRecord->save(false);

            // Now that we have a bundle type ID, save it on the model
            if (!$bundleType->id) {
                $bundleType->id = $bundleTypeRecord->id;
            }

            // Might as well update our cache of the bundle type while we have it.
            $this->_bundleTypesById[$bundleType->id] = $bundleType;

            // Update the site settings
            // -----------------------------------------------------------------

            $sitesNowWithoutUrls = [];
            $sitesWithNewUriFormats = [];
            $allOldSiteSettingsRecords = [];

            if (!$isNewBundleType) {
                // Get the old bundle type site settings
                $allOldSiteSettingsRecords = BundleTypeSiteRecord::find()
                    ->where(['bundleTypeId' => $bundleType->id])
                    ->indexBy('siteId')
                    ->all();
            }

            foreach ($allSiteSettings as $siteId => $siteSettings) {
                // Was this already selected?
                if (!$isNewBundleType && isset($allOldSiteSettingsRecords[$siteId])) {
                    $siteSettingsRecord = $allOldSiteSettingsRecords[$siteId];
                } else {
                    $siteSettingsRecord = new BundleTypeSiteRecord();
                    $siteSettingsRecord->bundleTypeId = $bundleType->id;
                    $siteSettingsRecord->siteId = $siteId;
                }

			    if ($siteSettingsRecord->hasUrls = $siteSettings['hasUrls']) {
                    $siteSettingsRecord->uriFormat = $siteSettings['uriFormat'];
                    $siteSettingsRecord->template = $siteSettings['template'];
                } else {
                    $siteSettingsRecord->uriFormat = null;
                    $siteSettingsRecord->template = null;
                }

                if (!$siteSettingsRecord->getIsNewRecord()) {
                    // Did it used to have URLs, but not anymore?
                    if ($siteSettingsRecord->isAttributeChanged('hasUrls', false) && !$siteSettings['hasUrls']) {
                        $sitesNowWithoutUrls[] = $siteId;
                    }

                    // Does it have URLs, and has its URI format changed?
                    if ($siteSettings['hasUrls'] && $siteSettingsRecord->isAttributeChanged('uriFormat', false)) {
                        $sitesWithNewUriFormats[] = $siteId;
                    }
                }

                $siteSettingsRecord->save(false);

                // Set the ID on the model
                $siteSettings->id = $siteSettingsRecord->id;
            }

            if (!$isNewBundleType) {
                // Drop any site settings that are no longer being used, as well as the associated bundle/element
                // site rows
                $affectedSiteUids = array_keys($allSiteSettings);

                foreach ($allOldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
                    if (!in_array($siteId, $affectedSiteUids, false)) {
                        $siteSettingsRecord->delete();
                    }
                }
            }

            if (!$isNewBundleType) {
                foreach ($allSiteSettings as $siteId => $siteSettings) {
                    Craft::$app->getQueue()->push(new ResaveElements([
                        'description' => Craft::t('app', 'Resaving {type} bundles ({site})', [
                            'type' => $bundleType->name,
                            'site' => $siteSettings->getSite()->name,
                        ]),
                        'elementType' => Bundle::class,
                        'criteria' => [
                            'siteId' => $siteId,
                            'typeId' => $bundleType->id,
                            'status' => null,
                            'enabledForSite' => false,
                        ]
                    ]));
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        // // Fire an 'afterSaveBundleType' event
        // if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_BUNDLETYPE)) {
        //     $this->trigger(self::EVENT_AFTER_SAVE_BUNDLETYPE, new BundleTypeEvent([
        //         'bundleType' => $bundleType,
        //         'isNew' => $isNewBundleType,
        //     ]));
        // }

        return true;
    }

    public function deleteBundleTypeById(int $id): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $bundleType = $this->getBundleTypeById($id);

            $criteria = Bundle::find();
            $criteria->typeId = $bundleType->id;
            $criteria->status = null;
            $criteria->limit = null;
            $bundles = $criteria->all();

            foreach ($bundles as $bundle) {
                Craft::$app->getElements()->deleteElement($bundle);
            }

            $fieldLayoutId = $bundleType->getBundleFieldLayout()->id;
            Craft::$app->getFields()->deleteLayoutById($fieldLayoutId);

            $bundleTypeRecord = BundleTypeRecord::findOne($bundleType->id);
            $affectedRows = $bundleTypeRecord->delete();

            if ($affectedRows) {
                $transaction->commit();
            }

            return (bool)$affectedRows;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    public function getBundleTypeById(int $bundleTypeId)
    {
        if (isset($this->_bundleTypesById[$bundleTypeId])) {
            return $this->_bundleTypesById[$bundleTypeId];
        }

        if ($this->_fetchedAllBundleTypes) {
            return null;
        }

        $result = $this->_createBundleTypeQuery()
            ->where(['id' => $bundleTypeId])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeBundleType(new BundleTypeModel($result));

        return $this->_bundleTypesById[$bundleTypeId];
    }

    public function isBundleTypeTemplateValid(BundleTypeModel $bundleType, int $siteId): bool
    {
        $bundleTypeSiteSettings = $bundleType->getSiteSettings();

        if (isset($bundleTypeSiteSettings[$siteId]) && $bundleTypeSiteSettings[$siteId]->hasUrls) {
            // Set Craft to the site template mode
            $view = Craft::$app->getView();
            $oldTemplateMode = $view->getTemplateMode();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

            // Does the template exist?
            $templateExists = Craft::$app->getView()->doesTemplateExist((string)$bundleTypeSiteSettings[$siteId]->template);

            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);

            if ($templateExists) {
                return true;
            }
        }

        return false;
    }

    public function afterSaveSiteHandler(SiteEvent $event)
    {
        if ($event->isNew) {
            $primarySiteSettings = (new Query())
                ->select(['bundleTypeId', 'uriFormat', 'template', 'hasUrls'])
                ->from(['{{%bundles_bundletypes_sites}}'])
                ->where(['siteId' => $event->oldPrimarySiteId])
                ->one();

            if ($primarySiteSettings) {
                $newSiteSettings = [];

                $newSiteSettings[] = [
                    $primarySiteSettings['bundleTypeId'],
                    $event->site->id,
                    $primarySiteSettings['uriFormat'],
                    $primarySiteSettings['template'],
                    $primarySiteSettings['hasUrls']
                ];

                Craft::$app->getDb()->createCommand()
                    ->batchInsert(
                        '{{%bundles_bundletypes_sites}}',
                        ['bundleTypeId', 'siteId', 'uriFormat', 'template', 'hasUrls'],
                        $newSiteSettings)
                    ->execute();
            }
        }
    }

    // Private methods
    // =========================================================================

    private function _memoizeBundleType(BundleTypeModel $bundleType)
    {
        $this->_bundleTypesById[$bundleType->id] = $bundleType;
        $this->_bundleTypesByHandle[$bundleType->handle] = $bundleType;
    }

    private function _createBundleTypeQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'fieldLayoutId',
                'name',
                'handle',
                'skuFormat'
            ])
            ->from(['{{%bundles_bundletypes}}']);
    }
}
