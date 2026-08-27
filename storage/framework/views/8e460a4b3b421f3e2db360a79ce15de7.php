<?php
    use App\Support\Avatar;
    use App\Support\TaskPresenter as P;
    use App\Support\TeamPresenter as T;
?>


<?php $action = $action ?? route('tasks.index'); ?>

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title"><?php echo e($title ?? 'All Tasks'); ?></span>

        <form class="table-tools" method="GET" action="<?php echo e($action); ?>">
            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="task-search">Search tasks</label>
                <input id="task-search" type="search" name="q" value="<?php echo e($search); ?>" placeholder="Search tasks…">
            </div>

            <label class="sr-only" for="task-project">Filter by project</label>
            <select class="chip-btn" id="task-project" name="project" data-auto-submit>
                <option value="">All projects</option>
                <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($option['id']); ?>" <?php if($project === $option['id']): echo 'selected'; endif; ?>><?php echo e($option['name']); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>

            <label class="sr-only" for="task-status">Filter by status</label>
            <select class="chip-btn" id="task-status" name="status" data-auto-submit>
                <option value="">All statuses</option>
                <?php $__currentLoopData = P::statusOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($option); ?>" <?php if($status === $option): echo 'selected'; endif; ?>><?php echo e(P::status($option)['label']); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>

            <label class="sr-only" for="task-priority">Filter by priority</label>
            <select class="chip-btn" id="task-priority" name="priority" data-auto-submit>
                <option value="">All priorities</option>
                <?php $__currentLoopData = P::priorityOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($option); ?>" <?php if($priority === $option): echo 'selected'; endif; ?>><?php echo e(P::priority($option)['label']); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>

            <button class="chip-btn" type="submit">Search</button>

            <?php if($filtered): ?>
                <a class="chip-btn chip-btn-accent" href="<?php echo e($action); ?>">Clear filters</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Task</th>
                    <th role="columnheader" scope="col">Project</th>
                    <th role="columnheader" scope="col">Assigned to</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Priority</th>
                    <th role="columnheader" scope="col">Due</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $statusPill = P::status($item['status']);
                        $priorityChip = P::priority($item['priority']);
                        $due = $item['due_meta'];
                    ?>
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Task">
                            <a class="row-link task-cell" href="<?php echo e(route('tasks.show', ['task' => $item['id']])); ?>">
                                <span class="chip <?php echo e(P::tint($item['id'])); ?>" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 12l2 2 4-4"/>
                                    </svg>
                                </span>
                                <span class="task-cell-text">
                                    <strong><?php echo e($item['name']); ?></strong>
                                    <span><?php echo e($item['id']); ?></span>
                                </span>
                            </a>
                        </td>

                        <td role="cell" data-label="Project">
                            <span class="stack-cell">
                                <strong><?php echo e($item['project_record']['name'] ?? '—'); ?></strong>
                                <span><?php echo e($item['project_record']['client'] ?? ''); ?></span>
                            </span>
                        </td>

                        <td role="cell" data-label="Assigned to">
                            
                            <?php if($item['assignee_record']): ?>
                                <span class="assignee-cell">
                                    <span class="avatar <?php echo e(Avatar::tint($item['assignee_record']['name'])); ?>" aria-hidden="true"><?php echo e(Avatar::initials($item['assignee_record']['name'])); ?></span>
                                    <span class="stack-cell">
                                        <strong><?php echo e($item['assignee_record']['name']); ?></strong>
                                        <span><?php echo e($item['team_record']['name'] ?? 'No team'); ?></span>
                                    </span>
                                </span>
                            <?php elseif($item['team_record']): ?>
                                <span class="assignee-cell">
                                    <span class="chip <?php echo e(Avatar::tint($item['team_record']['name'])); ?>" aria-hidden="true"><?php echo e(T::chip($item['team_record']['name'])); ?></span>
                                    <span class="stack-cell">
                                        <strong><?php echo e($item['team_record']['name']); ?></strong>
                                        <span>Whole team</span>
                                    </span>
                                </span>
                            <?php else: ?>
                                <span class="assignee-empty">Unassigned</span>
                            <?php endif; ?>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill <?php echo e($statusPill['tone']); ?>"><?php echo e($statusPill['label']); ?></span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Priority">
                            <span class="priority <?php echo e($priorityChip['tone']); ?>"><?php echo e($priorityChip['label']); ?></span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Due">
                            <span class="due-cell">
                                <strong><?php echo e(P::date($item['due'])); ?></strong>
                                <span class="<?php echo e($due['state']); ?>"><?php echo e($due['label']); ?></span>
                            </span>
                        </td>

                        <td role="cell" class="cell-actions">
                            <a class="row-menu" href="<?php echo e(route('tasks.show', ['task' => $item['id']])); ?>">
                                <span class="sr-only">Open <?php echo e($item['name']); ?></span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr role="row">
                        <td role="cell" colspan="7">
                            <div class="table-empty">
                                <?php if($filtered): ?>
                                    <strong>No tasks match that search.</strong>
                                    Try a different term, or <a class="card-link" href="<?php echo e($action); ?>">clear the filters</a>.
                                <?php else: ?>
                                    <strong><?php echo e($emptyTitle ?? 'No tasks yet.'); ?></strong>
                                    <?php echo e($emptyBody ?? 'Tasks assigned by a manager will appear here.'); ?>

                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($tasks->total() > 0): ?>
        <?php echo $__env->make('partials.pagination', ['paginator' => $tasks, 'unit' => 'tasks'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/tasks/partials/table.blade.php ENDPATH**/ ?>