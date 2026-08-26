<?php use App\Support\Theme; ?>
<?php
    $current = Theme::forRequest(request());
    $next = $current === 'dark' ? 'light' : 'dark';
?>


<form method="POST" action="<?php echo e(route('theme.store')); ?>" data-theme-form>
    <?php echo csrf_field(); ?>
    <input type="hidden" name="theme" value="<?php echo e($next); ?>">

    <button type="submit" class="theme-toggle" title="Switch to <?php echo e($next); ?> mode">
        <span class="sr-only">Switch to <?php echo e($next); ?> mode</span>

        
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
        </svg>

        
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"/>
        </svg>
    </button>
</form>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/partials/theme-toggle.blade.php ENDPATH**/ ?>