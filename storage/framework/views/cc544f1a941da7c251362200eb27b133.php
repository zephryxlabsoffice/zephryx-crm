<?php $__env->startSection('title', 'Verify your sign-in'); ?>

<?php $__env->startSection('form'); ?>
    <form class="auth-form" id="auth-form" method="POST" action="<?php echo e(route('login.verify.attempt')); ?>" novalidate data-auth-form>
        <?php echo csrf_field(); ?>

        <div class="form-brand">
            <?php echo $__env->make('partials.brand-mark', ['alt' => config('zephryx.brand.name')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        <h1 class="auth-heading">Check your email</h1>

        <p class="auth-subheading">
            We sent a 6-digit code to <strong><?php echo e($maskedEmail); ?></strong>.<br>
            It expires in <?php echo e($expiresInMinutes); ?> minutes.
        </p>

        <?php if($errors->has('code')): ?>
            <?php echo $__env->make('partials.notice', [
                'tone' => 'danger',
                'message' => $errors->first('code'),
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php elseif(session('status')): ?>
            <?php echo $__env->make('partials.notice', [
                'tone' => session('status_tone', 'success'),
                'message' => session('status'),
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>

        
        <fieldset class="otp-row <?php echo e($errors->has('code') ? 'has-error' : ''); ?>" data-otp>
            <legend class="sr-only">Enter the 6-digit code from your email</legend>

            <?php for($i = 0; $i < 6; $i++): ?>
                <input type="text"
                       inputmode="numeric"
                       pattern="[0-9]*"
                       maxlength="1"
                       name="code[]"
                       aria-label="Digit <?php echo e($i + 1); ?> of 6"
                       autocomplete="<?php echo e($i === 0 ? 'one-time-code' : 'off'); ?>"
                       <?php if($i === 0): ?> autofocus <?php endif; ?>
                       required>
            <?php endfor; ?>
        </fieldset>

        <button type="submit" class="btn-auth" data-auth-submit>
            <span class="btn-label">Verify</span>
        </button>

        
        <p class="resend-row">
            Didn't get it?
            <button type="submit"
                    form="resend-form"
                    class="resend-btn"
                    data-resend
                    <?php if($resendCooldown > 0): echo 'disabled'; endif; ?>>
                <span data-resend-label>
                    <?php if($resendCooldown > 0): ?>
                        Resend in <?php echo e($resendCooldown); ?>s
                    <?php else: ?>
                        Send a new code
                    <?php endif; ?>
                </span>
            </button>
        </p>

        <p class="form-foot">
            Wrong account? <a href="<?php echo e(route('login')); ?>">Start over</a>.
        </p>
    </form>

    
    <form id="resend-form"
          method="POST"
          action="<?php echo e(route('login.resend')); ?>"
          hidden
          data-resend-seconds="<?php echo e((int) $resendCooldown); ?>">
        <?php echo csrf_field(); ?>
    </form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/auth/verify.blade.php ENDPATH**/ ?>