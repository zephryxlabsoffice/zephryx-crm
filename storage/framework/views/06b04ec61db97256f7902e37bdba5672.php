<?php use App\Support\Chart; ?>

<section class="rail-card">
    <div class="rail-hd">
        <strong>Project Status</strong>
    </div>

    <?php if($breakdown === []): ?>
        <p class="rail-empty">No projects to break down yet.</p>
    <?php else: ?>
        <div class="chart-stack">
            
            <div class="donut">
                <svg viewBox="0 0 132 132" aria-hidden="true">
                    <circle class="ring-bg" cx="66" cy="66" r="<?php echo e($donutRadius); ?>" stroke-width="18"/>
                    <?php $offset = 0; ?>
                    <?php $__currentLoopData = $breakdown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $slice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $seg = Chart::donutSegment($slice['share'], $offset, $circumference); ?>
                        <circle class="seg <?php echo e(Chart::dot($index)); ?>"
                                cx="66" cy="66" r="<?php echo e($donutRadius); ?>"
                                stroke="var(--dot)"
                                stroke-dasharray="<?php echo e($seg['dash']); ?>"
                                stroke-dashoffset="<?php echo e($seg['offset']); ?>"/>
                        <?php $offset += $slice['share']; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </svg>
                <div class="donut-center">
                    <b><?php echo e(number_format($stats['total'])); ?></b>
                    <span>Total</span>
                </div>
            </div>

            <ul class="chart-legend">
                <?php $__currentLoopData = $breakdown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $slice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="chart-leg <?php echo e(Chart::dot($index)); ?>">
                        <strong><?php echo e($slice['name']); ?></strong>
                        <em><?php echo e($slice['count']); ?> (<?php echo e($slice['share']); ?>%)</em>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>
</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/projects/partials/status-chart.blade.php ENDPATH**/ ?>