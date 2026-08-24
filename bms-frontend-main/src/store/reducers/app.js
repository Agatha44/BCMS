import { createSlice } from '@reduxjs/toolkit';

const SELECTED_MODULE_KEY = 'selectedModule';
const SELECTED_ROLE_KEY = 'selectedRole';

const getInitialSelectedModule = () => {
  try {
    const legacy = localStorage.getItem(SELECTED_MODULE_KEY);
    if (legacy) {
      sessionStorage.setItem(SELECTED_MODULE_KEY, legacy);
      localStorage.removeItem(SELECTED_MODULE_KEY);
    }
    const stored = sessionStorage.getItem(SELECTED_MODULE_KEY);
    return stored ? stored : null;
  } catch (e) {
    return null;
  }
};

// Load selectedRole from sessionStorage (clears when browser tab closes)
const getInitialSelectedRole = () => {
  try {
    const legacy = localStorage.getItem(SELECTED_ROLE_KEY);
    if (legacy) {
      sessionStorage.setItem(SELECTED_ROLE_KEY, legacy);
      localStorage.removeItem(SELECTED_ROLE_KEY);
    }
    const stored = sessionStorage.getItem(SELECTED_ROLE_KEY);
    return stored ? stored : null;
  } catch (e) {
    return null;
  }
};

const initialState = {
  initializing: true,
  isVerified: false,
  openSideBarDrawer: false,
  isSideBarCollapsed: false,
  openSupportDeskDrawer: false,
  openUserActionCenterDrawer: false,
  canNavigateHome: true,
  selectedModule: getInitialSelectedModule(),
  selectedRole: getInitialSelectedRole()
};

const app = createSlice({
  name: 'app',
  initialState,
  reducers: {
    setInitializing: (state, action) => {
      state.initializing = action.payload;
    },
    setOpenSideBarDrawer: (state, action) => {
      state.openSideBarDrawer = action.payload;
    },
    setSideBarCollapsed: (state, action) => {
      state.isSideBarCollapsed = action.payload;
    },
    setCanNavigateHome: (state, action) => {
      state.canNavigateHome = action.payload;
    },
    setOpenUserActionCenterDrawer: (state, action) => {
      state.openUserActionCenterDrawer = action.payload;
    },
    setOpenSupportDeskDrawer: (state, action) => {
      state.openSupportDeskDrawer = action.payload;
    },
    setSelectedModule: (state, action) => {
      const nextModule = action.payload;
      const moduleChanged = state.selectedModule !== nextModule;

      if (!nextModule || moduleChanged) {
        state.selectedRole = null;
        try {
          sessionStorage.removeItem(SELECTED_ROLE_KEY);
          localStorage.removeItem(SELECTED_ROLE_KEY);
        } catch (e) {
          console.error('Error clearing selectedRole from storage:', e);
        }
      }

      state.selectedModule = nextModule;
      try {
        if (nextModule) {
          sessionStorage.setItem(SELECTED_MODULE_KEY, nextModule);
          localStorage.removeItem(SELECTED_MODULE_KEY);
        } else {
          sessionStorage.removeItem(SELECTED_MODULE_KEY);
          localStorage.removeItem(SELECTED_MODULE_KEY);
        }
      } catch (e) {
        console.error('Error saving selectedModule to sessionStorage:', e);
      }
    },
    setSelectedRole: (state, action) => {
      state.selectedRole = action.payload;
      try {
        if (action.payload) {
          sessionStorage.setItem(SELECTED_ROLE_KEY, action.payload);
          localStorage.removeItem(SELECTED_ROLE_KEY);
        } else {
          sessionStorage.removeItem(SELECTED_ROLE_KEY);
          localStorage.removeItem(SELECTED_ROLE_KEY);
        }
      } catch (e) {
        console.error('Error saving selectedRole to sessionStorage:', e);
      }
    },
    resetAppState: (state) => {
      // Reset app state to initial values, but preserve initializing flag
      state.isVerified = false;
      state.openSideBarDrawer = false;
      state.isSideBarCollapsed = false;
      state.openSupportDeskDrawer = false;
      state.openUserActionCenterDrawer = false;
      state.canNavigateHome = true;
      state.selectedModule = null;
      state.selectedRole = null;
      
      try {
        sessionStorage.removeItem(SELECTED_MODULE_KEY);
        sessionStorage.removeItem(SELECTED_ROLE_KEY);
        localStorage.removeItem(SELECTED_MODULE_KEY);
        localStorage.removeItem(SELECTED_ROLE_KEY);
      } catch (e) {
        console.error('Error removing selectedModule/selectedRole from storage:', e);
      }
    }
  }
});

export default app.reducer;
export const { setInitializing, setOpenSideBarDrawer, setSideBarCollapsed, setCanNavigateHome, setOpenUserActionCenterDrawer, setOpenSupportDeskDrawer, setSelectedModule, setSelectedRole, resetAppState } =
  app.actions;
