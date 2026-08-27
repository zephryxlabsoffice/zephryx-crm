<?php
    $actions = [
        ['label' => 'Add Project',   'icon' => 'projects',      'tone' => '',            'route' => 'projects.create'],
        ['label' => 'Create Task',   'icon' => 'tasks',         'tone' => 'tone-accent', 'route' => 'tasks.index'],
        ['label' => 'Submit EOD',    'icon' => 'announcements', 'tone' => 'tone-warn',   'route' => 'projects.updates'],
        ['label' => 'Assign Team',   'icon' => 'teams',         'tone' => '',            'route' => 'teams.index'],
        ['label' => 'Project Report','icon' => 'reports',       'tone' => 'tone-alt',    'route' => 'reports.index'],
        ['label' => 'View Meetings', 'icon' => 'meetings',      'tone' => 'tone-danger', 'route' => 'meetings.index'],
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
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/projects/partials/quick-actions.blade.php ENDPATH**/ ?>