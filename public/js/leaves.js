(function () {
    'use strict';

    function csrfToken() {
        var input = document.querySelector('input[name="_csrf_token"]');
        return window.ELLIOT_CSRF || (input ? input.value : '');
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

    function showError(box, message) {
        if (!box) {
            alert(message);
            return;
        }

        box.textContent = message;
        box.classList.remove('d-none');
    }

    function bindForms() {
        document.querySelectorAll('[data-leave-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var errorBox = form.querySelector('[data-form-error]') || document.querySelector('[data-leave-error]');
                var submit = form.querySelector('[data-submit-label]');
                var original = submit ? submit.textContent : '';

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
                            submit.textContent = original;
                        }
                    });
            });
        });
    }

    function bindApprovals() {
        document.querySelectorAll('[data-leave-approve]').forEach(function (button) {
            button.addEventListener('click', function () {
                var data = new FormData();
                data.append('id', button.getAttribute('data-leave-id') || '');
                button.disabled = true;

                request(button.getAttribute('data-leave-approve'), data)
                    .then(function (payload) {
                        if (!payload.httpOk || !payload.success) {
                            showError(document.querySelector('[data-leave-error]'), payload.message || 'Validation impossible.');
                            return;
                        }

                        if (payload.reload) {
                            window.location.reload();
                        }
                    })
                    .catch(function () {
                        showError(document.querySelector('[data-leave-error]'), 'Erreur reseau. Reessayez dans un instant.');
                    })
                    .finally(function () {
                        button.disabled = false;
                    });
            });
        });
    }

    function bindRejects() {
        document.querySelectorAll('[data-leave-reject]').forEach(function (button) {
            button.addEventListener('click', function () {
                var reason = prompt('Motif du refus');
                if (reason === null) {
                    return;
                }

                reason = reason.trim();
                if (!reason) {
                    showError(document.querySelector('[data-leave-error]'), 'Le motif de refus est obligatoire.');
                    return;
                }

                var data = new FormData();
                data.append('id', button.getAttribute('data-leave-id') || '');
                data.append('rejection_reason', reason);
                button.disabled = true;

                request(button.getAttribute('data-leave-reject'), data)
                    .then(function (payload) {
                        if (!payload.httpOk || !payload.success) {
                            showError(document.querySelector('[data-leave-error]'), payload.message || 'Refus impossible.');
                            return;
                        }

                        if (payload.reload) {
                            window.location.reload();
                        }
                    })
                    .catch(function () {
                        showError(document.querySelector('[data-leave-error]'), 'Erreur reseau. Reessayez dans un instant.');
                    })
                    .finally(function () {
                        button.disabled = false;
                    });
            });
        });
    }

    function bindTypeFiltering() {
        var employee = document.querySelector('[data-leave-employee-select]');
        var type = document.querySelector('[data-leave-type-select]');
        if (!employee || !type) {
            return;
        }

        function selectedCompanyId() {
            var option = employee.options[employee.selectedIndex];
            return option ? option.getAttribute('data-company-id') || '' : '';
        }

        function filterTypes() {
            var companyId = selectedCompanyId();
            var currentValue = type.value;
            var currentStillVisible = false;

            type.querySelectorAll('option').forEach(function (option) {
                var optionCompanyId = option.getAttribute('data-company-id');
                var visible = !optionCompanyId || !companyId || optionCompanyId === companyId;
                option.hidden = !visible;

                if (visible && option.value === currentValue) {
                    currentStillVisible = true;
                }
            });

            if (!currentStillVisible) {
                type.value = '';
            }
        }

        employee.addEventListener('change', filterTypes);
        filterTypes();
    }

    function bindTable() {
        var table = document.getElementById('leaves-table');
        if (!table) {
            return;
        }

        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
            var dataTable = window.jQuery(table).DataTable({
                pageLength: 15,
                order: [[2, 'desc']],
                language: {
                    search: 'Rechercher',
                    lengthMenu: 'Afficher _MENU_ lignes',
                    info: '_START_ a _END_ sur _TOTAL_ demandes',
                    infoEmpty: 'Aucune demande',
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

            var input = document.querySelector('[data-leave-search]');
            if (input) {
                input.addEventListener('input', function () {
                    dataTable.search(input.value).draw();
                });
            }
            return;
        }

        var search = document.querySelector('[data-leave-search]');
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

    document.addEventListener('DOMContentLoaded', function () {
        bindForms();
        bindApprovals();
        bindRejects();
        bindTypeFiltering();
        bindTable();
    });
})();
