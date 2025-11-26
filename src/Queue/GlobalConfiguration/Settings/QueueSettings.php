<?php

namespace Drupal\dmf_distributor_core\Queue\GlobalConfiguration\Settings;

use DigitalMarketingFramework\Distributor\Core\Queue\GlobalConfiguration\Settings\QueueSettings as DistributorCoreQueueSettings;
use Drupal\dmf_distributor_core\Queue\GlobalConfiguration\Schema\QueueSchema;

/**
 * Drupal-specific queue settings with cron integration options.
 */
class QueueSettings extends DistributorCoreQueueSettings
{
    /**
     * Whether the cron task is enabled.
     */
    public function isCronEnabled(): bool
    {
        return $this->get(QueueSchema::KEY_CRON_ENABLED);
    }

    /**
     * Get the minimum interval between cron runs (in seconds).
     */
    public function getCronMinInterval(): int
    {
        return $this->get(QueueSchema::KEY_CRON_MIN_INTERVAL);
    }
}