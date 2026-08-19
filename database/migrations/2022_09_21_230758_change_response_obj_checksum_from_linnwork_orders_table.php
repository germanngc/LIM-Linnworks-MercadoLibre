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
        Schema::table('linnwork_orders', function (Blueprint $table) {
            $table->text('response_obj_checksum')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('linnwork_orders', function (Blueprint $table) {
            $table->json('response_obj_checksum')->change();
        });
    }
};
