<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChannelTenant extends Model
{
	protected $table = 'channel_tenants';

	protected $fillable = [
		'linnworks_user_id',
		'linnworks_email',
		'authorization_token',
		'site_id',
		'active',
	];

	protected $casts = [
		'active' => 'boolean',
	];

	public static function generateAuthorizationToken(): string
	{
		return (string) Str::uuid();
	}
}
