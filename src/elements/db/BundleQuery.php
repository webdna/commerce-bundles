<?php
namespace webdna\commerce\bundles\elements\db;

use webdna\commerce\bundles\Bundles;
use webdna\commerce\bundles\elements\Bundle;
use webdna\commerce\bundles\models\BundleTypeModel;

use Craft;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;

use DateTime;
use yii\db\Connection;
use yii\db\ExpressionInterface;

class BundleQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    public bool $editable = false;
    public mixed $typeId = null;
    public mixed $postDate = null;
    public mixed $expiryDate = null;
    public array $purchasableIds = [];

    // Public Methods
    // =========================================================================

    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config["status"])) {
            $config["status"] = Bundle::STATUS_LIVE;
        }

        parent::__construct($elementType, $config);
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case "type":
                $this->type($value);
                break;
            case "before":
                $this->before($value);
                break;
            case "after":
                $this->after($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    public function type(mixed $value): BundleQuery
    {
        if ($value instanceof BundleTypeModel) {
            $this->typeId = $value->id;
        } elseif ($value !== null) {
            $this->typeId = (new Query())
                ->select(["id"])
                ->from(["{{%bundles_bundletypes}}"])
                ->where(Db::parseParam("handle", $value))
                ->column();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    public function before(mixed $value): BundleQuery
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = "<" . $value;

        return $this;
    }

    public function after(mixed $value): BundleQuery
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = ">=" . $value;

        return $this;
    }

    public function editable(bool $value = true): BundleQuery
    {
        $this->editable = $value;

        return $this;
    }

    public function typeId(mixed $value): BundleQuery
    {
        $this->typeId = $value;

        return $this;
    }

    public function postDate(mixed $value): BundleQuery
    {
        $this->postDate = $value;

        return $this;
    }

    public function expiryDate(mixed $value): BundleQuery
    {
        $this->expiryDate = $value;

        return $this;
    }

    public function purchasables(mixed $value): BundleQuery
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        foreach ($value as $purchasable) {
            $this->purchasableIds[] = $purchasable->id;
        }

        return $this;
    }

    // Protected Methods
    // =========================================================================

    protected function beforePrepare(): bool
    {
        // See if 'type' were set to invalid handles
        if ($this->typeId === []) {
            return false;
        }

        $this->joinElementTable("bundles_bundles");

        $this->query->select([
            "bundles_bundles.id",
            "bundles_bundles.typeId",
            "bundles_bundles.taxCategoryId",
            "bundles_bundles.shippingCategoryId",
            "bundles_bundles.postDate",
            "bundles_bundles.expiryDate",
            "bundles_bundles.sku",
            "bundles_bundles.price",
        ]);

        if ($this->postDate) {
            $this->subQuery->andWhere(
                Db::parseDateParam("bundles_bundles.postDate", $this->postDate)
            );
        }

        if ($this->expiryDate) {
            $this->subQuery->andWhere(
                Db::parseDateParam(
                    "bundles_bundles.expiryDate",
                    $this->expiryDate
                )
            );
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(
                Db::parseParam("bundles_bundles.typeId", $this->typeId)
            );
        }

        if ($this->purchasableIds) {
            $this->subQuery->innerJoin(
                "bundles_purchasables",
                "bundles_bundles.id = bundles_purchasables.bundleId"
            );
            $this->subQuery->andWhere(
                Db::parseParam(
                    "bundles_purchasables.purchasableId",
                    $this->purchasableIds
                )
            );
        }

        $this->_applyEditableParam();

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): string|array|ExpressionInterface|false|null
    {
        $currentTimeDb = Db::prepareDateForDb(new \DateTime());

        switch ($status) {
            case Bundle::STATUS_LIVE:
                return [
                    "and",
                    [
                        "elements.enabled" => true,
                        "elements_sites.enabled" => true,
                    ],
                    ["<=", "bundles_bundles.postDate", $currentTimeDb],
                    [
                        "or",
                        ["bundles_bundles.expiryDate" => null],
                        [">", "bundles_bundles.expiryDate", $currentTimeDb],
                    ],
                ];
            case Bundle::STATUS_PENDING:
                return [
                    "and",
                    [
                        "elements.enabled" => true,
                        "elements_sites.enabled" => true,
                    ],
                    [">", "bundles_bundles.postDate", $currentTimeDb],
                ];
            case Bundle::STATUS_EXPIRED:
                return [
                    "and",
                    [
                        "elements.enabled" => true,
                        "elements_sites.enabled" => true,
                    ],
                    ["not", ["bundles_bundles.expiryDate" => null]],
                    ["<=", "bundles_bundles.expiryDate", $currentTimeDb],
                ];
            default:
                return parent::statusCondition($status);
        }
    }

    // Private Methods
    // =========================================================================

    private function _applyEditableParam(): void
    {
        if (!$this->editable) {
            return;
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user) {
            throw new QueryAbortedException();
        }

        // Limit the query to only the sections the user has permission to edit
        $this->subQuery->andWhere([
            "bundles_bundles.typeId" => Bundles::$plugin->bundleTypes->getEditableBundleTypeIds(),
        ]);
    }
}
