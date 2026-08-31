<?php

namespace Drupal\dmf_distributor_core;

use DigitalMarketingFramework\Core\Backend\Controller\SectionController\SectionControllerInterface;
use DigitalMarketingFramework\Core\Backend\UriRouteResolver\UriRouteResolverInterface;
use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use DigitalMarketingFramework\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Distributor\Core\DataProvider\DataProviderInterface;
use DigitalMarketingFramework\Distributor\Core\DataSource\DistributorDataSourceStorageInterface;
use DigitalMarketingFramework\Distributor\Core\DistributorCoreInitialization;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface as DistributorRegistryInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\dmf_core\DrupalInitialization;
use Drupal\dmf_distributor_core\Backend\Controller\SectionController\DistributorEditSectionController;
use Drupal\dmf_distributor_core\Backend\UriRouteResolver\DrupalWebformDataSourceEditUriRouteResolver;
use Drupal\dmf_distributor_core\DataProvider\LanguageCodeDataProvider;
use Drupal\dmf_distributor_core\DataSource\DrupalWebformDataSourceStorage;
use Drupal\dmf_distributor_core\Entity\JobRepository;
use Drupal\dmf_distributor_core\GlobalConfiguration\Schema\DistributorCoreGlobalConfigurationSchema;

class DrupalDistributorCoreInitialization extends DrupalInitialization
{
    protected const PLUGINS = [
        RegistryDomain::CORE => [
            UriRouteResolverInterface::class => [
                DrupalWebformDataSourceEditUriRouteResolver::class,
            ],
            SectionControllerInterface::class => [
                DistributorEditSectionController::class,
            ],
        ],
        RegistryDomain::DISTRIBUTOR => [
            DataProviderInterface::class => [
                LanguageCodeDataProvider::class,
            ],
            DistributorDataSourceStorageInterface::class => [
                DrupalWebformDataSourceStorage::class,
            ],
        ],
    ];

    public function __construct(
        protected JobRepository $queue,
        protected EntityFormBuilderInterface $entityFormBuilder,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected RendererInterface $renderer,
        protected LanguageManagerInterface $languageManager,
    ) {
        parent::__construct(
            inner: new DistributorCoreInitialization('dmf_distributor_core'),
            packageName: 'drupal-distributor-core',
            packageAlias: 'dmf_distributor_core',
            globalConfigurationSchema: new DistributorCoreGlobalConfigurationSchema(),
        );
    }

    protected function getAdditionalPluginArguments(string $interface, string $pluginClass, RegistryInterface $registry): array
    {
        if ($pluginClass === DistributorEditSectionController::class) {
            return [
                $this->entityFormBuilder,
                $this->entityTypeManager,
                $this->renderer,
            ];
        }

        if ($pluginClass === DrupalWebformDataSourceStorage::class) {
            return [$this->entityTypeManager];
        }

        if ($pluginClass === LanguageCodeDataProvider::class) {
            return [$this->languageManager];
        }

        return parent::getAdditionalPluginArguments($interface, $pluginClass, $registry);
    }

    public function initServices(string $domain, RegistryInterface $registry): void
    {
        parent::initServices($domain, $registry);

        if ($registry instanceof DistributorRegistryInterface) {
            $registry->setPersistentQueue($this->queue);
        }
    }
}
