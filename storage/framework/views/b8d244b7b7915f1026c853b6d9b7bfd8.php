<header class="topbar">
    <button class="tb-menu" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
        <span class="sr-only">Toggle navigation</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>

    
    <div class="tb-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <label class="sr-only" for="tb-search-input">Search</label>
        <input id="tb-search-input" type="search" placeholder="Search — coming soon" disabled>
    </div>

    <div class="tb-right">
        <?php echo $__env->make('partials.notifications', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <?php echo $__env->make('partials.theme-toggle', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <div class="tb-user">
            <div class="tb-avatar" aria-hidden="true"><?php echo e($userInitials); ?></div>
            <div class="tb-user-info">
                <strong><?php echo e($userName); ?></strong>
                <span><?php echo e($userRole); ?></span>
            </div>
        </div>
    </div>
</header>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/partials/topbar.blade.php ENDPATH**/ ?>