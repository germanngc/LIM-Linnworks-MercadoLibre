<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Mercado Libre / Linnworks</title>
		<link rel="stylesheet" href="{{ asset('css/app.css') }}">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;1,100;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
	</head>
	<body class="bg-zinc-100" style="font-family:'IBM Plex Sans',sans-serif;">
		<div class="flex items-center justify-center min-h-screen px-4">
			<div class="bg-white shadow-lg w-full" style="max-width:44rem;padding:3.5rem 3rem;display:flex;flex-direction:column;align-items:center;">
				<div style="display:flex;align-items:center;justify-content:center;gap:1.5rem;">
					<img src="{{ asset('images/Linnworks-Logo.png') }}" alt="Linnworks" style="height:48px;width:auto;object-fit:contain;" />
					<span style="color:#a1a1aa;font-size:1.75rem;line-height:1;flex-shrink:0;">+</span>
					<img src="{{ asset('images/mercadolibre-logo-plus.png') }}" alt="Mercado Libre" style="height:72px;width:auto;object-fit:contain;" />
				</div>
				<p style="margin:2.25rem 0 0;color:#52525b;font-size:1.05rem;line-height:1.5;text-align:center;">Connect your Mercado Libre seller to Linnworks.</p>
				<a
					href="{{ url('/auth/mercadolibre') }}{{ ($q = http_build_query(array_filter(['token' => request('token'), 'channel_token' => request('channel_token'), 'linnwork_user_id' => request('linnwork_user_id')]))) ? '?'.$q : '' }}"
					style="display:block;margin-top:1.75rem;background:#ffe600;color:#333;padding:1rem 2.25rem;border-radius:0.6rem;font-weight:600;font-size:1.05rem;text-align:center;"
				>Connect Mercado Libre</a>
			</div>
		</div>
	</body>
</html>
