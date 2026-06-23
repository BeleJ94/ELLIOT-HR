<?php
$types = $types ?? [];
$employees = $employees ?? [];
$isEmployeeSelf = !empty($isEmployeeSelf);
$defaultEmployeeId = $defaultEmployeeId ?? null;
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Conges</span>
        <h1 class="page-title">Demande de conge</h1>
        <p>Soumettez une demande qui passera par la validation manager puis la validation RH.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/leaves')) ?>"><?= icon('calendar') ?><span>Historique</span></a>
</div>

<form class="card company-form-card" method="post" action="<?= e(url('/leaves/store')) ?>" data-leave-form>
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="alert alert-danger d-none" data-form-error></div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="employee_id">Employe</label>
                <select id="employee_id" class="form-select" name="employee_id" required data-leave-employee-select <?= $isEmployeeSelf ? 'disabled' : '' ?>>
                    <option value="">Selectionner</option>
                    <?php foreach ($employees as $employee): ?>
                        <?php $name = trim(($employee['last_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['first_name'] ?? '')); ?>
                        <option value="<?= e((string) $employee['id']) ?>" data-company-id="<?= e((string) $employee['company_id']) ?>" <?= (int) $defaultEmployeeId === (int) $employee['id'] ? 'selected' : '' ?>>
                            <?= e($name . ' - ' . ($employee['employee_number'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isEmployeeSelf): ?>
                    <input type="hidden" name="employee_id" value="<?= e((string) $defaultEmployeeId) ?>">
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="leave_type_id">Type de conge</label>
                <select id="leave_type_id" class="form-select" name="leave_type_id" required data-leave-type-select>
                    <option value="">Selectionner</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= e((string) $type['id']) ?>" data-company-id="<?= e((string) $type['company_id']) ?>">
                            <?= e($type['name'] . (!empty($type['company_name']) ? ' - ' . $type['company_name'] : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="start_date">Date debut</label>
                <input id="start_date" class="form-control" type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="end_date">Date fin</label>
                <input id="end_date" class="form-control" type="date" name="end_date" value="<?= e(date('Y-m-d')) ?>" required>
            </div>

            <div class="col-12">
                <label class="form-label" for="reason">Motif</label>
                <textarea id="reason" class="form-control" name="reason" rows="4" required></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer organization-form-actions">
        <a class="btn btn-outline" href="<?= e(url('/leaves')) ?>">Annuler</a>
        <button class="btn btn-primary" type="submit" data-submit-label>Envoyer la demande</button>
    </div>
</form>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/leaves.js')) ?>"></script>
