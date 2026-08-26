
<?php
    $actions = [
        ['label' => 'Add Client',       'icon' => 'clients',       'tone' => '',             'route' => 'clients.create'],
        ['label' => 'Create Invoice',   'icon' => 'invoices',      'tone' => 'tone-warn',    'route' => 'invoices.index'],
        ['label' => 'Send Proposal',    'icon' => 'announcements', 'tone' => 'tone-accent',  'route' => 'clients.index'],
        ['label' => 'Create Ticket',    'icon' => 'tickets',       'tone' => 'tone-danger',  'route' => 'tickets.index'],
        ['label' => 'Schedule Meeting', 'icon' => 'meetings',      'tone' => '',             'route' => 'meetings.index'],
        ['label' => 'Add Document',     'icon' => 'projects',      'tone' => 'tone-alt',     'route' => 'projects.index'],
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
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/clients/partials/quick-actions.blade.php ENDPATH**/ ?>