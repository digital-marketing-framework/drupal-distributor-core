<?php

namespace Drupal\dmf_distributor_core\Registry\EventSubscriber;

use Drupal\dmf_distributor_core\DrupalDistributorCoreInitialization;

/**
 * Event subscriber for distributor registry updates.
 */
class DistributorRegistryUpdateEventSubscriber extends AbstractDistributorRegistryUpdateEventSubscriber
{
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- narrowed type hint for runtime enforcement
    public function __construct(
        DrupalDistributorCoreInitialization $initialization,
    ) {
        parent::__construct($initialization);
    }
}
