<?php

namespace Drupal\dmf_distributor_core\Registry\EventSubscriber;

use DigitalMarketingFramework\Core\InitializationInterface;
use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use DigitalMarketingFramework\Core\Registry\RegistryUpdateType;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use Drupal\dmf_core\DrupalInitialization;
use Drupal\dmf_core\DrupalInitializationInterface;
use Drupal\dmf_distributor_core\Registry\Event\DistributorRegistryUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Abstract base class for distributor registry update event subscribers.
 */
abstract class AbstractDistributorRegistryUpdateEventSubscriber implements EventSubscriberInterface
{
    protected DrupalInitializationInterface $initialization;

    /**
     * Constructs an AbstractDistributorRegistryUpdateEventSubscriber object.
     *
     * @param InitializationInterface $initialization
     *   The initialization service
     */
    public function __construct(
        InitializationInterface $initialization,
    ) {
        if ($initialization instanceof DrupalInitializationInterface) {
            $this->initialization = $initialization;
        } else {
            $this->initialization = new DrupalInitialization(inner: $initialization);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            DistributorRegistryUpdateEvent::class => 'onRegistryUpdate',
        ];
    }

    /**
     * Initializes global configuration.
     *
     * @param RegistryInterface $registry
     *   The distributor registry
     */
    protected function initGlobalConfiguration(RegistryInterface $registry): void
    {
        $this->initialization->initGlobalConfiguration(RegistryDomain::DISTRIBUTOR, $registry);
    }

    /**
     * Initializes services.
     *
     * @param RegistryInterface $registry
     *   The distributor registry
     */
    protected function initServices(RegistryInterface $registry): void
    {
        $this->initialization->initServices(RegistryDomain::DISTRIBUTOR, $registry);
    }

    /**
     * Initializes plugins.
     *
     * @param RegistryInterface $registry
     *   The distributor registry
     */
    protected function initPlugins(RegistryInterface $registry): void
    {
        $this->initialization->initPlugins(RegistryDomain::DISTRIBUTOR, $registry);
    }

    /**
     * Handles registry update event.
     *
     * @param DistributorRegistryUpdateEvent $event
     *   The event
     */
    public function onRegistryUpdate(DistributorRegistryUpdateEvent $event): void
    {
        $registry = $event->getRegistry();

        // always init meta data
        $this->initialization->initMetaData($registry);

        // init rest depending on update type
        $type = $event->getUpdateType();
        switch ($type) {
            case RegistryUpdateType::GLOBAL_CONFIGURATION:
                $this->initGlobalConfiguration($registry);
                break;

            case RegistryUpdateType::SERVICE:
                $this->initServices($registry);
                break;

            case RegistryUpdateType::PLUGIN:
                $this->initPlugins($registry);
                break;
        }
    }
}
