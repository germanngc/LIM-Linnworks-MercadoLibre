<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinnworkCountry extends Model
{
    use HasFactory;

    protected $table = 'linnwork_countries';

    protected $primaryKey = 'CountryId';

    protected $fillable = [
        'CountryId', 'CountryName', 'CountryCode', 'CountryPhoneCode', 'Continent', 'Currency', 'CustomsRequired', 'TaxRate', 'AddressFormat', 'Regions', 'RegionsCount'
    ];

    protected $casts = [
        'Regions' => 'array',
    ];
}
