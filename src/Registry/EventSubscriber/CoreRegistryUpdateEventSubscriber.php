<?php

namespace Drupal\dmf_distributor_core\Registry\EventSubscriber;

use DigitalMarketingFramework\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Distributor\Core\DistributorCoreInitialization;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\dmf_core\Registry\EventSubscriber\AbstractCoreRegistryUpdateEventSubscriber;
use Drupal\dmf_distributor_core\Backend\Controller\SectionController\DistributorEditSectionController;
use Drupal\dmf_distributor_core\GlobalConfiguration\Schema\DistributorCoreGlobalConfigurationSchema;

/**
 * Event subscriber for Core registry updates from distributor package.
 */
class CoreRegistryUpdateEventSubscriber extends AbstractCoreRegistryUpdateEventSubscriber
{
    /**
     * Constructs a CoreRegistryUpdateEventSubscriber object.
     */
    public function __construct(
        protected EntityFormBuilderInterface $entityFormBuilder,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected RendererInterface $renderer,
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
    protected function initPlugins(RegistryInterface $registry): void
    {
        parent::initPlugins($registry);

        // Register Drupal distributor edit section controller with Drupal services
        $registry->registerBackendSectionController(
            DistributorEditSectionController::class,
            [
                $this->entityFormBuilder,
                $this->entityTypeManager,
                $this->renderer,
            ]
        );
    }
}