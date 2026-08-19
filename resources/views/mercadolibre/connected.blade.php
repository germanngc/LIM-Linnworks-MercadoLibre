<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Authorized: Mercado Libre / Linnworks</title>
		<link rel="stylesheet" href="{{ asset('css/app.css') }}">
	</head>
	<body class="bg-zinc-100">
		<div class="flex items-center justify-center min-h-screen max-w-lg mx-auto w-full">
			<div class="bg-white mt-4 px-8 py-6 shadow-lg text-center w-full">
				<div class="mt-8 text-lg">
					Mercado Libre connected as:
					<strong class="block font-bold">{{ $account->nickname ?? $account->ml_user_id }}</strong>
					<span class="block text-sm text-zinc-600 mt-2">site {{ $account->site_id ?? 'n/a' }}</span>
				</div>
				<p class="mt-4 text-sm text-zinc-600">Run <code>php artisan MercadoLibreSync:task</code> to pull orders.</p>
			</div>
		</div>
	</body>
</html>
