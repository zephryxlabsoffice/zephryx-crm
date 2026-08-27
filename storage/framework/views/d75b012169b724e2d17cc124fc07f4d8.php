<?php $__env->startSection('title', 'Back shortly'); ?>
<?php $__env->startSection('code', '503'); ?>
<?php $__env->startSection('tone', 'tone-accent'); ?>

<?php $__env->startSection('mark'); ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
    </svg>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('heading', 'We are doing some maintenance'); ?>

<?php $__env->startSection('message'); ?>
    The workspace is briefly unavailable while we finish an update. Nothing has
    been lost — try again in a few minutes.
<?php $__env->stopSection(); ?>

<?php $__env->startSection('actions'); ?>
    <a class="btn btn-primary" href="<?php echo e(url('/')); ?>">Try again</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/errors/503.blade.php ENDPATH**/ ?>