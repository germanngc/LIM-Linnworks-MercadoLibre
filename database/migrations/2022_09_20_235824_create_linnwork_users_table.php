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
		Schema::create('linnwork_users', function (Blueprint $table) {
			$table->id('id');
			$table->unsignedBigInteger('customer_id')->index();
			$table->uuid('user_id')->unique();
			$table->uuid('aplication_token');
			$table->timestamp('expiration_date')->nullable();
			$table->string('email')->index();
			$table->uuid('klaviyo_token');
			$table->string('push_server');
			$table->string('server');
			$table->json('response_obj');
			$table->uuid('token');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('linnwork_users');
	}
};
