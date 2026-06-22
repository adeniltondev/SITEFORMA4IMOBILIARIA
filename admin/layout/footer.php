    </div><!-- /.page-body -->
</main><!-- /.main-content -->

<!-- Scripts -->
<script src="<?= $appUrl ?>/assets/js/app.js"></script>
<script>
(function () {
    var btn = document.getElementById('shareToggleBtn');
    var dropdown = document.getElementById('shareDropdown');
    if (!btn || !dropdown) return;

    btn.addEventListener('click', function () {
        var open = dropdown.classList.toggle('open');
        btn.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', open);
    });

    document.addEventListener('click', function (e) {
        if (!document.getElementById('shareNavWrapper').contains(e.target)) {
            dropdown.classList.remove('open');
            btn.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();

function copyShareLink(url, el) {
    navigator.clipboard.writeText(url).then(function () {
        var orig = el.innerHTML;
        el.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Copiado!';
        el.classList.add('copied');
        setTimeout(function () {
            el.innerHTML = orig;
            el.classList.remove('copied');
        }, 2000);
    });
}
</script>
</body>
</html>
