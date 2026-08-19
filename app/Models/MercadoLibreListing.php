<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercadoLibreListing extends Model
{
	protected $table = 'mercadolibre_listings';

	protected $fillable = [
		'mercadolibre_account_id',
		'ml_item_id',
		'ml_variation_id',
		'sku',
		'title',
		'linnworks_stock_item_id',
		'available_quantity',
		'linnworks_quantity',
		'price',
		'ml_updated_at',
		'lw_updated_at',
		'last_sync_direction',
	];

	protected $casts = [
		'ml_updated_at' => 'datetime',
		'lw_updated_at' => 'datetime',
		'price' => 'float',
	];

	public function account()
	{
		return $this->belongsTo(MercadoLibreAccount::class, 'mercadolibre_account_id');
	}

	public function isLinked(): bool
	{
		return !empty($this->linnworks_stock_item_id) && !empty($this->sku);
	}

	public function lastSyncLabel(): string
	{
		return match ($this->last_sync_direction) {
			'ml_to_lw' => 'ML → Linnworks',
			'lw_to_ml' => 'Linnworks → ML',
			default => '—',
		};
	}

	public function stockMismatch(): bool
	{
		if ($this->available_quantity === null || $this->linnworks_quantity === null) {
			return false;
		}

		return (int) $this->available_quantity !== (int) $this->linnworks_quantity;
	}

	/**
	 * After items are linked: Linnworks stock wins when quantities differ.
	 * Returns: lw_to_ml | ml_to_lw | none
	 */
	public static function stockSyncDirection(?int $mlQty, ?int $lwQty, bool $linked): string
	{
		if ($mlQty === null && $lwQty === null) {
			return 'none';
		}

		if (!$linked) {
			return $mlQty !== null ? 'ml_to_lw' : 'none';
		}

		if ($lwQty === null) {
			return 'none';
		}

		if ($mlQty === null || (int) $mlQty !== (int) $lwQty) {
			return 'lw_to_ml';
		}

		return 'none';
	}
}
