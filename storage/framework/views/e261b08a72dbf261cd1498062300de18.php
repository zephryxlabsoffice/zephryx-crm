<?php
    use App\Support\Avatar;
    use App\Support\EmployeePresenter as P;
?>

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">All Employees</span>

        <form class="table-tools" method="GET" action="<?php echo e(route('employees.index')); ?>">
            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="employee-search">Search employees</label>
                <input id="employee-search" type="search" name="q" value="<?php echo e($search); ?>" placeholder="Search name, ID or role…">
            </div>

            <label class="sr-only" for="employee-department">Filter by department</label>
            <select class="chip-btn" id="employee-department" name="department" data-auto-submit>
                <option value="">All departments</option>
                <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($option); ?>" <?php if($department === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>

            <label class="sr-only" for="employee-status">Filter by status</label>
            <select class="chip-btn" id="employee-status" name="status" data-auto-submit>
                <option value="">All statuses</option>
                <?php $__currentLoopData = P::statusOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($option); ?>" <?php if($status === $option): echo 'selected'; endif; ?>><?php echo e(P::status($option)['label']); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>

            <button class="chip-btn" type="submit">Search</button>

            <?php if($filtered): ?>
                <a class="chip-btn chip-btn-accent" href="<?php echo e(route('employees.index')); ?>">Clear filters</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-body-table">
        
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Employee</th>
                    <th role="columnheader" scope="col">Department</th>
                    <th role="columnheader" scope="col">Designation</th>
                    <th role="columnheader" scope="col">Email</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Joined</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php $pill = P::status($employee['status']); ?>
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Employee">
                            <a class="row-link emp-name" href="<?php echo e(route('employees.show', ['employee' => $employee['user_id']])); ?>">
                                <span class="avatar <?php echo e(Avatar::tint($employee['name'])); ?>" aria-hidden="true"><?php echo e(Avatar::initials($employee['name'])); ?></span>
                                <span class="emp-name-text">
                                    <strong><?php echo e($employee['name']); ?></strong>
                                    <span><?php echo e($employee['user_id']); ?></span>
                                </span>
                            </a>
                        </td>
                        <td role="cell" data-label="Department"><?php echo e($employee['department']); ?></td>
                        <td role="cell" data-label="Designation"><?php echo e($employee['designation']); ?></td>
                        <td role="cell" data-label="Email">
                            
                            <a class="emp-email" href="mailto:<?php echo e($employee['email']); ?>" title="<?php echo e($employee['email']); ?>"><?php echo e($employee['email']); ?></a>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill <?php echo e($pill['tone']); ?>"><?php echo e($pill['label']); ?></span>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Joined"><?php echo e(P::joined($employee['joined'])); ?></td>
                        <td role="cell" class="cell-actions">
                            <button class="row-menu" type="button" disabled title="Row actions are not built yet">
                                <span class="sr-only">Actions for <?php echo e($employee['name']); ?></span>
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
                                    <strong>No employees match that search.</strong>
                                    Try a different term, or <a class="card-link" href="<?php echo e(route('employees.index')); ?>">clear the filters</a>.
                                <?php else: ?>
                                    <strong>No employees yet.</strong>
                                    Accounts created by an administrator will appear here.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($employees->total() > 0): ?>
        <?php echo $__env->make('partials.pagination', ['paginator' => $employees, 'unit' => 'employees'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/employees/partials/table.blade.php ENDPATH**/ ?>