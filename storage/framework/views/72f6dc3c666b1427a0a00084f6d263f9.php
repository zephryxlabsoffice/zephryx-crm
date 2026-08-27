<?php $__env->startSection('title', 'Not allowed'); ?>
<?php $__env->startSection('code', '403'); ?>
<?php $__env->startSection('tone', 'tone-warn'); ?>

<?php $__env->startSection('mark'); ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2"/>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    </svg>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('heading', 'You do not have access to this'); ?>


<?php $__env->startSection('message'); ?>
    Your account does not have permission to open this page. If you think it
    should, ask an administrator to check your access.
<?php $__env->stopSection(); ?>

<?php $__env->startSection('actions'); ?>
    <a class="btn btn-primary" href="<?php echo e(url('/dashboard')); ?>">Go to dashboard</a>
    
    <a class="btn btn-outline" href="<?php echo e(\App\Support\SupportContact::mailto('ZephryxLabs CRM — access request')); ?>">Contact an administrator</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/errors/403.blade.php ENDPATH**/ ?>