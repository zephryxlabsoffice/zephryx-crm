
<?php
    $unread = collect($notifications)->where('read_at', null)->count();
?>

<div class="notif-wrap" data-notifications data-open="false">
    <button class="tb-bell" type="button" data-notif-toggle aria-expanded="false" aria-haspopup="true">
        <span class="sr-only">
            Notifications<?php echo e($unread > 0 ? ' — '.$unread.' unread' : ''); ?>

        </span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>

        
        <?php if($unread > 0): ?>
            <span class="badge" aria-hidden="true"><?php echo e($unread > 99 ? '99+' : $unread); ?></span>
        <?php endif; ?>
    </button>

    <div class="notif-pop" data-notif-panel role="dialog" aria-label="Notifications">
        <div class="notif-hd">
            <strong>Notifications</strong>
            <?php if($unread > 0): ?>
                <span class="notif-count"><?php echo e($unread); ?> new</span>
            <?php endif; ?>
        </div>

        <div class="notif-list">
            <?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <a class="notif-item <?php if(! $notification['read_at']): ?> is-unread <?php endif; ?>"
                   href="<?php echo e($notification['link'] ?? '#'); ?>">
                    <span class="notif-ic" aria-hidden="true">
                        <?php echo $__env->make('partials.nav-icon', ['icon' => $notification['icon'] ?? 'announcements'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    </span>
                    <span class="notif-body">
                        <strong><?php echo e($notification['title']); ?></strong>
                        <span><?php echo e($notification['body']); ?></span>
                    </span>
                    <span class="notif-time"><?php echo e($notification['when']); ?></span>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="notif-empty">Nothing new right now.</p>
            <?php endif; ?>
        </div>

        <?php if($notifications !== []): ?>
            <div class="notif-foot">
                <a href="<?php echo e(route('notifications.index')); ?>">View all activity</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/partials/notifications.blade.php ENDPATH**/ ?>