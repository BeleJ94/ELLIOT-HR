(function () {
    'use strict';

    var config = window.ELLIOT_DASHBOARD || {};
    var modal = document.querySelector('[data-dashboard-modal]');

    if (!modal || !config.detailUrl || !config.exportUrl) {
        return;
    }

    var title = modal.querySelector('#dashboard-detail-title');
    var subtitle = modal.querySelector('[data-dashboard-subtitle]');
    var summary = modal.querySelector('[data-dashboard-summary]');
    var head = modal.querySelector('[data-dashboard-head]');
    var body = modal.querySelector('[data-dashboard-body]');
    var empty = modal.querySelector('[data-dashboard-empty]');
    var pdfLink = modal.querySelector('[data-dashboard-export-pdf]');
    var excelLink = modal.querySelector('[data-dashboard-export-excel]');
    var search = modal.querySelector('[data-dashboard-search]');
    var lastFocus = null;
    var currentReport = null;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function urlWithParams(base, params) {
        var url = new URL(base, window.location.origin);
        Object.keys(params).forEach(function (key) {
            url.searchParams.set(key, params[key]);
        });
        return url.toString();
    }

    function setLoading(type) {
        currentReport = null;
        title.textContent = 'Chargement...';
        subtitle.textContent = 'Preparation du rapport detaille.';
        summary.innerHTML = '<span class="dashboard-detail-chip">Veuillez patienter</span>';
        head.innerHTML = '';
        body.innerHTML = '';
        empty.hidden = true;
        if (search) {
            search.value = '';
        }
        pdfLink.href = urlWithParams(config.exportUrl, { type: type, format: 'pdf' });
        excelLink.href = urlWithParams(config.exportUrl, { type: type, format: 'excel' });
    }

    function badge(value) {
        var raw = String(value || '-');
        var key = raw.toLowerCase().replace(/[^a-z0-9_ -]/g, '');
        var badgeKeys = ['active', 'success', 'present', 'paid', 'validated', 'approved', 'trial', 'pending', 'warning', 'late', 'draft', 'absent', 'danger', 'rejected', 'expired', 'cancelled', 'terminated', 'inactive', 'suspended'];

        if (badgeKeys.indexOf(key) === -1) {
            return escapeHtml(raw);
        }

        return '<span class="dashboard-detail-status status-' + escapeHtml(key.replace(/\s+/g, '-')) + '">' + escapeHtml(raw) + '</span>';
    }

    function renderRows(columns, rows) {
        head.innerHTML = '<tr><th class="dashboard-detail-index">#</th>' + columns.map(function (column) {
            return '<th>' + escapeHtml(column) + '</th>';
        }).join('') + '</tr>';

        body.innerHTML = rows.map(function (row, index) {
            return '<tr><td class="dashboard-detail-index">' + (index + 1) + '</td>' + columns.map(function (column) {
                var value = row[column] || '-';
                var lowerColumn = column.toLowerCase();
                var rendered = lowerColumn.indexOf('statut') !== -1 || lowerColumn.indexOf('type') !== -1 || lowerColumn === 'rh' || lowerColumn === 'manager'
                    ? badge(value)
                    : escapeHtml(value);

                return '<td data-label="' + escapeHtml(column) + '">' + rendered + '</td>';
            }).join('') + '</tr>';
        }).join('');

        empty.hidden = rows.length > 0;
    }

    function renderReport(report) {
        var columns = Array.isArray(report.columns) ? report.columns : [];
        var rows = Array.isArray(report.rows) ? report.rows : [];
        currentReport = {
            columns: columns,
            rows: rows
        };

        title.textContent = report.title || 'Details';
        subtitle.textContent = (report.subtitle || '') + (report.generated_at ? ' · Genere le ' + report.generated_at : '');
        summary.innerHTML = (report.summary || []).map(function (item) {
            return '<span class="dashboard-detail-chip"><strong>' + escapeHtml(item.value) + '</strong><small>' + escapeHtml(item.label) + '</small></span>';
        }).join('');

        renderRows(columns, rows);
    }

    function openModal(type, trigger) {
        lastFocus = trigger || document.activeElement;
        modal.hidden = false;
        document.body.classList.add('dashboard-modal-open');
        setLoading(type);

        fetch(urlWithParams(config.detailUrl, { type: type }), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Impossible de charger ce rapport.');
                }
                return response.json();
            })
            .then(function (payload) {
                if (!payload.success) {
                    throw new Error(payload.message || 'Rapport indisponible.');
                }
                renderReport(payload);
            })
            .catch(function (error) {
                title.textContent = 'Rapport indisponible';
                subtitle.textContent = error.message;
                summary.innerHTML = '';
                head.innerHTML = '';
                body.innerHTML = '';
                currentReport = null;
                empty.hidden = false;
            });

        var closeButton = modal.querySelector('[data-dashboard-close]');
        if (closeButton) {
            closeButton.focus({ preventScroll: true });
        }
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('dashboard-modal-open');
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus({ preventScroll: true });
        }
    }

    document.addEventListener('click', function (event) {
        var close = event.target.closest('[data-dashboard-close]');
        if (close) {
            closeModal();
            return;
        }

        var trigger = event.target.closest('[data-dashboard-detail]');
        if (trigger) {
            openModal(trigger.getAttribute('data-dashboard-detail'), trigger);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
            return;
        }

        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        var trigger = event.target.closest('[data-dashboard-detail]');
        if (trigger) {
            event.preventDefault();
            openModal(trigger.getAttribute('data-dashboard-detail'), trigger);
        }
    });

    if (search) {
        search.addEventListener('input', function () {
            if (!currentReport) {
                return;
            }

            var term = search.value.trim().toLowerCase();
            var filtered = currentReport.rows.filter(function (row) {
                if (!term) {
                    return true;
                }

                return currentReport.columns.some(function (column) {
                    return String(row[column] || '').toLowerCase().indexOf(term) !== -1;
                });
            });

            renderRows(currentReport.columns, filtered);
        });
    }
})();
