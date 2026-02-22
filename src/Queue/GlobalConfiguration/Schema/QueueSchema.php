<?php

namespace Drupal\dmf_distributor_core\Queue\GlobalConfiguration\Schema;

use DigitalMarketingFramework\Core\Queue\GlobalConfiguration\Schema\QueueSchema as CoreQueueSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\BooleanSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\IntegerSchema;

/**
 * Drupal-specific queue schema with cron integration settings.
 */
class QueueSchema extends CoreQueueSchema
{
    public const KEY_CRON_ENABLED = 'cronEnabled';

    public const DEFAULT_CRON_ENABLED = false;

    public const KEY_CRON_MIN_INTERVAL = 'cronMinInterval';

    public const DEFAULT_CRON_MIN_INTERVAL = 60;

    public function __construct()
    {
        parent::__construct();

        $cronEnabledSchema = new BooleanSchema(static::DEFAULT_CRON_ENABLED);
        $cronEnabledSchema->getRenderingDefinition()->setLabel('Enable cron task');
        $this->addProperty(static::KEY_CRON_ENABLED, $cronEnabledSchema);

        $cronMinIntervalSchema = new IntegerSchema(static::DEFAULT_CRON_MIN_INTERVAL);
        $cronMinIntervalSchema->getRenderingDefinition()->setLabel('Minimum cron interval (in seconds)');
        $this->addProperty(static::KEY_CRON_MIN_INTERVAL, $cronMinIntervalSchema);
    }
}
