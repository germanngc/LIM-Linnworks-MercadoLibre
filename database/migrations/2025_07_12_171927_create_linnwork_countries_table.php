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
        Schema::create('linnwork_countries', function (Blueprint $table) {
            $table->uuid('CountryId')->primary();
            $table->string('CountryName');
            $table->string('CountryCode');
            $table->string('CountryPhoneCode')->default('1');
            $table->string('Continent')->nullable();
            $table->string('Currency')->default('USD');
            $table->boolean('CustomsRequired')->default(false);
            $table->decimal('TaxRate', 10, 2)->default(0.0);
            $table->string('AddressFormat')->nullable();
            $table->json('Regions')->nullable();
            $table->integer('RegionsCount')->default(0);
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
        Schema::dropIfExists('linnwork_countries');
    }
};
