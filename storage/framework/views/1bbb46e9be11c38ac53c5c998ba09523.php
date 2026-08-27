<?php $__env->startSection('title', 'Tasks'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Task Overview</h1>
            <p>Track and manage tasks across projects and teams.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="<?php echo e(route('tasks.create')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Assign Task
            </a>

            <a class="btn btn-outline" href="<?php echo e(route('tasks.mine')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                My Tasks
            </a>

            <a class="btn btn-outline" href="<?php echo e(route('tasks.team')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                </svg>
                Team Tasks
            </a>
        </div>
    </div>

    <?php echo $__env->make('tasks.partials.kpis', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <section class="tasks-grid">
        <?php echo $__env->make('tasks.partials.table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <aside class="rail">
            <?php echo $__env->make('tasks.partials.deadlines', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('tasks.partials.activity', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </aside>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/tasks/index.blade.php ENDPATH**/ ?>