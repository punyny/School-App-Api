<?php

namespace App\Policies;

use App\Models\IncidentReport;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolMembership;

class IncidentReportPolicy
{
    use ChecksSchoolMembership;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, IncidentReport $incidentReport): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($this->isStudentOwner($user, (int) $incidentReport->student_id)) {
            return true;
        }

        if ($this->isParentOfStudent($user, (int) $incidentReport->student_id)) {
            return true;
        }

        return $this->managesStudent($user, (int) $incidentReport->student_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher'], true);
    }

    public function update(User $user, IncidentReport $incidentReport): bool
    {
        return $this->create($user)
            && $this->managesStudent($user, (int) $incidentReport->student_id);
    }

    public function delete(User $user, IncidentReport $incidentReport): bool
    {
        return $this->update($user, $incidentReport);
    }

    public function updateAcknowledgment(User $user, IncidentReport $incidentReport): bool
    {
        return $this->isParentOfStudent($user, (int) $incidentReport->student_id);
    }
}
