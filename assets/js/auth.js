/**
 * Daan Chautari — Client-Side Authentication JavaScript
 * Handles form validation, role selection cards, password toggles, and login submit spinners.
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── 1. Role Cards (Signup Page) ──────────────────────────────────────────────
    const roleCards = document.querySelectorAll('.role-card');
    if (roleCards.length > 0) {
        // Mark initial selected radio's card as active
        document.querySelectorAll('input[name="role"]').forEach(function (radio) {
            if (radio.checked) {
                const card = document.getElementById('card-' + radio.value);
                if (card) card.classList.add('active');
            }
        });

        // Toggle active class on click
        roleCards.forEach(function (card) {
            card.addEventListener('click', function () {
                roleCards.forEach(function (c) { c.classList.remove('active'); });
                card.classList.add('active');
                const radio = card.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });
    }

    // ── 2. Password Match & Validation (Signup Form) ─────────────────────────────
    const signupForm = document.querySelector('form[action="signup_process.php"]');
    if (signupForm) {
        signupForm.addEventListener('submit', function (e) {
            const password = document.getElementById('password');
            const confirmPw = document.getElementById('confirm_password');
            const phone = document.getElementById('phone');

            if (password && confirmPw && password.value !== confirmPw.value) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Passwords Do Not Match',
                        text: 'Please make sure your password and confirm password fields match.',
                        toast: true,
                        position: 'top-end',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                } else {
                    alert('Passwords do not match. Please try again.');
                }
                return false;
            }

            if (phone && phone.value.trim().length < 7) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid Phone Number',
                        text: 'Please enter a valid phone number.',
                        toast: true,
                        position: 'top-end',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                } else {
                    alert('Please enter a valid phone number.');
                }
                return false;
            }
        });
    }

    // ── 3. Toggle Password Visibility (Admin & General Auth Forms) ────────────────
    const togglePwBtn = document.getElementById('togglePw');
    if (togglePwBtn) {
        togglePwBtn.addEventListener('click', function () {
            const pwInput = document.getElementById('admin_password') || document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (pwInput) {
                const isText = pwInput.type === 'text';
                pwInput.type = isText ? 'password' : 'text';
                if (eyeIcon) {
                    eyeIcon.innerHTML = isText
                        ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
                        : '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
                }
            }
        });
    }

    // ── 4. Admin Login Spinner & Submit Handler ──────────────────────────────────
    const adminLoginForm = document.getElementById('adminLoginForm');
    if (adminLoginForm) {
        adminLoginForm.addEventListener('submit', function () {
            const btn = document.getElementById('loginBtn');
            const spinner = document.getElementById('loginSpinner');
            const text = document.getElementById('loginBtnText');
            if (btn) btn.disabled = true;
            if (spinner) spinner.style.display = 'block';
            if (text) text.textContent = 'Signing in…';
        });
    }

});
