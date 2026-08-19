<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinnworkUser extends Model
{
	use HasFactory;

	protected $casts = ['response_obj' => 'array'];
	protected $fillable = ['customer_id', 'user_id', 'aplication_token', 'expiration_date', 'email', 'klaviyo_token', 'login_status', 'push_server', 'server', 'response_obj', 'token', 'access_token', 'expires_in', 'refresh_token'];

	public function orders()
	{
		return $this->hasMany(LinnworkOrder::class);
	}
}
