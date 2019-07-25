<?php
namespace kuriousagency\commerce\bundles\models;

use kuriousagency\commerce\bundles\Bundles;
use kuriousagency\commerce\bundles\elements\Bundle;
use kuriousagency\commerce\bundles\records\BundleTypeRecord;

use Craft;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;

class BundleTypeModel extends Model
{
    // Properties
    // =========================================================================

    public $id;
    public $name;
    public $handle;
    public $skuFormat;
    public $template;
    public $fieldLayoutId;

    private $_siteSettings;

    // Public Methods
    // =========================================================================

    public function __toString()
    {
        return $this->handle;
    }

    public function rules()
    {
        return [
            [['id', 'fieldLayoutId'], 'number', 'integerOnly' => true],
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['handle'], UniqueValidator::class, 'targetClass' => BundleTypeRecord::class, 'targetAttribute' => ['handle'], 'message' => 'Not Unique'],
            [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']],
        ];
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce-bundles/types/' . $this->id);
    }

    public function getSiteSettings(): array
    {
        if ($this->_siteSettings !== null) {
            return $this->_siteSettings;
        }

        if (!$this->id) {
            return [];
        }

        $this->setSiteSettings(ArrayHelper::index(Bundles::$plugin->bundleTypes->getBundleTypeSites($this->id), 'siteId'));

        return $this->_siteSettings;
    }

    public function setSiteSettings(array $siteSettings)
    {
        $this->_siteSettings = $siteSettings;

        foreach ($this->_siteSettings as $settings) {
            $settings->setBundleType($this);
        }
    }

    public function getBundleFieldLayout(): FieldLayout
    {
        $behavior = $this->getBehavior('bundleFieldLayout');
        return $behavior->getFieldLayout();
    }

    public function behaviors(): array
    {
        return [
            'bundleFieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => Bundle::class,
                'idAttribute' => 'fieldLayoutId'
            ]
        ];
    }
}
