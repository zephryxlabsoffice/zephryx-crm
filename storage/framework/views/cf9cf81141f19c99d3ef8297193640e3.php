
<?php
    $unit = $unit ?? 'results';
    // linkCollection() brackets the page numbers with its own Previous and
    // Next entries; this partial draws those itself, so they are trimmed off.
    $window = $paginator->onEachSide(1)->linkCollection()->slice(1, -1);
?>

<nav class="pagination" aria-label="Pagination">
    
    <span class="pagination-meta"><?php echo e($paginator->total() === 0
        ? 'No '.$unit
        : 'Showing '.$paginator->firstItem().' to '.$paginator->lastItem()
            .' of '.number_format($paginator->total()).' '.$unit); ?></span>

    <?php if($paginator->hasPages()): ?>
        <div class="pagination-pages">
            <a class="pg-btn"
               href="<?php echo e($paginator->previousPageUrl() ?? '#'); ?>"
               <?php if(! $paginator->previousPageUrl()): ?> aria-disabled="true" <?php endif; ?>
               rel="prev">
                <span class="sr-only">Previous page</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>

            <?php $__currentLoopData = $window; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($link['url'] === null): ?>
                    <span class="pg-ellipsis" aria-hidden="true"><?php echo $link['label']; ?></span>
                <?php else: ?>
                    <a class="pg-btn <?php if($link['active']): ?> active <?php endif; ?>"
                       href="<?php echo e($link['url']); ?>"
                       <?php if($link['active']): ?> aria-current="page" <?php endif; ?>>
                        <span class="sr-only">Page</span><?php echo e($link['label']); ?>

                    </a>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <a class="pg-btn"
               href="<?php echo e($paginator->nextPageUrl() ?? '#'); ?>"
               <?php if(! $paginator->nextPageUrl()): ?> aria-disabled="true" <?php endif; ?>
               rel="next">
                <span class="sr-only">Next page</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        </div>
    <?php endif; ?>
</nav>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/partials/pagination.blade.php ENDPATH**/ ?>