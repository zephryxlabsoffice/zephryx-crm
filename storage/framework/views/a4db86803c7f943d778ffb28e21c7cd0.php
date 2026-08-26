<?php use App\Support\ClientPresenter as P; ?>

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">All Clients</span>

        
        <form class="table-tools" method="GET" action="<?php echo e(route('clients.index')); ?>">
            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="client-search">Search clients</label>
                <input id="client-search" type="search" name="q" value="<?php echo e($search); ?>" placeholder="Search client…">
            </div>

            <label class="sr-only" for="client-status">Filter by status</label>
            
            <select class="chip-btn" id="client-status" name="status" data-auto-submit>
                <option value="">All statuses</option>
                <?php $__currentLoopData = ['active' => 'Active', 'pending' => 'Pending', 'review' => 'In Review', 'on_hold' => 'On Hold', 'completed' => 'Completed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($value); ?>" <?php if($status === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>

            <button class="chip-btn" type="submit">Search</button>

            
            <?php if($filtered): ?>
                <a class="chip-btn chip-btn-accent" href="<?php echo e(route('clients.index')); ?>">Clear filters</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-body-table">
        
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Client Name</th>
                    <th role="columnheader" scope="col">Industry</th>
                    <th role="columnheader" scope="col">Project</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Last Activity</th>
                    <th role="columnheader" scope="col">Payment</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $statusPill = P::status($client['status']);
                        $paymentPill = P::payment($client['payment']);
                    ?>
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Client">
                            <a class="row-link cl-name" href="<?php echo e(route('clients.show', ['client' => \Illuminate\Support\Str::slug($client['name'])])); ?>">
                                <span class="avatar <?php echo e(P::tint($client['name'])); ?>" aria-hidden="true"><?php echo e(P::initial($client['name'])); ?></span>
                                <strong><?php echo e($client['name']); ?></strong>
                            </a>
                        </td>
                        <td role="cell" data-label="Industry"><?php echo e($client['industry']); ?></td>
                        <td role="cell" data-label="Project"><?php echo e($client['project']); ?></td>
                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill <?php echo e($statusPill['tone']); ?>"><?php echo e($statusPill['label']); ?></span>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Last activity"><?php echo e($client['activity']); ?></td>
                        <td role="cell" class="cell-tight" data-label="Payment">
                            <span class="pill <?php echo e($paymentPill['tone']); ?>"><?php echo e($paymentPill['label']); ?></span>
                        </td>
                        <td role="cell" class="cell-actions">
                            <button class="row-menu" type="button" disabled title="Row actions are not built yet">
                                <span class="sr-only">Actions for <?php echo e($client['name']); ?></span>
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr role="row">
                        <td role="cell" colspan="7">
                            <div class="table-empty">
                                <?php if($filtered): ?>
                                    <strong>No clients match that search.</strong>
                                    Try a different term, or <a class="card-link" href="<?php echo e(route('clients.index')); ?>">clear the filters</a>.
                                <?php else: ?>
                                    <strong>No clients yet.</strong>
                                    Clients added by an administrator will appear here.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($clients->hasPages() || $clients->total() > 0): ?>
        <?php echo $__env->make('partials.pagination', ['paginator' => $clients, 'unit' => 'clients'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/clients/partials/table.blade.php ENDPATH**/ ?>