<?php
namespace webdna\commerce\bundles\events;

use webdna\commerce\bundles\elements\Bundle;
use yii\base\Event;

class CustomizeBundleSnapshotDataEvent extends Event
{
    // Properties
    // =========================================================================

    public Bundle $bundle;
    public array $fieldData;
}
