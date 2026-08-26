<?php use App\Support\Theme; ?>
<!DOCTYPE html>

<html lang="en"
      data-theme="<?php echo e(Theme::forRequest(request())); ?>"
      data-sidebar="<?php echo e($sidebarState); ?>"
      data-density="<?php echo e($density); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="robots" content="noindex, nofollow">

    <title><?php echo $__env->yieldContent('title', 'Dashboard'); ?> · <?php echo e(config('zephryx.brand.name')); ?> <?php echo e(config('zephryx.brand.suffix')); ?></title>

    <link rel="icon" href="<?php echo e(asset('assets/brand/z-black.svg')); ?>" media="(prefers-color-scheme: light)">
    <link rel="icon" href="<?php echo e(asset('assets/brand/z-white.svg')); ?>" media="(prefers-color-scheme: dark)">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="app-body"
      data-sidebar="<?php echo e($sidebarState); ?>"
      data-mobile-nav="closed">

    <a class="skip-link" href="#page">Skip to content</a>

    <div class="app">
        <?php echo $__env->make('partials.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <div class="main">
            <?php echo $__env->make('partials.topbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <main class="page" id="page">
                <?php if (! empty(trim($__env->yieldContent('page-heading')))): ?>
                    <div class="page-hd">
                        <h1><?php echo $__env->yieldContent('page-heading'); ?></h1>
                        <?php if (! empty(trim($__env->yieldContent('page-subheading')))): ?>
                            <p><?php echo $__env->yieldContent('page-subheading'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php echo $__env->yieldContent('content'); ?>
            </main>
        </div>
    </div>

    
    <div class="mobile-scrim" data-mobile-scrim></div>
</body>
</html>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/layouts/app.blade.php ENDPATH**/ ?>