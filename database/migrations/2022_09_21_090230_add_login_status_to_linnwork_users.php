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
		Schema::table('linnwork_users', function (Blueprint $table) {
			$table->boolean('login_status')->default(0)->after('klaviyo_token');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('linnwork_users', function (Blueprint $table) {
			$table->dropColumn('login_status');
		});
	}
};
