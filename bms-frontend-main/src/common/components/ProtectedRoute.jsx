import { Navigate, useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { useEffect } from 'react';
import { useDispatch } from 'react-redux';
import { setAuthenticated, setUser } from '../../store/reducers/auth';
import { apiService } from '../../services/api.jsx';
import { userMustChangePassword } from '../../modules/auth/authUtils.js';
import {
  getAuthToken,
  getMustChangePasswordFlag,
  getStoredUser,
  hasAuthSession,
  setMustChangePasswordFlag
} from '../../modules/auth/authSession.js';

const ProtectedRoute = ({ children }) => {
  const { authenticated, user } = useSelector((state) => state.auth);
  const location = useLocation();
  const dispatch = useDispatch();

  useEffect(() => {
    const token = getAuthToken();

    if (token && !authenticated) {
      apiService.refreshToken();

      const storedUser = getStoredUser();
      if (storedUser) {
        dispatch(setUser(storedUser));
      }

      dispatch(setAuthenticated(true));
    } else if (!token && authenticated) {
      dispatch(setAuthenticated(false));
      dispatch(setUser(null));
    }
  }, [authenticated, dispatch]);

  const isAuthenticated = authenticated || hasAuthSession();

  let mustChangePassword = getMustChangePasswordFlag();

  if (!mustChangePassword && user) {
    mustChangePassword = userMustChangePassword(user);
  }

  if (!mustChangePassword) {
    const storedUser = getStoredUser();
    if (storedUser) {
      mustChangePassword = userMustChangePassword(storedUser);
    }
  }

  if (mustChangePassword) {
    setMustChangePasswordFlag(true);
  }

  if (isAuthenticated && mustChangePassword && location.pathname !== '/change-password') {
    return <Navigate to="/change-password" replace />;
  }

  if (!isAuthenticated) {
    // Redirect to login page with return url
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  return children;
};

export default ProtectedRoute;

