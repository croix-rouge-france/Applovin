<?php
require_once('/home/vol19_2/infinityfree.com/if0_38609766/htdocs/paydunya-php-master/paydunya.php');

// Définir les constantes si elles ne le sont pas déjà
if (!defined('PAYDUNYA_MODE')) define('PAYDUNYA_MODE', 'live');
if (!defined('PAYDUNYA_MASTER_KEY')) define('PAYDUNYA_MASTER_KEY', '61UU2abw-fmvT-nNDA-GFMe-WcecHjEdfYoP');
if (!defined('PAYDUNYA_PUBLIC_KEY')) define('PAYDUNYA_PUBLIC_KEY', 'live_public_5Uhdeo8oxHpBR5CwevG4juyZ4yF');
if (!defined('PAYDUNYA_PRIVATE_KEY')) define('PAYDUNYA_PRIVATE_KEY', 'live_private_omjNDYClxSRu8KZoDBSvLRo4QEm');
if (!defined('PAYDUNYA_TOKEN')) define('PAYDUNYA_TOKEN', 'X7R67BRbIbnthZ7BTyPr');

class PaydunyaConfig {
    private static $instance = null;
    private $storeName = "Applovin";
    private $storeTagline = "applovin le meilleur";
    private $storePhoneNumber = "221770000000";
    private $storePostalAddress = "Dakar, Sénégal";
    private $storeWebsiteUrl = "https://applovin-invest.onrender.com/dashboard.php"; // Corrigé : URL valide
    private $storeCallbackUrl = "/deposit.php?status=success"; // Corrigé : ajout du ;

    private function __construct() {
        try {
            // Vérifier si la classe existe
            if (!class_exists('Paydunya_Setup')) {
                throw new Exception("La classe Paydunya_Setup n'est pas trouvée. Vérifiez l'installation de la bibliothèque.");
            }

            // Configuration des clés API
            Paydunya_Setup::setMasterKey(PAYDUNYA_MASTER_KEY);
            Paydunya_Setup::setPublicKey(PAYDUNYA_PUBLIC_KEY);
            Paydunya_Setup::setPrivateKey(PAYDUNYA_PRIVATE_KEY);
            Paydunya_Setup::setToken(PAYDUNYA_TOKEN);
            Paydunya_Setup::setMode(PAYDUNYA_MODE);

            // Configuration de la boutique
            $this->configureStore();

        } catch (Exception $e) {
            error_log("Erreur lors de l'initialisation de PaydunyaConfig : " . $e->getMessage());
            throw new Exception("Impossible d'initialiser la configuration Paydunya. Détails : " . $e->getMessage()); // Corrigé : concaténation
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function configureStore() {
        Paydunya_Checkout_Store::setName($this->storeName);
        Paydunya_Checkout_Store::setTagline($this->storeTagline);
        Paydunya_Checkout_Store::setPhoneNumber($this->storePhoneNumber);
        Paydunya_Checkout_Store::setPostalAddress($this->storePostalAddress);
        Paydunya_Checkout_Store::setWebsiteUrl($this->storeWebsiteUrl);
        Paydunya_Checkout_Store::setCallbackUrl($this->storeCallbackUrl);

        // Logo optionnel (commenté par défaut)
        // Paydunya_Checkout_Store::setLogoUrl("https://applovin.kesug.com/logo.png"); // Corrigé : URL valide si décommenté
    }

    public function getStoreConfig() {
        return [
            'name' => $this->storeName,
            'tagline' => $this->storeTagline,
            'phoneNumber' => $this->storePhoneNumber,
            'postalAddress' => $this->storePostalAddress,
            'websiteUrl' => $this->storeWebsiteUrl,
            'callbackUrl' => $this->storeCallbackUrl,
            'mode' => PAYDUNYA_MODE
        ];
    }

    public function isLiveMode() {
        return PAYDUNYA_MODE === 'live';
    }

    public static function updateKeys($masterKey, $publicKey, $privateKey, $token, $mode) {
        Paydunya_Setup::setMasterKey($masterKey);
        Paydunya_Setup::setPublicKey($publicKey);
        Paydunya_Setup::setPrivateKey($privateKey);
        Paydunya_Setup::setToken($token);
        Paydunya_Setup::setMode($mode);

        self::$instance = new self(); // Réinitialiser l'instance
    }
}

// Initialisation automatique
PaydunyaConfig::getInstance();
