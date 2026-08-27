<?php
    use App\Support\Avatar;
    use App\Support\ProjectPresenter as P;
?>


<?php
    $tools = $tools ?? true;
    $progress = $progress ?? true;
    $columns = $progress ? 8 : 7;
?>

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title"><?php echo e($title ?? 'All Projects'); ?></span>

        <?php if($tools): ?>
            <form class="table-tools" method="GET" action="<?php echo e(route('projects.index')); ?>">
                <div class="search-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <label class="sr-only" for="project-search">Search projects</label>
                    <input id="project-search" type="search" name="q" value="<?php echo e($search); ?>" placeholder="Search name, ref or client…">
                </div>

                <label class="sr-only" for="project-status">Filter by status</label>
                <select class="chip-btn" id="project-status" name="status" data-auto-submit>
                    <option value="">All statuses</option>
                    <?php $__currentLoopData = P::statusOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($option); ?>" <?php if($status === $option): echo 'selected'; endif; ?>><?php echo e(P::status($option)['label']); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>

                <label class="sr-only" for="project-priority">Filter by priority</label>
                <select class="chip-btn" id="project-priority" name="priority" data-auto-submit>
                    <option value="">All priorities</option>
                    <?php $__currentLoopData = P::priorityOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($option); ?>" <?php if($priority === $option): echo 'selected'; endif; ?>><?php echo e(P::priority($option)['label']); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>

                <button class="chip-btn" type="submit">Search</button>

                <?php if($filtered): ?>
                    <a class="chip-btn chip-btn-accent" href="<?php echo e(route('projects.index')); ?>">Clear filters</a>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Project</th>
                    <th role="columnheader" scope="col">Client</th>
                    <th role="columnheader" scope="col">Manager</th>
                    <?php if($progress): ?>
                        <th role="columnheader" scope="col">Progress</th>
                    <?php endif; ?>
                    <th role="columnheader" scope="col">Deadline</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Priority</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $statusPill = P::status($item['status']);
                        $priorityChip = P::priority($item['priority']);
                        $due = $item['deadline_meta'];
                    ?>
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

                        <?php if($progress): ?>
                            <td role="cell" data-label="Progress">
                                
                                <span class="progress-cell <?php echo e(P::progressState($item['progress'])); ?>">
                                    <span class="progress-pct"><?php echo e($item['progress']); ?>%</span>
                                    <progress class="progress" max="100" value="<?php echo e($item['progress']); ?>"><?php echo e($item['progress']); ?>%</progress>
                                </span>
                            </td>
                        <?php endif; ?>

                        <td role="cell" class="cell-tight" data-label="Deadline">
                            <span class="date-cell <?php echo e($due['state']); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                                </svg>
                                <?php echo e(P::date($item['deadline'])); ?>

                            </span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill <?php echo e($statusPill['tone']); ?>"><?php echo e($statusPill['label']); ?></span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Priority">
                            <span class="priority <?php echo e($priorityChip['tone']); ?>"><?php echo e($priorityChip['label']); ?></span>
                        </td>

                        <td role="cell" class="cell-actions">
                            <a class="row-menu" href="<?php echo e(route('projects.show', ['project' => $item['id']])); ?>">
                                <span class="sr-only">Open <?php echo e($item['name']); ?></span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr role="row">
                        <td role="cell" colspan="<?php echo e($columns); ?>">
                            <div class="table-empty">
                                <?php if($tools && $filtered): ?>
                                    <strong>No projects match that search.</strong>
                                    Try a different term, or <a class="card-link" href="<?php echo e(route('projects.index')); ?>">clear the filters</a>.
                                <?php elseif($tools): ?>
                                    <strong>No projects yet.</strong>
                                    Projects created by a manager will appear here.
                                <?php else: ?>
                                    <strong>No projects assigned to you.</strong>
                                    A manager can put you on one.
                                <?php endif; ?>
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
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/projects/partials/table.blade.php ENDPATH**/ ?>