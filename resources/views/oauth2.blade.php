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
	<body class="bg-zinc-100">
		<div class="flex items-center justify-center min-h-screen max-w-lg mx-auto w-full">
			<div class="bg-white mt-4 px-8 py-6 shadow-lg text-center w-full">
				<div class="flex gap-6 items-center justify-center">
					<img class="h-10" src="{{ asset('images/Linnworks-Logo.png') }}" alt="Linnworks" />
					<img class="h-10" src="{{ asset('images/mercadolibre-logo.svg') }}" alt="Mercado Libre" />
				</div>
				<p class="mt-6 text-sm text-zinc-600">Connect your Mercado Libre seller to Linnworks.</p>
				<a class="inline-block px-6 py-2 mt-4 text-white bg-zinc-600 rounded-lg hover:bg-zinc-900" href="{{ url('/auth/mercadolibre') }}">Connect Mercado Libre</a>
			</div>
		</div>
	</body>
</html>
