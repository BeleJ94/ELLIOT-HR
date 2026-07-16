(function () {
    'use strict';

    var config = window.ELLIOT_ATTENDANCE || null;
    var pendingAdministrativeAction = null;

    function csrfToken() {
        var input = document.querySelector('input[name="_csrf_token"]');
        return window.ELLIOT_CSRF || (input ? input.value : '');
    }

    function request(url, values) {
        var data = values instanceof FormData ? values : new FormData();
        if (!(values instanceof FormData)) {
            Object.keys(values || {}).forEach(function (key) {
                data.append(key, values[key]);
            });
        }
        if (!data.has('_csrf_token')) {
            data.append('_csrf_token', csrfToken());
        }

        return fetch(url, {
            method: 'POST',
            body: data,
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().catch(function () {
                return {success: false, message: 'Réponse serveur invalide.'};
            }).then(function (payload) {
                payload.httpOk = response.ok;
                return payload;
            });
        });
    }

    function notify(message, tone) {
        if (window.ElliotUI && typeof window.ElliotUI.toast === 'function') {
            window.ElliotUI.toast(message, tone || 'success');
            return;
        }
        if (tone === 'danger') {
            showError(message);
            return;
        }
        alert(message);
    }

    function showError(message) {
        var box = document.querySelector('[data-attendance-error]');
        if (!box) {
            alert(message);
            return;
        }
        box.textContent = message;
        box.classList.remove('d-none');
        box.scrollIntoView({behavior: 'smooth', block: 'center'});
    }

    function clearError() {
        var box = document.querySelector('[data-attendance-error]');
        if (box) {
            box.classList.add('d-none');
            box.textContent = '';
        }
    }

    function setBusy(button, busy, label) {
        if (!button) return;
        if (busy) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="attendance-button-loader"></span><span>' + (label || 'Traitement…') + '</span>';
        } else {
            button.disabled = false;
            if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
        }
    }

    function reloadAfter(payload) {
        if (payload.reload) {
            window.setTimeout(function () { window.location.reload(); }, 450);
        }
    }

    function rowValues(row) {
        return {
            employee_id: Number(row.dataset.employeeId || 0),
            status: row.querySelector('[data-attendance-status]').value,
            check_in: row.querySelector('[data-attendance-in]').value,
            check_out: row.querySelector('[data-attendance-out]').value,
            notes: row.querySelector('[data-attendance-notes]').value.trim()
        };
    }

    function syncRow(row) {
        var status = row.querySelector('[data-attendance-status]').value;
        var checkIn = row.querySelector('[data-attendance-in]');
        var checkOut = row.querySelector('[data-attendance-out]');
        var withoutTimes = ['', 'absent', 'leave', 'holiday'].indexOf(status) !== -1;

        checkIn.disabled = withoutTimes;
        checkOut.disabled = withoutTimes;
        if (withoutTimes) {
            checkIn.value = '';
            checkOut.value = '';
        }
        row.classList.toggle('is-unencoded', status === '');
        row.classList.toggle('is-absence', status === 'absent');
        row.classList.toggle('is-leave', status === 'leave' || status === 'holiday');
    }

    function bindWorkspace() {
        if (!config) return;

        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-attendance-entry]'));
        var page = 1;
        var pageSize = 20;
        var searchTerm = '';

        function filteredRows() {
            return rows.filter(function (row) {
                return searchTerm === '' || row.textContent.toLocaleLowerCase().indexOf(searchTerm) !== -1;
            });
        }

        function renderRows() {
            var matches = filteredRows();
            var totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
            page = Math.min(Math.max(1, page), totalPages);
            var start = (page - 1) * pageSize;
            var visibleRows = matches.slice(start, start + pageSize);

            rows.forEach(function (row) { row.hidden = true; });
            visibleRows.forEach(function (row) { row.hidden = false; });

            var count = document.querySelector('[data-attendance-result-count]');
            var info = document.querySelector('[data-attendance-page-info]');
            var empty = document.querySelector('[data-attendance-empty]');
            var pagination = document.querySelector('[data-attendance-pagination]');
            var previous = document.querySelector('[data-attendance-previous]');
            var next = document.querySelector('[data-attendance-next]');

            if (count) count.textContent = matches.length + ' agent(s)';
            if (info) info.textContent = matches.length ? 'Page ' + page + ' sur ' + totalPages + ' · ' + (start + 1) + '–' + Math.min(start + pageSize, matches.length) + ' sur ' + matches.length : 'Aucun résultat';
            if (empty) empty.hidden = matches.length !== 0;
            if (pagination) pagination.hidden = matches.length === 0;
            if (previous) previous.disabled = page <= 1;
            if (next) next.disabled = page >= totalPages;
        }

        rows.forEach(function (row) {
            var status = row.querySelector('[data-attendance-status]');
            if (!status || status.disabled) return;
            syncRow(row);
            status.addEventListener('change', function () { syncRow(row); });
        });

        var search = document.querySelector('[data-attendance-search]');
        if (search) {
            search.addEventListener('input', function () {
                searchTerm = search.value.trim().toLocaleLowerCase();
                page = 1;
                renderRows();
            });
        }

        var pageSizeSelect = document.querySelector('[data-attendance-page-size]');
        if (pageSizeSelect) {
            pageSize = Number(pageSizeSelect.value) || 20;
            pageSizeSelect.addEventListener('change', function () {
                pageSize = Number(pageSizeSelect.value) || 20;
                page = 1;
                renderRows();
            });
        }

        var previousPage = document.querySelector('[data-attendance-previous]');
        var nextPage = document.querySelector('[data-attendance-next]');
        if (previousPage) previousPage.addEventListener('click', function () { page--; renderRows(); });
        if (nextPage) nextPage.addEventListener('click', function () { page++; renderRows(); });

        var history = document.querySelector('[data-attendance-history]');
        if (history) {
            history.addEventListener('toggle', function () {
                var toggle = history.querySelector('.attendance-history-toggle');
                if (toggle) toggle.textContent = history.open ? 'Masquer' : 'Afficher';
            });
        }

        renderRows();

        var fillButton = document.querySelector('[data-attendance-fill-standard]');
        if (fillButton) {
            fillButton.addEventListener('click', function () {
                rows.forEach(function (row) {
                    var status = row.querySelector('[data-attendance-status]');
                    if (status.disabled) return;
                    status.value = 'present';
                    row.querySelector('[data-attendance-in]').value = '08:00';
                    row.querySelector('[data-attendance-out]').value = '17:00';
                    syncRow(row);
                });
                notify('Les horaires standards ont été appliqués. Vérifiez puis enregistrez.', 'info');
            });
        }

        var saveButton = document.querySelector('[data-attendance-save]');
        if (saveButton) {
            saveButton.addEventListener('click', function () {
                clearError();
                setBusy(saveButton, true, 'Enregistrement…');
                request(config.urls.save, {
                    company_id: config.companyId,
                    date: config.date,
                    items: JSON.stringify(rows.map(rowValues))
                }).then(function (payload) {
                    if (!payload.httpOk || !payload.success) {
                        showError(payload.message || 'Enregistrement impossible.');
                        return;
                    }
                    notify(payload.message || 'Présences enregistrées.', payload.anomalies && payload.anomalies.length ? 'warning' : 'success');
                    reloadAfter(payload);
                }).catch(function () {
                    showError('Connexion au serveur impossible. Réessayez dans un instant.');
                }).finally(function () {
                    setBusy(saveButton, false);
                });
            });
        }

        bindTransition('[data-attendance-close]', 'close', 'Clôturer cette journée ? Les encodages ne pourront plus être modifiés.', false);
        bindTransition('[data-attendance-lock]', 'lock', '', true);
        bindTransition('[data-attendance-reopen]', 'reopen', '', true);

        var anomalyToggle = document.querySelector('[data-anomaly-toggle]');
        var anomalyList = document.querySelector('[data-anomaly-list]');
        if (anomalyToggle && anomalyList) {
            anomalyToggle.addEventListener('click', function () {
                anomalyList.hidden = !anomalyList.hidden;
                anomalyToggle.textContent = anomalyList.hidden ? 'Afficher' : 'Masquer';
            });
        }
        bindReasonModal();
    }

    function confirmAction(message, callback) {
        if (window.ElliotUI && typeof window.ElliotUI.confirm === 'function') {
            window.ElliotUI.confirm(message, {confirmLabel: 'Clôturer', danger: false}).then(function (confirmed) {
                if (confirmed) callback();
            });
            return;
        }
        if (window.confirm(message)) callback();
    }

    function bindTransition(selector, action, confirmation, needsReason) {
        var button = document.querySelector(selector);
        if (!button) return;
        button.addEventListener('click', function () {
            if (needsReason) {
                openReasonModal(action, button);
                return;
            }
            confirmAction(confirmation, function () { submitTransition(action, button, ''); });
        });
    }

    function submitTransition(action, button, reason) {
        clearError();
        var labels = {close: 'Clôture…', lock: 'Verrouillage…', reopen: 'Réouverture…'};
        setBusy(button, true, labels[action]);
        request(config.urls[action], {company_id: config.companyId, date: config.date, reason: reason}).then(function (payload) {
            if (!payload.httpOk || !payload.success) {
                showError(payload.message || 'Action impossible.');
                return;
            }
            closeReasonModal();
            notify(payload.message || 'État de la journée mis à jour.', 'success');
            reloadAfter(payload);
        }).catch(function () {
            showError('Connexion au serveur impossible. Réessayez dans un instant.');
        }).finally(function () {
            setBusy(button, false);
        });
    }

    function openReasonModal(action, button) {
        var modal = document.querySelector('[data-attendance-reason-modal]');
        if (!modal) return;
        pendingAdministrativeAction = {action: action, button: button};
        modal.querySelector('[data-attendance-reason-title]').textContent =
            action === 'reopen' ? 'Rouvrir la journée' : 'Verrouiller la journée';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        window.setTimeout(function () { modal.querySelector('[data-attendance-reason]').focus(); }, 80);
    }

    function closeReasonModal() {
        var modal = document.querySelector('[data-attendance-reason-modal]');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.querySelector('[data-attendance-reason]').value = '';
        pendingAdministrativeAction = null;
        document.body.classList.remove('modal-open');
    }

    function bindReasonModal() {
        var modal = document.querySelector('[data-attendance-reason-modal]');
        if (!modal) return;
        modal.querySelectorAll('[data-attendance-reason-close]').forEach(function (button) {
            button.addEventListener('click', closeReasonModal);
        });
        modal.querySelector('[data-attendance-reason-confirm]').addEventListener('click', function () {
            var reason = modal.querySelector('[data-attendance-reason]').value.trim();
            if (!reason) {
                notify('Veuillez préciser le motif administratif.', 'warning');
                modal.querySelector('[data-attendance-reason]').focus();
                return;
            }
            if (pendingAdministrativeAction) {
                submitTransition(pendingAdministrativeAction.action, pendingAdministrativeAction.button, reason);
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) closeReasonModal();
        });
    }

    function bindCheckpoint(selector, urlAttribute, action) {
        document.querySelectorAll(selector).forEach(function (button) {
            button.addEventListener('click', function () {
                var execute = function () {
                    var data = new FormData();
                    data.append('employee_id', button.getAttribute('data-employee-id') || '');
                    if (action === 'absent') data.append('date', button.getAttribute('data-date') || '');
                    setBusy(button, true);
                    request(button.getAttribute(urlAttribute), data).then(function (payload) {
                        if (!payload.httpOk || !payload.success) {
                            showError(payload.message || 'Opération impossible.');
                            return;
                        }
                        notify(payload.message, 'success');
                        reloadAfter(payload);
                    }).catch(function () {
                        showError('Connexion au serveur impossible.');
                    }).finally(function () { setBusy(button, false); });
                };
                if (action === 'absent') confirmAction('Marquer cet employé absent pour cette date ?', execute);
                else execute();
            });
        });
    }

    function bindReportTable() {
        var table = document.getElementById('attendance-report-table');
        var dataTable = null;
        if (table && window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
            dataTable = window.jQuery(table).DataTable({
            pageLength: 15,
            order: [[0, 'asc']],
            language: {
                search: 'Rechercher', lengthMenu: 'Afficher _MENU_ lignes',
                info: '_START_ à _END_ sur _TOTAL_ lignes', infoEmpty: 'Aucune donnée',
                zeroRecords: 'Aucun résultat',
                paginate: {first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Précédent'}
            },
            dom: 'rt<"company-table-footer"ip>'
            });
        }
        var input = document.querySelector('[data-attendance-search]');
        var calendarRows = Array.prototype.slice.call(document.querySelectorAll('[data-report-calendar-row]'));
        if (input) input.addEventListener('input', function () {
            var term = input.value.trim().toLocaleLowerCase();
            if (dataTable) dataTable.search(input.value).draw();
            calendarRows.forEach(function (row) {
                row.hidden = term !== '' && row.textContent.toLocaleLowerCase().indexOf(term) === -1;
            });
        });
    }

    function bindReportViews() {
        var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-report-view]'));
        var panels = Array.prototype.slice.call(document.querySelectorAll('[data-report-panel]'));
        if (!buttons.length || !panels.length) return;

        function activate(view) {
            buttons.forEach(function (button) {
                var active = button.dataset.reportView === view;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                panel.classList.toggle('d-none', panel.dataset.reportPanel !== view);
            });
            try { window.localStorage.setItem('elliot-attendance-report-view', view); } catch (error) {}
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () { activate(button.dataset.reportView); });
        });

        var saved = 'calendar';
        try { saved = window.localStorage.getItem('elliot-attendance-report-view') || 'calendar'; } catch (error) {}
        activate(saved === 'summary' ? 'summary' : 'calendar');
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindCheckpoint('[data-check-in-url]', 'data-check-in-url', 'in');
        bindCheckpoint('[data-check-out-url]', 'data-check-out-url', 'out');
        bindCheckpoint('[data-absent-url]', 'data-absent-url', 'absent');
        bindWorkspace();
        bindReportTable();
        bindReportViews();
    });
})();
