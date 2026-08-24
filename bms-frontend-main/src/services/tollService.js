import { apiService } from './api.jsx';

export const tollService = {
  getTollTransactions(params = {}) {
    const queryParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value === undefined || value === null || value === '') return;
      queryParams.append(key, String(value));
    });

    const qs = queryParams.toString();
    const endpoint = `/api/tolls/toll-transactions${qs ? `?${qs}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },
};
