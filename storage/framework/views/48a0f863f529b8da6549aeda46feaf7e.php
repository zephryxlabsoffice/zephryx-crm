<?php $__env->startSection('title', 'My Projects'); ?>

<?php $__env->startSection('content'); ?>
    
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>My Projects</h1>
            <p>Every project assigned to you.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="<?php echo e(route('projects.updates')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
                </svg>
                Submit EOD
            </a>

            <a class="btn btn-outline" href="<?php echo e(route('projects.index')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                </svg>
                All Projects
            </a>
        </div>
    </div>

    <?php echo $__env->make('projects.partials.kpis', ['scope' => 'mine'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('projects.partials.table', [
        'title' => 'Projects you are on',
        'tools' => false,
        'progress' => false,
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/projects/mine.blade.php ENDPATH**/ ?>