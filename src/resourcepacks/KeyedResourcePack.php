<?php

declare(strict_types=1);

namespace pocketmine\resourcepacks;

use pocketmine\utils\BinaryStream;

class KeyedResourcePack extends ZippedResourcePack {
    private string $keyPath;
    private ?string $decryptionKey = null;

    public function __construct(string $path, string $keyPath){
        parent::__construct($path);
        $this->keyPath = $keyPath;
    }

    protected function loadKey() : void{
        if(!file_exists($this->keyPath)){
            throw new ResourcePackException("Key file not found: " . $this->keyPath);
        }
        $this->decryptionKey = trim(file_get_contents($this->keyPath));
    }

    public function getPackData() : string{
        if($this->decryptionKey === null){
            $this->loadKey();
        }

        $encryptedData = parent::getPackData();
        $iv = substr($encryptedData, 0, 16);
        $encrypted = substr($encryptedData, 16);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $this->decryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if($decrypted === false){
            throw new ResourcePackException("Failed to decrypt resource pack");
        }

        return $decrypted;
    }

    public static function isKeyedPack(string $path) : bool {
        return file_exists($path . ".key");
    }
}
