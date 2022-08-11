<?php
namespace webdna\commerce\bundles\events;

use webdna\commerce\bundles\elements\Bundle;
use yii\base\Event;

class CustomizeBundleSnapshotFieldsEvent extends Event
{
    // Properties
    // =========================================================================

    public Bundle $bundle;
    public array $fields;
}
