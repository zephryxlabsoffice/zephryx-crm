<?php
    use App\Support\Avatar;
    use App\Support\TeamPresenter as P;
?>


<?php $tools = $tools ?? true; ?>

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title"><?php echo e($title ?? 'All Teams'); ?></span>

        <?php if($tools): ?>
            <form class="table-tools" method="GET" action="<?php echo e(route('teams.index')); ?>">
                <div class="search-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <label class="sr-only" for="team-search">Search teams</label>
                    <input id="team-search" type="search" name="q" value="<?php echo e($search); ?>" placeholder="Search teams…">
                </div>

                <label class="sr-only" for="team-lead">Filter by team lead</label>
                <select class="chip-btn" id="team-lead" name="lead" data-auto-submit>
                    <option value="">All team leads</option>
                    <?php $__currentLoopData = $leads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($option['id']); ?>" <?php if($lead === $option['id']): echo 'selected'; endif; ?>><?php echo e($option['name']); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>

                <label class="sr-only" for="team-status">Filter by status</label>
                <select class="chip-btn" id="team-status" name="status" data-auto-submit>
                    <option value="">All statuses</option>
                    <?php $__currentLoopData = P::statusOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($option); ?>" <?php if($status === $option): echo 'selected'; endif; ?>><?php echo e(P::status($option)['label']); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>

                <button class="chip-btn" type="submit">Search</button>

                <?php if($filtered): ?>
                    <a class="chip-btn chip-btn-accent" href="<?php echo e(route('teams.index')); ?>">Clear filters</a>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Team</th>
                    <th role="columnheader" scope="col">Team Lead</th>
                    <th role="columnheader" scope="col">Members</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Created</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php $pill = P::status($team['status']); ?>
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Team">
                            <a class="row-link team-cell" href="<?php echo e(route('teams.show', ['team' => $team['id']])); ?>">
                                <span class="chip <?php echo e(Avatar::tint($team['name'])); ?>" aria-hidden="true"><?php echo e(P::chip($team['name'])); ?></span>
                                <span class="team-cell-text">
                                    <strong><?php echo e($team['name']); ?></strong>
                                    <span><?php echo e($team['purpose']); ?></span>
                                </span>
                            </a>
                        </td>

                        <td role="cell" data-label="Team lead">
                            <?php if($team['lead_record']): ?>
                                <span class="lead-cell">
                                    <span class="avatar <?php echo e(Avatar::tint($team['lead_record']['name'])); ?>" aria-hidden="true"><?php echo e(Avatar::initials($team['lead_record']['name'])); ?></span>
                                    <span class="lead-cell-text">
                                        <strong><?php echo e($team['lead_record']['name']); ?></strong>
                                        <span><?php echo e($team['lead_record']['designation']); ?></span>
                                    </span>
                                </span>
                            <?php else: ?>
                                
                                <span class="lead-empty">No lead assigned</span>
                            <?php endif; ?>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Members">
                            <span class="count-inline">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                </svg>
                                <?php echo e($team['member_count']); ?>

                                <span class="unit"><?php echo e(\Illuminate\Support\Str::plural('member', $team['member_count'])); ?></span>
                            </span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill <?php echo e($pill['tone']); ?>"><?php echo e($pill['label']); ?></span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Created"><?php echo e(P::created($team['created'])); ?></td>

                        <td role="cell" class="cell-actions">
                            
                            <a class="row-menu" href="<?php echo e(route('teams.show', ['team' => $team['id']])); ?>">
                                <span class="sr-only">Open <?php echo e($team['name']); ?></span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr role="row">
                        <td role="cell" colspan="6">
                            <div class="table-empty">
                                <?php if($tools && $filtered): ?>
                                    <strong>No teams match that search.</strong>
                                    Try a different term, or <a class="card-link" href="<?php echo e(route('teams.index')); ?>">clear the filters</a>.
                                <?php elseif($tools): ?>
                                    <strong>No teams yet.</strong>
                                    Teams created by a manager will appear here.
                                <?php else: ?>
                                    <strong>You are not in any teams yet.</strong>
                                    A manager can add you to one.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($teams->total() > 0): ?>
        <?php echo $__env->make('partials.pagination', ['paginator' => $teams, 'unit' => 'teams'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/teams/partials/table.blade.php ENDPATH**/ ?>