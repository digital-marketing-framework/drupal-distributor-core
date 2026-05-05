<?php

namespace Drupal\dmf_distributor_core\Plugin\WebformHandler;

use DateTime;
use DigitalMarketingFramework\Core\ConfigurationDocument\ConfigurationDocumentManagerInterface;
use DigitalMarketingFramework\Core\ConfigurationEditor\MetaData;
use DigitalMarketingFramework\Core\GlobalConfiguration\Settings\CoreSettings;
use DigitalMarketingFramework\Core\Model\Data\Value\BooleanValue;
use DigitalMarketingFramework\Core\Model\Data\Value\DateTimeValue;
use DigitalMarketingFramework\Core\Model\Data\Value\IntegerValue;
use DigitalMarketingFramework\Core\Model\Data\Value\MultiValue;
use DigitalMarketingFramework\Core\Model\Data\Value\ValueInterface;
use DigitalMarketingFramework\Core\Registry\RegistryCollectionInterface;
use DigitalMarketingFramework\Core\Registry\RegistryInterface as CoreRegistryInterface;
use DigitalMarketingFramework\Core\Utility\GeneralUtility;
use DigitalMarketingFramework\Distributor\Core\Model\DataSet\SubmissionDataSet;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface as DistributorRegistryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
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
class AnyrelWebformHandler extends WebformHandlerBase
{
    protected RegistryCollectionInterface $registryCollection;

    protected DistributorRegistryInterface $distributorRegistry;

    protected ConfigurationDocumentManagerInterface $configurationDocumentManager;

    /**
     * {@inheritdoc}
     *
     * @param array<mixed> $configuration
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        /** @var static $instance */
        $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

        // Get registry collection (always first!)
        $registryCollection = $container->get('dmf_core.registry_collection');
        $instance->setRegistryCollection($registryCollection);

