<?php use App\Support\Theme; ?>
<!DOCTYPE html>

<html lang="en" data-theme="<?php echo e(Theme::forRequest(request())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title><?php echo $__env->yieldContent('title'); ?> · <?php echo e(config('zephryx.brand.name')); ?> <?php echo e(config('zephryx.brand.suffix')); ?></title>

    <link rel="icon" href="<?php echo e(asset('assets/brand/z-black.svg')); ?>" media="(prefers-color-scheme: light)">
    <link rel="icon" href="<?php echo e(asset('assets/brand/z-white.svg')); ?>" media="(prefers-color-scheme: dark)">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body>
    <div class="error-page">
        <header class="error-top">
            <a class="brand" href="<?php echo e(url('/')); ?>" aria-label="<?php echo e(config('zephryx.brand.name')); ?> home">
                <?php echo $__env->make('partials.brand-mark', ['alt' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <span class="brand-name"><?php echo e(config('zephryx.brand.name')); ?><em><?php echo e(config('zephryx.brand.suffix')); ?></em></span>
            </a>

            <?php echo $__env->make('partials.theme-toggle', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </header>

        <main class="error-body">
            <div class="error-card">
                <div class="error-mark <?php echo $__env->yieldContent('tone'); ?>" aria-hidden="true">
                    <?php echo $__env->yieldContent('mark'); ?>
                </div>

                <span class="error-code">Error <?php echo $__env->yieldContent('code'); ?></span>

                <h1 class="error-title"><?php echo $__env->yieldContent('heading'); ?></h1>

                <p class="error-message"><?php echo $__env->yieldContent('message'); ?></p>

                <div class="error-actions">
                    <?php if (! empty(trim($__env->yieldContent('actions')))): ?>
                        <?php echo $__env->yieldContent('actions'); ?>
                    <?php else: ?>
                        <a class="btn btn-primary" href="<?php echo e(url('/dashboard')); ?>">Go to dashboard</a>
                        <a class="btn btn-outline" href="<?php echo e(url('/')); ?>">Back to start</a>
                    <?php endif; ?>
                </div>

                <?php if (! empty(trim($__env->yieldContent('meta')))): ?>
                    <p class="error-meta"><?php echo $__env->yieldContent('meta'); ?></p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/errors/layout.blade.php ENDPATH**/ ?>