<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<title>Authorized: Mercado Libre / Linnworks</title>

		<!-- CSS -->
		<link rel="stylesheet" href="{{ asset('css/app.css') }}">

		<!-- Fonts -->
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;1,100;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
	</head>

	<body class="bg-zinc-100">
		<div class="flex items-center justify-center min-h-screen max-w-lg mx-auto w-full">
			<div class="bg-white mt-4 px-8 py-6 shadow-lg text-left w-full">
				<div class="flex gap-6 items-center justify-center">
					<img src="{{ asset('images/Linnworks-Logo.png') }}" alt="Linnworks" style="height:38px;width:auto;max-width:160px;object-fit:contain;" />
					<span class="text-zinc-300" style="font-size:1.35rem;line-height:1;">+</span>
					<img src="{{ asset('images/mercadolibre-logo-plus.png') }}" alt="Mercado Libre" style="height:38px;width:auto;max-width:180px;object-fit:contain;" />
				</div>

				<div class="mt-8 text-center text-lg">
					You are authorized as:
					<strong class="block font-bold">{{ $LinnworkUser->email ?? 'Unknown' }}<strong>
				</div>

				<div class="mt-4">
					@csrf 
					<input name="token" type="hidden" value="{{ app('request')->input('token') ?? '' }}" />
					<input name="email" type="hidden" value="{{ $LinnworkUser->email ?? '' }}" />

					@error('email') 
					<div class="text-red-600 mt-4 py-2">{{ $message }}</div>
					@enderror 

					<div class="flex items-baseline justify-end">
						<a href="/uninstall?token={{ app('request')->input('token') ?? '' }}" class="px-6 py-2 mt-4 text-white bg-red-600 rounded-lg hover:bg-red-900">Revoke Authorization</a>
					</div>
				</div>
			</div>
		</div>
	</body>
</html>
