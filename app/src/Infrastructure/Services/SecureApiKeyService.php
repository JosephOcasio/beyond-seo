<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use DDD\Infrastructure\Libs\Config;
use DDD\Infrastructure\Libs\Encrypt;
use DDD\Infrastructure\Services\Service;
use RuntimeException;

class SecureApiKeyService extends Service
{
    /**
     * Encrypt an API key
     *
     * @param string $apiKey The API key to encrypt
     * @return string|bool Returns encrypted API key on success, false on failure
     */
    public function encryptApiKey(string $apiKey): string|bool
    {
        // Attempt to decrypt the data to check if it is already encrypted
        try {
            $this->decryptApiKey($apiKey);
            // If decryption is successful, it means the data was already encrypted
            return $apiKey;
        } catch (RuntimeException) {
            // Decryption failed, meaning the data is not encrypted, proceed with encryption
        }

        $apiKeySecret = Config::getEnv('API_KEYS_ENCRYPTION_KEY');

        // Encrypt the API key using a secure method
        return Encrypt::encrypt($apiKey, $apiKeySecret);
    }

    /**
     * Decrypt an encrypted API key
     *
     * @param string $encryptedApiKey The encrypted API key to decrypt
     * @return string
     */
    public function decryptApiKey(string $encryptedApiKey): string
    {
        $apiKeySecret = Config::getEnv('API_KEYS_ENCRYPTION_KEY');
        // Decrypt the API key
        $decryptedApiKey = Encrypt::decrypt($encryptedApiKey, $apiKeySecret);

        if ($decryptedApiKey === false) {
            throw new RuntimeException('Decryption failed.');
        }

        return $decryptedApiKey;
    }

    /**
     * Generate a random base key with a given length
     *
     * @param int $length
     * @return string
     * @throws RandomException
     */
    public function generateApiKey(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generates and returns the hash of an API key
     *
     * @param string $apiKey The API key to be hashed
     * @param int $keyDerivationIterations The number of iterations to derive the key (default: 1000)
     * @param int $derivedKeyLength The length of the derived key (default: 32)
     * @return string The hashed API key
     */
    public function getApiKeyHash(
        string $apiKey,
        int $keyDerivationIterations = 1000,
        int $derivedKeyLength = 32
    ): string {
        // Derive the salt from the provided API key (using the first 16 bytes of the hash as the salt)
        $salt = substr(hash('sha256', $apiKey), 0, 16);

        $apiKeyHashKey = Config::getEnv('API_KEYS_HASH_KEY');
        // Generate a derived key from the API key hash key and the salt
        $derivedKey = hash_pbkdf2('sha256', $apiKeyHashKey, $salt, $keyDerivationIterations, $derivedKeyLength, true);
        // Generate and return the API key hash using the derived key
        return hash_hmac('sha256', $apiKey, $derivedKey);
    }
}
