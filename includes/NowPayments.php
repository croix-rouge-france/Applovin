<?php
class NowPayments {
    private $api_key;
    private $endpoint = 'https://api.nowpayments.io/v1/';
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
    }
    
    private function request($method, $path, $data = []) {
        $url = $this->endpoint . $path;
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $this->api_key,
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('CURL error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($http_code >= 400) {
            $error = $result['message'] ?? 'Unknown error';
            throw new Exception("API Error ($http_code): $error");
        }
        
        return $result;
    }
    
    public function createPayment($data) {
        return $this->request('POST', 'payment', $data);
    }
    
    public function getPayment($payment_id) {
        return $this->request('GET', 'payment/' . $payment_id);
    }
    
    public function getMinimumAmount($currency_from, $currency_to = 'usdt') {
        $params = http_build_query([
            'currency_from' => $currency_from,
            'currency_to' => $currency_to
        ]);
        return $this->request('GET', 'min-amount?' . $params);
    }
}