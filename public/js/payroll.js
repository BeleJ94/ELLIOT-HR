(function () {
    'use strict';

    function csrfToken() {
        var input = document.querySelector('input[name="_csrf_token"]');
        return window.ELLIOT_CSRF || (input ? input.value : '');
    }

    function request(url, data) {
        if (!data.has('_csrf_token')) {
            data.append('_csrf_token', csrfToken());
        }
        return fetch(url, {
            method: 'POST',
            body: data,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().catch(function () {
                return { success: false, message: 'Réponse serveur invalide.' };
            }).then(function (payload) {
                payload.httpOk = response.ok;
                return payload;
            });
        });
    }

    function toast(message, tone) {
        if (window.ElliotUI && typeof window.ElliotUI.toast === 'function') {
            window.ElliotUI.toast(message, tone || 'success');
        }
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
        document.querySelectorAll('[data-payroll-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var errorBox = form.querySelector('[data-form-error]') || document.querySelector('[data-payroll-error]');
                var submit = form.querySelector('[data-submit-label]');
                var original = submit ? submit.innerHTML : '';
                if (errorBox) {
                    errorBox.classList.add('d-none');
                    errorBox.textContent = '';
                }
                if (submit) {
                    submit.disabled = true;
                    submit.innerHTML = '<span class="attendance-button-loader"></span><span>Enregistrement…</span>';
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
                            toast(payload.message || 'Paramètre enregistré.', 'success');
                            window.setTimeout(function () { window.location.reload(); }, 450);
                        }
                    })
                    .catch(function () {
                        showError(errorBox, 'Erreur reseau. Reessayez dans un instant.');
                    })
                    .finally(function () {
                        if (submit) {
                            submit.disabled = false;
                            submit.innerHTML = original;
                        }
                    });
            });
        });
    }

    function bindSettingsWorkspace() {
        var workspace = document.querySelector('[data-payroll-settings]');
        if (!workspace) {
            return;
        }

        var tabs = Array.prototype.slice.call(workspace.querySelectorAll('[data-payroll-settings-tab]'));
        var panels = Array.prototype.slice.call(workspace.querySelectorAll('[data-payroll-settings-panel]'));

        function activate(name, updateHash) {
            tabs.forEach(function (tab) {
                var active = tab.getAttribute('data-payroll-settings-tab') === name;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                var active = panel.getAttribute('data-payroll-settings-panel') === name;
                panel.classList.toggle('is-active', active);
                panel.hidden = !active;
            });
            if (updateHash && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + name);
            }
        }

        tabs.forEach(function (tab) {
            tab.setAttribute('role', 'tab');
            tab.addEventListener('click', function () {
                activate(tab.getAttribute('data-payroll-settings-tab'), true);
            });
        });

        var requested = window.location.hash.replace('#', '');
        if (['items', 'taxes', 'contributions'].indexOf(requested) !== -1) {
            activate(requested, false);
        }

        workspace.querySelectorAll('[data-settings-table]').forEach(function (table) {
            bindSettingsTable(workspace, table);
        });

        document.addEventListener('keydown', function (event) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                var activePanel = workspace.querySelector('[data-payroll-settings-panel].is-active');
                var search = activePanel && activePanel.querySelector('[data-settings-search]');
                if (search) {
                    event.preventDefault();
                    search.focus();
                }
            }
        });

        var companySelects = Array.prototype.slice.call(workspace.querySelectorAll('[data-payroll-company]'));
        companySelects.forEach(function (select) {
            select.addEventListener('change', function () {
                companySelects.forEach(function (other) {
                    if (other !== select) other.value = select.value;
                });
            });
        });

        workspace.querySelectorAll('[data-payroll-calculation]').forEach(function (radio) {
            radio.addEventListener('change', syncCalculationFields);
        });
        syncCalculationFields();

        workspace.querySelectorAll('input.text-uppercase').forEach(function (input) {
            input.addEventListener('input', function () {
                var cursor = input.selectionStart;
                input.value = input.value.toUpperCase().replace(/\s+/g, '_');
                if (cursor !== null) input.setSelectionRange(cursor, cursor);
            });
        });
        bindSettingCrud(workspace);
    }

    function bindSettingCrud(workspace) {
        var modal = document.querySelector('[data-payroll-setting-modal]');
        var urls = window.ELLIOT_PAYROLL_SETTING_URLS || {};
        if (!modal || !urls.detail || !urls.update || !urls.delete) return;

        var form = modal.querySelector('[data-payroll-setting-form]');
        var details = modal.querySelector('[data-payroll-setting-details]');
        var loader = modal.querySelector('[data-payroll-setting-loader]');
        var content = modal.querySelector('[data-payroll-setting-content]');
        var footer = modal.querySelector('[data-payroll-setting-footer]');
        var editButton = modal.querySelector('[data-payroll-setting-switch-edit]');
        var saveButton = modal.querySelector('[data-payroll-setting-save]');
        var current = null;

        function modalState(open) {
            modal.classList.toggle('is-open', open);
            modal.setAttribute('aria-hidden', open ? 'false' : 'true');
            document.body.classList.toggle('modal-open', open);
        }

        function setLoading() {
            loader.hidden = false;
            content.hidden = true;
            footer.hidden = true;
            modal.querySelector('[data-payroll-setting-error]').classList.add('d-none');
        }

        function setField(section, name, value) {
            var field = section.querySelector('[name="' + name + '"]');
            if (!field) return;
            if (field.type === 'checkbox') field.checked = Number(value) === 1;
            else field.value = value === null || value === undefined ? '' : value;
        }

        function activateSection(type) {
            form.querySelectorAll('[data-payroll-edit-fields]').forEach(function (section) {
                var active = section.getAttribute('data-payroll-edit-fields') === type;
                section.hidden = !active;
                section.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.disabled = !active;
                });
            });
            return form.querySelector('[data-payroll-edit-fields="' + type + '"]');
        }

        function formatNumber(value, suffix) {
            if (value === null || value === '' || value === undefined) return 'Non défini';
            return Number(value).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 4}) + (suffix || '');
        }

        function detailRows(type, record) {
            var common = [
                ['Entreprise', record.company_name || '—'],
                ['Nom', record.name || '—']
            ];
            if (type === 'item') {
                var typeLabels = {earning: 'Gain', deduction: 'Retenue', tax: 'Taxe', contribution: 'Cotisation'};
                return common.concat([
                    ['Code', record.code], ['Nature', typeLabels[record.type] || record.type],
                    ['Mode de calcul', record.calculation_type === 'percentage' ? 'Pourcentage' : 'Montant fixe'],
                    ['Montant par défaut', formatNumber(record.default_amount, ' USD')],
                    ['Taux par défaut', formatNumber(record.default_rate, ' %')],
                    ['Fiscalité', Number(record.taxable) === 1 ? 'Soumise à l’IPR' : 'Non taxable']
                ]);
            }
            if (type === 'tax') {
                return common.concat([
                    ['Code fiscal', record.tax_code],
                    ['Borne minimale', formatNumber(record.threshold_min, ' USD')],
                    ['Borne maximale', record.threshold_max === null ? 'Sans plafond' : formatNumber(record.threshold_max, ' USD')],
                    ['Taux', formatNumber(record.rate, ' %')],
                    ['État', Number(record.is_active) === 1 ? 'Active' : 'Inactive']
                ]);
            }
            return common.concat([
                ['Code', record.contribution_code],
                ['Part salariale', formatNumber(record.employee_rate, ' %')],
                ['Part patronale', formatNumber(record.employer_rate, ' %')],
                ['Plafond', record.ceiling_amount === null ? 'Sans plafond' : formatNumber(record.ceiling_amount, ' USD')],
                ['État', Number(record.is_active) === 1 ? 'Active' : 'Inactive']
            ]);
        }

        function renderDetails(type, record) {
            details.innerHTML = '';
            detailRows(type, record).forEach(function (entry) {
                var item = document.createElement('article');
                var label = document.createElement('span');
                var value = document.createElement('strong');
                label.textContent = entry[0];
                value.textContent = entry[1] === null || entry[1] === undefined ? '—' : entry[1];
                item.appendChild(label);
                item.appendChild(value);
                details.appendChild(item);
            });
        }

        function populateForm(type, record) {
            form.reset();
            form.querySelector('[name="id"]').value = record.id;
            form.querySelector('[name="setting_type"]').value = type;
            var company = form.querySelector('[name="company_id"]');
            if (company) company.value = record.company_id;
            var section = activateSection(type);
            Object.keys(record).forEach(function (name) { setField(section, name, record[name]); });
        }

        function showMode(mode) {
            var editing = mode === 'edit';
            details.hidden = editing;
            form.hidden = !editing;
            editButton.hidden = editing;
            saveButton.hidden = !editing;
            modal.querySelector('[data-payroll-setting-title]').textContent = editing ? 'Modifier le paramètre' : 'Détails du paramètre';
            modal.querySelector('[data-payroll-setting-subtitle]').textContent = editing
                ? 'Ajustez les valeurs puis enregistrez vos modifications.'
                : 'Consultez les informations actuellement utilisées par la paie.';
            if (editing && current) populateForm(current.type, current.record);
        }

        function openSetting(type, id, mode) {
            current = null;
            modalState(true);
            setLoading();
            fetch(urls.detail + '?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id), {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload.success) throw new Error(payload.message || 'Chargement impossible.');
                    return payload;
                });
            }).then(function (payload) {
                current = {type: type, record: payload.record};
                renderDetails(type, payload.record);
                loader.hidden = true;
                content.hidden = false;
                footer.hidden = false;
                showMode(mode);
            }).catch(function (error) {
                loader.hidden = true;
                content.hidden = false;
                var box = modal.querySelector('[data-payroll-setting-error]');
                box.textContent = error.message || 'Chargement impossible.';
                box.classList.remove('d-none');
            });
        }

        workspace.addEventListener('click', function (event) {
            var view = event.target.closest('[data-payroll-setting-view]');
            var edit = event.target.closest('[data-payroll-setting-edit]');
            var remove = event.target.closest('[data-payroll-setting-delete]');
            if (view || edit) {
                var trigger = view || edit;
                openSetting(trigger.getAttribute('data-type'), trigger.getAttribute('data-id'), edit ? 'edit' : 'view');
                return;
            }
            if (remove) {
                var name = remove.getAttribute('data-name') || 'ce paramètre';
                window.ElliotUI.confirm('Supprimer « ' + name + ' » ? Cette action le retirera des prochains calculs, sans altérer les paies historiques.', {
                    confirmLabel: 'Supprimer',
                    danger: true
                }).then(function (confirmed) {
                    if (!confirmed) return;
                    var data = new FormData();
                    data.append('setting_type', remove.getAttribute('data-type'));
                    data.append('id', remove.getAttribute('data-id'));
                    remove.disabled = true;
                    request(urls.delete, data).then(function (payload) {
                        if (!payload.httpOk || !payload.success) {
                            toast(payload.message || 'Suppression impossible.', 'danger');
                            return;
                        }
                        toast(payload.message || 'Paramètre supprimé.', 'success');
                        window.setTimeout(function () { window.location.reload(); }, 400);
                    }).finally(function () { remove.disabled = false; });
                });
            }
        });

        editButton.addEventListener('click', function () { showMode('edit'); });
        saveButton.addEventListener('click', function () {
            if (!form.reportValidity()) return;
            var error = modal.querySelector('[data-payroll-setting-error]');
            error.classList.add('d-none');
            var original = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = '<span class="attendance-button-loader"></span><span>Enregistrement…</span>';
            request(urls.update, new FormData(form)).then(function (payload) {
                if (!payload.httpOk || !payload.success) {
                    error.textContent = payload.message || 'Mise à jour impossible.';
                    error.classList.remove('d-none');
                    return;
                }
                toast(payload.message || 'Paramètre mis à jour.', 'success');
                window.setTimeout(function () { window.location.reload(); }, 400);
            }).finally(function () {
                saveButton.disabled = false;
                saveButton.innerHTML = original;
            });
        });

        modal.querySelectorAll('[data-payroll-setting-close]').forEach(function (button) {
            button.addEventListener('click', function () { modalState(false); });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) modalState(false);
        });
    }

    function bindSettingsTable(workspace, table) {
        var name = table.getAttribute('data-settings-table');
        var search = workspace.querySelector('[data-settings-search="' + name + '"]');
        var sizeSelect = workspace.querySelector('[data-settings-page-size="' + name + '"]');
        var info = workspace.querySelector('[data-settings-info="' + name + '"]');
        var pagination = workspace.querySelector('[data-settings-pagination="' + name + '"]');
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var state = {page: 1, pageSize: Number(sizeSelect ? sizeSelect.value : 10), query: ''};

        var empty = document.createElement('tr');
        empty.className = 'payroll-settings-row-empty';
        empty.hidden = true;
        empty.innerHTML = '<td colspan="' + Math.max(1, table.querySelectorAll('thead th').length) + '"><strong>Aucun résultat</strong><span>Modifiez votre recherche pour afficher d’autres paramètres.</span></td>';
        table.querySelector('tbody').appendChild(empty);

        function render() {
            var filtered = rows.filter(function (row) {
                return state.query === '' || row.textContent.toLocaleLowerCase().indexOf(state.query) !== -1;
            });
            var pages = Math.max(1, Math.ceil(filtered.length / state.pageSize));
            state.page = Math.min(state.page, pages);
            var start = (state.page - 1) * state.pageSize;
            var end = Math.min(start + state.pageSize, filtered.length);

            rows.forEach(function (row) { row.hidden = true; });
            filtered.slice(start, end).forEach(function (row) { row.hidden = false; });
            empty.hidden = filtered.length !== 0;

            if (info) {
                info.innerHTML = filtered.length === 0
                    ? '<strong>0</strong> résultat'
                    : 'Affichage de <strong>' + (start + 1) + '</strong> à <strong>' + end + '</strong> sur <strong>' + filtered.length + '</strong>';
            }
            renderPagination(pagination, state.page, pages, function (page) {
                state.page = page;
                render();
                table.closest('.payroll-settings-reference').scrollIntoView({behavior: 'smooth', block: 'start'});
            });
        }

        if (search) {
            search.addEventListener('input', function () {
                state.query = search.value.trim().toLocaleLowerCase();
                state.page = 1;
                render();
            });
        }
        if (sizeSelect) {
            sizeSelect.addEventListener('change', function () {
                state.pageSize = Number(sizeSelect.value) || 10;
                state.page = 1;
                render();
            });
        }
        render();
    }

    function renderPagination(container, current, total, onChange) {
        if (!container) return;
        container.innerHTML = '';
        if (total <= 1) {
            container.hidden = true;
            return;
        }
        container.hidden = false;

        function button(label, page, disabled, active, ariaLabel) {
            var element = document.createElement('button');
            element.type = 'button';
            element.className = 'payroll-page-button' + (active ? ' is-active' : '');
            element.textContent = label;
            element.disabled = disabled;
            element.setAttribute('aria-label', ariaLabel || ('Page ' + page));
            if (active) element.setAttribute('aria-current', 'page');
            element.addEventListener('click', function () { onChange(page); });
            container.appendChild(element);
        }

        button('‹', current - 1, current === 1, false, 'Page précédente');
        var from = Math.max(1, current - 2);
        var to = Math.min(total, from + 4);
        from = Math.max(1, to - 4);
        for (var page = from; page <= to; page++) {
            button(String(page), page, false, page === current);
        }
        button('›', current + 1, current === total, false, 'Page suivante');
    }

    function syncCalculationFields() {
        var checked = document.querySelector('[data-payroll-calculation]:checked');
        if (!checked) return;
        var form = checked.closest('form');
        var amount = form.querySelector('[data-payroll-amount-field]');
        var rate = form.querySelector('[data-payroll-rate-field]');
        var percentage = checked.value === 'percentage';
        if (amount) {
            amount.hidden = percentage;
            amount.querySelector('input').disabled = percentage;
        }
        if (rate) {
            rate.hidden = !percentage;
            rate.querySelector('input').disabled = !percentage;
        }
    }

    function bindCalculate() {
        document.querySelectorAll('[data-payroll-calculate]').forEach(function (button) {
            button.addEventListener('click', function () {
                var data = new FormData();
                data.append('id', button.getAttribute('data-period-id') || '');
                button.disabled = true;
                button.textContent = 'Calcul...';

                request(button.getAttribute('data-payroll-calculate'), data)
                    .then(function (payload) {
                        if (!payload.httpOk || !payload.success) {
                            showError(document.querySelector('[data-payroll-error]'), payload.message || 'Calcul impossible.');
                            return;
                        }
                        if (payload.reload) {
                            window.location.reload();
                        }
                    })
                    .catch(function () {
                        showError(document.querySelector('[data-payroll-error]'), 'Erreur reseau. Reessayez dans un instant.');
                    })
                    .finally(function () {
                        button.disabled = false;
                        button.textContent = 'Lancer le calcul';
                    });
            });
        });
    }

    function bindClose() {
        document.querySelectorAll('[data-payroll-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.ElliotUI.confirm('Clôturer cette période de paie ? Vérifiez le journal et les anomalies avant de continuer.', {
                    confirmLabel: 'Clôturer la période',
                    danger: false
                }).then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    var data = new FormData();
                    data.append('id', button.getAttribute('data-period-id') || '');
                    button.disabled = true;
                    button.textContent = 'Clôture...';

                    request(button.getAttribute('data-payroll-close'), data)
                        .then(function (payload) {
                            if (!payload.httpOk || !payload.success) {
                                showError(document.querySelector('[data-payroll-error]'), payload.message || 'Clôture impossible.');
                                return;
                            }
                            if (payload.reload) {
                                window.location.reload();
                            }
                        })
                        .catch(function () {
                            showError(document.querySelector('[data-payroll-error]'), 'Erreur réseau. Réessayez dans un instant.');
                        })
                        .finally(function () {
                            button.disabled = false;
                            button.textContent = 'Clôturer';
                        });
                });
            });
        });
    }

    function bindSimulation() {
        var workspace = document.querySelector('[data-payroll-simulation]');
        if (!workspace) return;

        var form = workspace.querySelector('[data-payroll-simulation-form]');
        var resultPanel = workspace.querySelector('[data-payroll-simulation-result]');
        var emptyPanel = workspace.querySelector('[data-payroll-simulation-empty]');
        var kpis = workspace.querySelector('[data-simulation-kpis]');
        var lines = workspace.querySelector('[data-simulation-lines]');
        var precision = workspace.querySelector('[data-simulation-precision]');
        var pdfForm = workspace.querySelector('[data-simulation-pdf-form]');
        if (!form || !resultPanel || !kpis || !lines) return;

        function money(value) {
            return Number(value || 0).toLocaleString('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function rate(value) {
            var number = Number(value || 0);
            return number > 0 ? number.toLocaleString('fr-FR', {maximumFractionDigits: 4}) + ' %' : '-';
        }

        function typeLabel(type) {
            return {
                earning: 'Gain',
                deduction: 'Retenue',
                contribution: 'Cotisation',
                employer_contribution: 'Charge patronale',
                tax: 'Taxe'
            }[type] || type || '-';
        }

        function typeTone(type) {
            return {
                earning: 'green',
                deduction: 'orange',
                contribution: 'purple',
                employer_contribution: 'blue',
                tax: 'blue'
            }[type] || 'blue';
        }

        function kpi(label, value, hint, strong) {
            var item = document.createElement('article');
            item.className = strong ? 'is-primary' : '';
            item.innerHTML = '<span></span><strong></strong><small></small>';
            item.querySelector('span').textContent = label;
            item.querySelector('strong').textContent = money(value);
            item.querySelector('small').textContent = hint || 'USD';
            return item;
        }

        function render(payload) {
            var result = payload.result || {};
            if (pdfForm) {
                var currentData = new FormData(form);
                ['company_id', 'target_net', 'taxable_earnings', 'non_taxable_earnings', 'deductions'].forEach(function (name) {
                    var field = pdfForm.querySelector('[name="' + name + '"]');
                    if (field) field.value = currentData.get(name) || '';
                });
            }
            kpis.innerHTML = '';
            kpis.appendChild(kpi('Salaire de base', result.base_salary, 'Montant a encoder', true));
            kpis.appendChild(kpi('Salaire brut', result.gross_salary, 'Avant retenues'));
            kpis.appendChild(kpi('Base imposable', result.taxable_salary, 'Soumise a l’IPR'));
            kpis.appendChild(kpi('Retenues salariales', result.total_deductions, 'IPR et cotisations'));
            kpis.appendChild(kpi('Net calcule', result.net_salary, 'Cible: ' + money(result.target_net), true));
            kpis.appendChild(kpi('Cout employeur', result.total_employer_cost, 'Brut + charges patronales'));

            if (precision) {
                var diff = Number(result.difference || 0);
                precision.classList.toggle('is-warning', Math.abs(diff) > 0.02);
                precision.querySelector('strong').textContent = Math.abs(diff) <= 0.02 ? 'Net atteint' : 'Approximation';
                precision.querySelector('small').textContent = 'Ecart: ' + money(diff) + ' USD';
            }

            lines.innerHTML = '';
            (result.lines || []).forEach(function (line) {
                var row = document.createElement('tr');
                var badgeTone = typeTone(line.type);
                row.innerHTML = '<td><strong></strong><small class="d-block text-secondary"></small></td><td><span class="payroll-type-badge"></span></td><td class="text-end"></td><td class="text-end"></td><td class="text-end"><strong class="payroll-value"></strong></td>';
                row.querySelector('td strong').textContent = line.name || '-';
                row.querySelector('td small').textContent = (line.code || '-') + (Number(line.taxable) === 1 ? ' · taxable' : '');
                row.querySelector('.payroll-type-badge').classList.add('is-' + badgeTone);
                row.querySelector('.payroll-type-badge').textContent = typeLabel(line.type);
                row.children[2].textContent = money(line.base_amount);
                row.children[3].textContent = rate(line.rate);
                row.querySelector('.payroll-value').textContent = money(line.amount);
                lines.appendChild(row);
            });

            resultPanel.hidden = false;
            if (emptyPanel) emptyPanel.hidden = true;
            resultPanel.scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var errorBox = form.querySelector('[data-form-error]');
            var submit = form.querySelector('[data-submit-label]');
            var original = submit ? submit.innerHTML : '';
            if (errorBox) {
                errorBox.textContent = '';
                errorBox.classList.add('d-none');
            }
            if (submit) {
                submit.disabled = true;
                submit.innerHTML = '<span class="attendance-button-loader"></span><span>Calcul…</span>';
            }

            request(form.action, new FormData(form)).then(function (payload) {
                if (!payload.httpOk || !payload.success) {
                    showError(errorBox, payload.message || 'Simulation impossible.');
                    return;
                }
                render(payload);
            }).catch(function () {
                showError(errorBox, 'Erreur reseau. Reessayez dans un instant.');
            }).finally(function () {
                if (submit) {
                    submit.disabled = false;
                    submit.innerHTML = original;
                }
            });
        });
    }

    function bindTables() {
        ['payroll-periods-table', 'payroll-journal-table'].forEach(function (id) {
            var table = document.getElementById(id);
            if (!table || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) {
                return;
            }
            window.jQuery(table).DataTable({
                pageLength: 15,
                order: [[0, 'desc']],
                language: {
                    search: 'Rechercher',
                    lengthMenu: 'Afficher _MENU_ lignes',
                    info: '_START_ a _END_ sur _TOTAL_ lignes',
                    infoEmpty: 'Aucune ligne',
                    zeroRecords: 'Aucun resultat',
                    paginate: { first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Precedent' }
                },
                dom: 'rt<"company-table-footer"ip>'
            });

            var search = document.querySelector('[data-payroll-search]');
            if (search && id === 'payroll-journal-table') {
                var dataTable = window.jQuery(table).DataTable();
                search.addEventListener('input', function () {
                    dataTable.search(search.value).draw();
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindSettingsWorkspace();
        bindForms();
        bindSimulation();
        bindCalculate();
        bindClose();
        bindTables();
    });
})();
