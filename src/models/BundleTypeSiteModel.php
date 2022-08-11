<?php
namespace webdna\commerce\bundles\models;

use webdna\commerce\bundles\Bundles;

use Craft;
use craft\base\Model;
use craft\models\Site;

use yii\base\InvalidConfigException;

class BundleTypeSiteModel extends Model
{
    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $bundleTypeId = null;
    public ?int $siteId = null;
    public ?bool $hasUrls = null;
    public string $uriFormat = '';
    public string $template = '';
    public bool $uriFormatIsRequired = true;

    private ?BundleTypeModel $_bundleType = null;
    private ?Site $_site = null;


    // Public Methods
    // =========================================================================

    public function getBundleType(): BundleTypeModel
    {
        if ($this->_bundleType !== null) {
            return $this->_bundleType;
        }

        if (!$this->bundleTypeId) {
            throw new InvalidConfigException('Site is missing its bundle type ID');
        }

        if (($this->_bundleType = Bundles::$plugin->bundleTypes->getBundleTypeById($this->bundleTypeId)) === null) {
            throw new InvalidConfigException('Invalid bundle type ID: ' . $this->bundleTypeId);
        }

        return $this->_bundleType;
    }

    public function setBundleType(BundleTypeModel $bundleType): void
    {
        $this->_bundleType = $bundleType;
    }

    public function getSite(): Site
    {
        if (!$this->_site) {
            $this->_site = Craft::$app->getSites()->getSiteById($this->siteId);
        }

        return $this->_site;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->uriFormatIsRequired) {
            $rules[] = ['uriFormat', 'required'];
        }

        return $rules;
    }
}
