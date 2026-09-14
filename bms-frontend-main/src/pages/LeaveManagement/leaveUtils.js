export const LEAVE_TYPES = [
  'Annual Leave',
  'Sick Leave',
  'Maternity Leave',
  'Paternity Leave',
  'Compassionate Leave',
  'Emergency Leave',
  'Study Leave',
];

export const LEAVE_STATUS = {
  APPLIED: 'Applied',
  VERIFIED: 'Verified',
  APPROVED: 'Approved',
  REJECTED: 'Rejected',
};

export const LEAVE_ROLES = {
  EMPLOYEE: 'employee',
  ADMINISTRATOR: 'employee administrator',
  APPROVER: 'employee approver',
};

export const getLeaveStatusStyle = (status) => {
  const value = String(status || '').trim();
  if (value === LEAVE_STATUS.APPROVED) {
    return { color: '#166534', backgroundColor: '#dcfce7' };
  }
  if (value === LEAVE_STATUS.REJECTED) {
    return { color: '#991b1b', backgroundColor: '#fee2e2' };
  }
  if (value === LEAVE_STATUS.VERIFIED) {
    return { color: '#0f766e', backgroundColor: '#ccfbf1' };
  }
  return { color: '#92400e', backgroundColor: '#fef3c7' };
};

export const countLeaveDays = (startDate, endDate) => {
  if (!startDate || !endDate) return 0;
  const start = startDate.startOf('day');
  const end = endDate.startOf('day');
  if (end.isBefore(start)) return 0;
  return end.diff(start, 'day') + 1;
};

export const normalizeLeaveRole = (roleName) => String(roleName || '').trim().toLowerCase();

export const isLeaveEmployee = (roleName) => normalizeLeaveRole(roleName) === LEAVE_ROLES.EMPLOYEE;
export const isLeaveAdministrator = (roleName) => normalizeLeaveRole(roleName) === LEAVE_ROLES.ADMINISTRATOR;
export const isLeaveApprover = (roleName) => normalizeLeaveRole(roleName) === LEAVE_ROLES.APPROVER;
