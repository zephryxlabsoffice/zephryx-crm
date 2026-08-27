<?php use App\Support\TaskPresenter as P; ?>

<section class="rail-card">
    <div class="rail-hd">
        <strong>Upcoming Deadlines</strong>
        <a class="card-link" href="<?php echo e(route('tasks.index')); ?>">View all</a>
    </div>

    <div class="rail-list">
        <?php $__empty_1 = true; $__currentLoopData = $upcoming; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php $due = $item['due_meta']; ?>
            <div class="rail-row">
                <div class="rail-ic <?php echo e(P::tint($item['id'])); ?>" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 12l2 2 4-4"/>
                    </svg>
                </div>
                <div class="rail-body">
                    <strong><?php echo e($item['name']); ?></strong>
                    <span><?php echo e($item['project_record']['name'] ?? 'No project'); ?></span>
                </div>
                <span class="rail-meta">
                    <strong><?php echo e(P::date($item['due'])); ?></strong>
                    <em class="<?php echo e($due['state']); ?>"><?php echo e($due['label']); ?></em>
                </span>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="rail-empty">Nothing due soon.</p>
        <?php endif; ?>
    </div>
</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/tasks/partials/deadlines.blade.php ENDPATH**/ ?>