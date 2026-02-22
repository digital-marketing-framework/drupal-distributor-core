<?php

namespace Drupal\dmf_distributor_core\Registry;

use DigitalMarketingFramework\Core\Registry\RegistryUpdateType;
use DigitalMarketingFramework\Distributor\Core\Registry\Registry as CoreDistributorRegistry;
use Drupal\dmf_core\Registry\Event\CoreRegistryUpdateEvent;
use Drupal\dmf_distributor_core\Registry\Event\DistributorRegistryUpdateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Distributor registry for Drupal.
 */
class Registry extends CoreDistributorRegistry
{
    /**
     * Constructs a Registry object.
     *
     * @param EventDispatcherInterface $eventDispatcher
     *   The event dispatcher
     */
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        // Dispatch core registry update events
        $this->eventDispatcher->dispatch(
            new CoreRegistryUpdateEvent($this, RegistryUpdateType::GLOBAL_CONFIGURATION)
        );
        $this->eventDispatcher->dispatch(
            new CoreRegistryUpdateEvent($this, RegistryUpdateType::SERVICE)
        );
        $this->eventDispatcher->dispatch(
            new CoreRegistryUpdateEvent($this, RegistryUpdateType::PLUGIN)
        );

        // Dispatch distributor registry update events
        $this->eventDispatcher->dispatch(
            new DistributorRegistryUpdateEvent($this, RegistryUpdateType::GLOBAL_CONFIGURATION)
        );
        $this->eventDispatcher->dispatch(
            new DistributorRegistryUpdateEvent($this, RegistryUpdateType::SERVICE)
        );
        $this->eventDispatcher->dispatch(
            new DistributorRegistryUpdateEvent($this, RegistryUpdateType::PLUGIN)
        );
    }
}
