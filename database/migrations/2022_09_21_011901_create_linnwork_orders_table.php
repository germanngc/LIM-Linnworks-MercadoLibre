<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('linnwork_orders', function (Blueprint $table) {
			$table->id('id');
			$table->unsignedBigInteger('num_order_id')->index();	// Order > Number
			$table->uuid('order_id')->index();						// --
			$table->uuid('user_id')->index();						// --

			$table->json('items_obj')->nullable();					// Items (Obj) {}
			$table->uuid('postal_service_id');						// Shipping Information > Postal Service Id
			$table->string('postal_service_name');					// Shipping Information > Postal Service Name
			$table->dateTimeTz('received_date');					// Created
			$table->json('response_obj')->nullable();				// --
			$table->json('response_obj_checksum');					// --
			$table->json('shipping_address_obj');					// Shipping Address (Obj) {}
			$table->string('source')->default('DIRECT');			// Source
			$table->unsignedTinyInteger('status')->default(0);		// Order > Status
			$table->string('tracking_number')->nullable();			// Shipping Status > Tracking Number
			$table->string('vendor');								// Shipping Information > Vendor
			$table->unsignedDecimal('total_weight', 10, 4);			// Shipping Information > Total Weight
			$table->timestamps();									// --

			$table->foreign('user_id')->references('user_id')->on('linnwork_users');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('linnwork_orders');
	}
};
