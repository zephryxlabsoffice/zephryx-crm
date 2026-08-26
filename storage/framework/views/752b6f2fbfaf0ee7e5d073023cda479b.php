
<?php
    $tone = in_array($tone ?? 'danger', ['danger', 'warning', 'info', 'success'], true)
        ? $tone
        : 'danger';
?>

<div class="notice notice-<?php echo e($tone); ?>" role="<?php echo e($tone === 'danger' ? 'alert' : 'status'); ?>">
    <span class="notice-icon" aria-hidden="true">
        <?php if($tone === 'danger'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/>
            </svg>
        <?php elseif($tone === 'warning'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                <path d="M12 9v4M12 17h.01"/>
            </svg>
        <?php elseif($tone === 'success'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/>
            </svg>
        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 16v-5M12 8h.01"/>
            </svg>
        <?php endif; ?>
    </span>

    <div class="notice-body">
        <?php if(isset($title)): ?>
            <strong><?php echo e($title); ?></strong>
        <?php endif; ?>
        <p><?php echo e($message); ?></p>
    </div>
</div>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/partials/notice.blade.php ENDPATH**/ ?>