(function () {
    'use strict';
    const overlay = document.createElement('div');
    overlay.className = 'gallery-lightbox-overlay';
    overlay.innerHTML = '<span class="lb-close" aria-label="Sluiten">&times;</span><span class="lb-prev">&#8249;</span><img src="" alt=""><span class="lb-caption"></span><span class="lb-next">&#8250;</span>';
    document.body.appendChild(overlay);

    let items = [], current = 0;

    function show(i) {
        current = (i + items.length) % items.length;
        overlay.querySelector('img').src = items[current].src;
        overlay.querySelector('.lb-caption').textContent = items[current].caption;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function close() { overlay.classList.remove('active'); document.body.style.overflow = ''; }

    document.addEventListener('click', function (e) {
        const item = e.target.closest('.gallery-item[data-src]');
        if (!item) return;
        const grid = item.closest('.gallery-grid');
        const all  = Array.from(grid.querySelectorAll('.gallery-item[data-src]'));
        items = all.map(el => ({ src: el.dataset.src, caption: el.dataset.caption || '' }));
        show(all.indexOf(item));
    });

    overlay.querySelector('.lb-close').addEventListener('click', close);
    overlay.querySelector('.lb-prev').addEventListener('click', () => show(current - 1));
    overlay.querySelector('.lb-next').addEventListener('click', () => show(current + 1));
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', e => {
        if (!overlay.classList.contains('active')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft')  show(current - 1);
        if (e.key === 'ArrowRight') show(current + 1);
    });
})();
