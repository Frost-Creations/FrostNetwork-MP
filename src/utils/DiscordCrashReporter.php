<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\utils;

use pocketmine\crash\CrashDump;
use pocketmine\crash\CrashDumpData;
use pocketmine\Server;
use pocketmine\YmlServerProperties;
use function array_slice;
use function basename;
use function bin2hex;
use function count;
use function date;
use function file_exists;
use function file_get_contents;
use function filesize;
use function gmdate;
use function implode;
use function is_array;
use function json_encode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function random_bytes;
use function round;
use function strlen;
use function substr;
use function time;

/**
 * Discord webhook crash reporter for FrostNetwork
 * Sends detailed crash reports to Discord when crashes occur
 */
final class DiscordCrashReporter{

	/** @var string Default Discord webhook URL for crash reports */
	private const DEFAULT_WEBHOOK_URL = "https://discord.com/api/webhooks/1403356527288651816/FS0PaWrTjlor5epoH9F_cCg1Vbgiunb01Wq00pg7CFtroQ4mi4mWWbNjDT6bniSUX7zV";

	/** @var int Default Discord file size limit for webhooks (8MB) */
	private const DEFAULT_MAX_FILE_SIZE = 8 * 1024 * 1024;

	/** @var int Discord message content limit */
	private const MAX_CONTENT_LENGTH = 2000;

	/**
	 * Send a crash report to Discord webhook
	 */
	public static function sendCrashReport(Server $server, CrashDump $dump, string $crashDumpPath) : void{

		try {
			$webhookUrl = self::DEFAULT_WEBHOOK_URL; // Use default for now, configuration can be added later
			self::sendMainCrashReport($server, $dump, $crashDumpPath, $webhookUrl);
			self::sendCrashDumpFile($server, $crashDumpPath, $webhookUrl, self::DEFAULT_MAX_FILE_SIZE);
		} catch(\Throwable $e) {
			$server->getLogger()->emergency("Failed to send Discord crash report: " . $e->getMessage());
		}
	}

	/**
	 * Send the main crash report embed
	 */
	private static function sendMainCrashReport(Server $server, CrashDump $dump, string $crashDumpPath, string $webhookUrl) : void{
		$serverName = $server->getMotd() ?: "FrostNetwork Server";
		$crashData = $dump->getData();
		$errorData = $crashData->error;

		// Prepare detailed error information
		$errorType = $errorData["type"] ?? "Unknown Error";
		$errorMessage = $errorData["message"] ?? "No message available";
		$errorFile = $errorData["file"] ?? "Unknown file";
		$errorLine = $errorData["line"] ?? "Unknown line";
		$thread = $crashData->thread ?? "Unknown thread";

		// Get plugin involvement
		$pluginInvolvement = $crashData->plugin_involvement ?? CrashDump::PLUGIN_INVOLVEMENT_NONE;
		$involvedPlugin = $crashData->plugin ?? "None";

		// Get basic server info
		$general = $crashData->general;
		$serverVersion = $general->name ?? "Unknown";
		$baseVersion = $general->base_version ?? "Unknown";
		$buildNumber = $general->build ?? "Unknown";
		$protocolVersion = $general->protocol ?? "Unknown";
		$phpVersion = $general->php ?? "Unknown";
		$osInfo = $general->os ?? "Unknown";

		// Get memory and performance info
		$memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2) . " MB";
		$memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB";
		$uptime = gmdate("H:i:s", (int) $crashData->uptime);

		// Prepare stack trace (first few lines)
		$stackTrace = "";
		if(isset($crashData->trace) && is_array($crashData->trace)){
			$traceLines = array_slice($crashData->trace, 0, 8); // First 8 lines to fit Discord limits
			$stackTrace = "```\n" . implode("\n", $traceLines) . "\n```";
			if(count($crashData->trace) > 8){
				$stackTrace .= "\n... and " . (count($crashData->trace) - 8) . " more lines";
			}
		}

		// Get plugin involvement color
		$embedColor = match($pluginInvolvement) {
			CrashDump::PLUGIN_INVOLVEMENT_DIRECT => 16711680, // Bright red
			CrashDump::PLUGIN_INVOLVEMENT_INDIRECT => 16753920, // Orange
			default => 15548997 // Default red
		};

