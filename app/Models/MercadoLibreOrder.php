<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercadoLibreOrder extends Model
{
	protected $table = 'mercadolibre_orders';

	protected $fillable = [
		'mercadolibre_account_id',
		'ml_order_id',
		'pack_id',
		'shipment_id',
		'status',
		'shipping_status',
		'shipping_substatus',
		'logistic_type',
		'tracking_number',
		'linnworks_order_id',
		'last_notified_status',
		'payload',
	];

	protected $casts = [
		'payload' => 'array',
	];

	public function account()
	{
		return $this->belongsTo(MercadoLibreAccount::class, 'mercadolibre_account_id');
	}

	public function isFullFulfillment(): bool
	{
		return MercadoLibreOrder::isFullLogisticType($this->logistic_type);
	}

	public static function isFullLogisticType(?string $type): bool
	{
		return in_array($type, ['fulfillment', 'fulfillment_lite'], true);
	}

	/** Terminal ML order / shipping states — sync leaves these alone after upsert. */
	public static function isTerminalStatus(?string $status, ?string $shippingStatus = null): bool
	{
		$status = strtolower((string) $status);
		$shipping = strtolower((string) $shippingStatus);

		if (in_array($status, ['cancelled', 'canceled', 'invalid'], true)) {
			return true;
		}

		return in_array($shipping, ['delivered', 'not_delivered', 'cancelled', 'canceled'], true);
	}

	public function isOpen(): bool
	{
		return !self::isTerminalStatus($this->status, $this->shipping_status);
	}

	/** ready_to_ship → Ready to ship */
	public static function humanize(?string $value): string
	{
		if ($value === null || $value === '') {
			return '—';
		}

		return ucfirst(str_replace('_', ' ', $value));
	}
}
