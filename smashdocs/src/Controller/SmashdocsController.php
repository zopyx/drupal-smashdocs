<?php

namespace Drupal\smashdocs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\Entity\Node;
use Drupal\smashdocs\Smashdocs;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class SmashdocsController.
 *
 * @package Drupal\smashdocs\Controller
 */
class SmashdocsController extends ControllerBase {

    /**
     * The renderer.
     *
     * @var \Drupal\Core\Render\RendererInterface
     */
    protected $renderer;

    /**
     * Constructs a BookController object.
     *
     * @param \Drupal\Core\Render\RendererInterface $renderer
     *   The renderer.
     */
    public function __construct(RendererInterface $renderer) {
        $this->renderer = $renderer;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('renderer')
        );
    }

    /**
     * CreateDocument method.
     *
     * @return string
     *   Return Hello string.
     */
    public function createDocument($node_id) {
        $sd = new \Drupal\smashdocs\Smashdocs();

        $node = Node::load($node_id);
        $body = $node->get('body')->getValue();
        $meta = json_decode($body[0]['value']);
        if(isset($meta->baseInfo)){
            $doc = $sd->open_document($meta->baseInfo->documentId);
        }else{
            $doc = $sd->new_document($node->getTitle(), $node->getTitle());

            $node->set('body', json_encode(['baseInfo' => $doc]));
            $node->save();
        }

        $response = new RedirectResponse($doc['documentAccessLink']);
        $response->send();
        return;
    }

    public function archiveDocument($node_id){
        $node = Node::load($node_id);
        $body = $node->get('body')->getValue();
        $meta = json_decode($body[0]['value']);

        $sd = new \Drupal\smashdocs\Smashdocs();
        $sd->archive_document($meta->baseInfo->documentId);

        $previousUrl = \Drupal::request()->server->get('HTTP_REFERER');

        $response = new RedirectResponse($previousUrl);
        $response->send();
        return;
    }

    public function unarchiveDocument($node_id){
        $node = Node::load($node_id);
        $body = $node->get('body')->getValue();
        $meta = json_decode($body[0]['value']);

        $sd = new \Drupal\smashdocs\Smashdocs();
        $sd->unarchive_document($meta->baseInfo->documentId);

        $previousUrl = \Drupal::request()->server->get('HTTP_REFERER');

        $response = new RedirectResponse($previousUrl);
        $response->send();
        return;
    }

    public function exportDocument($node_id, $format){
        $node = Node::load($node_id);
        $body = $node->get('body')->getValue();
        $meta = json_decode($body[0]['value']);

        $sd = new \Drupal\smashdocs\Smashdocs();
        $templates = $sd->list_templates();

        $tmp_file = $sd->export_document($meta->baseInfo->documentId, \Drupal::currentUser()->id(), $format, $templates[0]->id);

        $content = file_get_contents($tmp_file);
        $uri = 'public://smashdocs/'.$node_id.'/'.$node_id.'.'.$format;
        file_put_contents(\Drupal::service('file_system')->realpath($uri), $content);

        $file_url = \Drupal::service('stream_wrapper_manager')->getViaUri($uri)->getExternalUrl();


        $meta->files->{$format} = $file_url;
        $node->set('body', json_encode($meta));
        $node->save();


        $response = new RedirectResponse($file_url);
        $response->send();
        return;
    }

}
