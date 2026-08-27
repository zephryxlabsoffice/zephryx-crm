
<section class="rail-card">
    <div class="rail-hd">
        <strong>Recent Activity</strong>
        <a class="card-link" href="<?php echo e(route('notifications.index')); ?>">View all</a>
    </div>

    <div class="rail-list">
        <?php $__empty_1 = true; $__currentLoopData = $activity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="rail-row">
                <div class="rail-ic <?php echo e($entry['tone']); ?>" aria-hidden="true">
                    <?php echo $__env->make('partials.nav-icon', ['icon' => 'teams'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>
                <div class="rail-body">
                    <strong><?php echo e($entry['what']); ?></strong>
                    <span>by <?php echo e($entry['who']); ?> · <?php echo e($entry['when']); ?></span>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="rail-empty">No team changes recorded yet.</p>
        <?php endif; ?>
    </div>
</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/teams/partials/activity.blade.php ENDPATH**/ ?>