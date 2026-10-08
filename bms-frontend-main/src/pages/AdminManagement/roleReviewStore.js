const STORAGE_KEY = 'bms.roleMonthlyReviews';

const readReviews = () => {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
};

const persistReviews = (reviews) => {
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(reviews));
};

export const monthKeyFromDate = (value) => {
  const date = value?.format ? value : null;
  if (date) return date.format('YYYY-MM');
  const fallback = new Date(value || Date.now());
  if (Number.isNaN(fallback.getTime())) return new Date().toISOString().slice(0, 7);
  return `${fallback.getFullYear()}-${String(fallback.getMonth() + 1).padStart(2, '0')}`;
};

export const getMonthReviews = (monthKey) => {
  const all = readReviews();
  return all[monthKey] && typeof all[monthKey] === 'object' ? all[monthKey] : {};
};

export const getRoleReview = (monthKey, roleKey) => getMonthReviews(monthKey)[roleKey] || null;

export const markRoleReviewed = (monthKey, roleKey, payload) => {
  const all = readReviews();
  const monthReviews = { ...(all[monthKey] || {}) };
  monthReviews[roleKey] = {
    ...payload,
    reviewedAt: payload.reviewedAt || new Date().toISOString(),
  };
  persistReviews({ ...all, [monthKey]: monthReviews });
  return monthReviews[roleKey];
};

export const unmarkRoleReviewed = (monthKey, roleKey) => {
  const all = readReviews();
  const monthReviews = { ...(all[monthKey] || {}) };
  delete monthReviews[roleKey];
  persistReviews({ ...all, [monthKey]: monthReviews });
};

export const userReviewKey = (user) => {
  const id = user?.id ?? user?.user_id;
  if (id != null && String(id).trim() !== '') return `user:${id}`;
  const username = String(user?.username || user?.user_name || '').trim().toLowerCase();
  if (username) return `user:${username}`;
  const nida = user?.nida || user?.national_id;
  if (nida) return `user:${nida}`;
  return `user:${String(user?.email || 'unknown').trim().toLowerCase()}`;
};

export const getUserReview = (monthKey, user) => getRoleReview(monthKey, userReviewKey(user));

export const markUserReviewed = (monthKey, user, payload) => {
  const existing = getUserReview(monthKey, user) || {};
  return markRoleReviewed(monthKey, userReviewKey(user), {
    ...existing,
    ...payload,
    actions: payload.actions || existing.actions || [],
  });
};

export const recordUserReviewAction = (monthKey, user, payload) => {
  const existing = getUserReview(monthKey, user) || {};
  const action = {
    type: payload.type,
    roles: payload.roles || [],
    justification: String(payload.justification || '').trim(),
    by: payload.by || '',
    role: payload.role || '',
    at: payload.at || new Date().toISOString(),
  };
  return markRoleReviewed(monthKey, userReviewKey(user), {
    ...existing,
    username: payload.username || existing.username,
    accountName: payload.accountName || existing.accountName,
    assignedRoles: payload.assignedRoles ?? existing.assignedRoles,
    reviewedBy: payload.by || existing.reviewedBy,
    reviewedAt: existing.reviewedAt || action.at,
    actions: [...(existing.actions || []), action],
  });
};
