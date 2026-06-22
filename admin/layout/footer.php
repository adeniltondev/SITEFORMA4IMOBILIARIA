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
        dropdown.style.left   = '10px';
        dropdown.style.width  = '276px';
        dropdown.style.top    = 'auto';
        dropdown.style.bottom = '122.8px';
    }

    function openDropdown() {
        dropdown.classList.add('open');
        positionDropdown();
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
