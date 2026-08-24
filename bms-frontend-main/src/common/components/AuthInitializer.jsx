import { useEffect } from 'react';
import { useDispatch } from 'react-redux';
import { setAuthenticated, setUser } from '../../store/reducers/auth';
import { apiService } from '../../services/api.jsx';
import { getAuthToken, getStoredUser } from '../../modules/auth/authSession.js';

const AuthInitializer = ({ children }) => {
  const dispatch = useDispatch();

  useEffect(() => {
    const token = getAuthToken();
    if (token) {
      apiService.refreshToken();
      dispatch(setAuthenticated(true));

      const storedUser = getStoredUser();
      if (storedUser) {
        dispatch(setUser(storedUser));
      }
    }
  }, [dispatch]);

  return children;
};

export default AuthInitializer;

