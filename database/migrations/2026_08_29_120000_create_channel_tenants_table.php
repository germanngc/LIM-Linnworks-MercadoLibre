<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChannelTenantsTable extends Migration
{
	public function up()
	{
		Schema::create('channel_tenants', function (Blueprint $table) {
			$table->id();
			$table->string('linnworks_user_id')->unique();
			$table->string('linnworks_email')->nullable();
			$table->uuid('authorization_token')->unique();
			$table->string('site_id', 8)->default('MLM');
			$table->boolean('active')->default(true);
			$table->timestamps();
		});

		Schema::create('channel_listing_feeds', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('channel_tenant_id');
			$table->uuid('channel_feed_id')->unique();
			$table->string('operation', 16);
			$table->json('product_feeds')->nullable();
			$table->timestamps();
			$table->foreign('channel_tenant_id')->references('id')->on('channel_tenants')->onDelete('cascade');
		});
	}

	public function down()
	{
		Schema::dropIfExists('channel_listing_feeds');
		Schema::dropIfExists('channel_tenants');
	}
}
