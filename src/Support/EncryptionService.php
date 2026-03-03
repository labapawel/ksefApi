<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Support;

class EncryptionService
{
    public function encrypt(string $plainText, string $key): string
    {
        // TODO: replace with robust key derivation and authenticated encryption.
        return base64_encode($plainText);
    }

    public function decrypt(string $cipherText, string $key): string
    {
        return (string) base64_decode($cipherText, true);
    }
}
