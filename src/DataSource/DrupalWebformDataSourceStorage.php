<?php

namespace Drupal\dmf_distributor_core\DataSource;

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
class DrupalWebformDataSourceStorage extends DistributorDataSourceStorage {

  /**
   * Constructs a DrupalWebformDataSourceStorage object.
   *
   * @param string $keyword
   *   The plugin keyword.
   * @param RegistryInterface $registry
   *   The distributor registry.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
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
  public function getType(): string {
    return DrupalWebformDataSource::TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataSourceById(string $id, array $dataSourceContext): ?DistributorDataSourceInterface {
    $webformId = $this->getInnerIdentifier($id);
    $webform = $this->loadWebform($webformId);

    if ($webform === NULL) {
      return NULL;
    }

    $configurationDocument = $this->getConfigurationDocument($webform);

    return new DrupalWebformDataSource($webformId, $webform, $configurationDocument);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllDataSources(): array {
    $result = [];

    /** @var \Drupal\webform\WebformInterface[] $webforms */
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
   *   The webform ID (machine name).
   *
   * @return \Drupal\webform\WebformInterface|null
   *   The webform entity, or NULL if not found.
   */
  protected function loadWebform(string $webformId): ?WebformInterface {
    $webform = $this->entityTypeManager->getStorage('webform')->load($webformId);
    return $webform instanceof WebformInterface ? $webform : NULL;
  }

  /**
   * Checks if a webform has the Anyrel handler configured.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform entity.
   *
   * @return bool
   *   TRUE if the webform has Anyrel handler, FALSE otherwise.
   */
  protected function hasAnyrelHandler(WebformInterface $webform): bool {
    $handler = $this->getAnyrelHandler($webform);
    return $handler !== NULL;
  }

  /**
   * Gets the Anyrel handler from a webform.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform entity.
   *
   * @return \Drupal\dmf_distributor_core\Plugin\WebformHandler\AnyrelWebformHandler|null
   *   The Anyrel handler, or NULL if not configured.
   */
  protected function getAnyrelHandler(WebformInterface $webform): ?AnyrelWebformHandler {
    $handlers = $webform->getHandlers();
    foreach ($handlers as $handler) {
      if ($handler instanceof AnyrelWebformHandler) {
        return $handler;
      }
    }
    return NULL;
  }

  /**
   * Gets the configuration document from a webform's Anyrel handler.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform entity.
   *
   * @return string
   *   The YAML configuration document, or empty string if not configured.
   */
  protected function getConfigurationDocument(WebformInterface $webform): string {
    $handler = $this->getAnyrelHandler($webform);
    if ($handler === NULL) {
      return '';
    }

    $configuration = $handler->getConfiguration();
    return $configuration['settings']['configuration_document'] ?? '';
  }

}
