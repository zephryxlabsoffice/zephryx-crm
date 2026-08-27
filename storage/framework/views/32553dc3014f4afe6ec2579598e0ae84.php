<?php $__env->startSection('title', 'Something went wrong'); ?>
<?php $__env->startSection('code', '500'); ?>
<?php $__env->startSection('tone', 'tone-danger'); ?>

<?php $__env->startSection('mark'); ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/>
    </svg>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('heading', 'Something went wrong at our end'); ?>


<?php $__env->startSection('message'); ?>
    This is our fault, not yours. The problem has been recorded. Try again in a
    moment, and let an administrator know if it keeps happening.
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/errors/500.blade.php ENDPATH**/ ?>