		// Prepare the Discord embed
		$embedData = [
			"embeds" => [
				[
					"title" => "🔥 FrostNetwork Server Crash Report",
					"description" => "**{$serverName}** has crashed and generated a crash dump.",
					"color" => $embedColor,
					"fields" => [
						[
							"name" => "💥 Error Details",
							"value" => "**Type:** `{$errorType}`\n**Message:** " . self::truncateText($errorMessage, 200) . "\n**File:** `{$errorFile}:{$errorLine}`\n**Thread:** {$thread}",
							"inline" => false
						],
						[
							"name" => "🔌 Plugin Involvement",
							"value" => "**Level:** {$pluginInvolvement}\n**Plugin:** {$involvedPlugin}",
							"inline" => true
						],
						[
							"name" => "📊 Server Info",
							"value" => "**Version:** {$serverVersion}\n**Base:** {$baseVersion}\n**Build:** {$buildNumber}\n**Protocol:** {$protocolVersion}",
							"inline" => true
						],
						[
							"name" => "💻 System Info",
							"value" => "**PHP:** {$phpVersion}\n**OS:** " . self::truncateText($osInfo, 50) . "\n**Memory:** {$memoryUsage}\n**Peak:** {$memoryPeak}",
							"inline" => true
						],
						[
							"name" => "⏱️ Runtime Info",
							"value" => "**Uptime:** {$uptime}\n**Players:** " . count($server->getOnlinePlayers()) . "\n**Time:** " . date("Y-m-d H:i:s T"),
							"inline" => true
						],
						[
							"name" => "📄 Crash File",
							"value" => "**File:** `" . basename($crashDumpPath) . "`\n**Path:** `" . self::truncateText($crashDumpPath, 100) . "`",
							"inline" => false
						]
					],
					"footer" => [
						"text" => "PixelForge Studios - FrostNetwork | Crash ID: " . date("YmdHis"),
						"icon_url" => "https://cdn.discordapp.com/emojis/1234567890123456789.png"
					],
					"timestamp" => date("c")
				]
			]
		];

		// Add stack trace as a separate embed if available
		if(!empty($stackTrace)){
			$embedData["embeds"][] = [
				"title" => "📋 Stack Trace (First 8 lines)",
				"description" => $stackTrace,
				"color" => 15105570 // Orange color
			];
		}

		// Send the webhook
		$postUrlError = "Unknown error";
		$reply = Internet::postURL($webhookUrl, json_encode($embedData), 10, [
			"Content-Type: application/json"
		], $postUrlError);

		if($reply !== null){
			$server->getLogger()->emergency("Crash report sent to FrostNetwork Discord");
			$server->getLogger()->emergency("Server: {$serverName} | Error: {$errorType} | Plugin: {$involvedPlugin}");
		}else{
			$server->getLogger()->emergency("Failed to send crash report to FrostNetwork Discord: {$postUrlError}");
		}
	}

	/**
	 * Send crash dump file as attachment
	 */
	private static function sendCrashDumpFile(Server $server, string $crashDumpPath, string $webhookUrl, int $maxFileSize) : void{
		if(!file_exists($crashDumpPath)){
			return;
		}

		$fileSize = filesize($crashDumpPath);
		if($fileSize > $maxFileSize){
			$server->getLogger()->emergency("Crash dump file too large to send via Discord ({$fileSize} bytes)");
			return;
		}

		try {
			// Prepare multipart form data for file upload
			$boundary = "----WebKitFormBoundary" . bin2hex(random_bytes(8));
			$fileContent = file_get_contents($crashDumpPath);
			$fileName = basename($crashDumpPath);
			$serverName = $server->getMotd() ?: "PocketMine-PFS Server";

			$postData = "--{$boundary}\r\n";
			$postData .= "Content-Disposition: form-data; name=\"content\"\r\n\r\n";
			$postData .= "📁 **Crash dump file for {$serverName}**\r\n";
			$postData .= "--{$boundary}\r\n";
			$postData .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$fileName}\"\r\n";
			$postData .= "Content-Type: text/plain\r\n\r\n";
			$postData .= $fileContent . "\r\n";
			$postData .= "--{$boundary}--\r\n";

			$headers = [
				"Content-Type: multipart/form-data; boundary={$boundary}",
				"Content-Length: " . strlen($postData)
			];

			$postUrlError = "Unknown error";
			$reply = Internet::postURL($webhookUrl, $postData, 30, $headers, $postUrlError);

			if($reply !== null){
				$server->getLogger()->emergency("Crash dump file sent to Discord: {$fileName}");
			}else{
				$server->getLogger()->emergency("Failed to send crash dump file to Discord: {$postUrlError}");
			}
		} catch(\Throwable $e) {
			$server->getLogger()->emergency("Error sending crash dump file: " . $e->getMessage());
		}
	}

	/**
	 * Truncate text to fit Discord limits
	 */
	private static function truncateText(string $text, int $maxLength) : string{
		if(strlen($text) <= $maxLength){
			return $text;
		}
		return substr($text, 0, $maxLength - 3) . "...";
	}
}
