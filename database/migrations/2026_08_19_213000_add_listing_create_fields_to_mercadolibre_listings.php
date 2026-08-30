<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up()
	{
		Schema::table('mercadolibre_listings', function (Blueprint $table) {
			$table->string('ml_item_id')->nullable()->change();
			$table->string('source', 16)->nullable()->after('last_sync_direction');
			$table->string('category_id', 32)->nullable()->after('source');
			$table->string('permalink', 512)->nullable()->after('category_id');
			$table->text('last_error')->nullable()->after('permalink');
		});
	}

	public function down()
	{
		Schema::table('mercadolibre_listings', function (Blueprint $table) {
			$table->dropColumn(['source', 'category_id', 'permalink', 'last_error']);
		});
	}
};
