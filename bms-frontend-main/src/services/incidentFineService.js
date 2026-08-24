import { apiService } from './api.jsx';

// Incident Fine service migrated from bms-collection-management (TypeScript) to JS.
// This is a thin wrapper around apiService.request so that call-sites in bcms-admin-pro
// can use a focused, domain-specific API.
export const incidentFineService = {
  // Get all incident fines with pagination
  async getAll(params) {
    const queryParams = new URLSearchParams();

    if (params) {
      if (params.page) queryParams.append('page', params.page.toString());
      if (params.per_page) queryParams.append('per_page', params.per_page.toString());
      if (params.search) queryParams.append('search', params.search);
      if (params.status) queryParams.append('status', params.status);
      if (params.sort_by) queryParams.append('sort_by', params.sort_by);
      if (params.sort_order) queryParams.append('sort_order', params.sort_order);
    }

    const endpoint = `/api/incident-fine/query${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    const response = await apiService.request(endpoint);

    if (response.success && response.data) {
      return response.data;
    }
    throw new Error(response.message || 'Failed to fetch incident fines');
  },

  // Get incident fine by ID
  async getById(id) {
    const response = await apiService.request(`/api/incident-fine/query-details/${id}`);
    if (response.success && response.data && response.data.length > 0) {
      return response.data[0];
    }
    throw new Error(response.message || 'Failed to fetch incident fine details');
  },

  // Create new incident fine
  async create(data) {
    const response = await apiService.request('/api/incident-fine/create', {
      method: 'POST',
      body: JSON.stringify(data)
    });
    if (response.success) {
      return response.data;
    }
    throw new Error(response.message || 'Failed to create incident fine');
  },

  // Update incident fine
  async update(data) {
    const response = await apiService.request('/api/incident-fine/edit-incident', {
      method: 'POST',
      body: JSON.stringify(data)
    });
    if (response.success) {
      return response.data;
    }
    throw new Error(response.message || 'Failed to update incident fine');
  },

  // Cancel bill
  async cancelBill(data) {
    const response = await apiService.request('/api/incident-fine/bill-cancellation', {
      method: 'POST',
      body: JSON.stringify(data)
    });
    if (response.success) {
      return response.data;
    }
    throw new Error(response.message || 'Failed to cancel bill');
  },

  // Repost bill (for failed transactions)
  async repostBill(id) {
    const response = await apiService.request('/api/incident-fine/repost-bill', {
      method: 'POST',
      body: JSON.stringify({ id })
    });
    if (response.success) {
      return response.data;
    }
    throw new Error(response.message || 'Failed to repost bill');
  },

  // Get error reason for failed bills
  async getErrorReason(id) {
    const response = await apiService.request(`/api/incident-fine/view-reason/${id}`);
    if (response.success && response.data) {
      return response.data;
    }
    return [];
  },

  // Print bill (placeholder - no backend endpoint defined yet)
  async printBill(id) {
    // TODO: Wire this to a printer/receipt endpoint when available.
    // Keeping behaviour consistent with the original TS service.
    // eslint-disable-next-line no-console
    console.log('Print incident bill:', id);
    return { success: true, message: 'Print functionality to be implemented' };
  },

  // Print receipt (placeholder - no backend endpoint defined yet)
  async printReceipt(id) {
    // TODO: Wire this to a printer/receipt endpoint when available.
    // eslint-disable-next-line no-console
    console.log('Print incident receipt:', id);
    return { success: true, message: 'Print functionality to be implemented' };
  }
};


