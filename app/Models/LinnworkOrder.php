<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinnworkOrder extends Model
{
	use HasFactory;

	protected $casts = ['items_obj' => 'array', 'response_obj' => 'array', 'shipping_address_obj' => 'array', 'customer_obj' => 'array'];
	protected $fillable = ['num_order_id', 'order_id', 'user_id', 'items_obj','customer_obj', 'postal_service_id', 'postal_service_name', 'received_date', 'response_obj', 'response_obj_checksum', 'shipping_address_obj', 'source', 'status', 'tracking_number', 'vendor', 'total_weight'];

	public function user()
	{
		return $this->belongsTo(LinnworkUser::class);
	}
}
