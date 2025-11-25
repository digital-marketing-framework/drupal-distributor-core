<?php

namespace Drupal\dmf_distributor_core\Entity;

use DateTime;
use DigitalMarketingFramework\Core\Model\Queue\JobInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Distributor Job entity.
 *
 * @ContentEntityType(
 *   id = "dmf_distributor_job",
 *   label = @Translation("Distributor Job"),
 *   base_table = "dmf_distributor_job",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "edit" = "Drupal\dmf_distributor_core\Form\JobForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 * )
 */
class Job extends ContentEntityBase implements JobInterface
{
    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array
    {
        $fields = parent::baseFieldDefinitions($entity_type);

        $fields['environment'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Environment'))
            ->setDescription(t('The environment identifier'))
            ->setDefaultValue('')
            ->setSettings([
                'max_length' => 255,
            ]);

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Created'))
            ->setDescription(t('The time that the job was created'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the job was last changed'));

        $fields['status'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Status'))
            ->setDescription(t('The job status'))
            ->setDefaultValue(0);

        $fields['skipped'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Skipped'))
            ->setDescription(t('Whether the job was skipped'))
            ->setDefaultValue(FALSE);

        $fields['status_message'] = BaseFieldDefinition::create('string_long')
            ->setLabel(t('Status Message'))
            ->setDescription(t('Status message text'))
            ->setDefaultValue('');

        $fields['serialized_data'] = BaseFieldDefinition::create('string_long')
            ->setLabel(t('Serialized Data'))
            ->setDescription(t('Serialized job data'))
            ->setDefaultValue('');

        $fields['hash'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Hash'))
            ->setDescription(t('Job hash'))
            ->setDefaultValue('')
            ->setSettings([
                'max_length' => 255,
            ]);

        $fields['label'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Label'))
            ->setDescription(t('Job label'))
            ->setDefaultValue('')
            ->setSettings([
                'max_length' => 255,
            ]);

        $fields['type'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Type'))
            ->setDescription(t('Job type'))
            ->setDefaultValue('')
            ->setSettings([
                'max_length' => 255,
            ]);

        $fields['retry_amount'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Retry Amount'))
            ->setDescription(t('Number of retry attempts'))
            ->setDefaultValue(0);

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): int|string|null
    {
        return $this->id();
    }

    /**
     * {@inheritdoc}
     */
    public function setId(int|string $id): void
    {
        $this->set('id', $id);
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironment(): string
    {
        return $this->get('environment')->value ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function setEnvironment(string $environment): void
    {
        $this->set('environment', $environment);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreated(): DateTime
    {
        return new DateTime('@' . $this->get('created')->value);
    }

    /**
     * {@inheritdoc}
     */
    public function setCreated(DateTime $created): void
    {
        $this->set('created', $created->getTimestamp());
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus(): int
    {
        return (int) $this->get('status')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(int $status): void
    {
        $this->set('status', $status);
    }

    /**
     * {@inheritdoc}
     */
    public function getSkipped(): bool
    {
        return (bool) $this->get('skipped')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setSkipped(bool $skipped): void
    {
        $this->set('skipped', $skipped);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusMessage(): string
    {
        return $this->get('status_message')->value ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function setStatusMessage(string $message): void
    {
        $this->set('status_message', $message);
    }

    /**
     * {@inheritdoc}
     */
    public function addStatusMessage(string $message): void
    {
        $current = $this->getStatusMessage();
        $new = $current !== '' ? $current . "\n" . $message : $message;
        $this->setStatusMessage($new);
    }

    /**
     * {@inheritdoc}
     */
    public function getChanged(): DateTime
    {
        return new DateTime('@' . $this->get('changed')->value);
    }

    /**
     * {@inheritdoc}
     */
    public function setChanged(DateTime $changed): void
    {
        $this->set('changed', $changed->getTimestamp());
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): array
    {
        $data = $this->getSerializedData();
        if ($data === '') {
            return [];
        }

        try {
            return json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setData(array $data): void
    {
        try {
            $serializedData = json_encode($data, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $serializedData = '';
        }

        $this->setSerializedData($serializedData);
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializedData(): string
    {
        return $this->get('serialized_data')->value ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializedData(string $serializedData): void
    {
        $this->set('serialized_data', $serializedData);
    }

    /**
     * {@inheritdoc}
     */
    public function getHash(): string
    {
        return $this->get('hash')->value ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function setHash(string $hash): void
    {
        $this->set('hash', $hash);
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(): string
    {
        return $this->get('label')->value ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function setLabel(string $label): void
    {
        $this->set('label', $label);
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->get('type')->value ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function setType(string $type): void
    {
        $this->set('type', $type);
    }

    /**
     * {@inheritdoc}
     */
    public function getRetryAmount(): int
    {
        return (int) $this->get('retry_amount')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setRetryAmount(int $amount): void
    {
        $this->set('retry_amount', $amount);
    }
}