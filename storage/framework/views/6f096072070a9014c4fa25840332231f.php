<?php $__env->startSection('title', 'Reset your password'); ?>

<?php $__env->startSection('form'); ?>
    <form class="auth-form" id="auth-form" method="POST" action="<?php echo e(route('password.request')); ?>" novalidate data-auth-form>
        <?php echo csrf_field(); ?>

        <div class="form-brand">
            <?php echo $__env->make('partials.brand-mark', ['alt' => config('zephryx.brand.name')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        <h1 class="auth-heading">Forgot your password?</h1>

        <p class="auth-subheading">
            Enter your email address or user ID and we'll send you a link to set a new one.
        </p>

        <?php if(session('status')): ?>
            <?php echo $__env->make('partials.notice', [
                'tone' => session('status_tone', 'success'),
                'message' => session('status'),
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
                   value="<?php echo e(old('identifier')); ?>"
                   placeholder="Email or User ID"
                   autocomplete="username"
                   autocapitalize="none"
                   spellcheck="false"
                   required
                   <?php if($errors->has('identifier')): ?> aria-invalid="true" aria-describedby="identifier-error" <?php endif; ?>>
        </label>
        <?php $__errorArgs = ['identifier'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <span class="field-error" id="identifier-error"><?php echo e($message); ?></span>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <button type="submit" class="btn-auth" data-auth-submit>
            <span class="btn-label">Send reset link</span>
        </button>

        <p class="form-foot">
            Remembered it? <a href="<?php echo e(route('login')); ?>">Back to sign in</a>.
        </p>
    </form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/auth/forgot-password.blade.php ENDPATH**/ ?>