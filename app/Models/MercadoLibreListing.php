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
		'source',
		'category_id',
		'permalink',
		'last_error',
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

	public function isListed(): bool
	{
		return !empty($this->ml_item_id);
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
			'create' => 'Listing created',
			default => '—',
		};
	}

	public static function titlesDiffer(?string $left, ?string $right): bool
	{
		return mb_strtolower(trim((string) $left)) !== mb_strtolower(trim((string) $right));
	}

	public function stockMismatch(): bool
	{
		if ($this->available_quantity === null || $this->linnworks_quantity === null) {
			return false;
		}

		return (int) $this->available_quantity !== (int) $this->linnworks_quantity;
	}

	/**
	 * Bidirectional stock: the side that actually changed wins.
	 * If both changed, Linnworks (warehouse) wins.
	 * If no previous snapshot, treat as first link: LW qty is source of truth when present.
	 * Returns: lw_to_ml | ml_to_lw | none
	 */
	public static function stockSyncDirection(
		?int $mlQty,
		?int $lwQty,
		bool $linked,
		?int $prevMlQty = null,
		?int $prevLwQty = null
	): string {
		if ($mlQty === null && $lwQty === null) {
			return 'none';
		}

		if (!$linked) {
			return $mlQty !== null ? 'ml_to_lw' : 'none';
		}

		$mlChanged = $prevMlQty !== null && $mlQty !== null && (int) $mlQty !== (int) $prevMlQty;
		$lwChanged = $prevLwQty !== null && $lwQty !== null && (int) $lwQty !== (int) $prevLwQty;

		if ($mlChanged && !$lwChanged) {
			return 'ml_to_lw';
		}

		if ($lwChanged) {
			return 'lw_to_ml';
		}

		if ($lwQty === null) {
			return 'none';
		}

		if ($mlQty === null || (int) $mlQty !== (int) $lwQty) {
			return 'lw_to_ml';
		}

		return 'none';
	}

	/**
	 * Hard requirements before POST /items. Returns blocker messages (empty = ok).
	 * @param string[] $imageUrls
	 * @return string[]
	 */
	public static function createListingBlockers(?string $title, ?float $price, int $qty, array $imageUrls): array
	{
		$errors = [];

		if (!$title || mb_strlen(trim($title)) < 3) {
			$errors[] = 'Title must be at least 3 characters';
		}

		if ($price === null || $price <= 0) {
			$errors[] = 'Retail price must be greater than 0';
		}

		if ($qty < 1) {
			$errors[] = 'Stock must be at least 1 to list on Mercado Libre';
		}

		if (!$imageUrls) {
			$errors[] = 'At least one image is required in Linnworks';
		}

		return $errors;
	}
}
