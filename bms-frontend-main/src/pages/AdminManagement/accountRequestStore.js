const STORAGE_KEY = 'bms.accountRequests';

export const listAccountRequests = () => {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
};

const persistAccountRequests = (requests) => {
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(requests));
};

export const nextAccountRequestId = () => {
  const ids = listAccountRequests().map((request) => Number(request.id) || 0);
  return (ids.length ? Math.max(...ids) : 0) + 1;
};

export const addAccountRequest = (request) => {
  const next = [request, ...listAccountRequests()];
  persistAccountRequests(next);
  return next;
};

export const updateAccountRequest = (id, patch) => {
  const next = listAccountRequests().map((request) =>
    String(request.id) === String(id) ? { ...request, ...patch } : request
  );
  persistAccountRequests(next);
  return next;
};

const normalizeName = (value) => String(value || '').trim().toLowerCase();

const rolesFromRequest = (request) => {
  const roles = Array.isArray(request?.requestedRole) ? request.requestedRole : [request?.requestedRole];
  return roles.map((role) => String(role || '').trim()).filter(Boolean);
};

export const assignedRolesForAccountName = (accountName) => {
  const name = normalizeName(accountName);
  if (!name) return [];
  const roles = new Set();
  listAccountRequests().forEach((request) => {
    const requestName = normalizeName(request.accountName || request.staffName || request.name);
    if (requestName !== name) return;
    if (String(request.status || '').toLowerCase() !== 'granted') return;
    rolesFromRequest(request).forEach((role) => roles.add(role));
  });
  return [...roles];
};

export const overlappingAssignedRoles = (accountName, requestedRoles) => {
  const assigned = new Set(assignedRolesForAccountName(accountName).map((role) => role.toLowerCase()));
  const requested = Array.isArray(requestedRoles) ? requestedRoles : [requestedRoles];
  return requested
    .map((role) => String(role || '').trim())
    .filter((role) => role && assigned.has(role.toLowerCase()));
};
