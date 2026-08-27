<?php $__env->startSection('title', 'Session expired'); ?>
<?php $__env->startSection('code', '419'); ?>
<?php $__env->startSection('tone', 'tone-accent'); ?>

<?php $__env->startSection('mark'); ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
    </svg>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('heading', 'Your session expired'); ?>

<?php $__env->startSection('message'); ?>
    You were signed out for security after a period of inactivity, so that form
    was not submitted. Sign in again and it will take you back.
<?php $__env->stopSection(); ?>

<?php $__env->startSection('actions'); ?>
    <a class="btn btn-primary" href="<?php echo e(url('/login')); ?>">Sign in again</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/errors/419.blade.php ENDPATH**/ ?>