<?php

declare(strict_types=1);

namespace pocketmine\resourcepacks;

class ResourcePackEncryption {
    public static function encryptPack(string $inputPath, string $outputPath, string $key) : void {
        if(strlen($key) !== 32){
            throw new \InvalidArgumentException("Key must be exactly 32 bytes long");
        }

        $data = file_get_contents($inputPath);
        if($data === false){
            throw new ResourcePackException("Failed to read input file");
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if($encrypted === false){
            throw new ResourcePackException("Encryption failed");
        }

        if(file_put_contents($outputPath, $iv . $encrypted) === false){
            throw new ResourcePackException("Failed to write encrypted pack");
        }

        // Save the key file
        if(file_put_contents($outputPath . ".key", $key) === false){
            throw new ResourcePackException("Failed to write key file");
        }
    }
}
