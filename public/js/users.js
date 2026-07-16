(function () {
    'use strict';

    function request(url, data) {
        if (!data.has('_csrf_token')) {
            data.append('_csrf_token', window.ELLIOT_CSRF || '');
        }
        return fetch(url, {
            method: 'POST',
            body: data,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (payload) {
                payload.httpOk = response.ok;
                return payload;
            });
        });
    }

    function decodePayload(value) {
        try {
            return JSON.parse(decodeURIComponent(escape(window.atob(value))));
        } catch (error) {
            return null;
        }
    }

    function setField(form, name, value) {
        var field = form.querySelector('[name="' + name + '"]');
        if (field) {
            field.value = value === null || value === undefined ? '' : value;
        }
    }

    function modalState(modal, open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('modal-open', open);
        if (open) {
            var body = modal.querySelector('.user-modal-body');
            if (body) {
                body.scrollTop = 0;
            }
        }
    }

    function filterScopedOptions(form) {
        var companyField = form.querySelector('[data-user-company]');
        var companyId = companyField ? companyField.value : '';
        var role = form.querySelector('[data-user-role]');
        var employee = form.querySelector('[data-user-employee]');
        [role, employee].forEach(function (select) {
            if (!select) { return; }
            var current = select.value;
            var visibleCurrent = false;
            select.querySelectorAll('option').forEach(function (option) {
                var optionCompany = option.getAttribute('data-company-id');
                var visible = option.value === ''
                    || (select === role && companyId === '' && optionCompany === '')
                    || (companyId !== '' && optionCompany === companyId);
                option.hidden = !visible;
                if (visible && option.value === current) {
                    visibleCurrent = true;
                }
            });
            if (!visibleCurrent) {
                select.value = '';
            }
        });
        updatePermissionPreview(form);
    }

    function updatePermissionPreview(form) {
        var role = form.querySelector('[data-user-role]');
        var preview = form.querySelector('[data-role-permission-preview]');
        var list = form.querySelector('[data-permission-chip-list]');
        if (!role || !preview || !list) {
            return;
        }
        var option = role.options[role.selectedIndex];
        var description = option ? option.getAttribute('data-role-description') : '';
        var permissions = option && option.getAttribute('data-permissions')
            ? option.getAttribute('data-permissions').split('||').filter(Boolean)
            : [];
        preview.querySelector('small').textContent = description || 'Sélectionnez un rôle pour afficher ses autorisations.';
        list.innerHTML = '';
        permissions.forEach(function (permission) {
            var chip = document.createElement('span');
            chip.textContent = permission;
            list.appendChild(chip);
        });
        preview.classList.toggle('has-permissions', permissions.length > 0);
    }

    function strength(input) {
        var value = input.value;
        var score = 0;
        if (value.length >= 8) { score++; }
        if (/[A-Z]/.test(value) && /[a-z]/.test(value)) { score++; }
        if (/\d/.test(value)) { score++; }
        if (/[^A-Za-z0-9]/.test(value)) { score++; }
        var bar = input.closest('form').querySelector('[data-password-strength]');
        if (bar) {
            bar.style.width = (score * 25) + '%';
            bar.className = 'strength-' + score;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.querySelector('[data-user-modal]');
        var form = document.querySelector('[data-user-form]');
        var passwordModal = document.querySelector('[data-password-modal]');
        var passwordForm = document.querySelector('[data-password-form]');
        var permissionModal = document.querySelector('[data-permission-modal]');
        var permissionForm = document.querySelector('[data-permission-form]');
        if (!modal || !form || !passwordModal || !passwordForm) {
            return;
        }

        function openCreate() {
            form.reset();
            form.action = form.getAttribute('data-store-url');
            setField(form, 'id', '');
            modal.querySelector('[data-user-modal-title]').textContent = 'Nouvel utilisateur';
            form.querySelector('[data-submit-label]').textContent = 'Créer l’utilisateur';
            form.querySelector('[data-password-create-section]').hidden = false;
            form.querySelector('[data-security-progress]').hidden = false;
            form.querySelector('[name="password"]').required = true;
            form.querySelector('[data-form-error]').classList.add('d-none');
            filterScopedOptions(form);
            modalState(modal, true);
        }

        function openEdit(data) {
            form.reset();
            form.action = form.getAttribute('data-update-url');
            ['id', 'company_id', 'role_id', 'employee_id', 'first_name', 'last_name', 'email', 'phone', 'status'].forEach(function (name) {
                setField(form, name, data[name]);
            });
            filterScopedOptions(form);
            setField(form, 'role_id', data.role_id);
            setField(form, 'employee_id', data.employee_id);
            modal.querySelector('[data-user-modal-title]').textContent = 'Modifier l’utilisateur';
            form.querySelector('[data-submit-label]').textContent = 'Enregistrer les modifications';
            form.querySelector('[data-password-create-section]').hidden = true;
            form.querySelector('[data-security-progress]').hidden = true;
            form.querySelector('[name="password"]').required = false;
            form.querySelector('[data-form-error]').classList.add('d-none');
            modalState(modal, true);
        }

        function renderPermissions(data, mode) {
            var container = permissionModal.querySelector('[data-permission-groups]');
            var groups = {};
            (data.permissions || []).forEach(function (permission) {
                var module = permission.module || 'autres';
                if (!groups[module]) groups[module] = [];
                groups[module].push(permission);
            });
            container.innerHTML = '';
            Object.keys(groups).forEach(function (module) {
                var section = document.createElement('section');
                section.className = 'permission-group';
                var heading = document.createElement('div');
                heading.className = 'permission-group-heading';
                var title = document.createElement('strong');
                title.textContent = module.replace(/[-_]/g, ' ');
                var count = document.createElement('span');
                count.textContent = groups[module].length + ' droit(s)';
                heading.appendChild(title); heading.appendChild(count); section.appendChild(heading);

                groups[module].forEach(function (permission) {
                    var row = document.createElement('label');
                    row.className = 'permission-option';
                    row.dataset.permissionSearch = (permission.name + ' ' + permission.slug + ' ' + (permission.description || '')).toLocaleLowerCase();
                    var copy = document.createElement('span');
                    var name = document.createElement('strong'); name.textContent = permission.name;
                    var description = document.createElement('small'); description.textContent = permission.description || permission.slug;
                    copy.appendChild(name); copy.appendChild(description);
                    if (mode === 'delegation') {
                        var checkbox = document.createElement('input');
                        checkbox.type = 'checkbox'; checkbox.name = 'permission_id'; checkbox.value = permission.id;
                        checkbox.checked = (data.selected || []).map(Number).indexOf(Number(permission.id)) !== -1;
                        row.appendChild(checkbox); row.appendChild(copy);
                    } else {
                        row.appendChild(copy);
                        var control = document.createElement('select');
                        control.className = 'form-select form-select-sm'; control.name = 'permission_effect'; control.dataset.permissionId = permission.id;
                        [['inherit', permission.role_granted ? 'Hériter du rôle (autorisé)' : 'Hériter du rôle (non autorisé)'], ['allow', 'Autoriser'], ['deny', 'Refuser']].forEach(function (item) {
                            var option = document.createElement('option'); option.value = item[0]; option.textContent = item[1];
                            option.selected = permission.effect === item[0]; control.appendChild(option);
                        });
                        row.appendChild(control);
                    }
                    section.appendChild(row);
                });
                container.appendChild(section);
            });
            if (!Object.keys(groups).length) {
                container.innerHTML = '<div class="dashboard-empty">Aucune permission ne vous a été déléguée.</div>';
            }
        }

        function openPermissionAccess(id, mode) {
            permissionForm.reset();
            setField(permissionForm, 'id', id);
            setField(permissionForm, 'mode', mode);
            permissionModal.querySelector('[data-permission-error]').classList.add('d-none');
            permissionModal.querySelector('[data-permission-groups]').innerHTML = '<div class="dashboard-empty">Chargement…</div>';
            permissionModal.querySelector('[data-permission-title]').textContent = mode === 'delegation' ? 'Déléguer la gestion des droits' : 'Permissions individuelles';
            permissionModal.querySelector('[data-permission-kicker]').textContent = mode === 'delegation' ? 'Contrôle super-admin' : 'Accès utilisateur';
            permissionModal.querySelector('[data-permission-notice]').textContent = mode === 'delegation'
                ? 'Cet administrateur RH ne pourra attribuer que les permissions sélectionnées.'
                : 'Autoriser ou refuser explicitement un droit, ou conserver les droits du rôle.';
            permissionModal.querySelector('[data-permission-select-all]').hidden = mode !== 'delegation';
            modalState(permissionModal, true);

            fetch(window.ELLIOT_USER_URLS.permissionData + '?id=' + encodeURIComponent(id) + '&mode=' + encodeURIComponent(mode), {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (payload) { payload.httpOk = response.ok; return payload; });
            }).then(function (payload) {
                if (!payload.httpOk || !payload.success) throw new Error(payload.message || 'Chargement impossible.');
                var user = payload.data.user || {};
                permissionModal.querySelector('[data-permission-subtitle]').textContent = (user.first_name || '') + ' ' + (user.last_name || '') + ' · ' + (user.company_name || user.email || '');
                renderPermissions(payload.data, mode);
            }).catch(function (error) {
                var box = permissionModal.querySelector('[data-permission-error]');
                box.textContent = error.message; box.classList.remove('d-none');
            });
        }

        document.querySelector('[data-user-create]').addEventListener('click', openCreate);
        document.querySelectorAll('[data-user-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { modalState(modal, false); });
        });
        var companyField = form.querySelector('[data-user-company]');
        if (companyField && companyField.tagName === 'SELECT') {
            companyField.addEventListener('change', function () { filterScopedOptions(form); });
        }
        form.querySelector('[data-user-role]').addEventListener('change', function () {
            updatePermissionPreview(form);
        });

        document.getElementById('users-table').addEventListener('click', function (event) {
            var edit = event.target.closest('[data-user-edit]');
            var reset = event.target.closest('[data-user-password]');
            var toggle = event.target.closest('[data-user-toggle-status]');
            var remove = event.target.closest('[data-user-delete]');
            var access = event.target.closest('[data-user-access]');

            if (access) {
                openPermissionAccess(access.getAttribute('data-user-access'), access.getAttribute('data-access-mode') || 'permissions');
                return;
            }

            if (edit) {
                var data = decodePayload(edit.getAttribute('data-user-edit'));
                if (data) { openEdit(data); }
                return;
            }
            if (reset) {
                passwordForm.reset();
                setField(passwordForm, 'id', reset.getAttribute('data-user-password'));
                passwordModal.querySelector('[data-password-user-name]').textContent = reset.getAttribute('data-user-name') || '-';
                passwordModal.querySelector('[data-form-error]').classList.add('d-none');
                modalState(passwordModal, true);
                return;
            }
            if (toggle) {
                var current = toggle.getAttribute('data-current-status');
                var next = current === 'blocked' ? 'active' : 'blocked';
                var label = next === 'blocked' ? 'Bloquer ce compte et empêcher toute connexion ?' : 'Réactiver l’accès de ce compte ?';
                window.ElliotUI.confirm(label, { confirmLabel: next === 'blocked' ? 'Bloquer' : 'Réactiver', danger: next === 'blocked' })
                    .then(function (confirmed) {
                        if (!confirmed) { return; }
                        var data = new FormData();
                        data.append('id', toggle.getAttribute('data-user-toggle-status'));
                        data.append('status', next);
                        request(window.ELLIOT_USER_URLS.status, data).then(function (payload) {
                            if (payload.httpOk && payload.success) {
                                window.location.reload();
                            }
                        });
                    });
                return;
            }
            if (remove) {
                window.ElliotUI.confirm('Supprimer définitivement le compte de ' + (remove.getAttribute('data-user-name') || 'cet utilisateur') + ' ?', { confirmLabel: 'Supprimer' })
                    .then(function (confirmed) {
                        if (!confirmed) { return; }
                        var data = new FormData();
                        data.append('id', remove.getAttribute('data-user-delete'));
                        request(window.ELLIOT_USER_URLS.delete, data).then(function (payload) {
                            if (payload.httpOk && payload.success) {
                                window.location.reload();
                            }
                        });
                    });
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var submit = form.querySelector('[data-submit-label]');
            var error = form.querySelector('[data-form-error]');
            var original = submit.textContent;
            error.classList.add('d-none');
            submit.disabled = true;
            submit.textContent = 'Enregistrement…';
            request(form.action, new FormData(form))
                .then(function (payload) {
                    if (!payload.httpOk || !payload.success) {
                        error.textContent = payload.message || 'Enregistrement impossible.';
                        error.classList.remove('d-none');
                        return;
                    }
                    window.location.reload();
                })
                .finally(function () {
                    submit.disabled = false;
                    submit.textContent = original;
                });
        });

        document.querySelectorAll('[data-password-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { modalState(passwordModal, false); });
        });
        document.querySelectorAll('[data-permission-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { modalState(permissionModal, false); });
        });
        permissionModal.querySelector('[data-permission-search]').addEventListener('input', function (event) {
            var term = event.target.value.trim().toLocaleLowerCase();
            permissionModal.querySelectorAll('[data-permission-search]').forEach(function (row) {
                if (row.tagName !== 'INPUT') row.hidden = term !== '' && row.dataset.permissionSearch.indexOf(term) === -1;
            });
        });
        permissionModal.querySelector('[data-permission-select-all]').addEventListener('click', function () {
            var boxes = Array.prototype.slice.call(permissionModal.querySelectorAll('input[name="permission_id"]'));
            var select = boxes.some(function (box) { return !box.checked; });
            boxes.forEach(function (box) { box.checked = select; });
        });
        permissionForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var mode = permissionForm.querySelector('[name="mode"]').value;
            var data = new FormData(permissionForm);
            if (mode === 'delegation') {
                data.set('permission_ids', JSON.stringify(Array.prototype.slice.call(permissionForm.querySelectorAll('input[name="permission_id"]:checked')).map(function (box) { return Number(box.value); })));
            } else {
                var effects = {};
                permissionForm.querySelectorAll('[data-permission-id]').forEach(function (select) { effects[select.dataset.permissionId] = select.value; });
                data.set('effects', JSON.stringify(effects));
            }
            var submit = permissionForm.querySelector('[data-permission-submit]');
            var box = permissionModal.querySelector('[data-permission-error]');
            submit.disabled = true; box.classList.add('d-none');
            request(mode === 'delegation' ? window.ELLIOT_USER_URLS.saveDelegations : window.ELLIOT_USER_URLS.savePermissions, data)
                .then(function (payload) {
                    if (!payload.httpOk || !payload.success) { box.textContent = payload.message || 'Enregistrement impossible.'; box.classList.remove('d-none'); return; }
                    modalState(permissionModal, false);
                    if (window.ElliotUI) window.ElliotUI.toast(payload.message, 'success');
                }).finally(function () { submit.disabled = false; });
        });
        passwordForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var submit = passwordForm.querySelector('[data-submit-label]');
            var error = passwordForm.querySelector('[data-form-error]');
            var original = submit.textContent;
            error.classList.add('d-none');
            submit.disabled = true;
            submit.textContent = 'Réinitialisation…';
            request(passwordForm.action, new FormData(passwordForm))
                .then(function (payload) {
                    if (!payload.httpOk || !payload.success) {
                        error.textContent = payload.message || 'Réinitialisation impossible.';
                        error.classList.remove('d-none');
                        return;
                    }
                    modalState(passwordModal, false);
                    passwordForm.reset();
                })
                .finally(function () {
                    submit.disabled = false;
                    submit.textContent = original;
                });
        });

        document.querySelector('[data-generate-password]').addEventListener('click', function () {
            var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
            var value = '';
            var random = new Uint32Array(14);
            window.crypto.getRandomValues(random);
            random.forEach(function (number) { value += alphabet[number % alphabet.length]; });
            var input = form.querySelector('[name="password"]');
            input.type = 'text';
            input.value = value;
            strength(input);
        });
        document.querySelectorAll('input[name="password"]').forEach(function (input) {
            input.addEventListener('input', function () { strength(input); });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            if (passwordModal.classList.contains('is-open')) {
                modalState(passwordModal, false);
            } else if (permissionModal.classList.contains('is-open')) {
                modalState(permissionModal, false);
            } else if (modal.classList.contains('is-open')) {
                modalState(modal, false);
            }
        });
    });
})();
