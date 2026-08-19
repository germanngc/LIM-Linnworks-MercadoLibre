<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Mercado Libre / Linnworks</title>
		<link rel="stylesheet" href="{{ asset('css/app.css') }}">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;1,100;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
	</head>
	<body class="bg-zinc-100" style="font-family: 'IBM Plex Sans', sans-serif;">
		<div class="flex items-center justify-center min-h-screen w-full px-4 py-6">
			<div class="bg-white px-8 py-6 shadow-lg w-full" style="max-width: 56rem;">
				<div class="flex gap-4 items-center justify-between">
					<div class="flex gap-4 items-center">
						<img class="h-10" src="{{ asset('images/Linnworks-Logo.png') }}" alt="Linnworks" />
						<span class="text-zinc-600">+</span>
						<img
							class="h-10"
							src="https://http2.mlstatic.com/frontend-assets/ml-web-navigation/ui-navigation/6.6.92/mercadolibre/logo__large_plus@2x.png"
							alt="Mercado Libre"
							style="height:2.5rem;width:auto;"
							onerror="this.style.display='none';document.getElementById('meli-fallback').style.display='inline';"
						/>
						<span id="meli-fallback" class="font-bold text-lg" style="display:none;color:#333;">Mercado Libre</span>
					</div>
					@if ($account)
						<span class="text-sm text-green-600 font-bold">Connected</span>
					@endif
				</div>

				<h1 class="text-lg font-bold mt-8">Orders &amp; shipments</h1>
				<p class="text-sm text-zinc-600 mt-2">Track Mercado Libre orders and Linnworks shipping from this page.</p>

				@if ($account)
					<div class="mt-8 px-4 py-6 bg-zinc-100 rounded-lg">
						<div class="flex items-center justify-between gap-4 flex-wrap">
							<div>
								<div class="text-sm text-zinc-600">Signed in as</div>
								<div class="font-bold text-lg">{{ $account->nickname ?? $account->ml_user_id }}</div>
								<div class="text-sm text-zinc-600 mt-2">Site {{ $account->site_id ?? 'n/a' }} · Seller ID {{ $account->ml_user_id }}</div>
							</div>
							<div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
								<form action="{{ url('/mercadolibre/sync') }}" method="post" style="margin:0;">
									@csrf
									<button type="submit" style="appearance:none;border:0;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;min-height:2.75rem;padding:0 1.25rem;border-radius:0.65rem;background:#18181b;color:#fff;font-size:0.9rem;font-weight:600;line-height:1;white-space:nowrap;">
										Sync orders
									</button>
								</form>
								<form action="{{ url('/mercadolibre/sync-inventory') }}" method="post" style="margin:0;">
									@csrf
									<button type="submit" style="appearance:none;border:0;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;min-height:2.75rem;padding:0 1.25rem;border-radius:0.65rem;background:#FFE600;color:#18181b;font-size:0.9rem;font-weight:600;line-height:1;white-space:nowrap;">
										Sync inventory
									</button>
								</form>
							</div>
						</div>
					</div>

					@if (session('status'))
						<div class="mt-4 text-sm" style="display:flex;align-items:flex-start;gap:0.75rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:0.5rem;padding:0.85rem 1rem;">
							<span style="display:inline-flex;align-items:center;justify-content:center;width:1.5rem;height:1.5rem;border-radius:999px;background:#10b981;color:#fff;font-size:0.75rem;font-weight:700;flex-shrink:0;">✓</span>
							<div style="min-width:0;">
								<div style="font-weight:600;font-size:0.95rem;line-height:1.3;">Sync complete</div>
								<div style="margin-top:0.2rem;color:#047857;line-height:1.4;">{{ preg_replace('/^Sync complete\.\s*/i', '', session('status')) }}</div>
							</div>
						</div>
					@endif
					@error('sync')
						<div class="mt-4 text-sm" style="display:flex;align-items:flex-start;gap:0.75rem;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:0.5rem;padding:0.85rem 1rem;">
							<span style="display:inline-flex;align-items:center;justify-content:center;width:1.5rem;height:1.5rem;border-radius:999px;background:#ef4444;color:#fff;font-size:0.75rem;font-weight:700;flex-shrink:0;">!</span>
							<div>{{ $message }}</div>
						</div>
					@enderror

					<div class="mt-8 flex items-center justify-between">
						<div>
							<h2 class="font-bold">Open orders <span class="font-normal text-zinc-500">({{ $orders->count() }})</span></h2>
							@if (!empty($closedOrdersCount))
								<p class="text-xs text-zinc-500 mt-1">{{ $closedOrdersCount }} closed/cancelled hidden from sync list</p>
							@endif
						</div>
						<button class="text-sm text-red-600 hover:underline" type="button" onclick="document.getElementById('disconnect-modal').style.display='flex'">Disconnect</button>
					</div>

					@if ($orders->isEmpty())
						<div class="mt-4 px-4 py-6 border rounded-lg text-center">
							<p class="font-bold">No open orders</p>
							<p class="text-sm text-zinc-600 mt-2">Sync only pulls open orders. Click <strong>Sync orders</strong> after a purchase comes in.</p>
						</div>
					@else
						<div class="mt-4 overflow-x-auto">
							<table class="w-full text-sm text-left border" style="border-collapse:collapse;min-width:40rem;">
								<thead>
									<tr class="bg-zinc-100">
										<th class="px-4 py-2 border font-semibold">ML order</th>
										<th class="px-4 py-2 border font-semibold">Status</th>
										<th class="px-4 py-2 border font-semibold">Shipping</th>
										<th class="px-4 py-2 border font-semibold">Type</th>
										<th class="px-4 py-2 border font-semibold">Tracking</th>
										<th class="px-4 py-2 border font-semibold">Linnworks</th>
									</tr>
								</thead>
								<tbody>
									@foreach ($orders as $order)
										@php
											$status = strtolower((string) ($order->status ?? ''));
											$ship = strtolower((string) ($order->shipping_status ?? ''));
											$linked = (bool) $order->linnworks_order_id;
											$statusStyle = match (true) {
												in_array($status, ['paid', 'confirmed'], true) => 'background:#ecfdf5;color:#047857;',
												in_array($status, ['cancelled', 'canceled'], true) => 'background:#fef2f2;color:#b91c1c;',
												default => 'background:#f4f4f5;color:#52525b;',
											};
											$shipStyle = match (true) {
												in_array($ship, ['ready_to_ship', 'shipped', 'delivered'], true) => 'background:#eff6ff;color:#1d4ed8;',
												in_array($ship, ['not_delivered', 'cancelled', 'canceled'], true) => 'background:#fef2f2;color:#b91c1c;',
												default => 'background:#f4f4f5;color:#52525b;',
											};
										@endphp
										<tr>
											<td class="px-4 py-2 border font-medium" style="font-variant-numeric:tabular-nums;">{{ $order->ml_order_id }}</td>
											<td class="px-4 py-2 border">
												<span class="inline-block text-xs font-semibold" style="padding:0.2rem 0.55rem;border-radius:999px;{{ $statusStyle }}">{{ \App\Models\MercadoLibreOrder::humanize($order->status) }}</span>
											</td>
											<td class="px-4 py-2 border">
												<span class="inline-block text-xs font-semibold" style="padding:0.2rem 0.55rem;border-radius:999px;{{ $shipStyle }}">{{ \App\Models\MercadoLibreOrder::humanize($order->shipping_status) }}</span>
											</td>
											<td class="px-4 py-2 border text-zinc-700">{{ $order->isFullFulfillment() ? 'Full' : ($order->logistic_type ? \App\Models\MercadoLibreOrder::humanize($order->logistic_type) : 'Seller') }}</td>
											<td class="px-4 py-2 border">
												@if ($order->tracking_number)
													<code class="text-xs" style="background:#f4f4f5;padding:0.15rem 0.4rem;border-radius:0.35rem;">{{ $order->tracking_number }}</code>
												@else
													<span class="text-zinc-400">—</span>
												@endif
											</td>
											<td class="px-4 py-2 border">
												<span class="inline-block text-xs font-semibold" style="padding:0.2rem 0.55rem;border-radius:999px;{{ $linked ? 'background:#ecfdf5;color:#047857;' : 'background:#fffbeb;color:#b45309;' }}">
													{{ $linked ? 'Linked' : 'Not linked' }}
												</span>
											</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif

					<div class="flex items-center justify-between" style="margin-top:3rem;">
						<h2 class="font-bold">Inventory / Listings <span class="font-normal text-zinc-500">({{ $listings->count() }})</span></h2>
					</div>
					<p class="text-sm text-zinc-600" style="margin-top:0.5rem;margin-bottom:0.25rem;">ML publications mapped to Linnworks by SKU. New ML items create LW stock; LW quantity pushes back to ML.</p>

					@if ($listings->isEmpty())
						<div class="mt-4 px-4 py-6 border rounded-lg text-center">
							<p class="font-bold">No listings synced yet</p>
							<p class="text-sm text-zinc-600 mt-2">Click <strong>Sync inventory</strong>. Items need a seller SKU on Mercado Libre to link.</p>
						</div>
					@else
						<div class="mt-4 overflow-x-auto">
							<table class="w-full text-sm text-left border" style="border-collapse:collapse;min-width:40rem;">
								<thead>
									<tr class="bg-zinc-100">
										<th class="px-4 py-2 border font-semibold">SKU</th>
										<th class="px-4 py-2 border font-semibold">ML item</th>
										<th class="px-4 py-2 border font-semibold">Title</th>
										<th class="px-4 py-2 border font-semibold">ML qty</th>
										<th class="px-4 py-2 border font-semibold">LW qty</th>
										<th class="px-4 py-2 border font-semibold">Link</th>
										<th class="px-4 py-2 border font-semibold">Last sync</th>
									</tr>
								</thead>
								<tbody>
									@foreach ($listings as $listing)
										@php
											$linked = $listing->isLinked();
											$mismatch = $listing->stockMismatch();
										@endphp
										<tr>
											<td class="px-4 py-2 border">{{ $listing->sku ?: '—' }}</td>
											<td class="px-4 py-2 border" style="font-variant-numeric:tabular-nums;">{{ $listing->ml_item_id }}</td>
											<td class="px-4 py-2 border text-zinc-700">{{ \Illuminate\Support\Str::limit($listing->title, 40) }}</td>
											<td class="px-4 py-2 border">{{ $listing->available_quantity ?? '—' }}</td>
											<td class="px-4 py-2 border">
												<span @if($mismatch) class="inline-block text-xs font-semibold" style="padding:0.2rem 0.55rem;border-radius:999px;background:#fffbeb;color:#b45309;" @endif>
													{{ $listing->linnworks_quantity ?? '—' }}
												</span>
											</td>
											<td class="px-4 py-2 border">
												<span class="inline-block text-xs font-semibold" style="padding:0.2rem 0.55rem;border-radius:999px;{{ $linked ? 'background:#ecfdf5;color:#047857;' : 'background:#fffbeb;color:#b45309;' }}">
													{{ $linked ? 'Linked' : 'Unlinked' }}
												</span>
											</td>
											<td class="px-4 py-2 border text-zinc-600">{{ $listing->lastSyncLabel() }}</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif
				@else
					<div class="mt-8 rounded-lg" style="border:1px solid #e4e4e7;overflow:hidden;">
						<div class="flex" style="min-height:11rem;">
							<div style="width:6px;background:#FFE600;flex-shrink:0;"></div>
							<div class="w-full px-8 py-6 flex items-center justify-between gap-6">
								<div>
									<span class="text-sm font-bold" style="display:inline-block;background:#fef3c7;color:#92400e;padding:0.2rem 0.55rem;border-radius:999px;">Not connected</span>
									<p class="font-bold text-lg mt-4">Connect your Mercado Libre seller account</p>
									<p class="text-sm text-zinc-600 mt-2" style="max-width:28rem;">Authorize once to pull orders, track shipments, and sync Full fulfillment with Linnworks.</p>
								</div>
								<a href="{{ url('/auth/mercadolibre') }}{{ !empty($linnworkUserId) ? '?linnwork_user_id='.$linnworkUserId : '' }}" target="_top" class="font-bold text-center" style="background:#FFE600;color:#111;padding:0.75rem 1.25rem;border-radius:0.5rem;white-space:nowrap;">Connect Mercado Libre</a>
							</div>
						</div>
					</div>
				@endif
			</div>
		</div>

		<div id="disconnect-modal" class="items-center justify-center" style="display:none;position:fixed;inset:0;background:rgba(24,24,27,0.45);z-index:50;" onclick="if(event.target===this) this.style.display='none'">
			<div class="bg-white px-8 py-6 shadow-lg rounded-lg text-left" style="width:22rem;max-width:calc(100% - 2rem);">
				<p class="font-bold text-lg">Disconnect Mercado Libre?</p>
				<p class="text-sm text-zinc-600 mt-2">Order sync will stop until you connect the seller account again.</p>
				<div class="flex items-center justify-end gap-4 mt-8">
					<button type="button" class="px-4 py-2 text-sm text-zinc-600 hover:underline" onclick="document.getElementById('disconnect-modal').style.display='none'">Cancel</button>
					<form action="{{ url('/mercadolibre/disconnect') }}" method="post">
						@csrf
						<button type="submit" class="px-6 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-900">Disconnect</button>
					</form>
				</div>
			</div>
		</div>
	</body>
</html>
