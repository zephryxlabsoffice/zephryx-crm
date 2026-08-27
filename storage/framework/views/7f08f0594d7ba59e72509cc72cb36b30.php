<?php
    use App\Support\Avatar;
    use App\Support\TeamPresenter as P;
    $pill = P::status($team['status']);
?>

<?php $__env->startSection('title', $team['name']); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-hd-row">
        <div class="detail-hd">
            
            <a class="hd-back" href="<?php echo e(route('teams.index')); ?>">
                <span class="sr-only">Back to teams</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>

            <div class="page-hd">
                <div class="hd-title-row">
                    <h1><?php echo e($team['name']); ?></h1>
                    <span class="pill <?php echo e($pill['tone']); ?>"><?php echo e($pill['label']); ?></span>
                </div>
                <p class="hd-id">
                    Team ID: <strong data-copy-source><?php echo e($team['id']); ?></strong>
                    <button class="copy-btn" type="button" data-copy>
                        <span class="sr-only">Copy team ID</span>
                        <svg class="icon-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                        <svg class="icon-done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </button>
                </p>
            </div>
        </div>

        <div class="hd-actions">
            <a class="btn btn-outline" href="<?php echo e(route('teams.edit', ['team' => $team['id']])); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                </svg>
                Edit Team
            </a>

            <a class="btn btn-primary" href="<?php echo e(route('teams.edit', ['team' => $team['id']])); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/>
                    <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
                </svg>
                Add Member
            </a>
        </div>
    </div>

    <?php echo $__env->make('teams.partials.overview-kpis', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <section class="teams-grid">
        <?php echo $__env->make('teams.partials.members', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <aside class="rail">
            <?php echo $__env->make('teams.partials.composition', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('teams.partials.summary', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </aside>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/teams/show.blade.php ENDPATH**/ ?>