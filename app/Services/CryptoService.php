<?php

namespace App\Services;

class CryptoService
{
    static function encrypt($data)
    {
        // Store the cipher method
        $ciphering = env('CODE_CIPHERING');

        // Use OpenSSl Encryption method
        // $iv_length = openssl_cipher_iv_length($ciphering);
        $options = 0;

        // Non-NULL Initialization Vector for encryption
        $encryption_iv = env('CODE_ENCRYPTION_IV');

        // Store the encryption key
        $encryption_key = openssl_digest(
            config('app.key'),
            env('CODE_DIGEST'),
            TRUE
        );

        // Use openssl_encrypt() function to encrypt the data
        return openssl_encrypt(
            $data,
            $ciphering,
            $encryption_key,
            $options,
            $encryption_iv
        );
    }

    static function decrypt($data)
    {
        $ciphering = env('CODE_CIPHERING');

        // $iv_length = openssl_cipher_iv_length($ciphering);
        $options = 0;

        // Non-NULL Initialization Vector for decryption
        $decryption_iv = env('CODE_ENCRYPTION_IV');

        // Store the decryption key
        $decryption_key = openssl_digest(
            config('app.key'),
            env('CODE_DIGEST'),
            TRUE
        );

        // Use openssl_decrypt() function to decrypt the data
        return openssl_decrypt(
            $data,
            $ciphering,
            $decryption_key,
            $options,
            $decryption_iv
        );
    }
}
