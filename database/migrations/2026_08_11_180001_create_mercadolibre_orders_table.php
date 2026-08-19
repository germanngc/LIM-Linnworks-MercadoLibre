<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up()
	{
		Schema::create('mercadolibre_orders', function (Blueprint $table) {
			$table->id();
			$table->foreignId('mercadolibre_account_id')->constrained()->cascadeOnDelete();
			$table->unsignedBigInteger('ml_order_id')->unique();
			$table->unsignedBigInteger('pack_id')->nullable()->index();
			$table->unsignedBigInteger('shipment_id')->nullable()->index();
			$table->string('status')->nullable();
			$table->string('shipping_status')->nullable();
			$table->string('shipping_substatus')->nullable();
			$table->string('logistic_type')->nullable();
			$table->string('tracking_number')->nullable();
			$table->uuid('linnworks_order_id')->nullable()->index();
			$table->string('last_notified_status')->nullable();
			$table->json('payload')->nullable();
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::dropIfExists('mercadolibre_orders');
	}
};
