<?php
namespace webdna\commerce\bundles\models;

use webdna\commerce\bundles\Bundles;
use webdna\commerce\bundles\elements\Bundle;
use webdna\commerce\bundles\records\BundleTypeRecord;

use Craft;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use yii\base\InvalidConfigException;


/**
 *
 * @property-read string $cpEditUrl
 * @property array $siteSettings
 * @property-read FieldLayout $bundleFieldLayout
 */
class BundleTypeModel extends Model
{

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var string|null SKU format
     */
    public ?string $skuFormat = null;

    /**
     * @var string|null Template
     */
    public ?string $template = null;

    /**
     * @var int|null Field layout ID
     */
    public ?int $fieldLayoutId = null;

    private ?array $_siteSettings = null;


    /**
     * @return null|string
     */
    public function __toString()
    {
        return (string)$this->handle;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
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
        if (isset($this->_siteSettings)) {
            return $this->_siteSettings;
        }

        if (!$this->id) {
            return [];
        }

        $this->setSiteSettings(ArrayHelper::index(Bundles::$plugin->bundleTypes->getBundleTypeSites($this->id), 'siteId'));

        return $this->_siteSettings;
    }

    public function setSiteSettings(array $siteSettings): void
    {
        $this->_siteSettings = $siteSettings;

        foreach ($this->_siteSettings as $settings) {
            $settings->setBundleType($this);
        }
    }

    /**
     * @throws InvalidConfigException
     */
    public function getBundleFieldLayout(): FieldLayout
    {
        /** @var FieldLayoutBehavior $behavior */
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
