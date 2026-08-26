<?php $__env->startSection('title', $moduleLabel); ?>
<?php $__env->startSection('page-heading', $moduleLabel); ?>
<?php $__env->startSection('page-subheading', 'Not built yet.'); ?>

<?php $__env->startSection('content'); ?>
    <div class="card">
        <div class="card-body">
            <?php echo $__env->make('partials.notice', [
                'tone' => 'info',
                'title' => 'Placeholder',
                'message' => $moduleLabel.' has a navigation entry and a route so the shell can be '
                    .'reviewed as a whole, but the module itself has not been built. It is next in '
                    .'line when the roadmap reaches it.',
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/modules/placeholder.blade.php ENDPATH**/ ?>