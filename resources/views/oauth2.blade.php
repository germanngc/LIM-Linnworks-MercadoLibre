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
			<div class="bg-white mt-4 px-8 py-6 shadow-lg text-center w-full">
				<div class="flex gap-6 items-center justify-center">
					<img class="h-10" src="{{ asset('images/klaviyo-logo-black.png') }}" />
					<img class="h-10" src="{{ asset('images/Linnworks-Logo.png') }}" />
				</div>

				<form action="{{ url('/auth/klaviyo') }}" method="post" onsubmit="return false;">
					<div class="mt-4">
						@csrf
						<input name="token" type="hidden" value="{{ app('request')->input('token') ?? '' }}" />

						@error('bad_request') 
						<div class="text-red-600 mt-4 py-2">{{ $message }}</div>
						@enderror

						@error('token_expired') 
						<div class="text-red-600 mt-4 py-2">{{ $message }}</div>
						@enderror 

						<div id="errMessage" class="text-red-600 mt-4 py-2 hidden"></div>
						<div id="successMessage" class="text-green-600 mt-4 py-2 hidden"></div>

						<div class="flex items-baseline justify-center">
							<button class="px-6 py-2 mt-4 text-white bg-zinc-600 rounded-lg hover:bg-zinc-900"
								onclick="window.open('{{ url('/auth/klaviyo') }}?token={{ app('request')->input('token') ?? '' }}', 'OAuth2Popup', 'width=600,height=700')">Authorize</button>
						</div>

						<div class="flex items-baseline justify-center mt-4">
							<a class="text-sm text-zinc-600 hover:underline" 
								href="https://linnworks-klaviyo-assets.s3.us-west-2.amazonaws.com/Linnworks_Klaviyo_Integration_2.0.1.pdf" 
								target="_resources">Need instructions?</a>
						</div>
					</div>
				</form>

				<script>
					window.addEventListener('message', function(event) {
						document.getElementById('errMessage').classList.add('hidden');
						document.getElementById('successMessage').classList.add('hidden');

						if (event.data.success == true) {
							document.getElementById('successMessage').textContent = event.data.message;
							document.getElementById('successMessage').classList.remove('hidden');

							setTimeout(() => {
								window.location.reload();
							}, 2000);
						} else {
							document.getElementById('errMessage').textContent = event.data.message + '\nPlease refresh the browser and try again.';
							document.getElementById('errMessage').classList.remove('hidden');
						}
					});
				</script>
			</div>
		</div>
	</body>
</html>
