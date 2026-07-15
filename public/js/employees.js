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
            }).catch(function () {
                return {
                    success: false,
                    httpOk: false,
                    message: 'Le serveur a retourne une reponse invalide (HTTP ' + response.status + ').'
                };
            });
        });
    }

    function clearErrors(form, box) {
        form.querySelectorAll('.is-invalid').forEach(function (field) {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
        });
        form.querySelectorAll('[data-generated-field-error]').forEach(function (feedback) {
            feedback.remove();
        });

        if (box) {
            box.classList.add('d-none');
            box.textContent = '';
        }
    }

    function showError(form, box, message, errors) {
        var details = errors && typeof errors === 'object' ? errors : {};
        var messages = Object.keys(details).map(function (fieldName) {
            var field = form.querySelector('[name="' + fieldName.replace(/"/g, '\\"') + '"]');
            var fieldMessage = String(details[fieldName]);

            if (field) {
                field.classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');

                var feedback = document.createElement('div');
                feedback.className = 'invalid-feedback d-block';
                feedback.setAttribute('data-generated-field-error', '');
                feedback.textContent = fieldMessage;
                field.insertAdjacentElement('afterend', feedback);
            }

            return fieldMessage;
        });
        var fullMessage = message || 'Operation impossible.';

        if (messages.length) {
            fullMessage += '\n• ' + messages.join('\n• ');
        }

        if (!box) {
            alert(fullMessage);
            return;
        }

        box.style.whiteSpace = 'pre-line';
        box.textContent = fullMessage;
        box.classList.remove('d-none');
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function bindForms() {
        document.querySelectorAll('[data-employee-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var errorBox = form.querySelector('[data-form-error]') || document.querySelector('[data-form-error]');
                var submit = form.querySelector('[data-submit-label]');
                var original = submit ? submit.textContent : '';

                clearErrors(form, errorBox);

                if (submit) {
                    submit.disabled = true;
                    submit.textContent = 'Traitement...';
                }

                request(form.action, new FormData(form))
                    .then(function (payload) {
                        if (!payload.httpOk || !payload.success) {
                            showError(form, errorBox, payload.message || 'Operation impossible.', payload.errors);
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
                    .catch(function (error) {
                        showError(form, errorBox, 'Erreur reseau. Reessayez dans un instant.');
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

    function bindArchive() {
        document.querySelectorAll('[data-employee-archive]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.ElliotUI.confirm('Archiver cet employé ? Son dossier restera disponible dans l’historique.', {
                    confirmLabel: 'Archiver'
                }).then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    var data = new FormData();
                    data.append('id', button.getAttribute('data-employee-archive'));
                    button.disabled = true;

                    request(window.location.origin + window.location.pathname.replace(/\/employees.*/, '/employees/archive'), data)
                        .then(function (payload) {
                            if (!payload.httpOk || !payload.success) {
                                window.ElliotUI.toast(payload.message || 'Archivage impossible.', 'danger');
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
        });
    }

    function bindTable() {
        var table = document.getElementById('employees-table');
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
                    info: '_START_ a _END_ sur _TOTAL_ employes',
                    infoEmpty: 'Aucun employe',
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

            var input = document.querySelector('[data-employee-search]');
            if (input) {
                input.addEventListener('input', function () {
                    dataTable.search(input.value).draw();
                });
            }
            return;
        }

        var search = document.querySelector('[data-employee-search]');
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
        bindArchive();
        bindTable();
    });
})();
