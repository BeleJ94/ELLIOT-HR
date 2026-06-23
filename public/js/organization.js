(function () {
    'use strict';

    function csrfToken() {
        var input = document.querySelector('input[name="_csrf_token"]');
        return window.ELLIOT_CSRF || (input ? input.value : '');
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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

    function companyValue(form) {
        var field = form.querySelector('[data-company-select]');
        return field ? field.value : '';
    }

    function filterOptions(form) {
        var selectedCompany = companyValue(form);

        form.querySelectorAll('[data-filtered-options]').forEach(function (select) {
            var currentValue = select.value;
            var currentStillVisible = false;

            select.querySelectorAll('option').forEach(function (option) {
                var optionCompany = option.getAttribute('data-company-id');
                var visible = !optionCompany || !selectedCompany || optionCompany === selectedCompany;
                option.hidden = !visible;

                if (visible && option.value === currentValue) {
                    currentStillVisible = true;
                }
            });

            if (!currentStillVisible) {
                select.value = '';
            }
        });
    }

    function resetForm(form) {
        var title = form.querySelector('[data-org-form-title]');
        var id = form.querySelector('input[name="id"]');
        var errorBox = form.querySelector('[data-form-error]');

        form.reset();
        form.action = form.getAttribute('data-store-url');
        if (id) {
            id.value = '';
        }
        if (title) {
            title.textContent = document.getElementById('positions-table') ? 'Nouveau poste' : 'Nouveau departement';
        }
        if (errorBox) {
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
        }
        filterOptions(form);
    }

    function setField(form, name, value) {
        var field = form.querySelector('[name="' + name + '"]');
        if (!field) {
            return;
        }

        field.value = value === null || value === undefined ? '' : value;
    }

    function fillForm(form, row) {
        var title = form.querySelector('[data-org-form-title]');

        form.action = form.getAttribute('data-update-url');
        setField(form, 'id', row.id);
        setField(form, 'company_id', row.company_id);
        filterOptions(form);

        if (document.getElementById('departments-table')) {
            setField(form, 'name', row.name);
            setField(form, 'code', row.code === '-' ? '' : row.code);
            setField(form, 'branch_id', row.branch_id);
            setField(form, 'manager_id', row.manager_id);
            if (title) {
                title.textContent = 'Modifier le departement';
            }
            return;
        }

        setField(form, 'title', row.title);
        setField(form, 'code', row.code === '-' ? '' : row.code);
        setField(form, 'department_id', row.department_id);
        setField(form, 'description', row.description === '-' ? '' : row.description);
        if (title) {
            title.textContent = 'Modifier le poste';
        }
    }

    function dataTableLanguage(entity) {
        return {
            search: 'Rechercher',
            lengthMenu: 'Afficher _MENU_ lignes',
            info: '_START_ a _END_ sur _TOTAL_ ' + entity,
            infoEmpty: 'Aucun element',
            zeroRecords: 'Aucun resultat',
            processing: 'Chargement...',
            paginate: {
                first: 'Premier',
                last: 'Dernier',
                next: 'Suivant',
                previous: 'Precedent'
            }
        };
    }

    function departmentColumns() {
        return [
            {
                data: null,
                render: function (row) {
                    return '<strong class="company-name">' + escapeHtml(row.name) + '</strong>'
                        + '<span class="d-block text-secondary">' + escapeHtml(row.code || '-') + '</span>';
                }
            },
            { data: 'company' },
            { data: 'branch' },
            { data: 'manager' },
            {
                data: 'positions_count',
                className: 'text-end',
                render: function (value) {
                    return '<span class="badge bg-blue-lt">' + escapeHtml(value) + '</span>';
                }
            },
            {
                data: 'employees_count',
                className: 'text-end',
                render: function (value) {
                    return '<span class="badge bg-green-lt">' + escapeHtml(value) + '</span>';
                }
            },
            { data: 'actions', orderable: false, searchable: false }
        ];
    }

    function positionColumns() {
        return [
            {
                data: null,
                render: function (row) {
                    return '<strong class="company-name">' + escapeHtml(row.title) + '</strong>'
                        + '<span class="d-block text-secondary">' + escapeHtml(row.code || '-') + '</span>';
                }
            },
            { data: 'company' },
            { data: 'department' },
            { data: 'description' },
            {
                data: 'employees_count',
                className: 'text-end',
                render: function (value) {
                    return '<span class="badge bg-green-lt">' + escapeHtml(value) + '</span>';
                }
            },
            { data: 'actions', orderable: false, searchable: false }
        ];
    }

    function initDataTable(table) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) {
            return null;
        }

        var isDepartments = table.id === 'departments-table';
        var dataTable = window.jQuery(table).DataTable({
            ajax: table.getAttribute('data-ajax-url'),
            pageLength: 10,
            processing: true,
            order: [[0, 'asc']],
            columns: isDepartments ? departmentColumns() : positionColumns(),
            language: dataTableLanguage(isDepartments ? 'departements' : 'postes'),
            dom: 'rt<"company-table-footer"ip>'
        });

        var input = document.querySelector('[data-org-search]');
        if (input) {
            input.addEventListener('input', function () {
                dataTable.search(input.value).draw();
            });
        }

        return dataTable;
    }

    function bindForm(form, dataTable) {
        var errorBox = form.querySelector('[data-form-error]');
        var company = form.querySelector('[data-company-select]');

        filterOptions(form);

        if (company && company.tagName === 'SELECT') {
            company.addEventListener('change', function () {
                filterOptions(form);
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

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

                    resetForm(form);
                    if (dataTable) {
                        dataTable.ajax.reload(null, false);
                    } else if (payload.reload) {
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

        var reset = form.querySelector('[data-org-reset]');
        if (reset) {
            reset.addEventListener('click', function () {
                resetForm(form);
            });
        }
    }

    function bindTableActions(form, table, dataTable) {
        table.addEventListener('click', function (event) {
            var editButton = event.target.closest('[data-org-edit]');
            var deleteButton = event.target.closest('[data-org-delete]');
            var row;
            var rowData;

            if (!editButton && !deleteButton) {
                return;
            }

            row = window.jQuery(editButton || deleteButton).closest('tr');
            rowData = dataTable ? dataTable.row(row).data() : null;
            if (!rowData) {
                return;
            }

            if (editButton) {
                fillForm(form, rowData);
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }

            if (!confirm('Supprimer cet element ?')) {
                return;
            }

            var data = new FormData();
            data.append('id', rowData.id);
            deleteButton.disabled = true;

            request(form.getAttribute('data-delete-url'), data)
                .then(function (payload) {
                    if (!payload.httpOk || !payload.success) {
                        alert(payload.message || 'Suppression impossible.');
                        return;
                    }

                    if (dataTable) {
                        dataTable.ajax.reload(null, false);
                    } else {
                        window.location.reload();
                    }
                })
                .finally(function () {
                    deleteButton.disabled = false;
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var table = document.getElementById('departments-table') || document.getElementById('positions-table');
        var form = document.querySelector('[data-org-form]');
        var dataTable;

        if (!table || !form) {
            return;
        }

        dataTable = initDataTable(table);
        bindForm(form, dataTable);
        bindTableActions(form, table, dataTable);
    });
})();
