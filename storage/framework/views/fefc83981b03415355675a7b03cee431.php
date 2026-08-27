<?php use App\Support\ProjectPresenter as P; ?>

<section class="rail-card">
    <div class="rail-hd">
        <strong>Upcoming Deadlines</strong>
        <a class="card-link" href="<?php echo e(route('projects.index')); ?>">View all</a>
    </div>

    <div class="rail-list">
        <?php $__empty_1 = true; $__currentLoopData = $upcoming; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php $due = $item['deadline_meta']; ?>
            <div class="rail-row">
                <div class="rail-ic <?php echo e(P::tint($item['id'])); ?>" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    </svg>
                </div>
                <div class="rail-body">
                    <strong><?php echo e($item['name']); ?></strong>
                    <span><?php echo e($item['client']); ?></span>
                </div>
                <span class="rail-meta">
                    <strong><?php echo e(P::date($item['deadline'])); ?></strong>
                    
                    <em class="<?php echo e($due['state']); ?>"><?php echo e($due['label']); ?></em>
                </span>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="rail-empty">No deadlines coming up.</p>
        <?php endif; ?>
    </div>
</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/projects/partials/deadlines.blade.php ENDPATH**/ ?>