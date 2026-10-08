const STORAGE_KEY = 'bms.accountRequestNotifications';
const CHANGED_EVENT = 'bms-account-request-notifications';

const normalizeKey = (value) => String(value || '').trim().toLowerCase();

const formatRoles = (roles) => {
  if (Array.isArray(roles)) return roles.filter(Boolean).join(', ');
  return roles || 'the requested roles';
};

export const listAccountRequestNotifications = () => {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
};

const persistNotifications = (notifications) => {
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(notifications));
  window.dispatchEvent(new Event(CHANGED_EVENT));
};

export const subscribeAccountRequestNotifications = (onChange) => {
  const handler = () => onChange(listAccountRequestNotifications());
  window.addEventListener(CHANGED_EVENT, handler);
  return () => window.removeEventListener(CHANGED_EVENT, handler);
};

export const getUserIdentityKeys = (user) => {
  if (!user) return [];
  const keys = [];
  const id = user.id ?? user.user_id ?? user.employee_id;
  if (id != null && String(id).trim() !== '') keys.push(`id:${normalizeKey(id)}`);
  [user.username, user.user_name, user.pf_number, user.pfno].forEach((value) => {
    const username = String(value || '').trim();
    if (username) keys.push(`user:${normalizeKey(username)}`);
  });
  const email = String(user.email || '').trim();
  if (email) keys.push(`email:${normalizeKey(email)}`);
  const nida = String(user.nida || user.national_id || '').trim();
  if (nida) keys.push(`nida:${normalizeKey(nida)}`);
  const name = [user.full_name, [user.first_name, user.surname].filter(Boolean).join(' ')]
    .map((value) => String(value || '').trim())
    .find(Boolean);
  if (name) keys.push(`name:${normalizeKey(name)}`);
  return [...new Set(keys)];
};

export const getUsernameIdentityKeys = (user) =>
  [...new Set(
    [user?.username, user?.user_name, user?.pf_number, user?.pfno]
      .map((value) => String(value || '').trim())
      .filter(Boolean)
      .map((value) => `user:${normalizeKey(value)}`)
  )];

export const listNotificationsForUser = (user) => {
  const keys = new Set(getUserIdentityKeys(user));
  const usernameKeys = new Set(getUsernameIdentityKeys(user));
  if (keys.size === 0) return [];
  return listAccountRequestNotifications().filter((item) => {
    const recipientKeys = item.recipientKeys || [];
    if (item.recipientType === 'username') {
      return recipientKeys.some((key) => usernameKeys.has(key));
    }
    return recipientKeys.some((key) => keys.has(key));
  });
};

export const unreadNotificationCountForUser = (user) =>
  listNotificationsForUser(user).filter((item) => !item.read).length;

export const markAccountRequestNotificationRead = (id) => {
  const next = listAccountRequestNotifications().map((item) =>
    String(item.id) === String(id) ? { ...item, read: true } : item
  );
  persistNotifications(next);
  return next;
};

export const markAllAccountRequestNotificationsRead = (user) => {
  const keys = new Set(getUserIdentityKeys(user));
  const next = listAccountRequestNotifications().map((item) =>
    (item.recipientKeys || []).some((key) => keys.has(key)) ? { ...item, read: true } : item
  );
  persistNotifications(next);
  return next;
};

const addNotification = (notification) => {
  const next = [notification, ...listAccountRequestNotifications()];
  persistNotifications(next);
  return notification;
};

const accountNameOf = (request) =>
  String(request?.accountName || request?.staffName || request?.name || '').trim();

