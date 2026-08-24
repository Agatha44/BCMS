import dayjs from 'dayjs';

export const BRAND = '#962E32';
export const BRAND_DARK = '#7A2326';
export const EMPTY_VALUE = 'N/A';
export const DATE_FORMAT = 'YYYY-MM-DD';
export const NIDA_DIGIT_LENGTH = 20;

export const MODAL_STYLES = {
  body: { padding: 0 },
  content: { padding: 0, overflow: 'hidden' },
};

export const buildDisplayName = (user) => {
  if (!user) return '';
  if (user.full_name && String(user.full_name).trim()) return String(user.full_name).trim();
  return [user.first_name, user.middle_name, user.surname]
    .map((part) => (part == null ? '' : String(part).trim()))
    .filter(Boolean)
    .join(' ');
};

export const getUserNida = (user) => user?.nida || user?.national_id;

export const isUserActive = (status) => status === 1 || status === '1' || status === true;

export const isPastDate = (date) => date && date.isBefore(dayjs().startOf('day'), 'day');

export const disablePastDates = (current) => {
  if (!current) return false;
  return current.isBefore(dayjs().startOf('day'), 'day');
};

const normalizeRoleEntry = (roleAssignment) => {
  const role = roleAssignment?.role || roleAssignment || {};
  return {
    ...roleAssignment,
    id: role.id || role.role_id || roleAssignment?.id,
    name: role.role_name || role.name || roleAssignment?.role_name,
    user_role_id: roleAssignment?.user_role_id ?? roleAssignment?.assignment_id,
    start_date:
      roleAssignment?.start_date ||
      roleAssignment?.from_date ||
      roleAssignment?.assigned_date ||
      role?.start_date,
    end_date:
      roleAssignment?.end_date ||
      roleAssignment?.to_date ||
      roleAssignment?.expiration_date ||
      roleAssignment?.expires_at ||
      role?.end_date,
    is_currently_effective: roleAssignment?.is_currently_effective,
    assignment_is_active: roleAssignment?.assignment_is_active,
  };
};

export const normalizeRoleList = (roles) => {
  const roleArray = Array.isArray(roles) ? roles : roles ? [roles] : [];
  return roleArray.map(normalizeRoleEntry).filter((role) => role.id || role.name);
};

export const normalizeBridgeUser = (employee) => {
  if (!employee) return null;
  return {
    ...employee,
    national_id: getUserNida(employee),
    full_name: buildDisplayName(employee),
    roles: normalizeRoleList(employee.roles || employee.role || []),
  };
};

export const normalizeBridgeUserFromResponse = (data) => {
  if (!data) return null;
  let user = data.user || data.employee || data.bridge_user || data;
  if (Array.isArray(data.employees) && data.employees[0]) {
    user = data.employees[0];
  }
  if (user?.employee) user = user.employee;
  if (user?.user) user = user.user;
  if (user?.bridge_user) user = user.bridge_user;
  return normalizeBridgeUser(user);
};

export const mapUserToEditFormValues = (user) => ({
  nida: getUserNida(user) || '',
  pf_number: user?.pf_number || user?.pfno || user?.pfNumber || '',
  first_name: user?.first_name || user?.fname || '',
  middle_name: user?.middle_name || user?.mname || '',
  surname: user?.surname || user?.sname || '',
  email: user?.email || '',
  phone: user?.phone || user?.mobile || '',
});

export const buildUserPayload = (values, { includeNida = false } = {}) => {
  const payload = {
    pf_number: values.pf_number?.trim() || undefined,
    first_name: values.first_name?.trim(),
    middle_name: values.middle_name?.trim() || undefined,
    surname: values.surname?.trim(),
    email: values.email?.trim(),
    phone: values.phone?.trim(),
  };
  if (includeNida) {
    payload.nida = values.nida?.trim();
  }
  return payload;
};

export const roleRowKey = (role) => role.user_role_id ?? role.id ?? role.name;

export const formatRoleDate = (value) => {
  if (!value) return null;
  const d = dayjs(value);
  return d.isValid() ? d.format('D MMM YYYY') : null;
};

export const getRoleAssignmentBadge = (role) => {
  if (role.is_currently_effective === true) {
    return { label: 'Active', color: 'green' };
  }

  const today = dayjs().startOf('day');
  const start = role.start_date ? dayjs(role.start_date).startOf('day') : null;
  const end = role.end_date ? dayjs(role.end_date).startOf('day') : null;

  if (start?.isValid() && start.isAfter(today)) {
    return { label: 'Scheduled', color: 'blue' };
  }
  if (end?.isValid() && end.isBefore(today)) {
    return { label: 'Expired', color: 'default' };
  }
  if (role.assignment_is_active === false) {
    return { label: 'Inactive', color: 'red' };
  }
  return { label: 'Active', color: 'green' };
};

export const isRoleToggleOn = (role) => {
  if (role.assignment_is_active === false) return false;
  const badge = getRoleAssignmentBadge(role);
  return badge.label !== 'Expired' && badge.label !== 'Inactive';
};

export const getTableRowKey = (record) => {
  if (record?.id != null) return String(record.id);
  const nida = getUserNida(record);
  if (nida) return nida;
  if (record?.email) return record.email;
  if (record?.username) return record.username;
  return `${record?.email || ''}-${record?.username || ''}-${nida || ''}`;
};
