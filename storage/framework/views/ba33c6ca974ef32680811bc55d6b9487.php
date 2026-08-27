<?php
    use App\Support\Avatar;
    use App\Support\TeamPresenter as P;
?>


<section class="kpi-row" aria-label="Team details">

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Total Members</div>
            <div class="kpi-val"><?php echo e(number_format($members->total())); ?></div>
            <span class="kpi-sub">In this team</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/>
                <rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Departments</div>
            <div class="kpi-val"><?php echo e(count($breakdown)); ?></div>
            <span class="kpi-sub">Represented</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l2.6 5.6L21 8.5l-4.5 4.3 1.1 6.2L12 16l-5.6 3 1.1-6.2L3 8.5l6.4-.9z"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Team Lead</div>
            <?php if($team['lead_record']): ?>
                <div class="kpi-person">
                    <span class="avatar <?php echo e(Avatar::tint($team['lead_record']['name'])); ?>" aria-hidden="true"><?php echo e(Avatar::initials($team['lead_record']['name'])); ?></span>
                    <strong><?php echo e($team['lead_record']['name']); ?></strong>
                </div>
                <span class="kpi-sub"><?php echo e($team['lead_record']['designation']); ?></span>
            <?php else: ?>
                <div class="kpi-val kpi-val-sm">Not assigned</div>
                <span class="kpi-sub">No lead for this team</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Created</div>
            <div class="kpi-val kpi-val-sm"><?php echo e(P::created($team['created'])); ?></div>
            <span class="kpi-sub"><?php echo e($age); ?> ago</span>
        </div>
    </div>

</section>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/teams/partials/overview-kpis.blade.php ENDPATH**/ ?>