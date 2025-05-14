<?php
class CinetPay {
    private $site_id;
    private $api_key;
    private $api_url = 'https://api.cinetpay.com/v2/';
    
    public function __construct($site_id, $api_key) {
        $this->site_id = $site_id;
        $this->api_key = $api_key;
    }
    
    public function generatePaymentLink($payment_data) {
        $endpoint = $this->api_url . 'payment';
        $payment_data['site_id'] = $this->site_id;
        $payment_data['apikey'] = $this->api_key;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception("Erreur CURL: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Erreur API CinetPay: HTTP $http_code");
        }
        
        $result = json_decode($response, true);
        
        if (empty($result['data']['payment_url'])) {
            $error_msg = $result['message'] ?? 'Erreur inconnue de CinetPay';
            throw new Exception($error_msg);
        }
        
        return $result['data']['payment_url'];
    }
}