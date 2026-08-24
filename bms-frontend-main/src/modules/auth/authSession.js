/**
 * Auth state lives in sessionStorage so the login session ends when the browser tab/window closes.
 * selectedModule and selectedRole are stored in sessionStorage via the app reducer (same tab session as auth).
 */

const TOKEN_KEY = 'auth_token';
const LEGACY_TOKEN_KEY = 'userToken';
const USER_KEY = 'user';
const MUST_CHANGE_PASSWORD_KEY = 'must_change_password';

const LEGACY_LOCAL_KEYS = [TOKEN_KEY, LEGACY_TOKEN_KEY, USER_KEY, MUST_CHANGE_PASSWORD_KEY];

/** Remove auth keys from localStorage (legacy); auth must not persist across browser restarts. */
export const clearLegacyAuthFromLocalStorage = () => {
  LEGACY_LOCAL_KEYS.forEach((key) => localStorage.removeItem(key));
};

export const getAuthToken = () =>
  sessionStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(LEGACY_TOKEN_KEY) || '';

export const setAuthToken = (token) => {
  if (!token) return;
  sessionStorage.setItem(TOKEN_KEY, token);
  sessionStorage.setItem(LEGACY_TOKEN_KEY, token);
  clearLegacyAuthFromLocalStorage();
};

export const getStoredUser = () => {
  const raw = sessionStorage.getItem(USER_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
};

export const setStoredUser = (user) => {
  if (user) {
    sessionStorage.setItem(USER_KEY, JSON.stringify(user));
  } else {
    sessionStorage.removeItem(USER_KEY);
  }
  clearLegacyAuthFromLocalStorage();
};

export const getMustChangePasswordFlag = () =>
  sessionStorage.getItem(MUST_CHANGE_PASSWORD_KEY) === 'true';

export const setMustChangePasswordFlag = (required) => {
  if (required) {
    sessionStorage.setItem(MUST_CHANGE_PASSWORD_KEY, 'true');
  } else {
    sessionStorage.removeItem(MUST_CHANGE_PASSWORD_KEY);
  }
  clearLegacyAuthFromLocalStorage();
};

export const setAuthSession = ({ token, user, mustChangePassword }) => {
  if (token) setAuthToken(token);
  if (user !== undefined) setStoredUser(user);
  if (mustChangePassword !== undefined) setMustChangePasswordFlag(mustChangePassword);
};

export const clearAuthSession = () => {
  sessionStorage.removeItem(TOKEN_KEY);
  sessionStorage.removeItem(LEGACY_TOKEN_KEY);
  sessionStorage.removeItem(USER_KEY);
  sessionStorage.removeItem(MUST_CHANGE_PASSWORD_KEY);
  clearLegacyAuthFromLocalStorage();
};

export const hasAuthSession = () => Boolean(getAuthToken());
