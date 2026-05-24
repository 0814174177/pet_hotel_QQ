(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', initBookingHistory);

    function initBookingHistory() {
        const tabsContainer = document.getElementById('booking-tabs');
        const listContainer = document.getElementById('booking-list');

        if (!tabsContainer || !listContainer) {
            return;
        }

        bindTabEvents(tabsContainer);
    }

    function bindTabEvents(tabsContainer) {
        const tabs = tabsContainer.querySelectorAll('.bh-tab');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (item) {
                    item.classList.remove('active');
                });

                tab.classList.add('active');
                filterList(tab.getAttribute('data-target') || 'all');
            });
        });
    }

    function filterList(targetGroup) {
        const items = document.querySelectorAll('.js-booking-item');
        const emptyState = document.getElementById('booking-filter-empty');
        let visibleCount = 0;

        items.forEach(function (item) {
            const itemGroup = item.getAttribute('data-group');
            const isVisible = targetGroup === 'all' || targetGroup === itemGroup;

            item.hidden = !isVisible;

            if (isVisible) {
                visibleCount += 1;
            }
        });

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }
    }
})();
