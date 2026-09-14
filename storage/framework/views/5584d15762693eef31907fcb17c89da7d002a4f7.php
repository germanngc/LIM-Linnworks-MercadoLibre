<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<title>Klaviyo / Linnworks Integration</title>

		<!-- CSS -->
		<link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">

		<!-- Fonts -->
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;1,100;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
	</head>

	<body class="bg-zinc-100">
		<div class="flex items-center justify-center min-h-screen max-w-lg mx-auto w-full">
			<div class="bg-white mt-4 px-8 py-6 shadow-lg text-center w-full">
				<div class="flex gap-6 items-center justify-center">
					<img class="h-10" src="<?php echo e(asset('images/klaviyo-logo-black.png')); ?>" />
					<img class="h-10" src="<?php echo e(asset('images/Linnworks-Logo.png')); ?>" />
				</div>

				<form action="<?php echo e(url('/auth/klaviyo')); ?>" method="post" onsubmit="return false;">
					<div class="mt-4">
						<?php echo csrf_field(); ?>
						<input name="token" type="hidden" value="<?php echo e(app('request')->input('token') ?? ''); ?>" />

						<?php $__errorArgs = ['bad_request'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> 
						<div class="text-red-600 mt-4 py-2"><?php echo e($message); ?></div>
						<?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

						<?php $__errorArgs = ['token_expired'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> 
						<div class="text-red-600 mt-4 py-2"><?php echo e($message); ?></div>
						<?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> 

						<div id="errMessage" class="text-red-600 mt-4 py-2 hidden"></div>
						<div id="successMessage" class="text-green-600 mt-4 py-2 hidden"></div>

						<div class="flex items-baseline justify-center">
							<button class="px-6 py-2 mt-4 text-white bg-zinc-600 rounded-lg hover:bg-zinc-900"
								onclick="window.open('<?php echo e(url('/auth/klaviyo')); ?>?token=<?php echo e(app('request')->input('token') ?? ''); ?>', 'OAuth2Popup', 'width=600,height=700')">Authorize</button>
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
<?php /**PATH /Users/maxmancera/Documentos - Local/NEW NINA CODE/LIM-Linnworks-MercadoLibre/resources/views/oauth2.blade.php ENDPATH**/ ?>