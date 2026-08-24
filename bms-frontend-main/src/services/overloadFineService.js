import { apiService } from './api.jsx';

// Overload Fine service migrated from bms-collection-management (TypeScript) to JS.
// This mirrors the original behaviour but delegates all HTTP work to apiService.request.
export const overloadFineService = {
  // Get all overload fines with pagination
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

    const endpoint = `/api/overload-fine/query${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    const response = await apiService.request(endpoint);

    if (response.success && response.data) {
      return response.data;
    }
    throw new Error(response.message || 'Failed to fetch overload fines');
  },

  // Get overload fine by ID
  async getById(id) {
    const response = await apiService.request(`/api/overload-fine/query-details/${id}`);
    if (response.success && response.data && response.data.length > 0) {
      return response.data[0];
    }
    throw new Error(response.message || 'Failed to fetch overload fine details');
  },

  // Create new overload fine
  async create(data) {
    const response = await apiService.request('/api/overload-fine/create', {
      method: 'POST',
      body: JSON.stringify(data)
    });
    if (response.success) {
      return response.data;
    }
    throw new Error(response.message || 'Failed to create overload fine');
  },

  // Update overload fine
  async update(data) {
    const response = await apiService.request('/api/overload-fine/edit-overload', {
      method: 'POST',
      body: JSON.stringify(data)
    });
    if (response.success) {
      return response.data;
    }
    throw new Error(response.message || 'Failed to update overload fine');
  },

  // Cancel bill
  async cancelBill(data) {
    const response = await apiService.request('/api/overload-fine/bill-cancellation', {
      method: 'POST',
      body: JSON.stringify(data)
    });
    if (response.success) {
      return response.data;
    }
    throw new Error(response.message || 'Failed to cancel bill');
  },

  // Get error reason for failed bills
  async getErrorReason(id) {
    const response = await apiService.request(`/api/overload-fine/view-reason/${id}`);
    if (response.success && response.data) {
      return response.data;
    }
    return [];
  },

  // Repost bill
  async repostBill(id) {
    const response = await apiService.request('/api/overload-fine/repost-bill', {
      method: 'POST',
      body: JSON.stringify({ id })
    });
    if (response.success) {
      return response.data;
    }
    throw new Error(response.message || 'Failed to repost bill');
  },

  // Print receipt (placeholder - no backend endpoint defined yet)
  async printReceipt(id) {
    // TODO: Wire this to a printer/receipt endpoint when available.
    // Keeping behaviour consistent with the original TS service.
    // eslint-disable-next-line no-console
    console.log('Print overload receipt:', id);
    return { success: true, message: 'Print functionality to be implemented' };
  }
};


