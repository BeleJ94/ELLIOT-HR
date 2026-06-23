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

    function filterEmployees(form) {
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

    function syncCompanyFromEmployee(form) {
        var employee = form.querySelector('[data-auto-company-from-employee]');
        var company = form.querySelector('[data-company-select]');

        if (!employee || !company || !employee.value) {
            return;
        }

        var selectedOption = employee.options[employee.selectedIndex];
        var companyId = selectedOption ? selectedOption.getAttribute('data-company-id') : '';

        if (companyId && company.value !== companyId) {
            company.value = companyId;
            filterEmployees(form);
        }
    }

    function bindForms() {
        document.querySelectorAll('[data-contract-form]').forEach(function (form) {
            var company = form.querySelector('[data-company-select]');
            var errorBox = form.querySelector('[data-form-error]');
            var contractType = form.querySelector('[data-contract-type]');
            var endDate = form.querySelector('[data-contract-end-date]');

            filterEmployees(form);
            syncCompanyFromEmployee(form);

            if (company) {
                company.addEventListener('change', function () {
                    filterEmployees(form);
                });
            }

            form.querySelectorAll('[data-auto-company-from-employee]').forEach(function (employee) {
                employee.addEventListener('change', function () {
                    syncCompanyFromEmployee(form);
                });
            });

            if (contractType && endDate) {
                contractType.addEventListener('change', function () {
                    endDate.required = contractType.value !== 'cdi';
                });
                endDate.required = contractType.value !== 'cdi';
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

    function dataTableLanguage() {
        return {
            search: 'Rechercher',
            lengthMenu: 'Afficher _MENU_ lignes',
            info: '_START_ a _END_ sur _TOTAL_ contrats',
            infoEmpty: 'Aucun contrat',
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

    function initTable() {
        var table = document.getElementById('contracts-table');
        if (!table || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) {
            return;
        }

        var dataTable = window.jQuery(table).DataTable({
            ajax: table.getAttribute('data-ajax-url'),
            pageLength: 10,
            processing: true,
            order: [[4, 'asc']],
            columns: [
                {
                    data: null,
                    render: function (row) {
                        var alert = row.expires_soon ? '<span class="badge bg-orange-lt ms-2">Alerte</span>' : '';
                        return '<a class="company-name" href="' + contractUrl('/contracts/show?id=' + row.id) + '">' + escapeHtml(row.contract_number) + '</a>' + alert
                            + '<span class="d-block text-secondary">' + escapeHtml(row.position || '-') + '</span>';
                    }
                },
                {
                    data: null,
                    render: function (row) {
                        return '<strong>' + escapeHtml(row.employee) + '</strong>'
                            + '<span class="d-block text-secondary">' + escapeHtml(row.employee_number || '-') + '</span>';
                    }
                },
                { data: 'company' },
                { data: 'contract_type_label' },
                {
                    data: null,
                    render: function (row) {
                        return escapeHtml(row.start_date) + ' - ' + escapeHtml(row.end_date)
                            + '<span class="d-block text-secondary">Essai: ' + escapeHtml(row.probation_ends_at) + '</span>';
                    }
                },
                { data: 'base_salary' },
                {
                    data: null,
                    render: function (row) {
                        return '<span class="badge bg-' + escapeHtml(row.status_tone) + '-lt">' + escapeHtml(row.status_label) + '</span>';
                    }
                },
                { data: 'actions', orderable: false, searchable: false }
            ],
            language: dataTableLanguage(),
            dom: 'rt<"company-table-footer"ip>'
        });

        var search = document.querySelector('[data-contract-search]');
        if (search) {
            search.addEventListener('input', function () {
                dataTable.search(search.value).draw();
            });
        }
    }

    function contractUrl(path) {
        var base = window.location.pathname.replace(/\/contracts.*/, '');
        return window.location.origin + base + path;
    }

    function bindPdfActions() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-contract-pdf]');
            if (!button) {
                return;
            }

            var data = new FormData();
            data.append('id', button.getAttribute('data-contract-pdf'));
            button.disabled = true;

            request(contractUrl('/contracts/pdf/generate'), data)
                .then(function (payload) {
                    if (!payload.httpOk || !payload.success) {
                        alert(payload.message || 'Generation PDF impossible.');
                        return;
                    }

                    if (payload.pdf_url) {
                        var opened = window.open(payload.pdf_url, '_blank');
                        if (!opened) {
                            window.location.href = payload.pdf_url;
                            return;
                        }

                        if (payload.reload) {
                            window.setTimeout(function () {
                                window.location.reload();
                            }, 700);
                        }
                        return;
                    }

                    if (payload.reload) {
                        window.location.reload();
                    }
                })
                .catch(function () {
                    alert('Erreur reseau. Reessayez dans un instant.');
                })
                .finally(function () {
                    button.disabled = false;
                });
        });
    }

    function bindExpireAction() {
        var button = document.querySelector('[data-contract-expire]');
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            var data = new FormData();
            button.disabled = true;

            request(contractUrl('/contracts/expire'), data)
                .then(function (payload) {
                    if (!payload.httpOk || !payload.success) {
                        alert(payload.message || 'Expiration impossible.');
                        return;
                    }

                    window.location.reload();
                })
                .catch(function () {
                    alert('Erreur reseau. Reessayez dans un instant.');
                })
                .finally(function () {
                    button.disabled = false;
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindForms();
        initTable();
        bindPdfActions();
        bindExpireAction();
    });
})();
