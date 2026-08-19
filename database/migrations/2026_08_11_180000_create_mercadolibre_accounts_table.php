<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up()
	{
		Schema::create('mercadolibre_accounts', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('ml_user_id')->unique();
			$table->string('nickname')->nullable();
			$table->string('site_id', 8)->nullable();
			$table->text('access_token');
			$table->text('refresh_token')->nullable();
			$table->timestamp('expires_at')->nullable();
			$table->uuid('linnwork_user_id')->nullable()->index();
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::dropIfExists('mercadolibre_accounts');
	}
};
