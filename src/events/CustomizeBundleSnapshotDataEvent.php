<?php
namespace kuriousagency\commerce\bundles\events;

use yii\base\Event;

class CustomizeBundleSnapshotDataEvent extends Event
{
    // Properties
    // =========================================================================

    public $bundle;
    public $fieldData;
}
