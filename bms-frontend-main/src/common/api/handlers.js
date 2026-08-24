import { getAuthToken } from '../../modules/auth/authSession.js';

export function getToken() {
  return getAuthToken();
}
