<?php

namespace Drupal\smashdocs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;


class FileForm extends FormBase {

    public function buildForm(array $form, FormStateInterface $form_state, $node_id = null) {

        $form['file'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('File'),
            '#description' => $this->t('Upload file'),
            '#required' => TRUE,
            '#upload_validators'  => array(
                'file_validate_extensions' => array('xml sdxml doc docx'),
                'file_validate_size' => array(25600000),
            ),
            '#upload_location' => 'public://smashdocs/',
        ];
        $form['node_id'] = [
            '#type' => 'hidden',
            '#title' => $this->t('Node id'),
            '#description' => $this->t('Node id'),
            '#required' => TRUE,
            '#default_value' => $node_id,
            '#value' => $node_id,
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Upload file'),
        ];

        return $form;
    }

    public function getFormId() {
        return 'smashdocs_file_form';
    }

    public function validateForm(array &$form, FormStateInterface $form_state) {

    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
        $fid = $values['file'][0];
        $file = \Drupal\file\Entity\File::load($fid);

        //$url = \Drupal::service('stream_wrapper_manager')->getViaUri($file->getFileUri())->getExternalUrl();

        $url = \Drupal::service('file_system')->realpath($file->getFileUri());

        $node_id = $form_state->getValue('node_id');
        $node = Node::load($node_id);

        $sd = new \Drupal\smashdocs\Smashdocs();
        $doc = $sd->upload_document($url, $node->getTitle(), $node->getTitle());

        $node->set('body', json_encode(['baseInfo' => $doc]));
        $node->save();

        $response = new RedirectResponse($doc['documentAccessLink']);
        $response->send();
        return;
    }

}