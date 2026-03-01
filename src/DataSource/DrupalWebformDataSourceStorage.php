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
 * handler configured. Each Anyrel handler on a webform is a separate
 * data source, identified by webform:<webformId>:<handlerId>.
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
     * Parses the inner identifier into webform ID and handler ID.
     *
     * Supports both the current format (webformId:handlerId) and the
     * legacy format (webformId only, without handler ID).
     *
     * @param string $identifier
     *   The full data source identifier (e.g. "webform:my_form:anyrel")
     *
     * @return array{webformId:string,handlerId:?string}
     *   The parsed parts
     */
    protected function parseIdentifier(string $identifier): array
    {
        $innerIdentifier = $this->getInnerIdentifier($identifier);
        $parts = explode(':', $innerIdentifier, 2);

        return [
            'webformId' => $parts[0],
            'handlerId' => $parts[1] ?? null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSourceByIdentifier(string $identifier): ?DistributorDataSourceInterface
    {
        if (!$this->matches($identifier)) {
            return null;
        }

        ['webformId' => $webformId, 'handlerId' => $handlerId] = $this->parseIdentifier($identifier);

        $webform = $this->loadWebform($webformId);
        if (!$webform instanceof WebformInterface) {
            return null;
        }

        // Find the specific handler, or fall back to first handler for legacy identifiers.
        $handler = $handlerId !== null
            ? $this->getAnyrelHandlerById($webform, $handlerId)
            : $this->getFirstAnyrelHandler($webform);

        if (!$handler instanceof AnyrelWebformHandler) {
            return null;
        }

        return $this->createDataSource($webformId, $handler, $webform);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllDataSources(): array
    {
        $result = [];

        $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple();

        foreach ($webforms as $id => $webform) {
            foreach ($this->getAnyrelHandlers($webform) as $handler) {
                $result[] = $this->createDataSource($id, $handler, $webform);
            }
        }

        return $result;
    }

    /**
     * Creates a DrupalWebformDataSource from a webform and handler.
     *
     * @param string $webformId
     *   The webform ID (machine name)
     * @param AnyrelWebformHandler $handler
     *   The Anyrel handler instance
     * @param WebformInterface $webform
     *   The webform entity
     *
     * @return DrupalWebformDataSource
     *   The data source object
     */
    protected function createDataSource(string $webformId, AnyrelWebformHandler $handler, WebformInterface $webform): DrupalWebformDataSource
    {
        $configuration = $handler->getConfiguration();
        $configurationDocument = $configuration['settings']['configuration_document'] ?? '';

        return new DrupalWebformDataSource(
            $webformId,
            $handler->getHandlerId(),
            $webform,
            $handler->label(),
            $configurationDocument
        );
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
     * Gets all Anyrel handlers from a webform.
     *
     * @param WebformInterface $webform
     *   The webform entity
     *
     * @return AnyrelWebformHandler[]
     *   All Anyrel handler instances on the webform
     */
    protected function getAnyrelHandlers(WebformInterface $webform): array
    {
        $result = [];
        $handlers = $webform->getHandlers();
        foreach ($handlers as $handler) {
            if ($handler instanceof AnyrelWebformHandler) {
                $result[] = $handler;
            }
        }

        return $result;
    }

    /**
     * Gets a specific Anyrel handler by its instance ID.
     *
     * @param WebformInterface $webform
     *   The webform entity
     * @param string $handlerId
     *   The handler instance ID (e.g. "anyrel", "anyrel_1")
     *
     * @return AnyrelWebformHandler|null
     *   The handler, or NULL if not found
     */
    protected function getAnyrelHandlerById(WebformInterface $webform, string $handlerId): ?AnyrelWebformHandler
    {
        $handlers = $webform->getHandlers();
        foreach ($handlers as $handler) {
            if ($handler instanceof AnyrelWebformHandler && $handler->getHandlerId() === $handlerId) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * Gets the first Anyrel handler from a webform.
     *
     * Used as fallback for legacy identifiers without handler ID.
     *
     * @param WebformInterface $webform
     *   The webform entity
     *
     * @return AnyrelWebformHandler|null
     *   The first Anyrel handler, or NULL if not configured
     */
    protected function getFirstAnyrelHandler(WebformInterface $webform): ?AnyrelWebformHandler
    {
        $handlers = $this->getAnyrelHandlers($webform);

        return $handlers[0] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function updateConfigurationDocument(DataSourceInterface $dataSource, string $document): void
    {
        if (!$dataSource instanceof DrupalWebformDataSource) {
            return;
        }

        ['webformId' => $webformId, 'handlerId' => $handlerId] = $this->parseIdentifier($dataSource->getIdentifier());

        $webform = $this->loadWebform($webformId);
        if (!$webform instanceof WebformInterface) {
            return;
        }

        $handler = $handlerId !== null
            ? $this->getAnyrelHandlerById($webform, $handlerId)
            : $this->getFirstAnyrelHandler($webform);

        if (!$handler instanceof AnyrelWebformHandler) {
            return;
        }

        $configuration = $handler->getConfiguration();
        $configuration['settings']['configuration_document'] = $document;
        $handler->setConfiguration($configuration);

        $webform->save();
    }
}
