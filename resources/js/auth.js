/**
 * Authentication surfaces — progressive enhancement only.
 *
 * Every behaviour here is an upgrade on markup that already works: the form
 * posts, the OTP boxes accept typing, and the cooldown is rendered server-side.
 * Nothing below is required for a user to sign in with JavaScript disabled.
 */

/* ── password visibility ─────────────────────────────────────────────────── */

function initEyeToggles() {
    document.querySelectorAll('[data-eye-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const field = button.closest('.field');
            const input = field?.querySelector('input');

            if (!input) {
                return;
            }

            const revealing = input.type === 'password';

            input.type = revealing ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(revealing));
            button.setAttribute('aria-label', revealing ? 'Hide password' : 'Show password');

            // Returning focus to the input keeps the caret where the user left
            // it, rather than stranding them on the button.
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        });
    });
}

/* ── clearing stale validation ───────────────────────────────────────────── */

/**
 * Server-rendered field errors describe the *previous* submission. Once the
 * user starts fixing a field, keeping it outlined in red is just noise, so the
 * error state is dropped on first edit. The message itself is only removed
 * from view — the next response re-renders whatever is still true.
 */
function initErrorClearing() {
    document.querySelectorAll('.field.has-error').forEach((field) => {
        const input = field.querySelector('input');

        input?.addEventListener('input', () => {
            field.classList.remove('has-error');
            input.removeAttribute('aria-invalid');

            const messageId = input.getAttribute('aria-describedby');
            if (messageId) {
                document.getElementById(messageId)?.remove();
                input.removeAttribute('aria-describedby');
            }
        }, { once: true });
    });

    const otp = document.querySelector('.otp-row.has-error');
    otp?.addEventListener('input', () => otp.classList.remove('has-error'), { once: true });
}

/* ── submit state ────────────────────────────────────────────────────────── */

function initSubmitState() {
    document.querySelectorAll('[data-auth-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-auth-submit]');

            if (!button || button.dataset.busy === '1') {
                return;
            }

            // Marked busy rather than disabled: a disabled submit button is not
            // sent with the form, and disabling it before the browser has
            // serialised the request can drop the click entirely.
            button.dataset.busy = '1';
            button.classList.add('is-busy');
            button.setAttribute('aria-busy', 'true');
        });
    });

    // Coming back via the bfcache would otherwise show a form stuck mid-submit.
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) {
            return;
        }

        document.querySelectorAll('[data-auth-submit]').forEach((button) => {
            delete button.dataset.busy;
            button.classList.remove('is-busy');
            button.removeAttribute('aria-busy');
        });
    });
}

/* ── one-time code entry ─────────────────────────────────────────────────── */

function initOtp() {
    const row = document.querySelector('[data-otp]');

    if (!row) {
        return;
    }

    const boxes = Array.from(row.querySelectorAll('input'));

    const focusBox = (index) => {
        const box = boxes[Math.max(0, Math.min(index, boxes.length - 1))];
        box?.focus();
        box?.select();
    };

    boxes.forEach((box, index) => {
        box.addEventListener('input', () => {
            // Strip anything non-numeric rather than rejecting the keystroke, so
            // a phone keypad or an autofilled code still lands correctly.
            box.value = box.value.replace(/\D/g, '').slice(-1);

            if (box.value) {
                focusBox(index + 1);
            }
        });

        box.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !box.value && index > 0) {
                event.preventDefault();
                boxes[index - 1].value = '';
                focusBox(index - 1);
            }

            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                focusBox(index - 1);
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                focusBox(index + 1);
            }
        });

        box.addEventListener('paste', (event) => {
            const pasted = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '');

            if (!pasted) {
                return;
            }

            event.preventDefault();

            // A code pasted from a mail client lands in whichever box has focus;
            // spread it across the remaining boxes from there.
            pasted.split('').forEach((digit, offset) => {
                const target = boxes[index + offset];
                if (target) {
                    target.value = digit;
                }
            });

            focusBox(index + pasted.length);
        });
    });
}

/* ── countdowns ──────────────────────────────────────────────────────────── */

/**
 * Tick a whole number of seconds down to zero, calling `onTick` each second and
 * `onDone` once at the end. Returns nothing; there is nothing to cancel because
 * the page is replaced on every navigation.
 */
function countdown(seconds, onTick, onDone) {
    let remaining = Math.max(0, Math.floor(seconds));

    if (remaining === 0) {
        onDone();
        return;
    }

    onTick(remaining);

    const timer = setInterval(() => {
        remaining -= 1;

        if (remaining <= 0) {
            clearInterval(timer);
            onDone();
            return;
        }

        onTick(remaining);
    }, 1000);
}

function formatDuration(seconds) {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return minutes > 0
        ? `${minutes}m ${String(rest).padStart(2, '0')}s`
        : `${rest}s`;
}

function initLockoutCountdown() {
    const marker = document.querySelector('[data-lockout-seconds]');
    const notice = marker?.previousElementSibling?.querySelector('.notice-body p');

    if (!marker || !notice) {
        return;
    }

    const submit = document.querySelector('[data-auth-submit]');

    if (submit) {
        submit.disabled = true;
    }

    countdown(
        Number(marker.dataset.lockoutSeconds),
        (remaining) => {
            notice.textContent = `Sign-in is paused for this account. Try again in ${formatDuration(remaining)}.`;
        },
        () => {
            notice.textContent = 'You can try signing in again now.';
            if (submit) {
                submit.disabled = false;
            }
        }
    );
}

function initResendCooldown() {
    const form = document.querySelector('[data-resend-seconds]');
    const button = document.querySelector('[data-resend]');
    const label = document.querySelector('[data-resend-label]');

    if (!form || !button || !label) {
        return;
    }

    countdown(
        Number(form.dataset.resendSeconds),
        (remaining) => {
            button.disabled = true;
            label.textContent = `Resend in ${formatDuration(remaining)}`;
        },
        () => {
            button.disabled = false;
            label.textContent = 'Send a new code';
        }
    );
}

/* ── boot ────────────────────────────────────────────────────────────────── */

function init() {
    initEyeToggles();
    initErrorClearing();
    initSubmitState();
    initOtp();
    initLockoutCountdown();
    initResendCooldown();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
