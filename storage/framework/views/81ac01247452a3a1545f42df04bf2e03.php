<?php
    use App\Support\Avatar;
    use App\Support\EmployeePresenter as E;
?>

<?php
    $tabs = [
        'all' => ['All members', $tabCounts['all']],
        'by_department' => ['By department', null],
        'on_leave' => ['On leave', $tabCounts['on_leave']],
        'inactive' => ['Inactive', $tabCounts['inactive']],
    ];
    $lastDepartment = null;
?>

<div class="card table-card">
    
    <nav class="tabs" aria-label="Member views">
        <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$label, $count]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a class="tab <?php if($tab === $key): ?> active <?php endif; ?>"
               href="<?php echo e(route('teams.show', ['team' => $team['id'], 'tab' => $key])); ?>"
               <?php if($tab === $key): ?> aria-current="page" <?php endif; ?>>
                <?php echo e($label); ?>

                <?php if($count !== null): ?>
                    <span class="tab-count"><?php echo e($count); ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>

    <div class="card-hd">
        <form class="table-tools" method="GET" action="<?php echo e(route('teams.show', ['team' => $team['id']])); ?>">
            <input type="hidden" name="tab" value="<?php echo e($tab); ?>">

            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="member-search">Search members</label>
                <input id="member-search" type="search" name="q" value="<?php echo e($search); ?>" placeholder="Search members…">
            </div>

            <label class="sr-only" for="member-department">Filter by department</label>
            <select class="chip-btn" id="member-department" name="department" data-auto-submit>
                <option value="">All departments</option>
                <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($option); ?>" <?php if($department === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>

            <button class="chip-btn" type="submit">Search</button>

            <?php if($filtered): ?>
                <a class="chip-btn chip-btn-accent" href="<?php echo e(route('teams.show', ['team' => $team['id'], 'tab' => $tab])); ?>">Clear filters</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Member</th>
                    <th role="columnheader" scope="col">Department</th>
                    <th role="columnheader" scope="col">Role</th>
                    <th role="columnheader" scope="col">Email</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Joined</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php $pill = E::status($member['status']); ?>

                    <?php if($grouped && $member['department'] !== $lastDepartment): ?>
                        <?php $lastDepartment = $member['department']; ?>
                        <tr class="group-row" role="row">
                            <td role="cell" colspan="7"><?php echo e($member['department']); ?></td>
                        </tr>
                    <?php endif; ?>

                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Member">
                            <a class="row-link emp-name" href="<?php echo e(route('employees.show', ['employee' => $member['user_id']])); ?>">
                                <span class="avatar <?php echo e(Avatar::tint($member['name'])); ?>" aria-hidden="true"><?php echo e(Avatar::initials($member['name'])); ?></span>
                                <span class="emp-name-text">
                                    <strong><?php echo e($member['name']); ?></strong>
                                    <span><?php echo e($member['user_id']); ?></span>
                                </span>
                            </a>
                        </td>
                        <td role="cell" data-label="Department"><span class="tag"><?php echo e($member['department']); ?></span></td>
                        <td role="cell" data-label="Role"><span class="tag"><?php echo e($member['designation']); ?></span></td>
                        <td role="cell" data-label="Email">
                            <a class="emp-email" href="mailto:<?php echo e($member['email']); ?>" title="<?php echo e($member['email']); ?>"><?php echo e($member['email']); ?></a>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill <?php echo e($pill['tone']); ?>"><?php echo e($pill['label']); ?></span>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Joined"><?php echo e(E::joined($member['joined'])); ?></td>
                        <td role="cell" class="cell-actions">
                            <button class="row-menu" type="button" disabled title="Row actions are not built yet">
                                <span class="sr-only">Actions for <?php echo e($member['name']); ?></span>
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr role="row">
                        <td role="cell" colspan="7">
                            <div class="table-empty">
                                <?php if($filtered): ?>
                                    <strong>No members match that search.</strong>
                                    Try a different term, or <a class="card-link" href="<?php echo e(route('teams.show', ['team' => $team['id'], 'tab' => $tab])); ?>">clear the filters</a>.
                                <?php elseif($tab === 'on_leave'): ?>
                                    <strong>Nobody in this team is on leave.</strong>
                                <?php elseif($tab === 'inactive'): ?>
                                    <strong>Every member of this team is active.</strong>
                                <?php else: ?>
                                    <strong>This team has no members yet.</strong>
                                    Add someone to get started.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($members->total() > 0): ?>
        <?php echo $__env->make('partials.pagination', ['paginator' => $members, 'unit' => 'members'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/teams/partials/members.blade.php ENDPATH**/ ?>