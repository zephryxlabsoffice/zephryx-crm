<section class="rail-card">
    <div class="rail-hd">
        <strong>Task Timeline</strong>
    </div>

    <?php if($timeline === []): ?>
        <p class="rail-empty">Nothing recorded yet.</p>
    <?php else: ?>
        
        <ol class="timeline">
            <?php $__currentLoopData = $timeline; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li class="timeline-row is-done">
                    <span class="timeline-time"><?php echo e($event['when']); ?></span>
                    <strong><?php echo e($event['what']); ?></strong>
                    <span class="timeline-by">by <?php echo e($event['who']); ?></span>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ol>
    <?php endif; ?>
</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/tasks/partials/timeline.blade.php ENDPATH**/ ?>