<?php
namespace webdna\commerce\bundles\events;

use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use webdna\commerce\bundles\elements\Bundle;
use yii\base\Event;

class CompleteBundleOrderEvent extends Event
{
    // Properties
    // =========================================================================

    public Bundle $bundle;
    public Order $order;
    public LineItem $lineItem;
}
