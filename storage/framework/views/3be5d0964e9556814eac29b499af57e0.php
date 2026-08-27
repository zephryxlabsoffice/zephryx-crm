<?php use App\Support\EmployeePresenter as P; ?>

<section class="rail-card">
    <div class="rail-hd">
        <strong>Employees by Department</strong>
        <a class="card-link" href="<?php echo e(route('teams.index')); ?>">View all</a>
    </div>

    <?php if($breakdown === []): ?>
        <p class="rail-empty">No departments to show yet.</p>
    <?php else: ?>
        <div class="chart-stack">
            
            <div class="donut">
                <svg viewBox="0 0 132 132" aria-hidden="true">
                    <circle class="ring-bg" cx="66" cy="66" r="<?php echo e($donutRadius); ?>" stroke-width="18"/>
                    <?php $offset = 0; ?>
                    <?php $__currentLoopData = $breakdown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $dept): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $seg = P::donutSegment($dept['share'], $offset, $circumference); ?>
                        <circle class="seg <?php echo e(P::dot($index)); ?>"
                                cx="66" cy="66" r="<?php echo e($donutRadius); ?>"
                                stroke="var(--dot)"
                                stroke-dasharray="<?php echo e($seg['dash']); ?>"
                                stroke-dashoffset="<?php echo e($seg['offset']); ?>"/>
                        <?php $offset += $dept['share']; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </svg>
                <div class="donut-center">
                    <b><?php echo e(number_format($stats['total'])); ?></b>
                    <span>Total</span>
                </div>
            </div>

            <ul class="chart-legend">
                <?php $__currentLoopData = $breakdown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $dept): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="chart-leg <?php echo e(P::dot($index)); ?>">
                        <strong><?php echo e($dept['name']); ?></strong>
                        <em><?php echo e($dept['count']); ?> (<?php echo e($dept['share']); ?>%)</em>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>
</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/employees/partials/departments.blade.php ENDPATH**/ ?>