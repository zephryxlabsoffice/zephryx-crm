<?php $__env->startSection('title', 'Employees'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Employees Management</h1>
            <p>Manage your team, roles and employee information.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="<?php echo e(route('employees.create')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Employee
            </a>

            
            <button class="btn btn-outline" type="button" disabled title="Import is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Import
            </button>

            
            <button class="btn btn-outline" type="button" disabled title="Export is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export
            </button>
        </div>
    </div>

    <?php echo $__env->make('employees.partials.kpis', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <section class="emp-grid">
        <?php echo $__env->make('employees.partials.table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <aside class="rail">
            <?php echo $__env->make('employees.partials.departments', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('employees.partials.starters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('employees.partials.birthdays', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('employees.partials.quick-actions', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </aside>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/employees/index.blade.php ENDPATH**/ ?>