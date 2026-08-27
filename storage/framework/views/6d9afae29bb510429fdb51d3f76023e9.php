<?php $__env->startSection('title', 'My Tasks'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>My Tasks</h1>
            <p>Everything assigned to you.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-outline" href="<?php echo e(route('tasks.index')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 12l2 2 4-4"/>
                </svg>
                All Tasks
            </a>
        </div>
    </div>

    <?php echo $__env->make('tasks.partials.kpis', ['totalLabel' => 'My Tasks', 'totalSub' => 'Assigned to you'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('tasks.partials.table', [
        'title' => 'Tasks assigned to you',
        'action' => route('tasks.mine'),
        'emptyTitle' => 'Nothing assigned to you.',
        'emptyBody' => 'Tasks a manager or team lead gives you will appear here.',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/tasks/mine.blade.php ENDPATH**/ ?>