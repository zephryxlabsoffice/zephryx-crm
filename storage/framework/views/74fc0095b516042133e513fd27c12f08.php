<?php use App\Support\Avatar; ?>

<section class="rail-card">
    <div class="rail-hd">
        <strong>New Employees</strong>
        <a class="card-link" href="<?php echo e(route('employees.index')); ?>">View all</a>
    </div>

    <div class="rail-list">
        <?php $__empty_1 = true; $__currentLoopData = $starters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $person): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="person-row">
                <span class="avatar <?php echo e(Avatar::tint($person['name'])); ?>" aria-hidden="true"><?php echo e(Avatar::initials($person['name'])); ?></span>
                <div class="person-body">
                    <strong><?php echo e($person['name']); ?></strong>
                    <span><?php echo e($person['designation']); ?></span>
                </div>
                <span class="person-meta"><?php echo e($person['when']); ?></span>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="rail-empty">Nobody has joined recently.</p>
        <?php endif; ?>
    </div>
</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/employees/partials/starters.blade.php ENDPATH**/ ?>