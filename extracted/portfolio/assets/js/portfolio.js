/* Portfolio Module - Filter functionaliteit - ROICT CMS */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var filterBtns = document.querySelectorAll('.roict-filter-btn');
        var items = document.querySelectorAll('.roict-portfolio-item');

        if (!filterBtns.length || !items.length) return;

        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filter = this.getAttribute('data-filter');

                // Actieve knop bijwerken
                filterBtns.forEach(function (b) {
                    b.classList.remove('active', 'btn-primary');
                    b.classList.add('btn-outline-primary');
                });
                this.classList.add('active', 'btn-primary');
                this.classList.remove('btn-outline-primary');

                // Items filteren
                items.forEach(function (item) {
                    if (filter === 'all' || item.getAttribute('data-category') === filter) {
                        item.classList.remove('hidden');
                    } else {
                        item.classList.add('hidden');
                    }
                });
            });
        });
    });
})();
