<?php

namespace Drupal\dmf_distributor_core\Registry\EventSubscriber;

use DigitalMarketingFramework\Distributor\Core\DistributorCoreInitialization;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dmf_distributor_core\DataSource\DrupalWebformDataSourceStorage;
use Drupal\dmf_distributor_core\Entity\JobRepository;
use Drupal\dmf_distributor_core\GlobalConfiguration\Schema\DistributorCoreGlobalConfigurationSchema;

/**
 * Event subscriber for distributor registry updates.
 */
class DistributorRegistryUpdateEventSubscriber extends AbstractDistributorRegistryUpdateEventSubscriber
{
    /**
     * Constructs a DistributorRegistryUpdateEventSubscriber object.
     *
     * @param JobRepository $queue
     *   The job repository (queue)
     * @param EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager
     */
    public function __construct(
        protected JobRepository $queue,
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
        $initialization = new DistributorCoreInitialization(
            'dmf_distributor_core',
            new DistributorCoreGlobalConfigurationSchema()
        );
        parent::__construct($initialization);
    }

    /**
     * {@inheritdoc}
     */
    protected function initServices(RegistryInterface $registry): void
    {
        parent::initServices($registry);

        // Register the persistent queue (JobRepository).
        // The repository handles conversion between Drupal entities (storage)
        // and core Job objects (business logic/templates).
        $registry->setPersistentQueue($this->queue);
    }

    /**
     * {@inheritdoc}
     */
    protected function initPlugins(RegistryInterface $registry): void
    {
        parent::initPlugins($registry);

        // Register Drupal webform data source storage
        $registry->registerDistributorSourceStorage(
            DrupalWebformDataSourceStorage::class,
            [$this->entityTypeManager]
        );
    }
}
