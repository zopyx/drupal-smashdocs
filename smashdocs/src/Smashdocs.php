<?php

namespace Drupal\smashdocs;

use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;

class Smashdocs
{
    public $partner_url = 'https://partner-api.smashdocs.net';
    public $client_id = '';
    public $client_key = '';
    public $group_id = 'default';
    public $verbose = 0;
    public $user = [];

    function __construct($group_id = 'default', $verbose = 0)
    {
        $config = \Drupal::config('smashdocs.settings');

        $this->partner_url = $config->get('smashdocs.partner_url');
        $this->client_id = $config->get('smashdocs.client_id');
        $this->client_key = $config->get('smashdocs.client_key');
        $this->group_id = $group_id;
        $this->verbose = $verbose;

        $user = \Drupal::currentUser();
        $this->user = [
            'userId' => $user->id(),
            'firstname' => $user->getDisplayName(),
            'lastname' => $user->getDisplayName(),
            'email' => $user->getEmail(),
        ];

    }


    private function gen_token()
    {
        $iss = Uuid::uuid4();
        $iat = time();
        $jti = Uuid::uuid4();
        $jwt_payload = array(
            "iat" => $iat,
            "iss" => $iss,
            "jti" => $jti,
        );
        $jwt = new JWT;
        $token = $jwt->encode($jwt_payload, $this->client_key, "HS256");
        return $token;
    }


    private function standard_headers()
    {
        return array(
            "x-client-id" => $this->client_id,
            "content-type" => "application/json",
            "authorization" => "Bearer " . $this->gen_token(),
        );
    }


    private function check_http_response($response, $status_code_expected = 200, $exc_name = 'SmashdocsError', $decode_json = true)
    {
        $httpcode = $response->getStatusCode();
        if ($httpcode != $status_code_expected) {
            $msg = 'HTTP call returned with status ' . $httpcode . ' (expected: ' . $status_code_expected . ', ' . $out . ')';
            $exc = new $exc_name($msg);
            $exc->status_code_got = $httpcode;
            $exc->status_code_expected = $status_code_expected;
            $exc->error_msg = $out;
            throw $exc;
        }
        if ($decode_json) {
            return json_decode($response->getBody());
        } else {
            return $response->getBody();
        }
    }


    public function get_documents($group_id = null, $user_id = null)
    {
        $data = array();
        if ($group_id)
            $data["groupId"] = $group_id;
        if ($user_id)
            $data["userId"] = $user_id;
        $url = $this->partner_url . "/partner/documents/list";
        $client = new Client();
        $response = $client->get($url, array('debug' => $this->verbose, 'headers' => $this->standard_headers()));
        return (array)$this->check_http_response($response, 200, 'GetDocumentsError', true);
    }


    public function list_templates()
    {
        $url = $this->partner_url . "/partner/templates/word";
        $client = new Client();
        $response = $client->get($url, array(
            'debug' => $this->verbose,
            'headers' => $this->standard_headers()
        ));
        return (array)$this->check_http_response($response, 200, 'OpenError', true);
    }


    function delete_document($documentId)
    {
        check_document_id($documentId);
        $url = $this->partner_url . "/partner/documents/" . $documentId;
        $client = new Client();
        try {
            $response = $client->delete($url, array(
                'debug' => $this->verbose,
                'headers' => $this->standard_headers()
            ));
        } catch (Exception $e) {
            throw new DeletionError($e->getMessage());
        }
        return $this->check_http_response($response, 200, 'DeletionError', false);
    }


    function open_document($documentId, $role = 'editor')
    {
        check_role($role);
        check_document_id($documentId);
        check_user_data($this->user);
        $data = array(
            "user" => $this->user,
            "groupId" => $this->group_id,
            "userRole" => $role,
            "sectionHistory" => true
        );
        $url = $this->partner_url . "/partner/documents/" . $documentId;
        $client = new Client();
        $response = $client->post($url, array(
            'debug' => $this->verbose,
            'json' => $data,
            'headers' => $this->standard_headers()
        ));
        return (array)$this->check_http_response($response, 200, 'OpenError', true);
    }


    function archive_document($documentId)
    {
        $url = $this->partner_url . "/partner/documents/" . $documentId . "/archive";
        $client = new Client();
        $response = $client->post($url,  array(
            'debug' => $this->verbose,
            'json' => $data,
            'headers' => $this->standard_headers()
        ));
        return (array)$this->check_http_response($response, 200, 'ArchiveError', true);
    }


    function update_metadata($documentId, array $metadata = null)
    {
        check_document_id($documentId);
        $url = $this->partner_url . "/partner/documents/" . $documentId . "/metadata";
        $client = new Client();
        $response = $client->post($url, array(
            'debug' => $this->verbose,
            'json' => $metadata,
            'headers' => $this->standard_headers()
        ));
    }


