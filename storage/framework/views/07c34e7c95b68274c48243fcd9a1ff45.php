<?php $__env->startSection('title', 'My Teams'); ?>

<?php $__env->startSection('content'); ?>
    
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>My Teams</h1>
            <p>Every team you are a part of.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-outline" href="<?php echo e(route('teams.index')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                All Teams
            </a>
        </div>
    </div>

    <?php echo $__env->make('teams.partials.kpis', ['scope' => 'mine'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('teams.partials.table', ['title' => 'Teams you belong to', 'tools' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/teams/mine.blade.php ENDPATH**/ ?>