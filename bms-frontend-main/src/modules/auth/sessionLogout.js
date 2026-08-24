import { apiService } from '../../services/api.jsx';
import { clearAuthSession } from './authSession.js';
import { resetAuthState } from '../../store/reducers/auth.js';
import { resetAppState } from '../../store/reducers/app.js';

/** Clear session storage, Redux auth/app state, and redirect to login. */
export async function performSessionLogout({ dispatch, navigate, message, reason = 'inactivity' }) {
  try {
    await apiService.logout();
  } catch {
    // Proceed with local cleanup even if the API call fails.
  }

  clearAuthSession();
  dispatch(resetAuthState());
  dispatch(resetAppState());

  sessionStorage.removeItem('selectedModule');
  sessionStorage.removeItem('selectedRole');
  localStorage.removeItem('selectedModule');
  localStorage.removeItem('selectedRole');

  if (message) {
    const text =
      reason === 'inactivity'
        ? 'Your session ended due to inactivity. Please sign in again.'
        : 'You have been signed out. Please sign in again.';
    message.warning(text);
  }

  navigate('/login', { replace: true });
}
