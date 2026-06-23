(function () {
    'use strict';

    function csrfToken() {
        var input = document.querySelector('input[name="_csrf_token"]');
        return window.ELLIOT_CSRF || (input ? input.value : '');
    }

    function showError(container, message) {
        if (!container) {
            alert(message);
            return;
        }

        container.textContent = message;
        container.classList.remove('d-none');
    }

    function request(url, data) {
        data.append('_csrf_token', csrfToken());

        return fetch(url, {
            method: 'POST',
            body: data,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (payload) {
                payload.httpOk = response.ok;
                return payload;
            });
        });
    }

    function bindForms() {
        document.querySelectorAll('[data-company-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var errorBox = form.querySelector('[data-form-error]');
                var submit = form.querySelector('[data-submit-label]');
                var originalText = submit ? submit.textContent : '';

                if (errorBox) {
                    errorBox.classList.add('d-none');
                    errorBox.textContent = '';
                }

                if (submit) {
                    submit.disabled = true;
                    submit.textContent = 'Traitement...';
                }

                request(form.action, new FormData(form))
                    .then(function (payload) {
                        if (!payload.httpOk || !payload.success) {
                            showError(errorBox, payload.message || 'Operation impossible.');
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
                        showError(errorBox, 'Erreur reseau. Reessayez dans un instant.');
                    })
                    .finally(function () {
                        if (submit) {
                            submit.disabled = false;
                            submit.textContent = originalText;
                        }
                    });
            });
        });
    }

    function bindStatus() {
        document.querySelectorAll('[data-company-status]').forEach(function (select) {
            select.addEventListener('change', function () {
                var id = select.getAttribute('data-company-status');
                var row = select.closest('[data-company-row]');
                var badge = row ? row.querySelector('[data-status-badge]') : null;
                var data = new FormData();

                data.append('id', id);
                data.append('status', select.value);

                select.disabled = true;
                request(window.location.origin + window.location.pathname.replace(/\/companies.*/, '/companies/status'), data)
                    .then(function (payload) {
                        if (!payload.httpOk || !payload.success) {
                            alert(payload.message || 'Changement de statut impossible.');
                            return;
                        }

                        if (badge) {
                            badge.className = 'badge mt-2 bg-' + statusTone(select.value) + '-lt';
                            badge.textContent = statusLabel(select.value);
                        }
                    })
                    .finally(function () {
                        select.disabled = false;
                    });
            });
        });
    }

    function bindDelete() {
        document.querySelectorAll('[data-company-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!confirm('Supprimer cette entreprise ?')) {
                    return;
                }

                var data = new FormData();
                data.append('id', button.getAttribute('data-company-delete'));

                button.disabled = true;
                request(window.location.origin + window.location.pathname.replace(/\/companies.*/, '/companies/delete'), data)
                    .then(function (payload) {
                        if (!payload.httpOk || !payload.success) {
                            alert(payload.message || 'Suppression impossible.');
                            return;
                        }

                        var row = button.closest('tr');
                        if (row) {
                            row.remove();
                        }
                    })
                    .finally(function () {
                        button.disabled = false;
                    });
            });
        });

        document.querySelectorAll('[data-branch-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!confirm('Supprimer cette agence ?')) {
                    return;
                }

                var data = new FormData();
                data.append('branch_id', button.getAttribute('data-branch-delete'));

                button.disabled = true;
                request(window.location.origin + window.location.pathname.replace(/\/companies.*/, '/companies/branches/delete'), data)
                    .then(function (payload) {
                        if (payload.reload) {
                            window.location.reload();
                        } else if (!payload.success) {
                            alert(payload.message || 'Suppression impossible.');
                        }
                    })
                    .finally(function () {
                        button.disabled = false;
                    });
            });
        });
    }

    function bindTable() {
        var table = document.getElementById('companies-table');
        if (!table) {
            return;
        }

        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
            var dataTable = window.jQuery(table).DataTable({
                pageLength: 10,
                order: [[0, 'asc']],
                language: {
                    search: 'Rechercher',
                    lengthMenu: 'Afficher _MENU_ lignes',
                    info: '_START_ a _END_ sur _TOTAL_ entreprises',
                    infoEmpty: 'Aucune entreprise',
                    zeroRecords: 'Aucun resultat',
                    paginate: {
                        first: 'Premier',
                        last: 'Dernier',
                        next: 'Suivant',
                        previous: 'Precedent'
                    }
                },
                dom: 'rt<"company-table-footer"ip>'
            });

            var input = document.querySelector('[data-company-search]');
            if (input) {
                input.addEventListener('input', function () {
                    dataTable.search(input.value).draw();
                });
            }

            return;
        }

        var search = document.querySelector('[data-company-search]');
        if (!search) {
            return;
        }

        search.addEventListener('input', function () {
            var needle = search.value.toLowerCase();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().indexOf(needle) === -1 ? 'none' : '';
            });
        });
    }

    function statusLabel(status) {
        return {
            active: 'Actif',
            suspended: 'Suspendu',
            inactive: 'Inactif'
        }[status] || status;
    }

    function statusTone(status) {
        return {
            active: 'green',
            suspended: 'orange',
            inactive: 'red'
        }[status] || 'blue';
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindForms();
        bindStatus();
        bindDelete();
        bindTable();
    });
})();
