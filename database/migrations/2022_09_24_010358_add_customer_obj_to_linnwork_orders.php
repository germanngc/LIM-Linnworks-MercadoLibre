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
            $table->json("customer_obj")->nullable()->after("response_obj");
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
            $table->dropColumn('customer_obj');
        });
    }
};
