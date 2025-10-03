<?php
defined('BASEPATH') or exit('No direct script access allowed');

class inpaycheckoutapi
{
    protected $secret_key;
    protected $api_base;

    public function __construct($secret_key = '')
    {
        $this->secret_key = trim($secret_key);
        $this->api_base = 'https://api.inpaycheckout.com';
    }

    public function verify_transaction($reference = '')
    {
        $reference = trim($reference);
        if ($reference === '' || $this->secret_key === '') {
            return ['success' => false, 'message' => 'Missing credentials'];
        }

        $endpoints = [
            [
                'method' => 'GET',
                'url' => $this->api_base . '/api/v1/developer/transaction/status?reference=' . rawurlencode($reference),
            ],
            [
                'method' => 'POST',
                'url' => $this->api_base . '/api/v1/developer/transaction/verify',
                'body' => json_encode(['reference' => $reference]),
            ],
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->request($endpoint);
            if ($response && isset($response['success']) && $response['success']) {
                return $response;
            }
        }

        return ['success' => false, 'message' => 'Verification failed'];
    }

    protected function request($endpoint)
    {
        $ch = curl_init($endpoint['url']);
        $headers = [
            'Authorization: Bearer ' . $this->secret_key,
            'Accept: application/json',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $endpoint['method'],
            CURLOPT_HTTPHEADER => $headers,
        ];

        if (isset($endpoint['body'])) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = $endpoint['body'];
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return false;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            return false;
        }

        $decoded = json_decode($body, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : false;
    }
}
