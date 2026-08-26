<?php use App\Support\Theme; ?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo e(Theme::forRequest(request())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="robots" content="noindex, nofollow">

    <title><?php echo $__env->yieldContent('title', 'Sign in'); ?> · <?php echo e(config('zephryx.brand.name')); ?> <?php echo e(config('zephryx.brand.suffix')); ?></title>

    <link rel="icon" href="<?php echo e(asset('assets/brand/z-black.svg')); ?>" media="(prefers-color-scheme: light)">
    <link rel="icon" href="<?php echo e(asset('assets/brand/z-white.svg')); ?>" media="(prefers-color-scheme: dark)">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="auth-body">
    <a class="skip-link" href="#auth-form">Skip to the form</a>

    <main class="auth">
        <?php echo $__env->make('auth.partials.visual-panel', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <section class="form-panel">
            <div class="theme-toggle-slot">
                <?php echo $__env->make('partials.theme-toggle', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

            <?php echo $__env->yieldContent('form'); ?>
        </section>
    </main>
</body>
</html>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/layouts/auth.blade.php ENDPATH**/ ?>