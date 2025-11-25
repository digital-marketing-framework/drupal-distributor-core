<?php

namespace Drupal\dmf_distributor_core\Backend\Controller\SectionController;

use DigitalMarketingFramework\Core\Registry\RegistryInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\dmf_core\Backend\Controller\SectionController\EditSectionController;

/**
 * Drupal-specific distributor job edit section controller.
 *
 * Handles the 'edit' action for distributor jobs using Drupal's EntityForm.
 */
class DistributorEditSectionController extends EditSectionController
{
    /**
     * Constructor.
     */
    public function __construct(
        string $keyword,
        RegistryInterface $registry,
        EntityFormBuilderInterface $entityFormBuilder,
        EntityTypeManagerInterface $entityTypeManager,
        RendererInterface $renderer,
    ) {
        parent::__construct(
            $keyword,
            $registry,
            'distributor',
            $entityFormBuilder,
            $entityTypeManager,
            $renderer
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getEntityTypeId(): string
    {
        return 'dmf_distributor_job';
    }

    /**
     * {@inheritdoc}
     */
    protected function getListRoute(): string
    {
        return 'page.distributor.list';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditRoute(): string
    {
        return 'page.distributor.edit';
    }
}
