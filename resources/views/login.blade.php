<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<title>Klaviyo / Linnworks Integration</title>

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
					<img class="h-10" src="{{ asset('images/klaviyo-logo-black.png') }}" />
					<img class="h-10" src="{{ asset('images/Linnworks-Logo.png') }}" />
				</div>

				<form action="{{ url('/auth') }}" method="post">
					<div class="mt-4">
						@csrf
						<input name="token" type="hidden" value="{{ app('request')->input('token') ?? '' }}" />

						<div class="mt-4">
							<label class="block" for="klaviyo_token">Klaviyo Public API Token</label>
							<input id="klaviyo_token" class="w-full px-4 py-2 mt-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-zinc-600" name="klaviyo_token" placeholder="xxx000" />
							@error('bad_token') 
							<div class="text-red-600 mt-4 py-2">{{ $message }}</div>
							@enderror 
						</div>

						@error('bad_request') 
						<div class="text-red-600 mt-4 py-2">{{ $message }}</div>
						@enderror

						@error('token_expired') 
						<div class="text-red-600 mt-4 py-2">{{ $message }}</div>
						@enderror  

						<div class="flex items-baseline justify-between">
							<a class="text-sm text-zinc-600 hover:underline" href="{{ asset('resources/Linnworks_Klaviyo_Integration_1.0.0.pdf') }}" target="_resources">Need instructions?</a>
							<button class="px-6 py-2 mt-4 text-white bg-zinc-600 rounded-lg hover:bg-zinc-900">Authorize</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</body>
</html>
