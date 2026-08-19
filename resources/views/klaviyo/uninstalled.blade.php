<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="robot" content="noindex, nofollow">
		<meta name="crawler" content="noindex, nofollow">
		<meta name="spider" content="noindex, nofollow">
		<meta name="bot" content="noindex, nofollow">

		<title>Uninstalled: Klaviyo / Linnworks Integration</title>

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

                @if ($successMessage)
                <div class="mt-4 text-justify py-2">
                    <div class="flex items-center gap-4">
                        <svg style="max-width: 60px; width: 100%;" width="121px" height="121px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#16a34a"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M16 3.93552C14.795 3.33671 13.4368 3 12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21C16.9706 21 21 16.9706 21 12C21 11.662 20.9814 11.3283 20.9451 11M21 5L12 14L9 11" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                        <div>{!! $successMessage !!}</div>
                    </div>
                </div>
                @endif

                @if ($errMessage) 
                <div class="mt-4 text-justify py-2">
                    <div class="flex items-center gap-4">
                        <svg style="max-width: 60px; width: 100%;" width="121px" height="121px" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ad2e2e"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M8 18L20 30" stroke="#f04242" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M20 18L8 30" stroke="#f04242" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M34 8C39.0007 12.3609 42 17.9311 42 24C42 30.0689 39.0007 35.6391 34 40" stroke="#f04242" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M27 14C30.7505 16.7256 33 20.2069 33 24C33 27.7931 30.7505 31.2744 27 34" stroke="#f04242" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                        <div>{!! $errMessage !!}</div>
                    </div>
                </div>
                @endif
			</div>
		</div>
	</body>
</html>
