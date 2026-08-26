<?php use App\Support\Theme; ?>
<!DOCTYPE html>

<html lang="en" data-theme="<?php echo e(Theme::forRequest(request())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="<?php echo $__env->yieldContent('description', 'The ZephryxLabs workspace.'); ?>">

    <title><?php echo $__env->yieldContent('title', config('zephryx.brand.name').' '.config('zephryx.brand.suffix')); ?></title>

    <link rel="icon" href="<?php echo e(asset('assets/brand/z-black.svg')); ?>" media="(prefers-color-scheme: light)">
    <link rel="icon" href="<?php echo e(asset('assets/brand/z-white.svg')); ?>" media="(prefers-color-scheme: dark)">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="<?php echo $__env->yieldContent('body-class'); ?>">
    <a class="skip-link" href="#main">Skip to content</a>

    <div class="container">
        <header class="topbar">
            <a class="brand" href="<?php echo e(route('landing')); ?>" aria-label="<?php echo e(config('zephryx.brand.name')); ?> <?php echo e(config('zephryx.brand.suffix')); ?> home">
                <img class="brand-mark brand-mark-light" src="<?php echo e(asset('assets/brand/z-black.svg')); ?>" alt="" width="42" height="42">
                <img class="brand-mark brand-mark-dark" src="<?php echo e(asset('assets/brand/z-white.svg')); ?>" alt="" width="42" height="42">
                <span class="brand-name"><?php echo e(config('zephryx.brand.name')); ?><em><?php echo e(config('zephryx.brand.suffix')); ?></em></span>
            </a>

            <?php echo $__env->make('partials.theme-toggle', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </header>

        <main id="main">
            <?php echo $__env->yieldContent('content'); ?>
        </main>
    </div>
</body>
</html>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/layouts/public.blade.php ENDPATH**/ ?>