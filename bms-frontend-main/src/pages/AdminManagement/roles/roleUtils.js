export const BRAND = '#962E32';
export const BRAND_DARK = '#7A2326';

export const apiMessage = (response, fallback = 'Request failed') =>
  response?.message || fallback;

export const isRoleActive = (isActive) =>
  isActive === true || isActive === 1 || isActive === '1';

export const normalizeRoleRecord = (data) => {
  if (!data) return null;
  if (data.role && typeof data.role === 'object') return data.role;
  return data;
};

export const normalizeModuleList = (data) => {
  if (!data) return [];
  if (Array.isArray(data)) return data;
  if (Array.isArray(data.modules)) return data.modules;
  return [];
};

export const normalizeActiveRoles = (data) => {
  if (!data) return [];
  if (Array.isArray(data)) return data;
  if (Array.isArray(data.roles)) return data.roles;
  return [];
};
