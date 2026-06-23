(function () {
    'use strict';

    function csrf() {
        return window.ELLIOT_CSRF || '';
    }

    function showError(message) {
        var box = document.querySelector('[data-declaration-error]');
        if (!box) {
            return;
        }
        box.textContent = message || 'Operation impossible.';
        box.classList.remove('d-none');
    }

    function clearError() {
        var box = document.querySelector('[data-declaration-error]');
        if (box) {
            box.classList.add('d-none');
            box.textContent = '';
        }
    }

    function request(url, data) {
        return fetch(url, {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (payload) {
                payload.status = response.status;
                return payload;
            });
        });
    }

    function bindGenerate() {
        document.querySelectorAll('[data-declaration-generate]').forEach(function (button) {
            button.addEventListener('click', function () {
                clearError();
                var label = button.textContent;
                var data = new FormData();
                data.append('_csrf_token', csrf());
                data.append('id', button.getAttribute('data-period-id') || '');
                button.disabled = true;
                button.textContent = 'Generation...';

                request(button.getAttribute('data-declaration-generate'), data)
                    .then(function (payload) {
                        if (!payload.success) {
                            showError(payload.message || 'Generation impossible.');
                            return;
                        }
                        if (payload.redirect) {
                            window.location.href = payload.redirect;
                            return;
                        }
                        window.location.reload();
                    })
                    .catch(function () {
                        showError('Erreur reseau. Reessayez dans un instant.');
                    })
                    .finally(function () {
                        button.disabled = false;
                        button.textContent = label;
                    });
            });
        });
    }

    function bindForms() {
        document.querySelectorAll('[data-declaration-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                clearError();
                var button = form.querySelector('[data-submit-label]');
                var label = button ? button.textContent : '';
                var data = new FormData(form);
                if (!data.has('_csrf_token')) {
                    data.append('_csrf_token', csrf());
                }

                if (button) {
                    button.disabled = true;
                    button.textContent = 'Traitement...';
                }

                request(form.getAttribute('action'), data)
                    .then(function (payload) {
                        if (!payload.success) {
                            showError(payload.message || 'Enregistrement impossible.');
                            return;
                        }
                        if (payload.redirect) {
                            window.location.href = payload.redirect;
                            return;
                        }
                        if (payload.reload) {
                            window.location.reload();
                        }
                    })
                    .catch(function () {
                        showError('Erreur reseau. Reessayez dans un instant.');
                    })
                    .finally(function () {
                        if (button) {
                            button.disabled = false;
                            button.textContent = label;
                        }
                    });
            });
        });
    }

    function bindTables() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) {
            return;
        }

        ['declaration-periods-table', 'declarations-table', 'declaration-details-table'].forEach(function (id) {
            var table = document.getElementById(id);
            if (!table || table.dataset.bound === '1') {
                return;
            }
            table.dataset.bound = '1';
            var dataTable = window.jQuery(table).DataTable({
                pageLength: 10,
                order: [],
                language: {
                    search: 'Rechercher',
                    lengthMenu: '_MENU_ lignes',
                    info: '_START_ - _END_ sur _TOTAL_',
                    paginate: { previous: 'Precedent', next: 'Suivant' },
                    zeroRecords: 'Aucune donnee'
                }
            });

            var search = document.querySelector('[data-declaration-search]');
            if (search && id === 'declaration-details-table') {
                search.addEventListener('input', function () {
                    dataTable.search(search.value).draw();
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindGenerate();
        bindForms();
        bindTables();
    });
})();
