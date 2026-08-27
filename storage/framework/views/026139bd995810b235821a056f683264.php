<?php
    use App\Support\Avatar;
    use App\Support\ProjectPresenter as P;
?>

<?php $__env->startSection('title', 'Submit EOD'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-hd-row">
        <div class="detail-hd">
            <a class="hd-back" href="<?php echo e(route('projects.mine')); ?>">
                <span class="sr-only">Back to my projects</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>

            <div class="page-hd">
                <h1>End of Day Updates</h1>
                <p>Submit today's update for each project you are on.</p>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-hd">
            <span class="card-title">Your projects</span>
        </div>

        <div class="card-body-table">
            <table class="data-table data-table-stack" role="table">
                <thead>
                    <tr role="row">
                        <th role="columnheader" scope="col">Project</th>
                        <th role="columnheader" scope="col">Client</th>
                        <th role="columnheader" scope="col">Manager</th>
                        <th role="columnheader" scope="col"><span class="sr-only">Submit</span></th>
                    </tr>
                </thead>

                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr role="row">
                            <td role="cell" class="cell-lead" data-label="Project">
                                <a class="row-link proj-cell" href="<?php echo e(route('projects.show', ['project' => $item['id']])); ?>">
                                    <span class="chip <?php echo e(P::tint($item['id'])); ?>" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                        </svg>
                                    </span>
                                    <span class="proj-cell-text">
                                        <strong><?php echo e($item['name']); ?></strong>
                                        <span><?php echo e($item['id']); ?></span>
                                    </span>
                                </a>
                            </td>

                            <td role="cell" data-label="Client"><?php echo e($item['client']); ?></td>

                            <td role="cell" data-label="Manager">
                                <?php if($item['manager_record']): ?>
                                    <span class="pm-cell">
                                        <span class="avatar <?php echo e(Avatar::tint($item['manager_record']['name'])); ?>" aria-hidden="true"><?php echo e(Avatar::initials($item['manager_record']['name'])); ?></span>
                                        <strong><?php echo e($item['manager_record']['name']); ?></strong>
                                    </span>
                                <?php else: ?>
                                    <span class="pm-empty">Unassigned</span>
                                <?php endif; ?>
                            </td>

                            <td role="cell" class="cell-actions cell-actions-wide" data-label="Update">
                                <a class="eod-link" href="<?php echo e(route('projects.updates.create', ['project' => $item['id']])); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>
                                    </svg>
                                    Submit EOD
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr role="row">
                            <td role="cell" colspan="4">
                                <div class="table-empty">
                                    <strong>No projects assigned to you.</strong>
                                    There is nothing to report on today.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($projects->total() > 0): ?>
            <?php echo $__env->make('partials.pagination', ['paginator' => $projects, 'unit' => 'projects'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/projects/updates.blade.php ENDPATH**/ ?>