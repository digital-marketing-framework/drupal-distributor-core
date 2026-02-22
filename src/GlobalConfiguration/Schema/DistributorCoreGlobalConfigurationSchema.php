<?php

namespace Drupal\dmf_distributor_core\GlobalConfiguration\Schema;

use DigitalMarketingFramework\Distributor\Core\GlobalConfiguration\Schema\DistributorCoreGlobalConfigurationSchema as OriginalDistributorCoreGlobalConfigurationSchema;
use Drupal\dmf_distributor_core\Queue\GlobalConfiguration\Schema\QueueSchema;

/**
 * Drupal-specific distributor global configuration schema.
 *
 * Extends the core schema with Drupal-specific queue settings (cron integration).
 */
class DistributorCoreGlobalConfigurationSchema extends OriginalDistributorCoreGlobalConfigurationSchema
{
    public function __construct()
    {
        parent::__construct(new QueueSchema());
    }
}
