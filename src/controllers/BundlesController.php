<?php
namespace kuriousagency\commerce\bundles\controllers;

use kuriousagency\commerce\bundles\Bundles;
use kuriousagency\commerce\bundles\elements\Bundle;
use kuriousagency\commerce\bundles\models\BundlePurchasableModel;
use kuriousagency\commerce\bundles\records\BundlePurchasableRecord;

use Craft;
use craft\base\Element;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\Controller;

use craft\commerce\Plugin as Commerce;

use yii\base\Exception;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class BundlesController extends Controller
{
    // Properties
    // =========================================================================

    protected $allowAnonymous = [];


    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->requirePermission('commerce-bundles-manageBundles');

        parent::init();
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('commerce-bundles/bundles/index');
    }

    public function actionEdit(string $bundleTypeHandle, int $bundleId = null, string $siteHandle = null, Bundle $bundle = null): Response
    {
		$bundleType = null;

        $variables = [
            'bundleTypeHandle' => $bundleTypeHandle,
            'bundleId' => $bundleId,
            'bundle' => $bundle
        ];

        // Make sure a correct bundle type handle was passed so we can check permissions
        if ($bundleTypeHandle) {
            $bundleType = Bundles::$plugin->bundleTypes->getBundleTypeByHandle($bundleTypeHandle);
        }

        if (!$bundleType) {
            throw new Exception('The bundle type was not found.');
        }

        $this->requirePermission('commerce-bundles-manageBundleType:' . $bundleType->id);
        $variables['bundleType'] = $bundleType;

        if ($siteHandle !== null) {
            $variables['site'] = Craft::$app->getSites()->getSiteByHandle($siteHandle);

            if (!$variables['site']) {
                throw new Exception('Invalid site handle: '.$siteHandle);
            }
        }

        $this->_prepareVariableArray($variables);

        if (!empty($variables['bundle']->id)) {
            $variables['title'] = $variables['bundle']->title;
        } else {
            $variables['title'] = Craft::t('commerce-bundles', 'Create a New Bundle');
        }

        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'commerce-bundles/bundles/' . $variables['bundleTypeHandle'] . '/{id}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'] . (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/' . $variables['site']->handle : '');

        $this->_livePreview($variables);

        $variables['tabs'] = [];

        foreach ($variables['bundleType']->getFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;

            if ($variables['bundle']->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($hasErrors = $variables['bundle']->hasErrors($field->handle . '.*')) {
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t('site', $tab->name),
                'url' => '#' . $tab->getHtmlId(),
                'class' => $hasErrors ? 'error' : null
            ];
        }

        return $this->renderTemplate('commerce-bundles/bundles/_edit', $variables);
    }

    public function actionDeleteBundle()
    {
        $this->requirePostRequest();

        $bundleId = Craft::$app->getRequest()->getRequiredParam('bundleId');
        $bundle = Bundle::findOne($bundleId);

        if (!$bundle) {
            throw new Exception(Craft::t('commerce-bundles', 'No bundle exists with the ID “{id}”.',['id' => $bundleId]));
        }

        $this->enforceBundlePermissions($bundle);

        if (!Craft::$app->getElements()->deleteElement($bundle)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => false]);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce-bundles', 'Couldn’t delete bundle.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'bundle' => $bundle
            ]);

            return null;
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce-bundles', 'Bundle deleted.'));

        return $this->redirectToPostedUrl($bundle);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $bundle = $this->_setBundleFromPost();

        $this->enforceBundlePermissions($bundle);

        if ($bundle->enabled && $bundle->enabledForSite) {
            $bundle->setScenario(Element::SCENARIO_LIVE);
        }

        if (!Craft::$app->getElements()->saveElement($bundle)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $bundle->getErrors(),
                ]);
			}

			Craft::$app->getSession()->setError(Craft::t('commerce-bundles', 'Couldn’t save bundle.'));

			$variables = ['bundle' => $bundle];
			$this->_prepareVariableArray($variables);
			//Craft::dd($bundle->getErrors());
            // Send the category back to the template
            Craft::$app->getUrlManager()->setRouteParams($variables);

            return null;
		}
		
		$this->deleteAllPurchasablesByBundleId($bundle->id);

		//$products = $request->getBodyParam('products');
		//$qtys = $request->getBodyParam('qty');

		foreach ($bundle->getPurchasableIds() as $id) {
			
			$purchasable = Craft::$app->getElements()->getElementById($id);

			$bundlePurchasable = new BundlePurchasableModel;
			$bundlePurchasable->bundleId = $bundle->id;
			$bundlePurchasable->purchasableId = $id;
			$bundlePurchasable->purchasableType = get_class($purchasable);
			$bundlePurchasable->qty = $bundle->qtys[$id];

			$this->saveBundlePurchasables($bundlePurchasable);

		}


        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $bundle->id,
                'title' => $bundle->title,
                'status' => $bundle->getStatus(),
                'url' => $bundle->getUrl(),
                'cpEditUrl' => $bundle->getCpEditUrl()
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Bundle saved.'));

        return $this->redirectToPostedUrl($bundle);
    }

    public function actionPreviewBundle(): Response
    {

        $this->requirePostRequest();

        $bundle = $this->_setBundleFromPost();

        $this->enforceBundlePermissions($bundle);

        return $this->_showBundle($bundle);
    }

    public function actionShareBundle($bundleId, $siteId): Response
    {
        $bundle = Bundles::getInstance()->bundles->getBundleById($bundleId, $siteId);

        if (!$bundle) {
            throw new HttpException(404);
        }

        $this->enforceBundlePermissions($bundle);

        if (!Bundles::$plugin->bundleTypes->isBundleTypeTemplateValid($bundle->getType(), $bundle->siteId)) {
            throw new HttpException(404);
        }

        $this->requirePermission('commerce-bundles-manageBundleType:' . $bundle->typeId);

        // Create the token and redirect to the bundle URL with the token in place
        $token = Craft::$app->getTokens()->createToken([
            'commerce-bundles/bundles/view-shared-bundle', ['bundleId' => $bundle->id, 'siteId' => $siteId]
        ]);

        $url = UrlHelper::urlWithToken($bundle->getUrl(), $token);

        return $this->redirect($url);
    }

    public function actionViewSharedBundle($bundleId, $site = null)
    {
        $this->requireToken();

        $bundle = Bundles::getInstance()->bundles->getBundleById($bundleId, $site);

        if (!$bundle) {
            throw new HttpException(404);
        }

        $this->_showBundle($bundle);

        return null;
	}
	
	public function saveBundlePurchasables(BundlePurchasableModel $bundlePurchasable)
	{
		
		$bundlePurchasableRecord = new BundlePurchasableRecord();
		$bundlePurchasableRecord->bundleId = $bundlePurchasable->bundleId;
		$bundlePurchasableRecord->purchasableId = $bundlePurchasable->purchasableId;
		$bundlePurchasableRecord->purchasableType = $bundlePurchasable->purchasableType;
		$bundlePurchasableRecord->qty = $bundlePurchasable->qty;
		
		if (!$bundlePurchasable->hasErrors()) {

            $db = Craft::$app->getDb();
            $transaction = $db->beginTransaction();

            try {
                $success = $bundlePurchasableRecord->save(false);

                if ($success) {                    
                    $bundlePurchasable->id = $bundlePurchasableRecord->id;

                    $transaction->commit();
                }
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }

            return $success;
        }

        return false;
			

	}

	public function deleteAllPurchasablesByBundleId(int $bundleId): bool
    {
        return (bool)BundlePurchasableRecord::deleteAll(['bundleId' => $bundleId]);
    }


    // Protected Methods
    // =========================================================================

    protected function enforceBundlePermissions(Bundle $bundle)
    {
        if (!$bundle->getType()) {
            Craft::error('Attempting to access a bundle that doesn’t have a type', __METHOD__);
            throw new HttpException(404);
        }

        $this->requirePermission('commerce-bundles-manageBundleType:' . $bundle->getType()->id);
    }


    // Private Methods
    // =========================================================================

    private function _showBundle(Bundle $bundle): Response
    {

        $bundleType = $bundle->getType();

        if (!$bundleType) {
            throw new ServerErrorHttpException('Bundle type not found.');
        }

        $siteSettings = $bundleType->getSiteSettings();

        if (!isset($siteSettings[$bundle->siteId]) || !$siteSettings[$bundle->siteId]->hasUrls) {
            throw new ServerErrorHttpException('The bundle ' . $bundle->id . ' doesn\'t have a URL for the site ' . $bundle->siteId . '.');
        }

        $site = Craft::$app->getSites()->getSiteById($bundle->siteId);

        if (!$site) {
            throw new ServerErrorHttpException('Invalid site ID: ' . $bundle->siteId);
        }

        Craft::$app->language = $site->language;

        // Have this bundle override any freshly queried bundles with the same ID/site
        Craft::$app->getElements()->setPlaceholderElement($bundle);

        $this->getView()->getTwig()->disableStrictVariables();

        return $this->renderTemplate($siteSettings[$bundle->siteId]->template, [
            'bundle' => $bundle
        ]);
    }

    private function _prepareVariableArray(&$variables)
    {
        // Locale related checks
        if (Craft::$app->getIsMultiSite()) {
            // Only use the sites that the user has access to
            $variables['siteIds'] = Craft::$app->getSites()->getEditableSiteIds();
        } else {
            $variables['siteIds'] = [Craft::$app->getSites()->getPrimarySite()->id];
        }

        if (!$variables['siteIds']) {
            throw new ForbiddenHttpException('User not permitted to edit content in any sites supported by this section');
        }

        if (empty($variables['site'])) {
            $site = $variables['site'] = Craft::$app->getSites()->currentSite;

            if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
                $site = $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
            }
        } else {
            // Make sure they were requesting a valid site
            /** @var Site $site */
            $site = $variables['site'];
            if (!in_array($site->id, $variables['siteIds'], false)) {
                throw new ForbiddenHttpException('User not permitted to edit content in this site');
            }
        }

        // Bundle related checks
        if (empty($variables['bundle'])) {
            if (!empty($variables['bundleId'])) {
                $variables['bundle'] = Craft::$app->getElements()->getElementById($variables['bundleId'], Bundle::class, $site->id);

                if (!$variables['bundle']) {
                    throw new Exception('Missing bundle data.');
                }
            } else {
                $variables['bundle'] = new Bundle();
                $variables['bundle']->typeId = $variables['bundleType']->id;

                if (!empty($variables['siteId'])) {
                    $variables['bundle']->site = $variables['siteId'];
                }
            }
        }

        // Enable locales
        if ($variables['bundle']->id) {
            $variables['enabledSiteIds'] = Craft::$app->getElements()->getEnabledSiteIdsForElement($variables['bundle']->id);
        } else {
            $variables['enabledSiteIds'] = [];

            foreach (Craft::$app->getSites()->getEditableSiteIds() as $site) {
                $variables['enabledSiteIds'][] = $site;
            }
		}


		$variables['purchasables'] = null;
		$purchasables = [];
		foreach ($variables['bundle']->getPurchasableIds() as $purchasableId) {
			$purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
            if ($purchasable) {
                $class = get_class($purchasable);
                $purchasables[$class] = $purchasables[$class] ?? [];
                $purchasables[$class][] = $purchasable;
            }
        }
        $variables['purchasables'] = $purchasables;
		
		$variables['purchasableTypes'] = [];
        $purchasableTypes = Commerce::getInstance()->getPurchasables()->getAllPurchasableElementTypes();

        /** @var Purchasable $purchasableType */
        foreach ($purchasableTypes as $purchasableType) {
			if ($purchasableType != get_class($variables['bundle'])) {
				$variables['purchasableTypes'][] = [
					'name' => $purchasableType::displayName(),
					'elementType' => $purchasableType
				];
			}
        }
    }

    private function _livePreview(array &$variables)
    {
        if (!Craft::$app->getRequest()->isMobileBrowser(true) && Bundles::$plugin->bundleTypes->isBundleTypeTemplateValid($variables['bundleType'], $variables['site']->id)) {
            $this->getView()->registerJs('Craft.LivePreview.init('.Json::encode([
                    'fields' => '#title-field, #fields > div > div > .field',
                    'extraFields' => '#meta-pane',
                    'previewUrl' => $variables['bundle']->getUrl(),
                    'previewAction' => Craft::$app->getSecurity()->hashData('commerce-bundles/bundles/preview-bundle'),
                    'previewParams' => [
                        'typeId' => $variables['bundleType']->id,
                        'bundleId' => $variables['bundle']->id,
                        'siteId' => $variables['bundle']->siteId,
                    ]
                ]).');');

            $variables['showPreviewBtn'] = true;

            // Should we show the Share button too?
            if ($variables['bundle']->id) {
                // If the bundle is enabled, use its main URL as its share URL.
                if ($variables['bundle']->getStatus() === Bundle::STATUS_LIVE) {
                    $variables['shareUrl'] = $variables['bundle']->getUrl();
                } else {
                    $variables['shareUrl'] = UrlHelper::actionUrl('commerce-bundles/bundles/share-bundle', [
                        'bundleId' => $variables['bundle']->id,
                        'siteId' => $variables['bundle']->siteId
                    ]);
                }
            }
        } else {
            $variables['showPreviewBtn'] = false;
        }
    }

    private function _setBundleFromPost(): Bundle
    {
        $request = Craft::$app->getRequest();
        $bundleId = $request->getBodyParam('bundleId');
        $siteId = $request->getBodyParam('siteId');

        if ($bundleId) {
            $bundle = Bundles::getInstance()->bundles->getBundleById($bundleId, $siteId);

            if (!$bundle) {
                throw new Exception(Craft::t('commerce-bundles', 'No bundle with the ID “{id}”', ['id' => $bundleId]));
            }
        } else {
            $bundle = new Bundle();
        }

        $bundle->typeId = $request->getBodyParam('typeId');
        $bundle->siteId = $siteId ?? $bundle->siteId;
        $bundle->enabled = (bool)$request->getBodyParam('enabled');

        $bundle->price = Localization::normalizeNumber($request->getBodyParam('price'));
		$bundle->sku = $request->getBodyParam('sku');

		$purchasables = [];
        $purchasableGroups = $request->getBodyParam('purchasables') ?: [];
        foreach ($purchasableGroups as $group) {
            if (is_array($group)) {
                array_push($purchasables, ...$group);
            }
        }
        $purchasables = array_unique($purchasables);
        $bundle->setPurchasableIds($purchasables);
		
		//$bundle->products = $request->getBodyParam('products');
		$bundle->qtys = $request->getBodyParam('qty');

        if (($postDate = Craft::$app->getRequest()->getBodyParam('postDate')) !== null) {
            $bundle->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }
        
        if (($expiryDate = Craft::$app->getRequest()->getBodyParam('expiryDate')) !== null) {
            $bundle->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }

        // $bundle->promotable = (bool)$request->getBodyParam('promotable');
        $bundle->taxCategoryId = $request->getBodyParam('taxCategoryId');
        $bundle->shippingCategoryId = $request->getBodyParam('shippingCategoryId');
        $bundle->slug = $request->getBodyParam('slug');

        $bundle->enabledForSite = (bool)$request->getBodyParam('enabledForSite', $bundle->enabledForSite);
        $bundle->title = $request->getBodyParam('title', $bundle->title);

        $bundle->setFieldValuesFromRequest('fields');

        // Last checks
        if (empty($bundle->sku)) {
            $bundleType = $bundle->getType();
            $bundle->sku = Craft::$app->getView()->renderObjectTemplate($bundleType->skuFormat, $bundle);
        }

        return $bundle;
    }
}
