<?php

namespace Drupal\dmf_distributor_core\Plugin\WebformHandler;

use DigitalMarketingFramework\Core\ConfigurationDocument\ConfigurationDocumentManagerInterface;
use DigitalMarketingFramework\Core\ConfigurationEditor\MetaData;
use DigitalMarketingFramework\Core\Registry\RegistryCollectionInterface;
use DigitalMarketingFramework\Core\Registry\RegistryInterface as CoreRegistryInterface;
use DigitalMarketingFramework\Distributor\Core\Model\DataSet\SubmissionDataSet;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface as DistributorRegistryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Anyrel form submission handler.
 *
 * @WebformHandler(
 *     id="anyrel",
 *     label=@Translation("Anyrel"),
 *     category=@Translation("Marketing"),
 *     description=@Translation("Distribute form data via Anyrel"),
 *     cardinality=\Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *     results=\Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class AnyrelWebformHandler extends WebformHandlerBase {

  /**
   * The registry collection.
   *
   * @var RegistryCollectionInterface
   */
  protected RegistryCollectionInterface $registryCollection;

  /**
   * The distributor registry.
   *
   * @var DistributorRegistryInterface
   */
  protected DistributorRegistryInterface $distributorRegistry;

  /**
   * The configuration document manager.
   *
   * @var ConfigurationDocumentManagerInterface
   */
  protected ConfigurationDocumentManagerInterface $configurationDocumentManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var AnyrelWebformHandler $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Get registry collection (always first!)
    /** @var RegistryCollectionInterface $registryCollection */
    $registryCollection = $container->get('dmf_core.registry_collection');
    $instance->setRegistryCollection($registryCollection);

    return $instance;
  }

  /**
   * Sets the registry collection.
   *
   * @param RegistryCollectionInterface $registryCollection
   *   The registry collection service.
   */
  public function setRegistryCollection(RegistryCollectionInterface $registryCollection): void {
    $this->registryCollection = $registryCollection;
    $this->distributorRegistry = $this->registryCollection->getRegistryByClass(DistributorRegistryInterface::class);
    $this->configurationDocumentManager = $this->distributorRegistry->getConfigurationDocumentManager();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'configuration_document' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Get core registry for backend rendering service.
    /** @var CoreRegistryInterface $coreRegistry */
    $coreRegistry = $this->registryCollection->getRegistryByClass(CoreRegistryInterface::class);
    $renderingService = $coreRegistry->getBackendRenderingService();

    // Build context identifier for this webform (webform:webform_id).
    $webformId = $this->getWebform()->id() ?? 'new';
    $contextIdentifier = 'webform:' . $webformId;
    $uid = 'configuration-document:webform:' . $webformId;

    // Get configuration editor data attributes.
    $dataAttributes = $renderingService->getTextAreaDataAttributes(
      ready: TRUE,
      mode: 'modal',
      readonly: FALSE,
      globalDocument: FALSE,
      documentType: MetaData::DEFAULT_DOCUMENT_TYPE,
      includes: TRUE,
      parameters: [],
      contextIdentifier: $contextIdentifier,
      uid: $uid
    );

    // Convert data attributes to Drupal's attribute format.
    $attributes = ['class' => ['dmf-configuration-document']];
    foreach ($dataAttributes as $key => $value) {
      $attributes['data-' . $key] = $value;
    }

    $form['configuration_document'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration'),
      '#description' => $this->t('Anyrel configuration for distribution routes, data providers, and settings.'),
      '#default_value' => $this->configuration['configuration_document'],
      '#required' => FALSE,
      '#rows' => 10,
      '#attributes' => $attributes,
    ];

    // Attach configuration editor library.
    // This library is dynamically built in dmf_core_library_info_build()
    // and works with AJAX-loaded forms (assets are injected via add_js/add_css
    // AJAX commands).
    $form['#attached']['library'][] = 'dmf_core/configuration-editor';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // Get configuration stack from document
    $configurationStack = $this->getConfigurationStack();

    // Get form submission data (pass through as-is)
    $formData = $webform_submission->getData();

    // Build data source ID (webform:webform_id)
    $dataSourceId = 'webform:' . $webform_submission->getWebform()->id();

    // Create submission context (empty for now, can be extended)
    $dataSourceContext = [];

    // Build and process submission
    $submission = new SubmissionDataSet($dataSourceId, $dataSourceContext, $formData, $configurationStack);
    $submission->getContext()->setResponsive(TRUE);

    // Get distributor and process
    $distributor = $this->distributorRegistry->getDistributor();
    $distributor->process($submission);

    // Apply response data (sets cookies via PHP setcookie())
    $submission->getContext()->applyResponseData();
  }

  /**
   * Gets the configuration stack from the configuration document.
   *
   * @return array<array<string,mixed>>
   *   The configuration stack.
   */
  protected function getConfigurationStack(): array {
    $configurationDocument = $this->configuration['configuration_document'] ?? '';

    if (empty($configurationDocument)) {
      return [];
    }

    return $this->configurationDocumentManager->getConfigurationStackFromDocument($configurationDocument);
  }

}
