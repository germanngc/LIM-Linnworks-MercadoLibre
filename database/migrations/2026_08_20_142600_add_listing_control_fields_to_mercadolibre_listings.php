<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up()
	{
		Schema::table('mercadolibre_listings', function (Blueprint $table) {
			$table->string('ml_status', 32)->nullable()->after('permalink');
			$table->string('lw_pictures_hash', 32)->nullable()->after('ml_status');
		});
	}

	public function down()
	{
		Schema::table('mercadolibre_listings', function (Blueprint $table) {
			$table->dropColumn(['ml_status', 'lw_pictures_hash']);
		});
	}
};
