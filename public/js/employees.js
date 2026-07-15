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

        var invalid = form.querySelector('.is-invalid');
        if (invalid) {
            var optionalSection = invalid.closest('details');
            if (optionalSection) {
                optionalSection.open = true;
            }
            invalid.focus({ preventScroll: true });
        }
    }

    function optionVisible(option, filters) {
        if (!option.value) {
            return true;
        }

        return Object.keys(filters).every(function (attribute) {
            var required = filters[attribute];
            var actual = option.getAttribute('data-' + attribute) || '';
            return !required || !actual || actual === required;
        });
    }

    function filterSelect(select, filters) {
        if (!select) {
            return;
        }

        var selectedStillAvailable = false;
        Array.prototype.forEach.call(select.options, function (option) {
            var visible = optionVisible(option, filters);
            option.hidden = !visible;
            option.disabled = !visible;
            if (option.selected && visible) {
                selectedStillAvailable = true;
            }
        });

        if (!selectedStillAvailable) {
            select.value = '';
        }
    }

    function bindOrganization(form) {
        var company = form.querySelector('[data-company-select]');
        var branch = form.querySelector('[data-dependent-select="branch"]');
        var department = form.querySelector('[data-dependent-select="department"]');
        var position = form.querySelector('[data-dependent-select="position"]');
        var manager = form.querySelector('[data-dependent-select="manager"]');

        function refresh() {
            var companyId = company ? company.value : '';

            filterSelect(branch, { 'company-id': companyId });
            var branchId = branch ? branch.value : '';
            filterSelect(department, { 'company-id': companyId, 'branch-id': branchId });
            var departmentId = department ? department.value : '';
            filterSelect(position, { 'company-id': companyId, 'department-id': departmentId });
            filterSelect(manager, { 'company-id': companyId, 'department-id': departmentId });
            updateSummary(form);
        }

        [company, branch, department].forEach(function (select) {
            if (select) {
                select.addEventListener('change', refresh);
            }
        });
        if (position) {
            position.addEventListener('change', function () { updateSummary(form); });
        }
        refresh();
    }

    function bindContract(form) {
        var type = form.querySelector('[data-contract-type]');
        var end = form.querySelector('[data-contract-end]');
        var endGroup = form.querySelector('[data-contract-end-group]');

        function refresh() {
            var requiresEnd = type && ['cdd', 'internship', 'temporary'].indexOf(type.value) !== -1;
            if (endGroup) {
                endGroup.classList.toggle('d-none', !requiresEnd);
            }
            if (end) {
                end.required = Boolean(requiresEnd);
            }
            updateSummary(form);
        }

        if (type) {
            type.addEventListener('change', refresh);
        }
        form.querySelectorAll('[data-contract-summary-source]').forEach(function (field) {
            field.addEventListener('input', function () { updateSummary(form); });
            field.addEventListener('change', function () { updateSummary(form); });
        });
        refresh();
    }

    function selectedText(select) {
        if (!select || select.selectedIndex < 0) {
            return '';
        }
        return select.options[select.selectedIndex].textContent.trim();
    }

    function updateSummary(form) {
        var name = ['last_name', 'middle_name', 'first_name'].map(function (field) {
            var input = form.elements[field];
            return input ? input.value.trim() : '';
        }).filter(Boolean).join(' ');
        var nameTarget = form.querySelector('[data-summary-name]');
        if (nameTarget) {
            nameTarget.textContent = name || 'Nouvel agent';
        }

        var position = form.elements.position_id;
        var department = form.elements.department_id;
        var assignmentTarget = form.querySelector('[data-summary-assignment]');
        if (assignmentTarget) {
            assignmentTarget.textContent = selectedText(position) || selectedText(department) || 'Completez son affectation';
        }

        var type = form.elements.contract_type;
        var salary = parseFloat(form.elements.base_salary ? form.elements.base_salary.value : 0) || 0;
        var currency = form.elements.currency ? form.elements.currency.value : 'USD';
        var date = form.elements.hire_date ? form.elements.hire_date.value : '';
        var contractTarget = form.querySelector('[data-contract-summary]');
        if (contractTarget) {
            contractTarget.textContent = (selectedText(type) || 'Contrat') + (date ? ' — a partir du ' + date.split('-').reverse().join('/') : '') + ' — ' + salary.toLocaleString('fr-FR', { maximumFractionDigits: 2 }) + ' ' + currency + '/mois';
        }

        var initials = form.querySelector('[data-photo-initials]');
        if (initials) {
            var first = form.elements.first_name ? form.elements.first_name.value.trim().charAt(0) : '';
            var last = form.elements.last_name ? form.elements.last_name.value.trim().charAt(0) : '';
            initials.textContent = (first + last).toUpperCase() || 'A';
        }
    }

    function bindPhoto(form) {
        var input = form.querySelector('[data-photo-input]');
        var preview = form.querySelector('[data-photo-preview]');
        var initials = form.querySelector('[data-photo-initials]');
        if (!input || !preview) {
            return;
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) {
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                input.value = '';
                window.ElliotUI.toast('La photo ne doit pas depasser 10 Mo.', 'danger');
                return;
            }
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('d-none');
            if (initials) {
                initials.classList.add('d-none');
            }
        });
    }

    function bindSectionNavigation(form) {
        var links = form.querySelectorAll('[data-section-link]');
        var sections = form.querySelectorAll('[data-form-section]');

        links.forEach(function (link) {
            link.addEventListener('click', function () {
                var section = document.getElementById(link.getAttribute('data-section-link'));
                if (!section) {
                    return;
                }
                if (section.tagName === 'DETAILS') {
                    section.open = true;
                }
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    links.forEach(function (link) {
                        link.classList.toggle('is-active', link.getAttribute('data-section-link') === entry.target.id);
                    });
                });
            }, { rootMargin: '-20% 0px -65% 0px', threshold: 0 });
            sections.forEach(function (section) { observer.observe(section); });
        }
    }

    function bindFormExperience(form) {
        bindOrganization(form);
        bindContract(form);
        bindPhoto(form);
        bindSectionNavigation(form);

        form.querySelectorAll('[data-summary-source]').forEach(function (field) {
            field.addEventListener('input', function () { updateSummary(form); });
        });
        form.querySelectorAll('.employee-optional-section').forEach(function (section) {
            section.addEventListener('toggle', function () {
                var label = section.querySelector('.optional-toggle');
                if (label) {
                    label.textContent = section.open ? 'Masquer' : 'Afficher';
                }
            });
        });

        var dirty = false;
        form.addEventListener('input', function () { dirty = true; });
        form.addEventListener('change', function () { dirty = true; });
        form.addEventListener('submit', function () { dirty = false; });
        window.addEventListener('beforeunload', function (event) {
            if (!dirty) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        });

        form.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                form.requestSubmit();
            }
        });
        updateSummary(form);
    }

    function bindForms() {
        document.querySelectorAll('[data-employee-form]').forEach(function (form) {
            bindFormExperience(form);
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
                    .catch(function () {
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
