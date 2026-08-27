<?php
    use App\Support\Avatar;
    use App\Support\TeamPresenter as T;
?>

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">Assigned Teams</span>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Team</th>
                    <th role="columnheader" scope="col">Team Lead</th>
                    <th role="columnheader" scope="col">Members</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Team">
                            <a class="row-link team-cell" href="<?php echo e(route('teams.show', ['team' => $team['id']])); ?>">
                                <span class="chip <?php echo e(Avatar::tint($team['name'])); ?>" aria-hidden="true"><?php echo e(T::chip($team['name'])); ?></span>
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
                        <td role="cell" colspan="4">
                            <div class="table-empty">
                                <strong>No teams on this project yet.</strong>
                                Assign one to get started.
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/projects/partials/teams.blade.php ENDPATH**/ ?>