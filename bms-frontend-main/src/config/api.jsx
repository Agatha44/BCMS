// Environment-based API configuration
const getApiBaseUrl = () => {
  const env = import.meta.env.MODE || 'development';
  
  switch (env) {
    case 'production':
      return 'https://bcmspro-api.nssf.go.tz';
    case 'staging':
      return 'https://bridge-core-dev.nssf.go.tz';
    case 'development':
    default:
      return import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8001';
  }
};

export const API_CONFIG = {
  BASE_URL: getApiBaseUrl(),

  TIMEOUT: 30000, // 30 seconds
  RETRY_ATTEMPTS: 3
};

// Helper function to build full API URLs
export const buildApiUrl = (endpoint) => {
  const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
  return `${API_CONFIG.BASE_URL}${cleanEndpoint}`;
};

// Common API endpoints
export const API_ENDPOINTS = {
  AUTH: {
    LOGIN: '/api/auth/login',
    LOGOUT: '/api/auth/logout',
    REFRESH: '/api/auth/refresh',
  },
  ACCOUNTS: {
    LIST: '/api/accounts',
    CREATE: '/api/accounts',
    UPDATE: (id) => `/api/accounts/${id}`,
    DELETE: (id) => `/api/accounts/${id}`,
  },
  VEHICLES: {
    LIST: '/api/vehicles',
    CREATE: '/api/vehicles',
    UPDATE: (id) => `/api/vehicles/${id}`,
    DELETE: (id) => `/api/vehicles/${id}`,
  },
  TRANSACTIONS: {
    LIST: '/api/transactions',
    CREATE: '/api/transactions',
    UPDATE: (id) => `/api/transactions/${id}`,
    DELETE: (id) => `/api/transactions/${id}`,
  },
  REPORTS: {
    LIST: '/api/reports',
    GENERATE: '/api/reports/generate',
  },
  USERS: {
    LIST: '/api/users',
    CREATE: '/api/users',
    UPDATE: (id) => `/api/users/${id}`,
    DELETE: (id) => `/api/users/${id}`,
  },
  ROLES: {
    LIST: '/api/roles',
    CREATE: '/api/roles',
    UPDATE: (id) => `/api/roles/${id}`,
    DELETE: (id) => `/api/roles/${id}`,
  },
};