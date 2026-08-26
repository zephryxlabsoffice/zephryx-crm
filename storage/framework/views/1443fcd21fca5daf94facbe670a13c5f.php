
<aside class="sidebar" id="sidebar" aria-label="Main navigation">

    <a class="sb-brand" href="<?php echo e($navigation === [] ? url('/') : $navigation[0]['url']); ?>">
        <?php echo $__env->make('partials.brand-mark', ['alt' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <span class="brand-text"><?php echo e(config('zephryx.brand.name')); ?><em><?php echo e(config('zephryx.brand.suffix')); ?></em></span>
    </a>

    <nav class="sb-nav">
        <?php $__currentLoopData = $navigation; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a class="sb-link <?php if($item['active']): ?> active <?php endif; ?>"
               href="<?php echo e($item['url']); ?>"
               <?php if($item['active']): ?> aria-current="page" <?php endif; ?>>
                <span class="sb-ic" aria-hidden="true">
                    <?php echo $__env->make('partials.nav-icon', ['icon' => $item['icon']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </span>
                <span class="sb-label"><?php echo e($item['label']); ?></span>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>

    <div class="sb-foot">
        
        <form method="POST" action="<?php echo e(route('logout')); ?>">
            <?php echo csrf_field(); ?>
            <button type="submit" class="sb-link">
                <span class="sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <path d="M16 17l5-5-5-5"/>
                        <path d="M21 12H9"/>
                    </svg>
                </span>
                <span class="sb-label">Log out</span>
            </button>
        </form>
    </div>
</aside>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/partials/sidebar.blade.php ENDPATH**/ ?>