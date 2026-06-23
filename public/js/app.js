(function () {
    'use strict';

    var pendingRequests = 0;

    function loader(active) {
        var element = document.querySelector('[data-app-loader]');
        if (!element) {
            return;
        }
        pendingRequests = Math.max(0, pendingRequests + (active ? 1 : -1));
        element.classList.toggle('is-active', pendingRequests > 0);
        element.setAttribute('aria-hidden', pendingRequests > 0 ? 'false' : 'true');
    }

    function toast(message, tone, title) {
        var region = document.querySelector('[data-toast-region]');
        if (!region || !message) {
            return;
        }
        var item = document.createElement('div');
        item.className = 'elliot-toast is-' + (tone || 'success');
        item.innerHTML = '<span class="elliot-toast-dot"></span><div><strong></strong><p></p></div><button type="button" aria-label="Fermer">×</button>';
        item.querySelector('strong').textContent = title || (tone === 'danger' ? 'Action impossible' : 'Opération réussie');
        item.querySelector('p').textContent = message;
        item.querySelector('button').addEventListener('click', function () {
            item.remove();
        });
        region.appendChild(item);
        window.setTimeout(function () {
            item.classList.add('is-leaving');
            window.setTimeout(function () { item.remove(); }, 240);
        }, 4800);
    }

    function confirmAction(message, options) {
        var modal = document.querySelector('[data-confirm-modal]');
        if (!modal) {
            return Promise.resolve(window.confirm(message));
        }
        var text = modal.querySelector('[data-confirm-message]');
        var accept = modal.querySelector('[data-confirm-accept]');
        var settings = options || {};
        text.textContent = message || 'Cette opération nécessite votre confirmation.';
        accept.textContent = settings.confirmLabel || 'Confirmer';
        accept.className = 'btn ' + (settings.danger === false ? 'btn-primary' : 'btn-danger');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        return new Promise(function (resolve) {
            function close(result) {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                accept.removeEventListener('click', approve);
                modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
                    button.removeEventListener('click', cancel);
                });
                resolve(result);
            }
            function approve() { close(true); }
            function cancel() { close(false); }
            accept.addEventListener('click', approve);
            modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
                button.addEventListener('click', cancel);
            });
            accept.focus();
        });
    }

    function enhanceFetch() {
        if (!window.fetch || window.fetch.__elliotEnhanced) {
            return;
        }
        var nativeFetch = window.fetch;
        var enhanced = function () {
            loader(true);
            return nativeFetch.apply(window, arguments).then(function (response) {
                var contentType = response.headers.get('content-type') || '';
                if (contentType.indexOf('application/json') !== -1) {
                    response.clone().json().then(function (payload) {
                        if (payload && payload.message) {
                            toast(payload.message, response.ok && payload.success !== false ? 'success' : 'danger');
                        }
                    }).catch(function () {});
                }
                return response;
            }).finally(function () {
                loader(false);
            });
        };
        enhanced.__elliotEnhanced = true;
        window.fetch = enhanced;
    }

    function bindSidebar() {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) {
            return;
        }
        document.querySelectorAll('[data-sidebar-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                sidebar.classList.toggle('is-open');
                document.body.classList.toggle('sidebar-open', sidebar.classList.contains('is-open'));
            });
        });
        document.querySelectorAll('[data-sidebar-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                sidebar.classList.remove('is-open');
                document.body.classList.remove('sidebar-open');
            });
        });
    }

    function bindPopovers() {
        var pairs = [
            ['[data-notification-toggle]', '[data-notification-popover]'],
            ['[data-user-toggle]', '[data-user-popover]']
        ];
        pairs.forEach(function (pair) {
            var trigger = document.querySelector(pair[0]);
            var popover = document.querySelector(pair[1]);
            if (!trigger || !popover) {
                return;
            }
            trigger.addEventListener('click', function (event) {
                event.stopPropagation();
                document.querySelectorAll('.is-popover-open').forEach(function (open) {
                    if (open !== popover) {
                        open.classList.remove('is-popover-open');
                    }
                });
                popover.classList.toggle('is-popover-open');
            });
        });
        document.addEventListener('click', function () {
            document.querySelectorAll('.is-popover-open').forEach(function (popover) {
                popover.classList.remove('is-popover-open');
            });
        });
    }

    function bindCommandSearch() {
        var wrapper = document.querySelector('[data-command-search]');
        var input = document.querySelector('[data-command-input]');
        var results = document.querySelector('[data-command-results]');
        if (!wrapper || !input || !results) {
            return;
        }
        function filter() {
            var needle = input.value.trim().toLowerCase();
            results.classList.add('is-open');
            results.querySelectorAll('[data-command-item]').forEach(function (item) {
                item.hidden = needle !== '' && item.getAttribute('data-command-item').indexOf(needle) === -1;
            });
        }
        input.addEventListener('focus', filter);
        input.addEventListener('input', filter);
        document.addEventListener('keydown', function (event) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                input.focus();
                filter();
            }
            if (event.key === 'Escape') {
                results.classList.remove('is-open');
                input.blur();
            }
        });
        document.addEventListener('click', function (event) {
            if (!wrapper.contains(event.target)) {
                results.classList.remove('is-open');
            }
        });
    }

    function bindTheme() {
        var button = document.querySelector('[data-theme-toggle]');
        var stored = localStorage.getItem('elliot-theme');
        if (stored === 'dark') {
            document.body.classList.add('theme-dark');
        }
        if (!button) {
            return;
        }
        button.addEventListener('click', function () {
            document.body.classList.toggle('theme-dark');
            localStorage.setItem('elliot-theme', document.body.classList.contains('theme-dark') ? 'dark' : 'light');
        });
    }

    function bindNavigationLoader() {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[href]');
            if (!link || link.target === '_blank' || link.hasAttribute('download') || event.metaKey || event.ctrlKey) {
                return;
            }
            var href = link.getAttribute('href') || '';
            if (href.indexOf('#') === 0 || href.indexOf('javascript:') === 0) {
                return;
            }
            loader(true);
        });
        window.addEventListener('pageshow', function () {
            pendingRequests = 1;
            loader(false);
        });
    }

    function emptyStates() {
        document.querySelectorAll('table tbody').forEach(function (tbody) {
            var table = tbody.closest('table');
            if (table && window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable
                && window.jQuery.fn.DataTable.isDataTable(table)) {
                return;
            }
            if (tbody.children.length !== 0) {
                return;
            }
            var columns = table && table.querySelectorAll('thead th').length || 1;
            var row = document.createElement('tr');
            row.innerHTML = '<td colspan="' + columns + '"><div class="table-empty-state"><span>◇</span><strong>Aucune donnée disponible</strong><small>Les prochains éléments apparaîtront ici.</small></div></td>';
            tbody.appendChild(row);
        });
    }

    function formPolish() {
        document.querySelectorAll('.form-control, .form-select').forEach(function (field) {
            field.addEventListener('focus', function () {
                var card = field.closest('.card');
                if (card) { card.classList.add('has-focus'); }
            });
            field.addEventListener('blur', function () {
                var card = field.closest('.card');
                if (card) { card.classList.remove('has-focus'); }
            });
        });
    }

    function tableLanguage(label) {
        return {
            search: 'Rechercher',
            lengthMenu: 'Afficher _MENU_ lignes',
            info: '_START_ à _END_ sur _TOTAL_ ' + label,
            infoEmpty: 'Aucune donnée',
            zeroRecords: 'Aucun résultat ne correspond aux filtres',
            emptyTable: 'Aucune donnée disponible',
            processing: 'Chargement des données…',
            paginate: {
                first: 'Premier',
                last: 'Dernier',
                next: 'Suivant',
                previous: 'Précédent'
            }
        };
    }

    function plainText(value) {
        var node = document.createElement('div');
        node.innerHTML = String(value === null || value === undefined ? '' : value);
        return (node.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function csvValue(value) {
        return '"' + String(value || '').replace(/"/g, '""') + '"';
    }

    function downloadCsv(api, table) {
        var headers = [];
        var excluded = [];
        table.querySelectorAll('thead th').forEach(function (header, index) {
            var label = plainText(header.textContent);
            if (label.toLowerCase() === 'actions') {
                excluded.push(index);
                return;
            }
            headers.push(label);
        });
        var rows = [headers.map(csvValue).join(';')];
        api.rows({ search: 'applied' }).nodes().each(function (row) {
            var values = [];
            row.querySelectorAll('td').forEach(function (cell, index) {
                if (excluded.indexOf(index) === -1) {
                    values.push(csvValue(plainText(cell.innerHTML)));
                }
            });
            rows.push(values.join(';'));
        });
        var blob = new Blob(['\ufeff' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        var name = (document.body.getAttribute('data-page-title') || 'export').toLowerCase()
            .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        link.href = URL.createObjectURL(blob);
        link.download = (name || 'export') + '-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
        toast('Le fichier CSV a été préparé.', 'success', 'Export terminé');
    }

    function enhanceDataTable(table) {
        if (table.dataset.premiumTable === '1') {
            return;
        }
        var api = window.jQuery(table).DataTable();
        var wrapper = table.closest('.dataTables_wrapper');
        if (!wrapper) {
            return;
        }
        table.dataset.premiumTable = '1';
        table.classList.add('elliot-data-table');

        var titleNode = table.closest('.card') && table.closest('.card').querySelector('.card-title, .payroll-mini-header strong');
        var title = titleNode ? titleNode.textContent.trim() : (document.body.getAttribute('data-page-title') || 'Données');
        var toolbar = document.createElement('div');
        toolbar.className = 'dt-premium-toolbar';
        toolbar.innerHTML =
            '<div class="dt-premium-heading"><span class="dt-premium-icon">≡</span><div><strong></strong><small data-dt-count>0 élément</small></div></div>' +
            '<div class="dt-premium-actions">' +
                '<label class="dt-premium-search"><span>⌕</span><input type="search" placeholder="Rechercher dans le tableau"></label>' +
                '<button class="btn btn-outline dt-filter-button" type="button"><span>☷</span> Filtres <b data-filter-count hidden>0</b></button>' +
                '<label class="dt-length"><span>Afficher</span><select><option value="10">10</option><option value="15">15</option><option value="25">25</option><option value="50">50</option></select></label>' +
                '<button class="btn btn-outline dt-export-button" type="button"><span>⇩</span> Exporter</button>' +
            '</div>';
        toolbar.querySelector('strong').textContent = title;

        var filters = document.createElement('div');
        filters.className = 'dt-premium-filters';
        filters.innerHTML = '<div class="dt-filter-head"><div><strong>Filtres avancés</strong><small>Affinez les résultats par colonne</small></div><button type="button" class="btn btn-sm btn-outline" data-clear-filters>Réinitialiser</button></div><div class="dt-filter-grid" data-filter-grid></div>';
        wrapper.insertBefore(filters, wrapper.firstChild);
        wrapper.insertBefore(toolbar, filters);

        wrapper.querySelectorAll('.dataTables_filter, .dataTables_length').forEach(function (legacyControl) {
            legacyControl.style.display = 'none';
            var legacyRow = legacyControl.closest('.row');
            if (legacyRow) {
                legacyRow.style.display = 'none';
            }
        });

        if (!wrapper.querySelector('.company-table-footer')) {
            var footer = document.createElement('div');
            footer.className = 'company-table-footer';
            var infoNode = wrapper.querySelector('.dataTables_info');
            var paginateNode = wrapper.querySelector('.dataTables_paginate');
            if (infoNode) {
                footer.appendChild(infoNode);
            }
            if (paginateNode) {
                footer.appendChild(paginateNode);
            }
            wrapper.appendChild(footer);
        }

        var search = toolbar.querySelector('input');
        var length = toolbar.querySelector('select');
        var filterButton = toolbar.querySelector('.dt-filter-button');
        var filterCount = toolbar.querySelector('[data-filter-count]');
        var count = toolbar.querySelector('[data-dt-count]');
        length.value = String(api.page.len());

        search.addEventListener('input', function () {
            api.search(search.value).draw();
        });
        length.addEventListener('change', function () {
            api.page.len(Number(length.value)).draw();
        });
        filterButton.addEventListener('click', function () {
            filters.classList.toggle('is-open');
            filterButton.classList.toggle('is-active', filters.classList.contains('is-open'));
        });
        toolbar.querySelector('.dt-export-button').addEventListener('click', function () {
            downloadCsv(api, table);
        });

        function updateCount() {
            var info = api.page.info();
            count.textContent = info.recordsDisplay + (info.recordsDisplay > 1 ? ' éléments' : ' élément');
        }

        function updateFilterCount() {
            var active = filters.querySelectorAll('select[data-column-filter]').length
                ? Array.from(filters.querySelectorAll('select[data-column-filter]')).filter(function (select) { return select.value !== ''; }).length
                : 0;
            filterCount.textContent = String(active);
            filterCount.hidden = active === 0;
        }

        function buildFilters() {
            var grid = filters.querySelector('[data-filter-grid]');
            var previous = {};
            grid.querySelectorAll('select[data-column-filter]').forEach(function (select) {
                previous[select.dataset.columnFilter] = select.value;
            });
            grid.innerHTML = '';

            table.querySelectorAll('thead th').forEach(function (header, index) {
                var label = plainText(header.textContent);
                if (!label || /actions?/i.test(label)) {
                    return;
                }
                var values = [];
                try {
                    values = api.cells(null, index).render('filter').toArray().map(plainText).filter(Boolean);
                } catch (error) {
                    return;
                }
                values = values.filter(function (value, position, array) {
                    return array.indexOf(value) === position;
                }).sort(function (a, b) {
                    return a.localeCompare(b, 'fr', { numeric: true });
                });
                if (values.length < 2 || values.length > 30) {
                    return;
                }
                var field = document.createElement('label');
                field.className = 'dt-filter-field';
                field.innerHTML = '<span></span><select class="form-select form-select-sm" data-column-filter="' + index + '"><option value="">Tous</option></select>';
                field.querySelector('span').textContent = label;
                var select = field.querySelector('select');
                values.forEach(function (value) {
                    var option = document.createElement('option');
                    option.value = value;
                    option.textContent = value;
                    select.appendChild(option);
                });
                select.value = previous[index] || '';
                select.addEventListener('change', function () {
                    api.column(index).search(select.value).draw();
                    updateFilterCount();
                });
                grid.appendChild(field);
            });
            filters.classList.toggle('has-filters', grid.children.length > 0);
            filterButton.hidden = grid.children.length === 0;
            updateFilterCount();
        }

        filters.querySelector('[data-clear-filters]').addEventListener('click', function () {
            filters.querySelectorAll('select[data-column-filter]').forEach(function (select) {
                select.value = '';
                api.column(Number(select.dataset.columnFilter)).search('');
            });
            search.value = '';
            api.search('').draw();
            updateFilterCount();
        });

        api.on('draw.elliotPremium', updateCount);
        api.on('xhr.elliotPremium', function () {
            window.setTimeout(buildFilters, 0);
        });
        updateCount();
        buildFilters();
    }

    function autoDataTables() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) {
            return;
        }
        document.querySelectorAll('table.table').forEach(function (table) {
            if (!table.querySelector('thead')
                || !table.querySelector('tbody')
                || table.hasAttribute('data-no-datatable')
                || table.hasAttribute('data-settings-table')) {
                return;
            }
            var headers = table.querySelectorAll('thead th').length;
            var placeholder = table.querySelector('tbody tr:only-child td[colspan]');
            if (placeholder && Number(placeholder.getAttribute('colspan')) >= headers) {
                placeholder.closest('tr').remove();
            }
            if (window.jQuery.fn.DataTable.isDataTable(table)) {
                enhanceDataTable(table);
                return;
            }
            if (table.hasAttribute('data-ajax-url')) {
                window.setTimeout(function () {
                    if (window.jQuery.fn.DataTable.isDataTable(table)) {
                        enhanceDataTable(table);
                    }
                }, 250);
                return;
            }
            window.jQuery(table).DataTable({
                pageLength: 10,
                order: [],
                autoWidth: false,
                language: tableLanguage('lignes'),
                dom: 'rt<"company-table-footer"ip>'
            });
            enhanceDataTable(table);
        });
    }

    window.ElliotUI = {
        toast: toast,
        loading: loader,
        confirm: confirmAction
    };

    enhanceFetch();
    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.classList.add('js-ready');
        bindSidebar();
        bindPopovers();
        bindCommandSearch();
        bindTheme();
        bindNavigationLoader();
        formPolish();
        autoDataTables();
        emptyStates();
        document.body.classList.add('app-ready');
    });
})();
