<?php
    use App\Support\Navigation\Navigation;

    // Leads and Calendar keep their navigation entries and 404 until v2 (§12).
    // Someone who clicked a link we chose to show them deserves to be told it
    // is not built yet, not that it does not exist.
    $deferred = Navigation::deferredEntryFor(request()->path());
?>

<?php $__env->startSection('title', $deferred ? $deferred['label'] : 'Page not found'); ?>
<?php $__env->startSection('code', '404'); ?>

<?php if($deferred): ?>
    <?php $__env->startSection('tone', 'tone-accent'); ?>

    <?php $__env->startSection('mark'); ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
    <?php $__env->stopSection(); ?>

    <?php $__env->startSection('heading', $deferred['label'].' is not built yet'); ?>

    <?php $__env->startSection('message'); ?>
        This module is planned for a later version. Its place in the navigation is
        already reserved, so nothing will move around when it arrives.
    <?php $__env->stopSection(); ?>
<?php else: ?>
    <?php $__env->startSection('mark'); ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            <line x1="8.5" y1="11" x2="13.5" y2="11"/>
        </svg>
    <?php $__env->stopSection(); ?>

    <?php $__env->startSection('heading', 'We cannot find that page'); ?>

    <?php $__env->startSection('message'); ?>
        The link may be out of date, or the page may have been moved. Check the
        address, or head back and try again from the navigation.
    <?php $__env->stopSection(); ?>

    
    <?php $__env->startSection('meta'); ?>
        You asked for <code>/<?php echo e(request()->path()); ?></code>
    <?php $__env->stopSection(); ?>
<?php endif; ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/errors/404.blade.php ENDPATH**/ ?>