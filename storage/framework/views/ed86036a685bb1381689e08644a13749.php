<?php
    $actions = [
        ['label' => 'Add Employee',    'icon' => 'employees',   'tone' => '',            'route' => 'employees.create'],
        ['label' => 'Assign Role',     'icon' => 'settings',    'tone' => 'tone-warn',   'route' => 'employees.index'],
        ['label' => 'Mark Attendance', 'icon' => 'attendance',  'tone' => 'tone-accent', 'route' => 'attendance.index'],
        ['label' => 'Run Payroll',     'icon' => 'salary',      'tone' => '',            'route' => 'salary.index'],
        ['label' => 'Leave Requests',  'icon' => 'leave',       'tone' => 'tone-alt',    'route' => 'leave.index'],
        ['label' => 'View Reports',    'icon' => 'reports',     'tone' => 'tone-danger', 'route' => 'reports.index'],
    ];
?>

<section class="rail-card">
    <div class="rail-hd">
        <strong>Quick Actions</strong>
    </div>

    <div class="qa-grid">
        <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a class="qa-tile <?php echo e($action['tone']); ?>" href="<?php echo e(route($action['route'])); ?>">
                <span class="qa-ic" aria-hidden="true">
                    <?php echo $__env->make('partials.nav-icon', ['icon' => $action['icon']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </span>
                <span><?php echo e($action['label']); ?></span>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/employees/partials/quick-actions.blade.php ENDPATH**/ ?>