(function () {
    'use strict';

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function openModal(name) {
        var modal = qs('[data-medical-modal="' + name + '"]');
        if (!modal) {
            return;
        }
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        document.body.classList.add('dashboard-modal-open');
    }

    function closeModals() {
        qsa('[data-medical-modal]').forEach(function (modal) {
            modal.setAttribute('aria-hidden', 'true');
            modal.classList.remove('is-open');
        });
        document.body.classList.remove('dashboard-modal-open');
    }

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-medical-open]');
        if (opener) {
            openModal(opener.getAttribute('data-medical-open'));
            return;
        }
        if (event.target.closest('[data-medical-close]')) {
            closeModals();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModals();
        }
    });

    qsa('[data-medical-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var submit = qs('[data-submit-label]', form);
            var errorBox = qs('[data-form-error]', form);
            var original = submit ? submit.innerHTML : '';

            if (errorBox) {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
            }
            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Traitement...';
            }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, payload: payload };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.payload.success) {
                        if (result.payload.redirect) {
                            window.location.href = result.payload.redirect;
                            return;
                        }
                        window.location.reload();
                        return;
                    }
                    if (errorBox) {
                        errorBox.textContent = result.payload.message || 'Operation impossible.';
                        errorBox.classList.remove('d-none');
                    }
                })
                .catch(function () {
                    if (errorBox) {
                        errorBox.textContent = 'Erreur reseau. Reessayez.';
                        errorBox.classList.remove('d-none');
                    }
                })
                .finally(function () {
                    if (submit) {
                        submit.disabled = false;
                        submit.innerHTML = original;
                    }
                });
        });
    });

    var search = qs('[data-medical-search]');
    var table = qs('#medical-requests-table');
    if (search && table) {
        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            qsa('tbody tr', table).forEach(function (row) {
                row.hidden = term !== '' && row.textContent.toLowerCase().indexOf(term) === -1;
            });
        });
    }

    var dependentFilters = qs('[data-dependent-filters]');
    var dependentTable = qs('[data-dependent-table]');
    var dependentCards = qsa('[data-dependent-card]');
    var dependentEmpty = qs('[data-dependent-empty]');
    if (dependentFilters && (dependentCards.length || dependentTable)) {
        var dependentSearch = qs('[data-dependent-search]', dependentFilters);
        var dependentStatus = qs('[data-dependent-status]', dependentFilters);
        var dependentRelationship = qs('[data-dependent-relationship]', dependentFilters);
        var resetDependents = qs('[data-dependent-reset]', dependentFilters);
        var dependentFilterRegistered = false;

        function dependentDataTable() {
            if (!dependentTable || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) {
                return null;
            }
            if (!window.jQuery.fn.DataTable.isDataTable(dependentTable)) {
                return null;
            }
            return window.jQuery(dependentTable).DataTable();
        }

        function registerDependentDataTableFilter() {
            if (dependentFilterRegistered || !dependentTable || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.dataTable) {
                return;
            }

            window.jQuery.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (settings.nTable !== dependentTable) {
                    return true;
                }

                var row = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
                if (!row) {
                    return true;
                }

                var status = dependentStatus ? dependentStatus.value : '';
                var relationship = dependentRelationship ? dependentRelationship.value : '';
                var matchesStatus = status === '' || row.getAttribute('data-status') === status;
                var matchesRelationship = relationship === '' || row.getAttribute('data-relationship') === relationship;

                return matchesStatus && matchesRelationship;
            });

            dependentFilterRegistered = true;
        }

        function filterDependents() {
            var term = dependentSearch ? dependentSearch.value.trim().toLowerCase() : '';
            var status = dependentStatus ? dependentStatus.value : '';
            var relationship = dependentRelationship ? dependentRelationship.value : '';
            var api = dependentDataTable();

            if (api) {
                registerDependentDataTableFilter();
                api.search(term).draw();
                return;
            }

            var visible = 0;

            dependentCards.forEach(function (card) {
                var matchesTerm = term === '' || (card.getAttribute('data-search') || '').indexOf(term) !== -1;
                var matchesStatus = status === '' || card.getAttribute('data-status') === status;
                var matchesRelationship = relationship === '' || card.getAttribute('data-relationship') === relationship;
                var show = matchesTerm && matchesStatus && matchesRelationship;
                card.hidden = !show;
                if (show) {
                    visible += 1;
                }
            });

            if (dependentEmpty) {
                dependentEmpty.classList.toggle('d-none', visible !== 0);
            }
        }

        if (dependentSearch) {
            dependentSearch.addEventListener('input', filterDependents);
        }
        if (dependentStatus) {
            dependentStatus.addEventListener('change', filterDependents);
        }
        if (dependentRelationship) {
            dependentRelationship.addEventListener('change', filterDependents);
        }
        if (resetDependents) {
            resetDependents.addEventListener('click', function () {
                if (dependentSearch) {
                    dependentSearch.value = '';
                }
                if (dependentStatus) {
                    dependentStatus.value = '';
                }
                if (dependentRelationship) {
                    dependentRelationship.value = '';
                }
                filterDependents();
            });
        }
    }
})();
