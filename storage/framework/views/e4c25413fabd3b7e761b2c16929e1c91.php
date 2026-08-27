
<?php
    $share = fn (int $n) => $stats['total'] > 0
        ? round($n / $stats['total'] * 100, 1).'% of all'
        : 'None yet';
?>

<section class="kpi-row kpi-row-compact" aria-label="Task summary">

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 12l2 2 4-4"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl"><?php echo e($totalLabel ?? 'Total Tasks'); ?></div>
            <div class="kpi-val"><?php echo e(number_format($stats['total'])); ?></div>
            <span class="kpi-sub"><?php echo e($totalSub ?? 'All tasks'); ?></span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Pending</div>
            <div class="kpi-val"><?php echo e(number_format($stats['pending'])); ?></div>
            <span class="kpi-sub"><?php echo e($share($stats['pending'])); ?></span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">In Progress</div>
            <div class="kpi-val"><?php echo e(number_format($stats['in_progress'])); ?></div>
            <span class="kpi-sub"><?php echo e($share($stats['in_progress'])); ?></span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Completed</div>
            <div class="kpi-val"><?php echo e(number_format($stats['completed'])); ?></div>
            <span class="kpi-sub"><?php echo e($share($stats['completed'])); ?></span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                <path d="M12 9v4M12 17h.01"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Overdue</div>
            <div class="kpi-val"><?php echo e(number_format($stats['overdue'])); ?></div>
            <span class="kpi-sub">Past due, not done</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                <circle cx="12" cy="16" r="1.6" fill="currentColor"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Due Today</div>
            <div class="kpi-val"><?php echo e(number_format($stats['due_today'])); ?></div>
            <span class="kpi-sub">Still open</span>
        </div>
    </div>

</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/tasks/partials/kpis.blade.php ENDPATH**/ ?>