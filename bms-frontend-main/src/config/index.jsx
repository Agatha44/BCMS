// Environment-based configuration
const getEnvironmentConfig = () => {
  const env = import.meta.env.MODE || 'development';

  switch (env) {
    case 'production':
      return {
        API_BASE_URL: 'https://bcmspro-api.nssf.go.tz',
        APP_NAME: 'Bridge Collection Management System',
        VERSION: '1.0.0',
      };
    case 'staging':
      return {
        API_BASE_URL: 'https://bridge-core-dev.nssf.go.tz',
        APP_NAME: 'Bridge Collection Management System',
        VERSION: '1.0.0',
      };
    case 'development':
    default:
      return {
        // Empty in local so the Vite proxy forwards /api to Laravel on 8000.
        API_BASE_URL: import.meta.env.VITE_API_BASE_URL ?? '',
        APP_NAME: 'Bridge Collection Management System',
        VERSION: '1.0.0',
      };
  }
};

export const CONFIG = {
  ...getEnvironmentConfig(),
  // API Configuration
  API_CONFIG: {
    BASE_URL: getEnvironmentConfig().API_BASE_URL,
    TIMEOUT: 30000, // 30 seconds
    RETRY_ATTEMPTS: 3,
  },
  // App-wide settings
  APP: {
    NAME: 'Bridge Collection Management System',
    VERSION: '1.0.0',
    DESCRIPTION: 'NSSF Bridge Collection Management System',
  },
  // Feature flags
  FEATURES: {
    DARK_MODE: true,
    MULTI_LANGUAGE: false,
    DEBUG_MODE: import.meta.env.DEV,
  },
  // UI Configuration
  UI: {
    THEME: {
      PRIMARY_COLOR: '#1e40af', // blue-800
      SECONDARY_COLOR: '#64748b', // slate-500
      SUCCESS_COLOR: '#059669', // emerald-600
      WARNING_COLOR: '#d97706', // amber-600
      ERROR_COLOR: '#dc2626', // red-600
    },
    LAYOUT: {
      SIDEBAR_WIDTH: '280px',
      HEADER_HEIGHT: '64px',
    },
  },
};

// Re-export API config
export * from './api';
