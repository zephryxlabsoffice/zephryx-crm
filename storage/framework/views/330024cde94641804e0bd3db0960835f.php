<?php $__env->startSection('title', 'Login'); ?>

<?php $__env->startSection('form'); ?>
    <form class="auth-form" id="auth-form" method="POST" action="<?php echo e(route('login.attempt')); ?>" novalidate data-auth-form>
        <?php echo csrf_field(); ?>

        <div class="form-brand">
            <?php echo $__env->make('partials.brand-mark', ['alt' => config('zephryx.brand.name')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        <h1 class="auth-heading">Welcome Back !</h1>

        
        <?php if($lockedUntil ?? null): ?>
            
            <?php echo $__env->make('partials.notice', [
                'tone' => 'warning',
                'title' => 'Too many attempts',
                'message' => 'Sign-in is paused for this account. Try again in about '
                    .max(1, (int) ceil($lockedUntil / 60)).' minute'
                    .(ceil($lockedUntil / 60) === 1.0 ? '' : 's').'.',
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <p class="sr-only" data-lockout-seconds="<?php echo e((int) $lockedUntil); ?>"></p>
        <?php elseif($errors->has('auth')): ?>
            
            <?php echo $__env->make('partials.notice', [
                'tone' => 'danger',
                'message' => $errors->first('auth'),
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php elseif(session('status')): ?>
            <?php echo $__env->make('partials.notice', [
                'tone' => session('status_tone', 'info'),
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

        <label class="field <?php echo e($errors->has('password') ? 'has-error' : ''); ?>">
            <span class="sr-only">Password</span>
            <span class="field-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="11" width="16" height="10" rx="2"/>
                    <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                </svg>
            </span>
            <input type="password"
                   name="password"
                   placeholder="Password"
                   autocomplete="current-password"
                   required
                   <?php if($errors->has('password')): ?> aria-invalid="true" aria-describedby="password-error" <?php endif; ?>>

            <button type="button" class="eye-toggle" data-eye-toggle aria-pressed="false" aria-label="Show password">
                <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                    <path d="M1 1l22 22"/>
                </svg>
            </button>
        </label>
        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <span class="field-error" id="password-error"><?php echo e($message); ?></span>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <div class="form-row">
            <label class="check">
                <input type="checkbox" name="remember" value="1" <?php if(old('remember')): echo 'checked'; endif; ?>>
                <span class="box" aria-hidden="true"></span>
                <span>Remember me</span>
            </label>

            <a class="link-quiet" href="<?php echo e(url('/forgot-password')); ?>">Forgot password?</a>
        </div>

        <button type="submit" class="btn-auth" data-auth-submit>
            <span class="btn-label">Login Now</span>
        </button>

        <p class="form-foot">
            Need access? Contact your <a href="<?php echo e($supportMailto); ?>">administrator</a>.
        </p>
    </form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/auth/login.blade.php ENDPATH**/ ?>