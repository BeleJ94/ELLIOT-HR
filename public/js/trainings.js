(function () {
    'use strict';

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function openModal(name) {
        var modal = qs('[data-training-modal="' + name + '"]');
        if (modal) {
            modal.setAttribute('aria-hidden', 'false');
            modal.classList.add('is-open');
            document.body.classList.add('dashboard-modal-open');
        }
    }

    function closeModals() {
        qsa('[data-training-modal]').forEach(function (modal) {
            modal.setAttribute('aria-hidden', 'true');
            modal.classList.remove('is-open');
        });
        document.body.classList.remove('dashboard-modal-open');
    }

    document.addEventListener('click', function (event) {
        var open = event.target.closest('[data-training-open]');
        if (open) {
            openModal(open.getAttribute('data-training-open'));
            return;
        }

        if (event.target.closest('[data-training-close]')) {
            closeModals();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModals();
        }
    });

    qsa('[data-training-form]').forEach(function (form) {
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

    var search = qs('[data-training-search]');
    var table = qs('#training-sessions-table');
    if (search && table) {
        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            qsa('tbody tr', table).forEach(function (row) {
                row.hidden = term !== '' && row.textContent.toLowerCase().indexOf(term) === -1;
            });
        });
    }
})();
