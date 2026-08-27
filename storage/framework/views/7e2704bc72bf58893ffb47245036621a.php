<?php
    use App\Support\Avatar;
    use App\Support\ProjectPresenter as P;
?>

<section class="kpi-row" aria-label="Project details">

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Teams Assigned</div>
            <div class="kpi-val"><?php echo e(count($teams)); ?></div>
            <span class="kpi-sub">Working on this</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l2.6 5.6L21 8.5l-4.5 4.3 1.1 6.2L12 16l-5.6 3 1.1-6.2L3 8.5l6.4-.9z"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Project Manager</div>
            <?php if($project['manager_record']): ?>
                <div class="kpi-person">
                    <span class="avatar <?php echo e(Avatar::tint($project['manager_record']['name'])); ?>" aria-hidden="true"><?php echo e(Avatar::initials($project['manager_record']['name'])); ?></span>
                    <strong><?php echo e($project['manager_record']['name']); ?></strong>
                </div>
                <span class="kpi-sub"><?php echo e($project['manager_record']['designation']); ?></span>
            <?php else: ?>
                <div class="kpi-val kpi-val-sm">Unassigned</div>
                <span class="kpi-sub">No manager for this project</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Status</div>
            
            <label class="sr-only" for="project-status-control">Project status</label>
            <select class="status-select" id="project-status-control" disabled title="Changing status is not built yet">
                <?php $__currentLoopData = P::statusOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($option); ?>" <?php if($project['status'] === $option): echo 'selected'; endif; ?>><?php echo e(P::status($option)['label']); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <span class="kpi-sub"><?php echo e($project['progress']); ?>% complete</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Deadline</div>
            <div class="kpi-val kpi-val-sm"><?php echo e(P::date($project['deadline'])); ?></div>
            <span class="kpi-sub <?php echo e($due['state'] === 'is-overdue' ? 'down' : ''); ?>"><?php echo e($due['label']); ?></span>
        </div>
    </div>

</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/projects/partials/overview-kpis.blade.php ENDPATH**/ ?>