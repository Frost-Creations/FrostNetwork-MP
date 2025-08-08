<?php

declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";

use pocketmine\resourcepacks\ResourcePackEncryption;

if($argc !== 3){
    echo "Usage: php encrypt-pack.php <input-pack> <output-pack>\n";
    exit(1);
}

$inputPath = $argv[1];
$outputPath = $argv[2];

if(!file_exists($inputPath)){
    echo "Input file does not exist\n";
    exit(1);
}

try {
    $key = random_bytes(32); // Generate a random 32-byte key
    ResourcePackEncryption::encryptPack($inputPath, $outputPath, $key);
    echo "Pack encrypted successfully!\n";
    echo "Key saved to: {$outputPath}.key\n";
} catch(\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
