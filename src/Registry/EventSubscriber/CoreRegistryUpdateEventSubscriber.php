<?php

namespace Drupal\dmf_distributor_core\Registry\EventSubscriber;

use Drupal\dmf_core\Registry\EventSubscriber\AbstractCoreRegistryUpdateEventSubscriber;
use Drupal\dmf_distributor_core\DrupalDistributorCoreInitialization;

/**
 * Event subscriber for Core registry updates from distributor package.
 */
class CoreRegistryUpdateEventSubscriber extends AbstractCoreRegistryUpdateEventSubscriber
{
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- narrowed type hint for runtime enforcement
    public function __construct(
        DrupalDistributorCoreInitialization $initialization,
    ) {
        parent::__construct($initialization);
    }
}