        return $instance;
    }

    /**
     * Sets the registry collection.
     *
     * @param RegistryCollectionInterface $registryCollection
     *   The registry collection service
     */
    public function setRegistryCollection(RegistryCollectionInterface $registryCollection): void
    {
        $this->registryCollection = $registryCollection;
        $this->distributorRegistry = $this->registryCollection->getRegistryByClass(DistributorRegistryInterface::class);
        $this->configurationDocumentManager = $this->distributorRegistry->getConfigurationDocumentManager();
    }

    /**
     * {@inheritdoc}
     *
     * @return array{configuration_document:string}
     */
    public function defaultConfiguration(): array
    {
        return [
            'configuration_document' => '',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param array<mixed> $form
     *
     * @return array<mixed>
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state): array
    {
        // Get core registry for backend rendering service.
        $coreRegistry = $this->registryCollection->getRegistryByClass(CoreRegistryInterface::class);
        $renderingService = $coreRegistry->getBackendRenderingService();

        // Build context identifier for this webform handler (webform:webform_id:handler_id).
        $webformId = $this->getWebform()->id() ?? 'new';
        $handlerId = $this->getHandlerId();
        $contextIdentifier = 'webform:' . $webformId . ':' . $handlerId;
        $uid = 'configuration-document:webform:' . $webformId . ':' . $handlerId;

        // Get configuration editor data attributes.
        $dataAttributes = $renderingService->getTextAreaDataAttributes(
            ready: true,
            mode: 'modal',
            readonly: false,
            globalDocument: false,
            documentType: MetaData::DEFAULT_DOCUMENT_TYPE,
            includes: true,
            parameters: [],
            contextIdentifier: $contextIdentifier,
            uid: $uid,
            documentName: $this->getWebform()->label() ?? '',
            contextType: 'form',
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
            '#required' => false,
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
     *
     * @param array<mixed> $form
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
    {
        parent::submitConfigurationForm($form, $form_state);
        $this->applyFormStateToConfiguration($form_state);
    }

    /**
     * @param array<mixed> $formElements
     *
     * @return ?array<mixed>
     */
    protected function getFieldDefinition(array $formElements, string $fieldName): ?array
    {
        if (isset($formElements[$fieldName])) {
            return $formElements[$fieldName];
        }

        foreach ($formElements as $name => $config) {
            if (str_starts_with((string)$name, '#')) {
                continue;
            }

            $childConfig = $this->getFieldDefinition($config, $fieldName);

            if ($childConfig !== null) {
                return $childConfig;
            }
        }

        return null;
    }

    protected function getDefaultTimezone(): string
    {
        return $this->distributorRegistry->getGlobalConfiguration()
          ->getGlobalSettings(CoreSettings::class)
          ->getDefaultTimezone();
    }

    /**
     * @param array<mixed> $form
     */
    protected function getFormDataField(array &$form, string $key, mixed $value): string|ValueInterface|null
    {
        $config = $this->getFieldDefinition($form['elements'] ?? [], $key);
        if ($config === null) {
            $this->getLogger('dmf_distributor_core')->error(sprintf('No webform field definition found for field "%s".', $key));

            return null;
        }

        switch ($config['#type'] ?? '') {
            case 'checkbox':
            case 'webform_terms_of_service':
                return new BooleanValue($value);

            case 'text_format':
                // Drupal already renders the formatted text, so $value['value'] contains the HTML output.
                return $value['value'];

            case 'date':
                if ($value === '') {
                    return '';
                }

                $format = $config['#date_date_format'] ?? 'Y-m-d';

                return new DateTimeValue($value, $format, $this->getDefaultTimezone());

            case 'datetime':
                if ($value === '') {
                    return '';
                }

                // Drupal bakes the field's timezone offset into the value string.
                // Parse it and extract the user's intended date/time, then re-interpret
                // in Anyrel's default timezone (same approach as TYPO3).
                $dt = new DateTime($value);
                $dateString = $dt->format('Y-m-d H:i:s');

                $dateFormat = $config['#date_date_format'] ?? 'Y-m-d';
                $timeFormat = $config['#date_time_format'] ?? 'H:i:s';
                $format = $dateFormat . ' ' . $timeFormat;

                return new DateTimeValue($dateString, $format, $this->getDefaultTimezone());

            case 'datelist':
                if ($value === '') {
                    return '';
                }

                // Drupal bakes the field's timezone offset into the value string.
                // Parse it and extract the user's intended date/time, then re-interpret
                // in Anyrel's default timezone (same approach as TYPO3).
                // The format is built from the active parts only (#date_part_order),
                // since datelist allows arbitrary part combinations.
                $dt = new DateTime($value);
                $dateString = $dt->format('Y-m-d H:i:s');

                $datePartVarMap = ['year' => 'Y', 'month' => 'm', 'day' => 'd'];
                $parts = $config['#date_part_order'] ?? ['year', 'month', 'day'];
                $dateParts = [];
                foreach ($datePartVarMap as $part => $var) {
                    if (in_array($part, $parts, true)) {
                        $dateParts[] = $var;
                    }
                }

                $timePartVarMap = ['hour' => 'H', 'minute' => 'i', 'second' => 's'];
                $timeParts = [];
                foreach ($timePartVarMap as $part => $var) {
                    if (in_array($part, $parts, true)) {
                        $timeParts[] = $var;
                    }
                }

                $format = trim(implode('-', $dateParts) . ' ' . implode(':', $timeParts));

                return new DateTimeValue($dateString, $format, $this->getDefaultTimezone());

            case 'webform_scale':
                if ($value === null) {
                    return '';
                }

                return new IntegerValue($value);

            case 'webform_likert':
                $multiValue = new MultiValue();
                foreach ($value as $childKey => $childValue) {
                    $multiValue[$childKey] = (string)$childValue;
                }

                return $multiValue;

            default:
                if ($value === null) {
                    return '';
                }

                return GeneralUtility::convertToFieldValue($value);
        }
    }

    /**
     * @param array<mixed> $form
     *
     * @return array<string,string|ValueInterface>
     */
    protected function getFormData(array &$form, WebformSubmissionInterface $webform_submission): array
    {
        $data = [];
        $formData = $webform_submission->getData();
        foreach ($formData as $key => $value) {
            $formattedValue = $this->getFormDataField($form, $key, $value);
            if ($formattedValue === null) {
                $this->getLogger('dmf_distributor_core')->warning(sprintf('Skipping unknown field type "%s".', $key));
                continue;
            }

            $data[$key] = $formattedValue;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @param array<mixed> $form
     */
    public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission): void
    {
        // Get configuration stack from document
        $configurationStack = $this->getConfigurationStack();

        // Get form submission data (pass through as-is)
        $formData = $this->getFormData($form, $webform_submission);

        // Build data source ID (webform:webform_id:handler_id)
        $dataSourceId = 'webform:' . $webform_submission->getWebform()->id() . ':' . $this->getHandlerId();

        // Build and process submission
        $submission = new SubmissionDataSet($dataSourceId, $formData, $configurationStack);
        $submission->getContext()->setResponsive(true);

        // Get distributor and process
        $distributor = $this->distributorRegistry->getDistributor();
        $distributor->process($submission);

        // Apply response data (sets cookies via PHP setcookie())
        $submission->getContext()->applyResponseData();

        $redirectUrl = $submission->getContext()->getResponseRedirect();
        if ($redirectUrl !== null && $redirectUrl !== '') {
            $form_state->setTemporaryValue('anyrel_redirect_url', $redirectUrl);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param array<mixed> $form
     */
    public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission): void
    {
        $redirectUrl = $form_state->getTemporaryValue('anyrel_redirect_url');
        if (is_string($redirectUrl) && $redirectUrl !== '') {
            $form_state->setResponse(new TrustedRedirectResponse($redirectUrl));
        }
    }

    /**
     * Gets the configuration stack from the configuration document.
     *
     * @return array<array<string,mixed>>
     *   The configuration stack
     */
    protected function getConfigurationStack(): array
    {
        $configurationDocument = $this->configuration['configuration_document'] ?? '';

        if ($configurationDocument === '') {
            return [];
        }

        $schemaDocument = $this->registryCollection->getConfigurationSchemaDocument();

        return $this->configurationDocumentManager->getConfigurationStackFromDocument($configurationDocument, $schemaDocument);
    }
}
