// ERP Lite - global UI helpers
document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss alerts
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
        setTimeout(function (i) {
            el.classList.remove('show');
            setTimeout(function () { el.remove(); }, 300);
        }, 3500);
    });
});