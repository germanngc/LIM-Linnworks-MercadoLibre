<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercadoLibreAccount extends Model
{
	protected $table = 'mercadolibre_accounts';

	protected $fillable = [
		'ml_user_id',
		'nickname',
		'site_id',
		'access_token',
		'refresh_token',
		'expires_at',
		'linnwork_user_id',
	];

	protected $casts = [
		'expires_at' => 'datetime',
	];

	protected $hidden = [
		'access_token',
		'refresh_token',
	];

	public function orders()
	{
		return $this->hasMany(MercadoLibreOrder::class);
	}

	public function listings()
	{
		return $this->hasMany(MercadoLibreListing::class);
	}

	public function linnworkUser()
	{
		return $this->belongsTo(LinnworkUser::class, 'linnwork_user_id', 'user_id');
	}

	public function tokenExpired(): bool
	{
		return !$this->expires_at || $this->expires_at->lte(now()->addMinutes(5));
	}
}
