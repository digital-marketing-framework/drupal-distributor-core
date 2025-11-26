<?php

namespace Drupal\dmf_distributor_core\Commands;

use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use Drupal\dmf_core\Registry\RegistryCollection;
use Drush\Commands\DrushCommands;

/**
 * Drush command for processing Anyrel distributor jobs.
 */
class DistributorWorkCommand extends DrushCommands
{
    public function __construct(
        protected RegistryCollection $registryCollection
    ) {
        parent::__construct();
    }

    /**
     * Process queued Anyrel distribution jobs.
     *
     * @command anyrel:distributor-work
     * @aliases anyrel-distributor-work
     * @usage anyrel:distributor-work
     *   Process queued distribution jobs.
     */
    public function work(): void
    {
        $this->output()->writeln('Processing Anyrel distribution queue...');

        /** @var RegistryInterface $registry */
        $registry = $this->registryCollection->getRegistryByClass(RegistryInterface::class);

        $queueProcessor = $registry->getQueueProcessor(
            $registry->getPersistentQueue(),
            $registry->getDistributor()
        );
        $queueProcessor->updateJobsAndProcessBatch();

        $this->output()->writeln('Anyrel distribution queue processed successfully.');
    }
}
