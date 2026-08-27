<?php $__env->startSection('title', 'Team Tasks'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Team Tasks</h1>
            <p>Tasks held by a team rather than a person. Put someone on them.</p>
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

    
    <?php if($stats['total'] > 0): ?>
        <?php echo $__env->make('partials.notice', [
            'tone' => 'info',
            'message' => $stats['total'].' '.\Illuminate\Support\Str::plural('task', $stats['total'])
                .' '.($stats['total'] === 1 ? 'is' : 'are').' assigned to a team but not yet to a person.',
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <?php echo $__env->make('tasks.partials.kpis', ['totalLabel' => 'Team Tasks', 'totalSub' => 'Awaiting an assignee'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('tasks.partials.table', [
        'title' => 'Waiting to be assigned',
        'action' => route('tasks.team'),
        'emptyTitle' => 'Every team task has someone on it.',
        'emptyBody' => 'Nothing is waiting to be assigned.',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/tasks/team.blade.php ENDPATH**/ ?>