    function document_info($documentId)
    {
        check_document_id($documentId);
        $url = $this->partner_url . "/partner/documents/" . $documentId;
        $client = new Client();
        $response = $client->get($url, array(
            'debug' => $this->verbose,
            'headers' => $this->standard_headers()
        ));
        return (array)$this->check_http_response($response, 200, 'DocumentInfoError', true);
    }


    function unarchive_document($documentId)
    {
        check_document_id($documentId);
        $url = $this->partner_url . "/partner/documents/" . $documentId . "/unarchive";
        $client = new Client();
        $response = $client->post($url, array(
            'debug' => $this->verbose,
            'json' => $data,
            'headers' => $this->standard_headers()
        ));
        return (array)$this->check_http_response($response, 200, 'UnarchiveError', true);
    }


    function export_document($documentId, $user_id, $format, $template_id = '')
    {
        check_document_id($documentId);
        if (!in_array($format, array('docx', 'html', 'sdxml'))) {
            throw new SmashdocsError('Unknown export format ' . $format);
        }
        $data = array(
            "userId" => $user_id,
        );
        if ($format == 'sdxml') {
            $url = $this->partner_url . '/partner/documents/' . $documentId . '/export/sdxml';
        } elseif ($format == 'html') {
            $url = $this->partner_url . '/partner/documents/' . $documentId . '/export/html';
        } elseif ($format == 'docx') {
            $url = $this->partner_url . '/partner/documents/' . $documentId . '/export/word';
            $data['templateId'] = $template_id;
            $data['settings'] = (object)array();
        }
        $client = new Client();
        $response = $client->post($url,  array(
            'debug' => $this->verbose,
            'json' => $data,
            'headers' => $this->standard_headers()
        ));
        $out = $this->check_http_response($response, 200, 'ExportError', false);
        $fn = tempnam(sys_get_temp_dir(), '');
        if ($format == 'docx') {
            $fn = $fn . '.docx';
        } else {
            $fn = $fn . '.' . $format . '.zip';
        }
        $fp = fopen($fn, "wb");
        fwrite($fp, $out);
        fclose($fp);
        return $fn;
    }


    function new_document($title = null, $description = null, $role = 'editor')
    {
        check_title($title);
        check_description($title);
        check_role($role);
        check_user_data($this->user);
        $data = array(
            "user" => $this->user,
            "title" => $title,
            "description" => $description,
            "groupId" => $this->group_id,
            "userRole" => $role,
            "sectionHistory" => true
        );
        $url = $this->partner_url . "/partner/documents/create";
        $client = new Client();
        $response = $client->post($url, array(
            'debug' => $this->verbose,
            'json' => $data,
            'headers' => $this->standard_headers()
        ));
        return (array)$this->check_http_response($response, 200, 'CreationFailed', true);
    }


    function duplicate_document($document_id, $title = null, $description = null, $creator_id = null)
    {
        check_title($title);
        check_description($title);
        $data = array(
            "description" => $description,
            "title" => $title,
            "creatorUserId" => $creator_id
        );
        $url = $this->partner_url . "/partner/documents/" . $document_id . "/duplicate";
        $client = new Client();
        $response = $client->post($url, array(
            'debug' => $this->verbose,
            'json' => $data,
            'headers' => $this->standard_headers()
        ));
        return (array)$this->check_http_response($response, 200, 'DuplicationFailed', true);
    }



    function upload_document($fn, $title = null, $description = null, $role = 'editor')
    {
        $headers = array(
            "x-client-id" => $this->client_id,
            "authorization" => "Bearer " . $this->gen_token()
        );
        $data = array(
            "user" => $this->user,
            "title" => $title,
            "description" => $description,
            "groupId" => $this->group_id,
            "userRole" => $role,
            "sectionHistory" => true
        );
        if (ends_with($fn, '.docx')) {
            $url = $this->partner_url . "/partner/imports/word/upload";
        } else {
            $url = $this->partner_url . "/partner/imports/sdxml/upload";
        }

        $client = new Client();
        $fp = fopen($fn, 'rb');
        $response = $client->post($url, array(
            'debug' => 1,
            'headers' => $headers,
            'multipart' => array(
                array(
                    'name' => 'data',
                    'contents' => json_encode($data),
                    'headers' => array('content-type' => 'application/json')
                ),
                array(
                    'name' => 'file',
                    'Content-type' => 'multipart/form-data',
                    'contents' => $fp
                )
            )
        ));
        return (array)$this->check_http_response($response, 200, 'UploadError', true);
    }
}