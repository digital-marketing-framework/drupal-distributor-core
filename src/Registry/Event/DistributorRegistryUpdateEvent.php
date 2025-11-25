<?php

namespace Drupal\dmf_distributor_core\Registry\Event;

use DigitalMarketingFramework\Core\Registry\RegistryUpdateType;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;

/**
 * Event for distributor registry updates.
 */
class DistributorRegistryUpdateEvent
{
    /**
     * Constructs a DistributorRegistryUpdateEvent object.
     *
     * @param \DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface $registry
     *   The distributor registry.
     * @param \DigitalMarketingFramework\Core\Registry\RegistryUpdateType $type
     *   The update type.
     */
    public function __construct(
        protected RegistryInterface $registry,
        protected RegistryUpdateType $type,
    ) {
    }

    /**
     * Gets the distributor registry.
     *
     * @return \DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface
     *   The distributor registry.
     */
    public function getRegistry(): RegistryInterface
    {
        return $this->registry;
    }

    /**
     * Gets the update type.
     *
     * @return \DigitalMarketingFramework\Core\Registry\RegistryUpdateType
     *   The update type.
     */
    public function getUpdateType(): RegistryUpdateType
    {
        return $this->type;
    }
}