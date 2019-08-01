<?php
namespace kuriousagency\commerce\bundles\controllers;

use kuriousagency\commerce\bundles\Bundles;
use kuriousagency\commerce\bundles\elements\Bundle;
use kuriousagency\commerce\bundles\models\BundleTypeModel;
use kuriousagency\commerce\bundles\models\BundleTypeSiteModel;

use Craft;
use craft\web\Controller;

use yii\base\Exception;
use yii\web\Response;

class BundleTypesController extends Controller
{
    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->requirePermission('commerce-bundle-manageTypes');

        parent::init();
    }

    public function actionEdit(int $bundleTypeId = null, BundleTypeModel $bundleType = null): Response
    {
        $variables = [
            'bundleTypeId' => $bundleTypeId,
            'bundleType' => $bundleType,
            'brandNewBundleType' => false,
        ];

        if (empty($variables['bundleType'])) {
            if (!empty($variables['bundleTypeId'])) {
                $bundleTypeId = $variables['bundleTypeId'];
                $variables['bundleType'] = Bundles::getInstance()->bundleTypes->getBundleTypeById($bundleTypeId);

                if (!$variables['bundleType']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['bundleType'] = new BundleTypeModel();
                $variables['brandNewBundleType'] = true;
            }
        }

        if (!empty($variables['bundleTypeId'])) {
            $variables['title'] = $variables['bundleType']->name;
        } else {
            $variables['title'] = Craft::t('commerce-bundles', 'Create a Bundle Type');
        }
        
        return $this->renderTemplate('commerce-bundles/bundle-types/_edit', $variables);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $bundleType = new BundleTypeModel();

        $request = Craft::$app->getRequest();
        
        $bundleType->id = $request->getBodyParam('bundleTypeId');
        $bundleType->name = $request->getBodyParam('name');
        $bundleType->handle = $request->getBodyParam('handle');
        $bundleType->skuFormat = $request->getBodyParam('skuFormat');

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $request->getBodyParam('sites.' . $site->handle);

            $siteSettings = new BundleTypeSiteModel();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = !empty($postedSettings['uriFormat']);

            if ($siteSettings->hasUrls) {
                $siteSettings->uriFormat = $postedSettings['uriFormat'];
                $siteSettings->template = $postedSettings['template'];
            } else {
                $siteSettings->uriFormat = null;
                $siteSettings->template = null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $bundleType->setSiteSettings($allSiteSettings);

        // Set the bundle type field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Bundle::class;
        $bundleType->setFieldLayout($fieldLayout);

        // Save it
        if (Bundles::getInstance()->bundleTypes->saveBundleType($bundleType)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce-bundles', 'Bundle type saved.'));

            return $this->redirectToPostedUrl($bundleType);
        }

        Craft::$app->getSession()->setError(Craft::t('commerce-bundles', 'Couldn’t save bundle type.'));

        // Send the bundleType back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'bundleType' => $bundleType
        ]);

        return null;
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $bundleTypeId = Craft::$app->getRequest()->getRequiredParam('id');
        Bundles::getInstance()->bundleTypes->deleteBundleTypeById($bundleTypeId);

        return $this->asJson(['success' => true]);
    }

}
