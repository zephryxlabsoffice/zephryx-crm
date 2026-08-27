<?php $__env->startSection('title', 'Too many attempts'); ?>
<?php $__env->startSection('code', '429'); ?>
<?php $__env->startSection('tone', 'tone-warn'); ?>

<?php $__env->startSection('mark'); ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
        <path d="M12 9v4M12 17h.01"/>
    </svg>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('heading', 'Slow down for a moment'); ?>

<?php $__env->startSection('message'); ?>
    That was a lot of requests in a short time, so we have paused them briefly.
    Wait a minute and try again.
<?php $__env->stopSection(); ?>

<?php $__env->startSection('actions'); ?>
    <a class="btn btn-primary" href="<?php echo e(url('/dashboard')); ?>">Go to dashboard</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/errors/429.blade.php ENDPATH**/ ?>