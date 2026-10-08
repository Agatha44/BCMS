import { getStoredUser } from '../../modules/auth/authSession.js';

export const getSupportUsername = (user) => {
  const source = user || {};
  const nested = source.user || source.employee || source.auth_user || {};
  return (
    source.username ||
    source.user_name ||
    source.userName ||
    nested.username ||
    nested.user_name ||
    source.login ||
    getStoredUser()?.username ||
    ''
  ).trim();
};

export const getSupportRoleName = (role) => String(role || '').trim();

export const getNextSupportStatus = (status) => {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'opened') return 'Verified';
  if (normalized === 'verified') return 'Assigned';
  if (normalized === 'assigned') return 'Closed';
  if (normalized === 'reopened') return 'Assigned';
  if (normalized === 'on hold') return 'Assigned';
  if (normalized === 'closed' || normalized === 'resolved') return 'Reopened';
  if (normalized === 'cancelled' || normalized === 'canceled') return '—';
  return '—';
};

export const buildSupportMinute = (status, username, roleName, comment) => ({
  at: new Date().toISOString(),
  status: status || 'Opened',
  username: username || '—',
  roleName: roleName || '—',
  comment: typeof comment === 'string' ? comment.trim() : '',
  nextStatus: getNextSupportStatus(status),
});

export const appendSupportMinute = (request, status, username, roleName, comment) => {
  const entry = buildSupportMinute(status, username, roleName, comment);
  const existing = Array.isArray(request?.minutes) ? request.minutes : [];
  return [...existing, entry];
};

export const ensureSupportMinutes = (request, username, roleName) => {
  if (Array.isArray(request?.minutes) && request.minutes.length > 0) {
    return request.minutes;
  }
  return [buildSupportMinute(request?.status || 'Opened', username || getSupportUsername(), roleName)];
};
