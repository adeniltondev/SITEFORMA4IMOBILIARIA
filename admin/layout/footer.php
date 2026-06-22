    </div><!-- /.page-body -->
</main><!-- /.main-content -->

<!-- Scripts -->
<script src="<?= $appUrl ?>/assets/js/app.js"></script>
<script>
(function () {
    var btn      = document.getElementById('shareToggleBtn');
    var dropdown = document.getElementById('shareDropdown');
    var wrapper  = document.getElementById('shareNavWrapper');
    if (!btn || !dropdown) return;

    function positionDropdown() {
        var r = btn.getBoundingClientRect();
        dropdown.style.left   = r.left + 'px';
        dropdown.style.top    = (r.bottom + 6) + 'px';
        dropdown.style.width  = Math.max(r.width, 220) + 'px';
    }

    function openDropdown() {
        positionDropdown();
        dropdown.classList.add('open');
        btn.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
    }

    function closeDropdown() {
        dropdown.classList.remove('open');
        btn.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.contains('open') ? closeDropdown() : openDropdown();
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && !wrapper.contains(e.target)) {
            closeDropdown();
        }
    });

    window.addEventListener('resize', function () {
        if (dropdown.classList.contains('open')) positionDropdown();
    });
})();

function copyShareLink(url, el) {
    navigator.clipboard.writeText(url).then(function () {
        var orig = el.innerHTML;
        el.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Copiado!';
        el.classList.add('copied');
        setTimeout(function () { el.innerHTML = orig; el.classList.remove('copied'); }, 2000);
    });
}
</script>
</body>
</html>
