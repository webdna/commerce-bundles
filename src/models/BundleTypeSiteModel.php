<?php
namespace webdna\commerce\bundles\models;

use webdna\commerce\bundles\Bundles;

use Craft;
use craft\base\Model;
use craft\models\Site;

use yii\base\InvalidConfigException;


/**
 *
 * @property-read Site $site
 * @property-read BundleTypeModel $bundleType
 */
class BundleTypeSiteModel extends Model
{

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var int Bundle type ID
     */
    public int $bundleTypeId;

    /**
     * @var int Site ID
     */
    public int $siteId;

    /**
     * @var bool Has Urls
     */
    public bool $hasUrls = false;

    /**
     * @var string|null URL Format
     */
    public ?string $uriFormat = null;

    /**
     * @var string|null Template Path
     */
    public ?string $template = null;

    /**
     * @var bool
     */
    public bool $uriFormatIsRequired = true;

    /**
     * @var BundleTypeModel|null
     */
    private ?BundleTypeModel $_bundleType;

    /**
     * @var Site|null
     */
    private ?Site $_site = null;


    // Public Methods
    // =========================================================================

    /**
     * @throws InvalidConfigException
     */
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

    public function setBundleType(BundleTypeModel $bundleType) : void
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
