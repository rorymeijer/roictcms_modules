/**
 * Frontend Login Module — JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // ── Wachtwoord tonen/verbergen ──────────────────────────────────────────
    document.querySelectorAll('.fl-pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = this.closest('.fl-pw-wrap').querySelector('input');
            var icon  = this.querySelector('i');
            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
                this.setAttribute('aria-label', 'Wachtwoord verbergen');
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
                this.setAttribute('aria-label', 'Wachtwoord tonen');
            }
        });
    });
});
