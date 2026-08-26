<?php $__env->startSection('title', 'Choose a new password'); ?>

<?php $__env->startSection('form'); ?>
    <form class="auth-form" id="auth-form" method="POST" action="<?php echo e(route('password.reset')); ?>" novalidate data-auth-form>
        <?php echo csrf_field(); ?>

        
        <input type="hidden" name="token" value="<?php echo e($token); ?>">

        <div class="form-brand">
            <?php echo $__env->make('partials.brand-mark', ['alt' => config('zephryx.brand.name')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        <h1 class="auth-heading">Choose a new password</h1>

        <p class="auth-subheading">
            Signing you out everywhere else — you'll need to sign in again on your other devices.
        </p>

        <?php if($errors->has('token')): ?>
            <?php echo $__env->make('partials.notice', [
                'tone' => 'danger',
                'message' => $errors->first('token'),
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>

        <label class="field <?php echo e($errors->has('identifier') ? 'has-error' : ''); ?>">
            <span class="sr-only">Email or User ID</span>
            <span class="field-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </span>
            <input type="text"
                   name="identifier"
                   value="<?php echo e(old('identifier', $identifier)); ?>"
                   placeholder="Email or User ID"
                   autocomplete="username"
                   autocapitalize="none"
                   spellcheck="false"
                   required>
        </label>
        <?php $__errorArgs = ['identifier'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <span class="field-error"><?php echo e($message); ?></span>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <label class="field <?php echo e($errors->has('password') ? 'has-error' : ''); ?>">
            <span class="sr-only">New password</span>
            <span class="field-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="11" width="16" height="10" rx="2"/>
                    <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                </svg>
            </span>
            <input type="password"
                   name="password"
                   placeholder="New password"
                   autocomplete="new-password"
                   required
                   aria-describedby="password-hint">

            <button type="button" class="eye-toggle" data-eye-toggle aria-pressed="false" aria-label="Show password">
                <?php echo $__env->make('partials.eye-icons', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </button>
        </label>
        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <span class="field-error"><?php echo e($message); ?></span>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        
        <p class="field-hint" id="password-hint">
            At least 12 characters. A short phrase you'll remember beats a
            scrambled word.
        </p>

        <label class="field <?php echo e($errors->has('password') ? 'has-error' : ''); ?>">
            <span class="sr-only">Confirm new password</span>
            <span class="field-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
            </span>
            <input type="password"
                   name="password_confirmation"
                   placeholder="Confirm new password"
                   autocomplete="new-password"
                   required>

            <button type="button" class="eye-toggle" data-eye-toggle aria-pressed="false" aria-label="Show password">
                <?php echo $__env->make('partials.eye-icons', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </button>
        </label>

        <button type="submit" class="btn-auth" data-auth-submit>
            <span class="btn-label">Update password</span>
        </button>

        <p class="form-foot">
            <a href="<?php echo e(route('login')); ?>">Back to sign in</a>
        </p>
    </form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/auth/reset-password.blade.php ENDPATH**/ ?>