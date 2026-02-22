<?php

namespace Drupal\dmf_distributor_core\DataSource;

use DigitalMarketingFramework\Core\Model\DataSource\DataSourceInterface;
use DigitalMarketingFramework\Distributor\Core\DataSource\DistributorDataSourceStorage;
use DigitalMarketingFramework\Distributor\Core\Model\DataSource\DistributorDataSourceInterface;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dmf_distributor_core\Model\DataSource\DrupalWebformDataSource;
use Drupal\dmf_distributor_core\Plugin\WebformHandler\AnyrelWebformHandler;
use Drupal\webform\WebformInterface;

/**
 * Data source storage for Drupal webforms.
 *
 * This storage plugin provides access to webforms that have the Anyrel
 * handler configured. It allows the job processor to load the configuration
 * document for a given webform submission.
 *
 * @extends DistributorDataSourceStorage<DrupalWebformDataSource>
 */
class DrupalWebformDataSourceStorage extends DistributorDataSourceStorage
{
    /**
     * Constructs a DrupalWebformDataSourceStorage object.
     *
     * @param string $keyword
     *   The plugin keyword
     * @param RegistryInterface $registry
     *   The distributor registry
     * @param EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager
     */
    public function __construct(
        string $keyword,
        RegistryInterface $registry,
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
        parent::__construct($keyword, $registry);
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return DrupalWebformDataSource::TYPE;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSourceByIdentifier(string $identifier): ?DistributorDataSourceInterface
    {
        if (!$this->matches($identifier)) {
            return null;
        }

        $webformId = $this->getInnerIdentifier($identifier);
        $webform = $this->loadWebform($webformId);

        if (!$webform instanceof WebformInterface) {
            return null;
        }

        $configurationDocument = $this->getConfigurationDocument($webform);

        return new DrupalWebformDataSource($webformId, $webform, $configurationDocument);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllDataSources(): array
    {
        $result = [];

        $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple();

        foreach ($webforms as $id => $webform) {
            if ($this->hasAnyrelHandler($webform)) {
                $configurationDocument = $this->getConfigurationDocument($webform);
                $result[] = new DrupalWebformDataSource($id, $webform, $configurationDocument);
            }
        }

        return $result;
    }

    /**
     * Loads a webform by its ID.
     *
     * @param string $webformId
     *   The webform ID (machine name)
     *
     * @return WebformInterface|null
     *   The webform entity, or NULL if not found
     */
    protected function loadWebform(string $webformId): ?WebformInterface
    {
        $webform = $this->entityTypeManager->getStorage('webform')->load($webformId);

        return $webform instanceof WebformInterface ? $webform : null;
    }

    /**
     * Checks if a webform has the Anyrel handler configured.
     *
     * @param WebformInterface $webform
     *   The webform entity
     *
     * @return bool
     *   TRUE if the webform has Anyrel handler, FALSE otherwise
     */
    protected function hasAnyrelHandler(WebformInterface $webform): bool
    {
        $handler = $this->getAnyrelHandler($webform);

        return $handler instanceof AnyrelWebformHandler;
    }

    /**
     * Gets the Anyrel handler from a webform.
     *
     * @param WebformInterface $webform
     *   The webform entity
     *
     * @return AnyrelWebformHandler|null
     *   The Anyrel handler, or NULL if not configured
     */
    protected function getAnyrelHandler(WebformInterface $webform): ?AnyrelWebformHandler
    {
        $handlers = $webform->getHandlers();
        foreach ($handlers as $handler) {
            if ($handler instanceof AnyrelWebformHandler) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * Gets the configuration document from a webform's Anyrel handler.
     *
     * @param WebformInterface $webform
     *   The webform entity
     *
     * @return string
     *   The YAML configuration document, or empty string if not configured
     */
    protected function getConfigurationDocument(WebformInterface $webform): string
    {
        $handler = $this->getAnyrelHandler($webform);
        if (!$handler instanceof AnyrelWebformHandler) {
            return '';
        }

        $configuration = $handler->getConfiguration();

        return $configuration['settings']['configuration_document'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function updateConfigurationDocument(DataSourceInterface $dataSource, string $document): void
    {
        if (!$dataSource instanceof DrupalWebformDataSource) {
            return;
        }

        $webformId = $this->getInnerIdentifier($dataSource->getIdentifier());
        $webform = $this->loadWebform($webformId);

        if (!$webform instanceof WebformInterface) {
            return;
        }

        $handler = $this->getAnyrelHandler($webform);
        if (!$handler instanceof AnyrelWebformHandler) {
            return;
        }

        $configuration = $handler->getConfiguration();
        $configuration['settings']['configuration_document'] = $document;
        $handler->setConfiguration($configuration);

        $webform->save();
    }
}
