<?php

namespace App\service;

class EncryptionService
{
    private $encryptionKey;
    private string $encryptionMethod = 'AES-256-CBC';

    public function __construct() {
        $this->encryptionKey = $_ENV['ENCRYPTION_KEY'];
    }

    /**
     * Chiffre une donnée avec AES-256-CBC
     */
    public function encrypt(string $plaintext) {
        $IVLength = openssl_cipher_iv_length($this->encryptionMethod);
        $IV = openssl_random_pseudo_bytes($IVLength);

        $encrypted = openssl_encrypt(
            $plaintext,
            $this->encryptionMethod,
            $this->encryptionKey,
            0,
            $IV
        );

        if ($encrypted === false) {
            throw new \Exception('Encryption failed: ' . openssl_error_string());
        }

        return base64_encode($IV . $encrypted);
    }


    /**
     * Déchiffre une donnée chiffrée avec AES-256-CBC
     */
    public function decrypt(string $encryptedData): string
    {
        $encryptedData = base64_decode($encryptedData);
        $ivLength = openssl_cipher_iv_length($this->encryptionMethod);

        // Extract IV from the encrypted data
        $iv = substr($encryptedData, 0, $ivLength);
        $encrypted = substr($encryptedData, $ivLength);

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->encryptionMethod,
            $this->encryptionKey,
            0,
            $iv
        );

        if ($decrypted === false) {
            throw new \Exception('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

}