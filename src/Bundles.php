<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Bundles plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\bundles;

use kuriousagency\commerce\bundles\services\BundlesService;
use kuriousagency\commerce\bundles\services\BundleTypesService;
use kuriousagency\commerce\bundles\variables\BundlesVariable;
use kuriousagency\commerce\bundles\elements\Bundle;
use kuriousagency\commerce\bundles\fields\Bundles as BundlesField;
use kuriousagency\commerce\bundles\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\web\UrlManager;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Sites;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterUrlRulesEvent;

use craft\commerce\services\Purchasables;

use yii\base\Event;

/**
 * Class Bundles
 *
 * @author    Kurious Agency
 * @package   Bundles
 * @since     1.0.0
 *
 * @property  BundlesServiceService $bundlesService
 */
class Bundles extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Bundles
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.1.1';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
		self::$plugin = $this;
		
		$this->setComponents([
            'bundles' => BundlesService::class,
            'bundleTypes' => BundleTypesService::class,
        ]);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['siteActionTrigger1'] = 'bundles/default';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
				$event->rules = array_merge($event->rules, [
					'commerce-bundles/bundle-types/new' => 'commerce-bundles/bundle-types/edit',
					'commerce-bundles/bundle-types/<bundleTypeId:\d+>' => 'commerce-bundles/bundle-types/edit',
					
					'commerce-bundles/bundles/<bundleTypeHandle:{handle}>' => 'commerce-bundles/bundles/index',
					'commerce-bundles/bundles/<bundleTypeHandle:{handle}>/new' => 'commerce-bundles/bundles/edit',
					'commerce-bundles/bundles/<bundleTypeHandle:{handle}>/new/<siteHandle:\w+>' => 'commerce-bundles/bundles/edit',
					'commerce-bundles/bundles/<bundleTypeHandle:{handle}>/<bundleId:\d+>' => 'commerce-bundles/bundles/edit',
					'commerce-bundles/bundles/<bundleTypeHandle:{handle}>/<bundleId:\d+>/<siteHandle:\w+>' => 'commerce-bundles/bundles/edit',
				]);
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
				$event->types[] = Bundle::class;
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->attachBehavior('bundles', BundlesVariable::class);
            }
		);
		
		Event::on(
			Purchasables::class,
			Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES,
			function(RegisterComponentTypesEvent $event) {
           		$event->types[] = Bundle::class;
			}
		);

		Event::on(
			Fields::class,
			Fields::EVENT_REGISTER_FIELD_TYPES,
			function(RegisterComponentTypesEvent $event) {
            	$event->types[] = BundlesField::class;
			}
		);

		Event::on(
			UserPermissions::class,
			UserPermissions::EVENT_REGISTER_PERMISSIONS,
			function(RegisterUserPermissionsEvent $event) {
            $bundleTypes = $this->bundleTypes->getAllBundleTypes();

            $bundleTypePermissions = [];

            foreach ($bundleTypes as $id => $bundleType) {
                $suffix = ':' . $id;
                $bundleTypePermissions['commerce-bundles-managebundleType' . $suffix] = ['label' => Craft::t('commerce-bundles', 'Manage “{type}” bundles', ['type' => $bundleType->name])];
            }

            $event->permissions[Craft::t('commerce-bundles', 'Bundles')] = [
				'commerce-bundles-manageBundles' => ['label' => Craft::t('commerce-bundles', 'Manage bundles'), 'nested' => $bundleTypePermissions],
                'commerce-bundles-manageBundleType' => ['label' => Craft::t('commerce-bundles', 'Manage bundle types')],
                
            ];
        });
		
		Event::on(Sites::class, Sites::EVENT_AFTER_SAVE_SITE, [$this->bundleTypes, 'afterSaveSiteHandler']);
        Event::on(Sites::class, Sites::EVENT_AFTER_SAVE_SITE, [$this->bundles, 'afterSaveSiteHandler']);

        Craft::info(
            Craft::t(
                'commerce-bundles',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
	}
	
	public function getCpNavItem(): array
    {
		$navItems = parent::getCpNavItem();
		
		$navItems['label'] = Craft::t('commerce-bundles', 'Bundles');

        if (Craft::$app->getUser()->checkPermission('commerce-bundles-manageBundles')) {
            $navItems['subnav']['bundles'] = [
                'label' => Craft::t('commerce-bundles', 'Bundles'),
                'url' => 'commerce-bundles/bundles',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('commerce-bundles-manageBundleType')) {
            $navItems['subnav']['bundleTypes'] = [
                'label' => Craft::t('commerce-bundles', 'Bundle Types'),
                'url' => 'commerce-bundles/bundle-types',
            ];
        }

        // if (Craft::$app->getUser()->getIsAdmin()) {
        //     $navItems['subnav']['settings'] = [
        //         'label' => Craft::t('commerce-bundles', 'Settings'),
        //         'url' => 'bundles/settings',
        //     ];
        // }

        return $navItems;
    }

    // Protected Methods
    // =========================================================================

    // /**
    //  * @inheritdoc
    //  */
    // protected function createSettingsModel()
    // {
    //     return new Settings();
    // }

    // /**
    //  * @inheritdoc
    //  */
    // protected function settingsHtml(): string
    // {
    //     return Craft::$app->view->renderTemplate(
    //         'bundles/settings',
    //         [
    //             'settings' => $this->getSettings()
    //         ]
    //     );
    // }
}
