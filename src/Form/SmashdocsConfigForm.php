<?php

/**
 * @file
 * Contains \Drupal\smashdocs\Form\SmashdocsConfigForm.
 */

namespace Drupal\smashdocs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SmashdocsConfigForm extends ConfigFormBase {
    /**
     * {@inheritdoc}
     */

    public function getFormId() {
        return 'smashdocs_config_form';
    }

    /**
     * {@inheritdoc}
     */

    public function buildForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildForm($form, $form_state);
        $config = $this->config('smashdocs.settings');
        $form['partner_url'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Partner Url'),
            '#default_value' => $config->get('smashdocs.partner_url'),
            '#required' => TRUE,
        );
        $form['client_id'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Client id'),
            '#default_value' => $config->get('smashdocs.client_id'),
            '#required' => TRUE,
        );
        $form['client_key'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Client key'),
            '#default_value' => $config->get('smashdocs.client_key'),
            '#required' => TRUE,
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $config = $this->config('smashdocs.settings');
        $config->set('smashdocs.partner_url', $form_state->getValue('partner_url'));
        $config->set('smashdocs.client_id', $form_state->getValue('client_id'));
        $config->set('smashdocs.client_key', $form_state->getValue('client_key'));
        $config->save();
        return parent::submitForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return [
            'smashdocs.settings',
        ];
    }

}