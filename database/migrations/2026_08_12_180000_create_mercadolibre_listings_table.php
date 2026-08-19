<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up()
	{
		Schema::create('mercadolibre_listings', function (Blueprint $table) {
			$table->id();
			$table->foreignId('mercadolibre_account_id')->constrained()->cascadeOnDelete();
			$table->string('ml_item_id')->index();
			$table->unsignedBigInteger('ml_variation_id')->default(0)->index();
			$table->string('sku')->nullable()->index();
			$table->string('title')->nullable();
			$table->uuid('linnworks_stock_item_id')->nullable()->index();
			$table->integer('available_quantity')->nullable();
			$table->integer('linnworks_quantity')->nullable();
			$table->decimal('price', 12, 2)->nullable();
			$table->timestamp('ml_updated_at')->nullable();
			$table->timestamp('lw_updated_at')->nullable();
			$table->string('last_sync_direction')->nullable();
			$table->timestamps();

			$table->unique(['mercadolibre_account_id', 'ml_item_id', 'ml_variation_id'], 'meli_listings_item_var_unique');
		});
	}

	public function down()
	{
		Schema::dropIfExists('mercadolibre_listings');
	}
};
