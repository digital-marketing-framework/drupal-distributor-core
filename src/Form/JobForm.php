<?php

namespace Drupal\dmf_distributor_core\Form;

use DigitalMarketingFramework\Core\Queue\QueueInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dmf_distributor_core\Entity\Job;

/**
 * Form handler for Distributor Job edit forms.
 */
class JobForm extends ContentEntityForm
{
    /**
     * {@inheritdoc}
     */
    public function form(array $form, FormStateInterface $form_state): array
    {
        $form = parent::form($form, $form_state);

        /** @var Job $job */
        $job = $this->entity;

        // Add vertical tabs container
        $form['tabs'] = [
            '#type' => 'vertical_tabs',
            '#weight' => 99,
        ];

        // General tab
        $form['general'] = [
            '#type' => 'details',
            '#title' => $this->t('General'),
            '#group' => 'tabs',
            '#weight' => 0,
            '#open' => TRUE,
        ];

        $form['general']['label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Label'),
            '#maxlength' => 255,
            '#default_value' => $job->getLabel(),
            '#description' => $this->t('The label of the job.'),
        ];

        $form['general']['type'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Type'),
            '#maxlength' => 255,
            '#default_value' => $job->getType(),
            '#description' => $this->t('The job type.'),
        ];

        // Status options mapping
        $statusOptions = [
            QueueInterface::STATUS_QUEUED => $this->t('Queued'),
            QueueInterface::STATUS_PENDING => $this->t('Pending'),
            QueueInterface::STATUS_RUNNING => $this->t('Running'),
            QueueInterface::STATUS_DONE => $this->t('Done'),
            QueueInterface::STATUS_FAILED => $this->t('Failed'),
        ];

        $form['general']['status'] = [
            '#type' => 'select',
            '#title' => $this->t('Status'),
            '#options' => $statusOptions,
            '#default_value' => $job->getStatus(),
            '#description' => $this->t('The current status of the job.'),
        ];

        $form['general']['skipped'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Skipped'),
            '#default_value' => $job->getSkipped(),
            '#description' => $this->t('Whether this job was skipped.'),
        ];

        $form['general']['retry_amount'] = [
            '#type' => 'number',
            '#title' => $this->t('Retry Amount'),
            '#default_value' => $job->getRetryAmount(),
            '#description' => $this->t('Number of retry attempts.'),
            '#min' => 0,
        ];

        // Status message tab
        $form['messages'] = [
            '#type' => 'details',
            '#title' => $this->t('Status Message'),
            '#group' => 'tabs',
            '#weight' => 1,
        ];

        $form['messages']['status_message'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Status Message'),
            '#default_value' => $job->getStatusMessage(),
            '#description' => $this->t('Status message text.'),
            '#rows' => 6,
        ];

        // Data tab (read-only for viewing serialized data)
        $form['data'] = [
            '#type' => 'details',
            '#title' => $this->t('Data'),
            '#group' => 'tabs',
            '#weight' => 2,
        ];

        $form['data']['serialized_data'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Serialized Data'),
            '#default_value' => $job->getSerializedData(),
            '#description' => $this->t('Serialized job data (JSON format).'),
            '#rows' => 15,
        ];

        // Metadata tab (read-only info)
        $form['metadata'] = [
            '#type' => 'details',
            '#title' => $this->t('Metadata'),
            '#group' => 'tabs',
            '#weight' => 3,
        ];

        $form['metadata']['id_display'] = [
            '#type' => 'item',
            '#title' => $this->t('Job ID'),
            '#markup' => $job->getId() ?? $this->t('New'),
        ];

        $form['metadata']['environment'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Environment'),
            '#default_value' => $job->getEnvironment(),
            '#description' => $this->t('The environment identifier.'),
        ];

        $form['metadata']['hash'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Hash'),
            '#default_value' => $job->getHash(),
            '#description' => $this->t('Job hash.'),
        ];

        $form['metadata']['created_display'] = [
            '#type' => 'item',
            '#title' => $this->t('Created'),
            '#markup' => $job->getCreated()->format('Y-m-d H:i:s'),
        ];

        $form['metadata']['changed_display'] = [
            '#type' => 'item',
            '#title' => $this->t('Changed'),
            '#markup' => $job->getChanged()->format('Y-m-d H:i:s'),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    protected function actions(array $form, FormStateInterface $form_state): array
    {
        $actions = parent::actions($form, $form_state);

        // Get the return URL from controller (passed via form state)
        $returnUrl = $this->getReturnUrl($form_state);

        // Update delete button to redirect to our return URL after deletion
        if (isset($actions['delete'])) {
            $actions['delete']['#url']->setOption('query', ['destination' => $returnUrl]);
        }

        // Add "Cancel" button
        $actions['cancel'] = [
            '#type' => 'link',
            '#title' => $this->t('Cancel'),
            '#url' => Url::fromUserInput($returnUrl),
            '#attributes' => [
                'class' => ['button'],
            ],
            '#weight' => 15,
        ];

        // Add "Save and continue editing" button
        $actions['save_continue'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save and continue editing'),
            '#submit' => ['::submitForm', '::save', '::saveAndContinue'],
            '#weight' => 10,
        ];

        // Adjust weight of default Save button
        $actions['submit']['#weight'] = 5;

        return $actions;
    }

    /**
     * Get the return URL passed from controller.
     */
    protected function getReturnUrl(FormStateInterface $form_state): string
    {
        return $form_state->get('dmf_returnUrl') ?? '/admin/dmf';
    }

    /**
     * Get the edit URL passed from controller.
     */
    protected function getEditUrl(FormStateInterface $form_state): string
    {
        return $form_state->get('dmf_editUrl') ?? '';
    }

    /**
     * Form submission handler for "Save and continue editing".
     */
    public function saveAndContinue(array $form, FormStateInterface $form_state): void
    {
        $editUrl = $this->getEditUrl($form_state);
        if ($editUrl) {
            $form_state->setRedirectUrl(Url::fromUserInput($editUrl));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state): int
    {
        /** @var Job $job */
        $job = $this->entity;

        $status = $job->save();

        if ($status === SAVED_NEW) {
            $this->messenger()->addStatus($this->t('Created job %label.', [
                '%label' => $job->getLabel(),
            ]));
        } else {
            $this->messenger()->addStatus($this->t('Saved job %label.', [
                '%label' => $job->getLabel(),
            ]));
        }

        // Get return URL from form state (passed by controller)
        $returnUrl = $this->getReturnUrl($form_state);
        $form_state->setRedirectUrl(Url::fromUserInput($returnUrl));

        return $status;
    }
}