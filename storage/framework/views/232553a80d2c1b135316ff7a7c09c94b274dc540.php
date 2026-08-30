<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Mercado Libre / Linnworks</title>
		<link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;1,100;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
		<style>
			.spin{display:inline-block;width:.9rem;height:.9rem;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:spin .7s linear infinite;vertical-align:-.15rem;flex-shrink:0}
			.spin-lg{width:2rem;height:2rem;border-width:3px}
			@keyframes spin{to{transform:rotate(360deg)}}
			.busy-veil{display:none;position:fixed;inset:0;background:rgba(255,255,255,.45);z-index:60;align-items:center;justify-content:center}
			body.is-busy .busy-veil{display:flex}
			.status-pill{display:inline-flex;align-items:center;gap:.4rem;padding:.28rem .65rem;border-radius:999px;font-size:.75rem;font-weight:600;line-height:1;white-space:nowrap}
			.status-dot{width:.45rem;height:.45rem;border-radius:50%;flex-shrink:0}
			.status-dot.live{animation:pulse 1.6s ease-out infinite}
			@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(22,163,74,.45)}70%{box-shadow:0 0 0 .4rem rgba(22,163,74,0)}100%{box-shadow:0 0 0 0 rgba(22,163,74,0)}}
		</style>
	</head>
	<body class="bg-zinc-100" style="font-family: 'IBM Plex Sans', sans-serif;">
		<div class="flex items-start justify-center min-h-screen w-full" style="padding:0.75rem 0.75rem 1.5rem;">
			<div class="bg-white shadow-lg w-full" style="max-width:92rem;padding:1.75rem 2rem;">
				<div class="flex gap-4 items-center justify-between">
					<div class="flex gap-4 items-center">
						<img class="h-10" src="<?php echo e(asset('images/Linnworks-Logo.png')); ?>" alt="Linnworks" />
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
					<?php if($account): ?>
						<span class="text-sm text-green-600 font-bold">Connected</span>
					<?php endif; ?>
				</div>

				<h1 class="text-lg font-bold mt-8">Orders &amp; shipments</h1>
				<p class="text-sm text-zinc-600 mt-2">Track Mercado Libre orders and Linnworks shipping from this page.</p>

				<?php if($account): ?>
					<div class="mt-8 px-6 py-6 bg-zinc-100 rounded-lg">
						<div class="flex items-center justify-between gap-8 flex-wrap">
							<div style="min-width:0;flex:1;">
								<div class="text-sm text-zinc-600">Signed in as</div>
								<div class="font-bold" style="overflow-wrap:anywhere;"><?php echo e($account->nickname ?? $account->ml_user_id); ?></div>
								<div class="text-sm text-zinc-600 mt-1">Site <?php echo e($account->site_id ?? 'n/a'); ?> · Seller ID <?php echo e($account->ml_user_id); ?></div>
							</div>
							<div style="display:grid;grid-template-columns:minmax(9.5rem,1fr) minmax(9.5rem,1fr);gap:12px;min-width:21rem;">
								<form action="<?php echo e(url('/mercadolibre/sync')); ?>" method="post" style="margin:0;">
									<?php echo csrf_field(); ?>
									<button type="submit" data-busy="Syncing orders…" style="appearance:none;border:0;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:0.4rem;width:100%;height:2.25rem;padding:0 0.95rem;border-radius:0.5rem;background:#18181b;color:#fff;font-size:0.85rem;font-weight:600;line-height:1;white-space:nowrap;">
										Sync orders
									</button>
								</form>
								<form action="<?php echo e(url('/mercadolibre/sync-inventory')); ?>" method="post" style="margin:0;">
									<?php echo csrf_field(); ?>
									<button type="submit" data-busy="Syncing inventory…" style="appearance:none;border:0;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:0.4rem;width:100%;height:2.25rem;padding:0 0.95rem;border-radius:0.5rem;background:#FFE600;color:#18181b;font-size:0.85rem;font-weight:600;line-height:1;white-space:nowrap;">
										Sync inventory
									</button>
								</form>
								<form action="<?php echo e(url('/mercadolibre/create-listings')); ?>" method="post" style="margin:0;grid-column:1 / -1;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
									<?php echo csrf_field(); ?>
									<input name="sku" type="text" placeholder="SKU (ej. CF9-76C-7FE)" value="<?php echo e(old('sku')); ?>" style="width:100%;min-width:0;height:2.25rem;padding:0 0.7rem;border:1px solid #d4d4d8;border-radius:0.5rem;font-size:0.8rem;background:#fff;box-sizing:border-box;">
									<button type="submit" data-busy="Publishing…" style="appearance:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:0.4rem;width:100%;height:2.25rem;padding:0 0.95rem;border-radius:0.5rem;background:#fff;color:#18181b;font-size:0.85rem;font-weight:600;line-height:1;white-space:nowrap;border:1px solid #d4d4d8;">
										Create listing
									</button>
								</form>
							</div>
						</div>
					</div>

					<?php if(session('status')): ?>
						<?php
							$rawStatus = trim(preg_replace('/^Sync complete\.?\s*/i', '', (string) session('status')));
							$mlUser = preg_match('/ML user\s+(\S+)/i', $rawStatus, $m) ? rtrim($m[1], ':') : null;
							$inv = null;
							$lis = null;
							$ord = null;
							if (preg_match('/(\d+)\s+items scanned,\s+(\d+)\s+linked,\s+(\d+)\s+created in LW,\s+(\d+)\s+stock\s*→\s*ML,\s+(\d+)\s+stock\s*→\s*LW(?:,\s+(\d+)\s+title\s*→\s*ML)?(?:,\s+(\d+)\s+price\s*→\s*ML)?(?:,\s+(\d+)\s+pictures\s*→\s*ML)?(?:,\s+(\d+)\s+description\s*→\s*ML)?/u', $rawStatus, $m)) {
								$inv = ['scanned' => $m[1], 'linked' => $m[2], 'created' => $m[3], 'to_ml' => $m[4], 'to_lw' => $m[5], 'title_ml' => $m[6] ?? null, 'price_ml' => $m[7] ?? null, 'pictures_ml' => $m[8] ?? null, 'desc_ml' => $m[9] ?? null];
							}
							if (preg_match('/Create listings:\s+(\d+)\s+published,\s+(\d+)\s+skipped[^,]*,\s+(\d+)\s+failed/i', $rawStatus, $m)) {
								$lis = ['published' => $m[1], 'skipped' => $m[2], 'failed' => $m[3]];
							}
							if (preg_match('/(\d+)\s+open orders/i', $rawStatus, $m)) {
								$ord = $m[1];
							}
							$hasCards = $inv || $lis || $ord !== null;
							$statusTitle = $lis ? 'Listing created' : ($inv ? 'Inventory synced' : ($ord !== null ? 'Orders synced' : (preg_match('/^Listing /i', $rawStatus) ? rtrim($rawStatus, '.') : 'Sync complete')));
						?>
						<div class="mt-4 text-sm" style="display:flex;align-items:flex-start;gap:0.65rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:0.5rem;padding:0.7rem 0.9rem;">
							<span style="display:inline-flex;align-items:center;justify-content:center;width:1.35rem;height:1.35rem;border-radius:999px;background:#10b981;color:#fff;font-size:0.7rem;font-weight:700;flex-shrink:0;margin-top:0.05rem;">✓</span>
							<div style="min-width:0;flex:1;">
								<div style="display:flex;align-items:baseline;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;">
									<div style="font-weight:600;line-height:1.3;"><?php echo e($statusTitle); ?></div>
									<?php if($mlUser): ?>
										<div class="text-xs" style="color:#047857;">ML user <?php echo e($mlUser); ?></div>
									<?php endif; ?>
								</div>
								<?php if($hasCards): ?>
									<div style="margin-top:0.3rem;color:#047857;line-height:1.45;">
										<?php if($ord !== null): ?>
											<div><span class="font-semibold"><?php echo e($ord); ?></span> open orders</div>
										<?php endif; ?>
										<?php if($inv): ?>
											<div>
												<span class="font-semibold"><?php echo e($inv['scanned']); ?></span> scanned ·
												<span class="font-semibold"><?php echo e($inv['linked']); ?></span> linked ·
												<span class="font-semibold"><?php echo e($inv['created']); ?></span> created in LW ·
												<span class="font-semibold"><?php echo e($inv['to_ml']); ?></span> stock → ML ·
												<span class="font-semibold"><?php echo e($inv['to_lw']); ?></span> stock → LW
												<?php if($inv['title_ml'] !== null): ?>
													· <span class="font-semibold"><?php echo e($inv['title_ml']); ?></span> title → ML
												<?php endif; ?>
												<?php if($inv['price_ml'] !== null): ?>
													· <span class="font-semibold"><?php echo e($inv['price_ml']); ?></span> price → ML
												<?php endif; ?>
												<?php if($inv['pictures_ml'] !== null): ?>
													· <span class="font-semibold"><?php echo e($inv['pictures_ml']); ?></span> pictures → ML
												<?php endif; ?>
												<?php if($inv['desc_ml'] !== null): ?>
													· <span class="font-semibold"><?php echo e($inv['desc_ml']); ?></span> description → ML
												<?php endif; ?>
											</div>
										<?php endif; ?>
										<?php if($lis): ?>
											<div>
												<span class="font-semibold"><?php echo e($lis['published']); ?></span> published ·
												<span class="font-semibold"><?php echo e($lis['skipped']); ?></span> skipped ·
												<span <?php if((int)$lis['failed'] > 0): ?> style="color:#b91c1c;" <?php endif; ?> class="font-semibold"><?php echo e($lis['failed']); ?></span> failed
											</div>
										<?php endif; ?>
									</div>
								<?php else: ?>
									<div style="margin-top:0.2rem;color:#047857;line-height:1.4;"><?php echo e($rawStatus); ?></div>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
					<?php $__errorArgs = ['sync'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
						<div class="mt-4 text-sm" style="display:flex;align-items:flex-start;gap:0.75rem;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:0.5rem;padding:0.85rem 1rem;">
							<span style="display:inline-flex;align-items:center;justify-content:center;width:1.5rem;height:1.5rem;border-radius:999px;background:#ef4444;color:#fff;font-size:0.75rem;font-weight:700;flex-shrink:0;">!</span>
							<div><?php echo e($message); ?></div>
						</div>
					<?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

					<div class="mt-8 flex items-center justify-between">
						<div>
							<h2 class="font-bold">Open orders <span class="font-normal text-zinc-500">(<?php echo e($orders->count()); ?>)</span></h2>
							<?php if(!empty($closedOrdersCount)): ?>
								<p class="text-xs text-zinc-500 mt-1"><?php echo e($closedOrdersCount); ?> closed/cancelled hidden from sync list</p>
							<?php endif; ?>
						</div>
						<button class="text-sm text-red-600 hover:underline" type="button" onclick="document.getElementById('disconnect-modal').style.display='flex'">Disconnect</button>
					</div>

					<?php if($orders->isEmpty()): ?>
						<div class="mt-4 px-4 py-6 border rounded-lg text-center">
							<p class="font-bold">No open orders</p>
							<p class="text-sm text-zinc-600 mt-2">Sync only pulls open orders. Click <strong>Sync orders</strong> after a purchase comes in.</p>
						</div>
					<?php else: ?>
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
									<?php $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
										<?php
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
										?>
										<tr>
											<td class="px-4 py-2 border font-medium" style="font-variant-numeric:tabular-nums;"><?php echo e($order->ml_order_id); ?></td>
											<td class="px-4 py-2 border">
												<span class="inline-block text-xs font-semibold" style="padding:0.2rem 0.55rem;border-radius:999px;<?php echo e($statusStyle); ?>"><?php echo e(\App\Models\MercadoLibreOrder::humanize($order->status)); ?></span>
											</td>
											<td class="px-4 py-2 border">
												<span class="inline-block text-xs font-semibold" style="padding:0.2rem 0.55rem;border-radius:999px;<?php echo e($shipStyle); ?>"><?php echo e(\App\Models\MercadoLibreOrder::humanize($order->shipping_status)); ?></span>
											</td>
											<td class="px-4 py-2 border text-zinc-700"><?php echo e($order->isFullFulfillment() ? 'Full' : ($order->logistic_type ? \App\Models\MercadoLibreOrder::humanize($order->logistic_type) : 'Seller')); ?></td>
											<td class="px-4 py-2 border">
												<?php if($order->tracking_number): ?>
													<code class="text-xs" style="background:#f4f4f5;padding:0.15rem 0.4rem;border-radius:0.35rem;"><?php echo e($order->tracking_number); ?></code>
												<?php else: ?>
													<span class="text-zinc-400">—</span>
												<?php endif; ?>
											</td>
											<td class="px-4 py-2 border">
												<span class="inline-block text-xs font-semibold" style="padding:0.2rem 0.55rem;border-radius:999px;<?php echo e($linked ? 'background:#ecfdf5;color:#047857;' : 'background:#fffbeb;color:#b45309;'); ?>">
													<?php echo e($linked ? 'Linked' : 'Not linked'); ?>

												</span>
											</td>
										</tr>
									<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>

					<div class="flex items-center justify-between" style="margin-top:3rem;">
						<h2 class="font-bold">Inventory / Listings <span class="font-normal text-zinc-500">(<?php echo e($listings->count()); ?>)</span></h2>
					</div>
					<p class="text-sm text-zinc-600" style="margin-top:0.5rem;margin-bottom:0.25rem;">Stock syncs both ways by SKU (the side that changed wins; if both change, Linnworks wins). Title, price, pictures and description push Linnworks → Mercado Libre on <strong>Sync inventory</strong>. <strong>Pause</strong> hides the listing; <strong>Activate</strong> brings it back. <strong>Create listing</strong> only publishes SKUs that are not yet on Mercado Libre — needs title, price, stock ≥ 1 and at least one image.</p>

					<?php if($listings->isEmpty()): ?>
						<div class="mt-4 px-4 py-6 border rounded-lg text-center">
							<p class="font-bold">No listings synced yet</p>
							<p class="text-sm text-zinc-600 mt-2">Click <strong>Sync inventory</strong> to map existing ML items, or <strong>Create listings</strong> to publish Linnworks SKUs.</p>
						</div>
					<?php else: ?>
						<div class="mt-4 overflow-x-auto">
							<table class="w-full text-sm text-left border" style="border-collapse:collapse;min-width:56rem;">
								<thead>
									<tr class="bg-zinc-100">
										<th class="px-4 py-2 border font-semibold">SKU</th>
										<th class="px-4 py-2 border font-semibold">ML item</th>
										<th class="px-4 py-2 border font-semibold">Title</th>
										<th class="px-4 py-2 border font-semibold">ML qty</th>
										<th class="px-4 py-2 border font-semibold">LW qty</th>
										<th class="px-4 py-2 border font-semibold">Link</th>
										<th class="px-4 py-2 border font-semibold">Status</th>
										<th class="px-4 py-2 border font-semibold">Action</th>
										<th class="px-4 py-2 border font-semibold">Last sync</th>
									</tr>
								</thead>
								<tbody>
									<?php $__currentLoopData = $listings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $listing): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
										<?php
											$linked = $listing->isLinked();
											$mismatch = $listing->stockMismatch();
										?>
										<tr>
											<td class="px-4 py-2 border"><?php echo e($listing->sku ?: '—'); ?></td>
											<td class="px-4 py-2 border" style="font-variant-numeric:tabular-nums;">
												<?php if($listing->permalink): ?>
													<a href="<?php echo e($listing->permalink); ?>" target="_blank" rel="noopener" class="hover:underline"><?php echo e($listing->ml_item_id); ?></a>
												<?php else: ?>
													<?php echo e($listing->ml_item_id ?: '—'); ?>

												<?php endif; ?>
											</td>
											<td class="px-4 py-2 border text-zinc-700">
												<?php echo e(\Illuminate\Support\Str::limit($listing->title, 40)); ?>

												<?php if($listing->last_error): ?>
													<div class="text-xs mt-1" style="color:#b91c1c;"><?php echo e(\Illuminate\Support\Str::limit($listing->last_error, 80)); ?></div>
												<?php endif; ?>
											</td>
											<td class="px-4 py-2 border"><?php echo e($listing->available_quantity ?? '—'); ?></td>
											<td class="px-4 py-2 border">
												<span <?php if($mismatch): ?> class="inline-block text-xs font-semibold" style="padding:0.2rem 0.55rem;border-radius:999px;background:#fffbeb;color:#b45309;" <?php endif; ?>>
													<?php echo e($listing->linnworks_quantity ?? '—'); ?>

												</span>
											</td>
											<td class="px-4 py-2 border">
												<?php
													$linkLabel = !$listing->isListed() ? 'Not listed' : ($linked ? 'Linked' : 'Unlinked');
													$linkOk = $listing->isListed() && $linked;
												?>
												<span class="inline-block text-xs font-semibold" style="padding:0.2rem 0.55rem;border-radius:999px;<?php echo e($linkOk ? 'background:#ecfdf5;color:#047857;' : 'background:#fffbeb;color:#b45309;'); ?>">
													<?php echo e($linkLabel); ?>

												</span>
											</td>
											<td class="px-4 py-2 border" style="white-space:nowrap;">
												<?php if($listing->isListed()): ?>
													<?php
														$statusKey = strtolower((string) ($listing->ml_status ?: 'active'));
														$statusUi = match ($statusKey) {
															'paused' => ['bg' => '#fff7ed', 'fg' => '#c2410c', 'dot' => '#ea580c', 'live' => false],
															'closed' => ['bg' => '#fef2f2', 'fg' => '#b91c1c', 'dot' => '#dc2626', 'live' => false],
															'under_review' => ['bg' => '#eff6ff', 'fg' => '#1d4ed8', 'dot' => '#2563eb', 'live' => false],
															default => ['bg' => '#ecfdf5', 'fg' => '#047857', 'dot' => '#16a34a', 'live' => true],
														};
													?>
													<span class="status-pill" style="background:<?php echo e($statusUi['bg']); ?>;color:<?php echo e($statusUi['fg']); ?>;">
														<span class="status-dot<?php echo e($statusUi['live'] ? ' live' : ''); ?>" style="background:<?php echo e($statusUi['dot']); ?>;"></span>
														<?php echo e(\App\Models\MercadoLibreOrder::humanize($listing->ml_status ?: 'active')); ?>

													</span>
												<?php else: ?>
													<span class="text-zinc-400">—</span>
												<?php endif; ?>
											</td>
											<td class="px-4 py-2 border">
												<?php if($listing->canPause()): ?>
													<form action="<?php echo e(url('/mercadolibre/listings/'.$listing->id.'/pause')); ?>" method="post" style="margin:0;">
														<?php echo csrf_field(); ?>
														<button type="submit" data-busy="Pausing…" style="appearance:none;cursor:pointer;display:inline-flex;align-items:center;gap:0.35rem;height:2rem;padding:0 0.75rem;border-radius:0.45rem;border:0;background:#71717a;color:#fff;font-size:0.75rem;font-weight:600;white-space:nowrap;">Pause listing</button>
													</form>
												<?php elseif($listing->canActivate()): ?>
													<form action="<?php echo e(url('/mercadolibre/listings/'.$listing->id.'/activate')); ?>" method="post" style="margin:0;">
														<?php echo csrf_field(); ?>
														<button type="submit" data-busy="Activating…" style="appearance:none;cursor:pointer;display:inline-flex;align-items:center;gap:0.35rem;height:2rem;padding:0 0.75rem;border-radius:0.45rem;border:0;background:#16a34a;color:#fff;font-size:0.75rem;font-weight:600;white-space:nowrap;">Activate listing</button>
													</form>
												<?php elseif($listing->isClosed()): ?>
													<span class="text-xs text-zinc-500">Closed</span>
												<?php else: ?>
													<span class="text-zinc-400">—</span>
												<?php endif; ?>
											</td>
											<td class="px-4 py-2 border text-zinc-600"><?php echo e($listing->lastSyncLabel()); ?></td>
										</tr>
									<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				<?php else: ?>
					<div class="mt-8 rounded-lg" style="border:1px solid #e4e4e7;overflow:hidden;">
						<div class="flex" style="min-height:11rem;">
							<div style="width:6px;background:#FFE600;flex-shrink:0;"></div>
							<div class="w-full px-8 py-6 flex items-center justify-between gap-6">
								<div>
									<span class="text-sm font-bold" style="display:inline-block;background:#fef3c7;color:#92400e;padding:0.2rem 0.55rem;border-radius:999px;">Not connected</span>
									<p class="font-bold text-lg mt-4">Connect your Mercado Libre seller account</p>
									<p class="text-sm text-zinc-600 mt-2" style="max-width:28rem;">Authorize once to pull orders, track shipments, and sync Full fulfillment with Linnworks.</p>
								</div>
								<a href="<?php echo e(url('/auth/mercadolibre')); ?><?php echo e(!empty($linnworkUserId) ? '?linnwork_user_id='.$linnworkUserId : ''); ?>" target="_top" class="font-bold text-center" style="background:#FFE600;color:#111;padding:0.75rem 1.25rem;border-radius:0.5rem;white-space:nowrap;">Connect Mercado Libre</a>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div id="disconnect-modal" class="items-center justify-center" style="display:none;position:fixed;inset:0;background:rgba(24,24,27,0.45);z-index:50;" onclick="if(event.target===this) this.style.display='none'">
			<div class="bg-white px-8 py-6 shadow-lg rounded-lg text-left" style="width:22rem;max-width:calc(100% - 2rem);">
				<p class="font-bold text-lg">Disconnect Mercado Libre?</p>
				<p class="text-sm text-zinc-600 mt-2">Order sync will stop until you connect the seller account again.</p>
				<div class="flex items-center justify-end gap-4 mt-8">
					<button type="button" class="px-4 py-2 text-sm text-zinc-600 hover:underline" onclick="document.getElementById('disconnect-modal').style.display='none'">Cancel</button>
					<form action="<?php echo e(url('/mercadolibre/disconnect')); ?>" method="post">
						<?php echo csrf_field(); ?>
						<button type="submit" data-busy="Disconnecting…" class="px-6 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-900" style="display:inline-flex;align-items:center;gap:0.4rem;">Disconnect</button>
					</form>
				</div>
			</div>
		</div>
		<div class="busy-veil" aria-hidden="true">
			<span class="spin spin-lg" style="color:#18181b;border-color:#18181b;border-right-color:transparent;"></span>
		</div>
		<script>
			document.addEventListener('submit', function (e) {
				var form = e.target;
				if (!form || form.tagName !== 'FORM') return;
				if (form.getAttribute('data-busy') === '1') {
					e.preventDefault();
					return;
				}
				form.setAttribute('data-busy', '1');
				var btn = form.querySelector('button[type="submit"]');
				var label = (btn && btn.getAttribute('data-busy')) || 'Working…';
				document.body.classList.add('is-busy');
				document.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
				if (btn) {
					btn.innerHTML = '<span class="spin" aria-hidden="true"></span> ' + label;
				}
			});
		</script>
	</body>
</html>
<?php /**PATH /var/www/resources/views/mercadolibre/dashboard.blade.php ENDPATH**/ ?>