export const notifyAccountRequestDecision = ({ request, action, actor, comment }) => {
  const isGrant = action === 'grant';
  const statusLabel = isGrant ? 'granted' : 'revoked';
  const requestNo = request?.request_no || request?.id || 'this request';
  const accountName = accountNameOf(request) || 'the account';
  const requestorName = String(request?.requestedBy || '').trim() || 'the requestor';
  const roles = formatRoles(request?.requestedRole);
  const moduleName = request?.moduleName || 'the requested module';
  const now = new Date().toISOString();
  const ids = listAccountRequestNotifications().map((item) => Number(item.id) || 0);
  let nextId = (ids.length ? Math.max(...ids) : 0) + 1;

  const requestorKeys = [];
  if (request?.requestedByUserId != null) requestorKeys.push(`id:${normalizeKey(request.requestedByUserId)}`);
  if (request?.requestedByUsername) requestorKeys.push(`user:${normalizeKey(request.requestedByUsername)}`);
  if (requestorName) requestorKeys.push(`name:${normalizeKey(requestorName)}`);

  const accountKeys = [];
  if (accountName) accountKeys.push(`name:${normalizeKey(accountName)}`);

  const requestorMessage = isGrant
    ? `Account request ${requestNo} for ${accountName} has been granted. Roles: ${roles}. Module: ${moduleName}.`
    : `Account request ${requestNo} for ${accountName} has been revoked. Roles: ${roles}. Module: ${moduleName}.`;
  const accountMessage = isGrant
    ? `Access for ${accountName} on request ${requestNo} has been granted. Roles: ${roles}. Module: ${moduleName}.`
    : `Access for ${accountName} on request ${requestNo} has been revoked. Roles: ${roles}. Module: ${moduleName}.`;

  const created = [
    addNotification({
      id: nextId,
      recipientType: 'requestor',
      recipientName: requestorName,
      recipientKeys: [...new Set(requestorKeys.filter(Boolean))],
      title: isGrant ? 'Account request granted' : 'Account request revoked',
      message: requestorMessage,
      comment: comment || '',
      actor: actor || '',
      requestNo,
      action: statusLabel,
      read: false,
      created_at: now,
    }),
  ];
  nextId += 1;

  if (normalizeKey(accountName) && normalizeKey(accountName) !== normalizeKey(requestorName)) {
    created.push(
      addNotification({
        id: nextId,
        recipientType: 'account',
        recipientName: accountName,
        recipientKeys: [...new Set(accountKeys.filter(Boolean))],
        title: isGrant ? 'Account access granted' : 'Account access revoked',
        message: accountMessage,
        comment: comment || '',
        actor: actor || '',
        requestNo,
        action: statusLabel,
        read: false,
        created_at: now,
      })
    );
  }

  return created;
};

export const notifyRoleReviewDecision = ({
  user,
  username,
  action,
  roles,
  actor,
  justification,
}) => {
  const isApprove = action === 'approve';
  const statusLabel = isApprove ? 'approved' : 'revoked';
  const roleNames = (Array.isArray(roles) ? roles : [roles])
    .map((role) => String(role || '').trim())
    .filter(Boolean);
  const usernameLabel = String(
    username || user?.username || user?.user_name || user?.pf_number || ''
  ).trim();
  if (!usernameLabel || !roleNames.length) return [];

  const now = new Date().toISOString();
  const ids = listAccountRequestNotifications().map((item) => Number(item.id) || 0);
  let nextId = (ids.length ? Math.max(...ids) : 0) + 1;
  const recipientKeys = [`user:${normalizeKey(usernameLabel)}`];

  return roleNames.map((roleName) => {
    const notification = addNotification({
      id: nextId,
      recipientType: 'username',
      recipientName: usernameLabel,
      recipientKeys,
      title: isApprove ? 'Assigned role approved' : 'Assigned role revoked',
      message: `Your assigned role ${roleName} has been ${statusLabel}.`,
      comment: justification || '',
      commentLabel: 'Justification',
      actor: actor || '',
      requestNo: '',
      action: statusLabel,
      roleName,
      read: false,
      created_at: now,
    });
    nextId += 1;
    return notification;
  });
};

export const notifyRequestorRoleAlreadyAssigned = ({
  requestorName,
  requestorUserId,
  requestorUsername,
  accountName,
  assignedRoles,
  requestNo,
}) => {
  const rolesText = formatRoles(assignedRoles);
  const plural = Array.isArray(assignedRoles) && assignedRoles.length > 1;
  const requestorKeys = [];
  if (requestorUserId != null) requestorKeys.push(`id:${normalizeKey(requestorUserId)}`);
  if (requestorUsername) requestorKeys.push(`user:${normalizeKey(requestorUsername)}`);
  if (requestorName) requestorKeys.push(`name:${normalizeKey(requestorName)}`);

  const ids = listAccountRequestNotifications().map((item) => Number(item.id) || 0);
  const nextId = (ids.length ? Math.max(...ids) : 0) + 1;
  const requestLabel = requestNo ? ` on request ${requestNo}` : '';

  return addNotification({
    id: nextId,
    recipientType: 'requestor',
    recipientName: requestorName || 'the requestor',
    recipientKeys: [...new Set(requestorKeys.filter(Boolean))],
    title: 'Role already assigned',
    message: `The role${plural ? 's' : ''} ${rolesText} ${plural ? 'are' : 'is'} already assigned to ${accountName || 'this account'}${requestLabel}.`,
    comment: '',
    actor: '',
    requestNo: requestNo || '',
    action: 'already-assigned',
    read: false,
    created_at: new Date().toISOString(),
  });
};
