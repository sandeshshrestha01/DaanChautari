/**
 * Daan Chautari — General Pages & Interactivity JavaScript
 * Handles homepage animated counters, image upload previews, dynamic custom category toggling, and general page UI events.
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── 1. Animated Stats Counter (Homepage) ────────────────────────────────────
    const counters = document.querySelectorAll(".stat-number");
    if (counters.length > 0) {
        counters.forEach(function (counter) {
            const target = parseInt(counter.getAttribute("data-count") || "0", 10);
            if (target === 0) return;

            let current = 0;
            const increment = target > 100 ? Math.ceil(target / 40) : 1;
            const timer = setInterval(function () {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                counter.innerText = current.toLocaleString() + "+";
            }, 30);
        });
    }

    // ── 2. Donation Image Preview Handler ───────────────────────────────────────
    const dnImageInput = document.getElementById('dn_image');
    if (dnImageInput) {
        dnImageInput.addEventListener('change', function () {
            previewDonationImage(this);
        });
    }

});

/**
 * Preview uploaded donation image in form.
 * @param {HTMLInputElement} input 
 */
function previewDonationImage(input) {
    const preview = document.getElementById('dn_image_preview');
    const thumb   = document.getElementById('dn_image_thumb');
    if (input && input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            if (thumb) thumb.src = e.target.result;
            if (preview) preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Clear selected donation image.
 */
function clearDonationImage() {
    const input   = document.getElementById('dn_image');
    const thumb   = document.getElementById('dn_image_thumb');
    const preview = document.getElementById('dn_image_preview');
    
    if (input) input.value = '';
    if (thumb) thumb.src = '';
    if (preview) preview.style.display = 'none';
}

/**
 * Toggle custom category text input when "Other" option is selected.
 * @param {HTMLSelectElement} selectEl 
 * @param {string} wrapId 
 */
function toggleCustomCategory(selectEl, wrapId) {
    const wrap  = document.getElementById(wrapId);
    const input = wrap ? wrap.querySelector('input') : null;
    if (selectEl && selectEl.value === 'Other') {
        if (wrap) wrap.style.display = 'block';
        if (input) {
            input.required = true;
            input.focus();
        }
    } else {
        if (wrap) wrap.style.display = 'none';
        if (input) {
            input.required = false;
            input.value = '';
        }
    }
}
