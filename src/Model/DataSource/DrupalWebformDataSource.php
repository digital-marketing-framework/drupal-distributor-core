<?php

namespace Drupal\dmf_distributor_core\Model\DataSource;

use DigitalMarketingFramework\Core\SchemaDocument\FieldDefinition\FieldDefinition;
use DigitalMarketingFramework\Core\SchemaDocument\FieldDefinition\FieldListDefinition;
use DigitalMarketingFramework\Core\Utility\GeneralUtility;
use DigitalMarketingFramework\Distributor\Core\Model\DataSource\DistributorDataSource;
use Drupal\webform\WebformInterface;

/**
 * Data source model for Drupal webforms.
 */
class DrupalWebformDataSource extends DistributorDataSource
{
    public const TYPE = 'webform';

    /**
     * Constructs a DrupalWebformDataSource object.
     *
     * @param string $webformId
     *   The webform ID (machine name)
     * @param string $handlerId
     *   The Anyrel handler instance ID
     * @param WebformInterface $webform
     *   The webform entity
     * @param string $handlerLabel
     *   The handler label for display purposes
     * @param string $configurationDocument
     *   The YAML configuration document from the Anyrel handler
     */
    public function __construct(
        protected string $webformId,
        protected string $handlerId,
        protected WebformInterface $webform,
        string $handlerLabel,
        string $configurationDocument,
    ) {
        $webformLabel = $this->webform->label() ?? $webformId;
        $name = $webformLabel . ' (' . $handlerLabel . ')';
        $hash = GeneralUtility::calculateHash($this->webform->getElementsDecoded());

        parent::__construct(
            static::TYPE,
            static::TYPE . ':' . $webformId . ':' . $handlerId,
            $name,
            $hash,
            $configurationDocument
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldListDefinition(): FieldListDefinition
    {
        $fieldListDefinition = parent::getFieldListDefinition();
        $elements = $this->webform->getElementsDecoded();
        $this->readFields($elements, $fieldListDefinition);

        return $fieldListDefinition;
    }

    /**
     * Recursively reads webform elements and adds field definitions.
     *
     * @param array<string, mixed> $elements
     *   The webform elements array
     * @param FieldListDefinition $fieldListDefinition
     *   The field list definition to populate
     */
    protected function readFields(array $elements, FieldListDefinition $fieldListDefinition): void
    {
        foreach ($elements as $key => $element) {
            // Skip non-element entries (like #title, #type at root level).
            if (str_starts_with($key, '#')) {
                continue;
            }

            // Check if this element has children (container element).
            $hasChildren = false;
            foreach ($element as $childKey => $childValue) {
                if (!str_starts_with((string)$childKey, '#') && is_array($childValue)) {
                    $hasChildren = true;
                    break;
                }
            }

            if ($hasChildren) {
                // Recurse into container elements.
                $this->readFields($element, $fieldListDefinition);
            } else {
                // This is a field element.
                $this->addFieldDefinition($key, $element, $fieldListDefinition);
            }
        }
    }

    /**
     * Adds a field definition for a webform element.
     *
     * @param string $name
     *   The element key/name
     * @param array<string, mixed> $element
     *   The element definition
     * @param FieldListDefinition $fieldListDefinition
     *   The field list definition to add to
     */
    protected function addFieldDefinition(string $name, array $element, FieldListDefinition $fieldListDefinition): void
    {
        $type = $element['#type'] ?? 'textfield';
        $label = $element['#title'] ?? $name;
        $required = (bool)($element['#required'] ?? false);

        $fieldType = FieldDefinition::TYPE_UNKNOWN;
        $multiValue = null;
        $values = null;

        // Extract options for select/checkbox elements.
        $options = $element['#options'] ?? [];
        if (is_string($options)) {
            // @todo handle referenced options
        } elseif ($options !== []) {
            $values = array_keys($options);
        }

        // Map Drupal webform element types to Anyrel field types.
        switch ($type) {
            case 'textfield':
            case 'textarea':
            case 'email':
            case 'tel':
            case 'url':
            case 'hidden':
            case 'password':
            case 'date':
            case 'datetime':
            case 'time':
            case 'select':
            case 'radios':
            case 'webform_radios_other':
            case 'webform_select_other':
                $fieldType = FieldDefinition::TYPE_STRING;
                $multiValue = false;
                break;

            case 'number':
            case 'range':
                $fieldType = FieldDefinition::TYPE_INTEGER;
                $multiValue = false;
                break;

            case 'checkbox':
                $fieldType = FieldDefinition::TYPE_BOOLEAN;
                $multiValue = false;
                break;

            case 'checkboxes':
            case 'webform_checkboxes_other':
            case 'webform_entity_checkboxes':
                $fieldType = FieldDefinition::TYPE_STRING;
                $multiValue = true;
                break;

            case 'managed_file':
            case 'webform_document_file':
            case 'webform_image_file':
            case 'webform_audio_file':
            case 'webform_video_file':
                $fieldType = FieldDefinition::TYPE_STRING;
                break;
        }

        $fieldListDefinition->addField(
            new FieldDefinition($name, $fieldType, $label, $multiValue, values: $values, required: $required)
        );
    }
}
