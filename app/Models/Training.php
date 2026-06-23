<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Throwable;

class Training extends Model
{
    protected string $table = 'training_sessions';

    public function dashboard(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'training_sessions');

        return [
            'sessions' => (int) Database::query("SELECT COUNT(*) FROM training_sessions WHERE deleted_at IS NULL {$scope}", $params)->fetchColumn(),
            'ongoing' => (int) Database::query("SELECT COUNT(*) FROM training_sessions WHERE deleted_at IS NULL AND status = 'ongoing' {$scope}", $params)->fetchColumn(),
            'completed' => (int) Database::query("SELECT COUNT(*) FROM training_sessions WHERE deleted_at IS NULL AND status = 'completed' {$scope}", $params)->fetchColumn(),
            'participants' => (int) Database::query(
                "SELECT COUNT(*) FROM training_participants WHERE deleted_at IS NULL" . ($companyId !== null ? ' AND company_id = :company_id' : ''),
                $params
            )->fetchColumn(),
        ];
    }

    public function sessions(?int $companyId, array $filters = []): array
    {
        [$scope, $params] = $this->scope($companyId, 'ts');
        $where = ["ts.deleted_at IS NULL {$scope}"];

        if (!empty($filters['status'])) {
            $where[] = 'ts.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['from'])) {
            $where[] = 'ts.end_date >= :from_date';
            $params['from_date'] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $where[] = 'ts.start_date <= :to_date';
            $params['to_date'] = $filters['to'];
        }

        return Database::query(
            'SELECT ts.*, tc.title AS course_title, tc.category, c.name AS company_name,
                    COUNT(DISTINCT tp.id) AS participants_count,
                    COALESCE(AVG(tp.attendance_rate), 0) AS average_attendance
             FROM training_sessions ts
             INNER JOIN training_courses tc ON tc.id = ts.training_course_id
             INNER JOIN companies c ON c.id = ts.company_id
             LEFT JOIN training_participants tp ON tp.training_session_id = ts.id AND tp.deleted_at IS NULL
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY ts.id
             ORDER BY ts.start_date DESC, ts.id DESC',
            $params
        )->fetchAll();
    }

    public function courses(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'tc');

        return Database::query(
            "SELECT tc.*, c.name AS company_name
             FROM training_courses tc
             INNER JOIN companies c ON c.id = tc.company_id
             WHERE tc.deleted_at IS NULL {$scope}
             ORDER BY tc.title ASC",
            $params
        )->fetchAll();
    }

    public function employees(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'e');

        return Database::query(
            "SELECT e.id, e.company_id, e.employee_number, e.first_name, e.middle_name, e.last_name,
                    d.name AS department_name, p.title AS position_title
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE e.deleted_at IS NULL AND e.employment_status IN ('active', 'on_leave') {$scope}
             ORDER BY e.last_name ASC, e.first_name ASC",
            $params
        )->fetchAll();
    }

    public function companies(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'companies');

        return Database::query(
            "SELECT id, name FROM companies WHERE deleted_at IS NULL {$scope} ORDER BY name ASC",
            $params
        )->fetchAll();
    }

    public function saveCourse(array $data): int
    {
        $status = $data['status'] ?? 'active';
        $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';

        Database::query(
            'INSERT INTO training_courses
                (company_id, title, code, category, objectives, default_duration_days, certificate_valid_months, status, created_at)
             VALUES
                (:company_id, :title, :code, :category, :objectives, :default_duration_days, :certificate_valid_months, :status, NOW())',
            [
                'company_id' => (int) $data['company_id'],
                'title' => trim($data['title']),
                'code' => trim($data['code'] ?? '') ?: null,
                'category' => trim($data['category'] ?? '') ?: null,
                'objectives' => trim($data['objectives'] ?? '') ?: null,
                'default_duration_days' => max(0.5, (float) ($data['default_duration_days'] ?? 1)),
                'certificate_valid_months' => (int) ($data['certificate_valid_months'] ?? 0) ?: null,
                'status' => $status,
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    public function saveSession(array $data): int
    {
        Database::beginTransaction();

        try {
            Database::query(
                'INSERT INTO training_sessions
                    (company_id, training_course_id, title, trainer_name, provider, location, start_date, end_date, start_time, end_time, budget, currency, min_attendance_rate, status, notes, created_at)
                 VALUES
                    (:company_id, :training_course_id, :title, :trainer_name, :provider, :location, :start_date, :end_date, :start_time, :end_time, :budget, :currency, :min_attendance_rate, :status, :notes, NOW())',
                [
                    'company_id' => (int) $data['company_id'],
                    'training_course_id' => (int) $data['training_course_id'],
                    'title' => trim($data['title']),
                    'trainer_name' => trim($data['trainer_name'] ?? '') ?: null,
                    'provider' => trim($data['provider'] ?? '') ?: null,
                    'location' => trim($data['location'] ?? '') ?: null,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'start_time' => $data['start_time'] ?: null,
                    'end_time' => $data['end_time'] ?: null,
                    'budget' => max(0, (float) ($data['budget'] ?? 0)),
                    'currency' => trim($data['currency'] ?? 'USD') ?: 'USD',
                    'min_attendance_rate' => max(0, min(100, (float) ($data['min_attendance_rate'] ?? 80))),
                    'status' => 'planned',
                    'notes' => trim($data['notes'] ?? '') ?: null,
                ]
            );

            $sessionId = (int) Database::connection()->lastInsertId();
            $this->createSessionDays($sessionId, (int) $data['company_id'], $data);
            Database::commit();

            return $sessionId;
        } catch (Throwable $exception) {
            Database::rollBack();
            throw $exception;
        }
    }

    public function findSession(int $id, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'ts');
        $params['id'] = $id;

        $row = Database::query(
            "SELECT ts.*, tc.title AS course_title, tc.category, tc.objectives, c.name AS company_name
             FROM training_sessions ts
             INNER JOIN training_courses tc ON tc.id = ts.training_course_id
             INNER JOIN companies c ON c.id = ts.company_id
             WHERE ts.id = :id AND ts.deleted_at IS NULL {$scope}
             LIMIT 1",
            $params
        )->fetch();

        return $row ?: null;
    }

    public function days(int $sessionId): array
    {
        return Database::query(
            'SELECT * FROM training_session_days
             WHERE training_session_id = :session_id AND deleted_at IS NULL
             ORDER BY day_date ASC',
            ['session_id' => $sessionId]
        )->fetchAll();
    }

    public function participants(int $sessionId): array
    {
        return Database::query(
            "SELECT tp.*, e.employee_number, e.first_name, e.middle_name, e.last_name,
                    d.name AS department_name, p.title AS position_title
             FROM training_participants tp
             INNER JOIN employees e ON e.id = tp.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE tp.training_session_id = :session_id AND tp.deleted_at IS NULL
             ORDER BY e.last_name ASC, e.first_name ASC",
            ['session_id' => $sessionId]
        )->fetchAll();
    }

    public function attendanceByDay(int $dayId): array
    {
        $rows = Database::query(
            'SELECT * FROM training_attendance
             WHERE training_session_day_id = :day_id AND deleted_at IS NULL',
            ['day_id' => $dayId]
        )->fetchAll();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['training_participant_id']] = $row;
        }

        return $indexed;
    }

    public function addParticipants(int $sessionId, array $employeeIds, ?int $companyId): int
    {
        $session = $this->findSession($sessionId, $companyId);
        if (!$session) {
            return 0;
        }

        $count = 0;
        foreach (array_unique(array_map('intval', $employeeIds)) as $employeeId) {
            if ($employeeId <= 0 || !$this->employeeBelongsToCompany($employeeId, (int) $session['company_id'])) {
                continue;
            }

            $statement = Database::query(
                'INSERT IGNORE INTO training_participants
                    (company_id, training_session_id, employee_id, invitation_status, final_status, created_at)
                 VALUES
                    (:company_id, :session_id, :employee_id, "invited", "invited", NOW())',
                [
                    'company_id' => (int) $session['company_id'],
                    'session_id' => $sessionId,
                    'employee_id' => $employeeId,
                ]
            );
            $count += $statement->rowCount() > 0 ? 1 : 0;
        }

        return $count;
    }

    public function saveAttendance(int $dayId, array $rows, ?int $companyId): bool
    {
        $day = $this->findDay($dayId, $companyId);
        if (!$day) {
            return false;
        }

        foreach ($rows as $participantId => $payload) {
            $participant = $this->participant((int) $participantId, (int) $day['training_session_id']);
            if (!$participant) {
                continue;
            }

            $status = $payload['status'] ?? 'present';
            $status = in_array($status, ['present', 'absent', 'late', 'excused'], true) ? $status : 'present';
            Database::query(
                'INSERT INTO training_attendance
                    (company_id, training_session_id, training_session_day_id, training_participant_id, employee_id, status, notes, created_at)
                 VALUES
                    (:company_id, :session_id, :day_id, :participant_id, :employee_id, :status, :notes, NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes), updated_at = NOW(), deleted_at = NULL',
                [
                    'company_id' => (int) $day['company_id'],
                    'session_id' => (int) $day['training_session_id'],
                    'day_id' => (int) $day['id'],
                    'participant_id' => (int) $participant['id'],
                    'employee_id' => (int) $participant['employee_id'],
                    'status' => $status,
                    'notes' => trim($payload['notes'] ?? '') ?: null,
                ]
            );

            $this->recalculateParticipant((int) $participant['id']);
        }

        Database::query('UPDATE training_session_days SET status = "completed", updated_at = NOW() WHERE id = :id', ['id' => $dayId]);
        Database::query('UPDATE training_sessions SET status = IF(status = "planned", "ongoing", status), updated_at = NOW() WHERE id = :id', ['id' => (int) $day['training_session_id']]);

        return true;
    }

    public function finalize(int $sessionId, ?int $companyId): bool
    {
        $session = $this->findSession($sessionId, $companyId);
        if (!$session) {
            return false;
        }

        foreach ($this->participants($sessionId) as $participant) {
            $this->recalculateParticipant((int) $participant['id'], (float) $session['min_attendance_rate']);
        }

        Database::query('UPDATE training_sessions SET status = "completed", updated_at = NOW() WHERE id = :id', ['id' => $sessionId]);

        return true;
    }

    public function employeeHistory(int $employeeId, ?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'tp');
        $params['employee_id'] = $employeeId;

        return Database::query(
            "SELECT tp.*, ts.title AS session_title, ts.start_date, ts.end_date, tc.title AS course_title, tc.category
             FROM training_participants tp
             INNER JOIN training_sessions ts ON ts.id = tp.training_session_id
             INNER JOIN training_courses tc ON tc.id = ts.training_course_id
             WHERE tp.employee_id = :employee_id AND tp.deleted_at IS NULL {$scope}
             ORDER BY ts.start_date DESC",
            $params
        )->fetchAll();
    }

    private function createSessionDays(int $sessionId, int $companyId, array $data): void
    {
        $start = new DateTimeImmutable($data['start_date']);
        $end = (new DateTimeImmutable($data['end_date']))->modify('+1 day');
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);

        foreach ($period as $date) {
            Database::query(
                'INSERT INTO training_session_days
                    (company_id, training_session_id, day_date, topic, start_time, end_time, created_at)
                 VALUES
                    (:company_id, :session_id, :day_date, :topic, :start_time, :end_time, NOW())',
                [
                    'company_id' => $companyId,
                    'session_id' => $sessionId,
                    'day_date' => $date->format('Y-m-d'),
                    'topic' => trim($data['daily_topic'] ?? '') ?: null,
                    'start_time' => $data['start_time'] ?: null,
                    'end_time' => $data['end_time'] ?: null,
                ]
            );
        }
    }

    private function findDay(int $dayId, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'training_session_days');
        $params['id'] = $dayId;

        $row = Database::query(
            "SELECT * FROM training_session_days
             WHERE id = :id AND deleted_at IS NULL {$scope}
             LIMIT 1",
            $params
        )->fetch();

        return $row ?: null;
    }

    private function participant(int $participantId, int $sessionId): ?array
    {
        $row = Database::query(
            'SELECT * FROM training_participants
             WHERE id = :id AND training_session_id = :session_id AND deleted_at IS NULL
             LIMIT 1',
            ['id' => $participantId, 'session_id' => $sessionId]
        )->fetch();

        return $row ?: null;
    }

    private function recalculateParticipant(int $participantId, ?float $threshold = null): void
    {
        $participant = Database::query('SELECT * FROM training_participants WHERE id = :id', ['id' => $participantId])->fetch();
        if (!$participant) {
            return;
        }

        $session = Database::query('SELECT * FROM training_sessions WHERE id = :id', ['id' => (int) $participant['training_session_id']])->fetch();
        $totalDays = (int) Database::query(
            'SELECT COUNT(*) FROM training_session_days WHERE training_session_id = :session_id AND deleted_at IS NULL',
            ['session_id' => (int) $participant['training_session_id']]
        )->fetchColumn();
        $presentDays = (int) Database::query(
            "SELECT COUNT(*) FROM training_attendance
             WHERE training_participant_id = :participant_id
             AND deleted_at IS NULL
             AND status IN ('present', 'late')",
            ['participant_id' => $participantId]
        )->fetchColumn();
        $excusedDays = (int) Database::query(
            "SELECT COUNT(*) FROM training_attendance
             WHERE training_participant_id = :participant_id
             AND deleted_at IS NULL
             AND status = 'excused'",
            ['participant_id' => $participantId]
        )->fetchColumn();

        $rate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;
        $threshold = $threshold ?? (float) ($session['min_attendance_rate'] ?? 80);
        $finalStatus = 'invited';

        if ($presentDays === 0 && $excusedDays > 0) {
            $finalStatus = 'excused';
        } elseif ($presentDays === 0 && $totalDays > 0) {
            $finalStatus = 'absent';
        } elseif ($rate >= $threshold) {
            $finalStatus = 'completed';
        } else {
            $finalStatus = 'failed';
        }

        Database::query(
            'UPDATE training_participants
             SET attendance_rate = :rate, final_status = :status, certificate_issued = :certificate, updated_at = NOW()
             WHERE id = :id',
            [
                'rate' => $rate,
                'status' => $finalStatus,
                'certificate' => $finalStatus === 'completed' ? 1 : 0,
                'id' => $participantId,
            ]
        );
    }

    private function employeeBelongsToCompany(int $employeeId, int $companyId): bool
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM employees WHERE id = :id AND company_id = :company_id AND deleted_at IS NULL',
            ['id' => $employeeId, 'company_id' => $companyId]
        )->fetchColumn() > 0;
    }

    private function scope(?int $companyId, string $table): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }
}
