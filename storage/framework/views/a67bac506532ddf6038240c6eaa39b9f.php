<?php use App\Support\Avatar; ?>

<section class="rail-card">
    <div class="rail-hd">
        <strong>Upcoming Birthdays</strong>
    </div>

    <div class="rail-list">
        <?php $__empty_1 = true; $__currentLoopData = $birthdays; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $person): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="person-row">
                <span class="avatar <?php echo e(Avatar::tint($person['name'])); ?>" aria-hidden="true"><?php echo e(Avatar::initials($person['name'])); ?></span>
                <div class="person-body">
                    <strong><?php echo e($person['name']); ?></strong>
                    <span><?php echo e($person['date']); ?></span>
                </div>
                <span class="person-meta"><?php echo e($person['countdown']); ?></span>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            
            <p class="rail-empty">No birthdays recorded yet.</p>
        <?php endif; ?>
    </div>
</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/employees/partials/birthdays.blade.php ENDPATH**/ ?>