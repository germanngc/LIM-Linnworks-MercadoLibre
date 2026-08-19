<?php

namespace App\Traits;

use Log;

trait CustomLogger
{
	/**
	 * log
	 * Log the exception.
	 * 
	 * @param mixed $exception The exception to be logged.
	 * @param string $level The level of the log.
	 * @param string $message The message to be logged.
	 * @return void
	 */
	public static function log(mixed $exception, string $level = 'debug', string $class = null, string $function = null): void
	{
		$data = self::formatter($exception);
		$data = array_merge(['class' => $class, 'function' => $function], $data);
		$message = $data['message'];

		unset($data['message']);

		switch ($level) {
			case 'alert':     Log::alert($message, $data); break;
			case 'critical':  Log::critical($message, $data); break;
			case 'emergency': Log::emergency($message, $data); break;
			case 'error':     Log::error($message, $data); break;
			case 'info':      Log::info($message, $data); break;
			case 'debug':
			default:          Log::debug($message, $data); break;
		}
	}

	/**
	 * formatter
	 * Format the exception to be logged.
	 * 
	 * @param mixed $exception The exception to be logged.
	 * @return array
	 */
	private static function formatter(mixed $exception): array
	{
		return [
			'file' => (is_array($exception) ? $exception['file'] ?? 'Undefined' : ($exception->getFile() ?? 'Undefined')),
			'line' => (is_array($exception) ? $exception['line'] ?? 'Undefined' : ($exception->getLine() ?? 'Undefined')),
			'message' => (is_array($exception) ? $exception['message'] ?? 'Undefined' : ($exception->getMessage() ?? 'Undefined'))
		];
	}
}