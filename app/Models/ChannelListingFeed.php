<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelListingFeed extends Model
{
	protected $table = 'channel_listing_feeds';

	protected $fillable = [
		'channel_tenant_id',
		'channel_feed_id',
		'operation',
		'product_feeds',
	];

	protected $casts = [
		'product_feeds' => 'array',
	];
}
