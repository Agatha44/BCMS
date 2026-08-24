import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  user: null,
  authenticated: false,
  error: null,
  loading: false
};

const auth = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setUser: (state, action) => {
      state.user = action.payload;
    },
    setError: (state, action) => {
      state.error = action.payload;
    },
    setLoading: (state, action) => {
      state.loading = action.payload;
    },
    setAuthenticated: (state, action) => {
      state.authenticated = action.payload;
    },
    resetAuthState: (state) => {
      // Reset auth state to initial values
      state.user = null;
      state.authenticated = false;
      state.error = null;
      state.loading = false;
    }
  }
});

export default auth.reducer;
export const { setUser, setError, setLoading, setAuthenticated, resetAuthState } = auth.actions;
