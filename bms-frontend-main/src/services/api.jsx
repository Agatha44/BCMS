import { CONFIG } from '../config';
import {
  clearAuthSession,
  clearLegacyAuthFromLocalStorage,
  getAuthToken,
  setAuthToken
} from '../modules/auth/authSession.js';

const UNAUTHORIZED_STATUS = 401;
const LOGIN_PATH = '/login';
const PUBLIC_AUTH_ENDPOINTS = [
  '/api/auth/login',
  '/api/auth/forgot-password',
  '/api/auth/reset-password',
  '/api/auth/test-cors'
];
const SESSION_CONTEXT_KEYS = ['selectedModule', 'selectedRole'];

class ApiService {
  constructor() {
    // Keep a cached token, but always refresh before requests in case storage changes.
    clearLegacyAuthFromLocalStorage();
    this.token = this.getTokenFromStorage();
  }

  getTokenFromStorage() {
    return getAuthToken();
  }

  async requestWithAutoTokenFallback(url, config) {
    return fetch(url, config);
  }

  // Get baseURL dynamically from config to ensure it's always up-to-date
  get baseURL() {
    return CONFIG.API_CONFIG.BASE_URL;
  }

  isPublicAuthEndpoint(endpoint = '') {
    return PUBLIC_AUTH_ENDPOINTS.some((publicEndpoint) =>
      endpoint.startsWith(publicEndpoint)
    );
  }

  clearClientSession() {
    this.token = null;
    clearAuthSession();

    SESSION_CONTEXT_KEYS.forEach((key) => {
      sessionStorage.removeItem(key);
      localStorage.removeItem(key);
    });
  }

  handleUnauthorized(endpoint) {
    if (this.isPublicAuthEndpoint(endpoint)) {
      return;
    }

    this.clearClientSession();

    if (window.location.pathname !== LOGIN_PATH) {
      window.location.replace(LOGIN_PATH);
    }
  }

  getHeaders() {
    const headers = {
      'Content-Type': 'application/json',
      Accept: 'application/json'
    };

    // Refresh token on each request so sessionStorage/localStorage changes take effect immediately.
    this.token = this.getTokenFromStorage();

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    return headers;
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;

    const isMultipart =
      typeof FormData !== 'undefined' && options.body instanceof FormData;

    const autoHeaders = this.getHeaders();
    if (isMultipart && autoHeaders['Content-Type']) {
      delete autoHeaders['Content-Type'];
    }

    const config = {
      headers: autoHeaders,
      mode: 'cors',
      credentials: 'include',
      method: 'GET', // Default method
      ...options // This should override the method if specified
    };

    if (isMultipart && config.headers && config.headers['Content-Type']) {
      delete config.headers['Content-Type'];
    }

    try {
      console.log('Making API request:', {
        baseURL: this.baseURL,
        endpoint: endpoint,
        fullURL: url,
        method: config.method,
        headers: config.headers,
        body: config.body ? 'BODY_PRESENT' : 'NO_BODY'
      });
      const response = await this.requestWithAutoTokenFallback(url, config);

      // Handle empty responses
      let data;
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        data = await response.json();
      } else {
        data = await response.text();
      }

      if (!response.ok) {
        if (response.status === UNAUTHORIZED_STATUS) {
          this.handleUnauthorized(endpoint);
        }

        const msg =
          data && typeof data === 'object' && data.message
            ? data.message
            : typeof data === 'string'
              ? data
              : `HTTP error! status: ${response.status}`;
        const apiError = new Error(msg);
        if (data && typeof data === 'object') {
          apiError.responseData = data;
          apiError.validationErrors =
            data.data?.errors || data.errors || data.data || null;
        }
        throw apiError;
      }

      // Some APIs return HTTP 200 with success=false for validation/business errors.
      if (data && typeof data === 'object' && data.success === false) {
        const apiError = new Error(data.message || 'Request failed');
        apiError.validationErrors =
          data.data?.errors || data.errors || data.data || null;
        apiError.responseData = data;
        throw apiError;
      }

      // Preserve pagination metadata when data is wrapped
      const responseData = data.data || data;
      const hasPaginationMetadata = data.total !== undefined || data.count !== undefined || data.pagination !== undefined;
      
      return {
        success: true,
        message: data.message || 'Success',
        data: responseData,
        // Preserve pagination metadata at root level for easy access
        ...(hasPaginationMetadata && {
          count: data.count,
          total: data.total,
          pagination: data.pagination,
          current_page: data.current_page,
          per_page: data.per_page,
          last_page: data.last_page
        })
      };
    } catch (error) {
      console.error('API request failed:', error);

      // Normalize the error message
      const rawMessage = error instanceof Error ? error.message : typeof error === 'string' ? error : '';

      // Detect common database connection/authentication errors
      const isDbAuthError = rawMessage?.includes('SQLSTATE[HY000] [1045]') || rawMessage?.includes('Access denied for user');

      if (isDbAuthError) {
        // Log full database error for debugging/monitoring
        console.error('Database connection/authentication error detected:', rawMessage);
      }

      // User-facing message (do not expose full DB details if you don't want to)
      const userMessage = isDbAuthError
        ? 'Database connection error. Please contact the system administrator.'
        : rawMessage || 'An error occurred';

      return {
        success: false,
        message: userMessage,
        errors: error,
        validationErrors: error?.validationErrors || null,
        responseData: error?.responseData || null,
      };
    }
  }

  // Test CORS
  async testCors() {
    return this.request('/api/auth/test-cors', {
      method: 'GET'
    });
  }

  // Authentication methods
  async login(credentials) {
    const response = await this.request('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify(credentials)
    });

    if (response.success && response.data?.token) {
      this.token = response.data.token;
      setAuthToken(this.token);
    }

    return response;
  }

  async logout() {
    const response = await this.request('/api/auth/logout', {
      method: 'POST'
    });

    if (response.success) {
      this.token = null;
      clearAuthSession();
    }

    return response;
  }

  /** Request OTP for password reset: { username } */
  async forgotPassword(data) {
    return this.request('/api/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  /** Reset password with OTP: { username, otp, new_password, confirm_password } */
  async resetPassword(data) {
    return this.request('/api/auth/reset-password', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async updatePassword(data) {
    // Expected payload: { username: string, new_password: string, current_password?: string }
    return this.request('/api/auth/update-password', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async getUser() {
    return this.request('/api/auth/user');
  }

  // Vehicle methods
  async getVehicles() {
    return this.request('/api/vehicles/all');
  }

  async getVehicleById(id) {
    return this.request(`/api/vehicles/${id}`);
  }

  // Collection-management (SoD) vehicle endpoints
  async getCollectionVehicles(params = {}) {
    const queryParams = new URLSearchParams();
    if (params.page !== undefined && params.page !== null) {
      queryParams.set('page', String(params.page));
    }
    if (params.per_page !== undefined && params.per_page !== null) {
      queryParams.set('per_page', String(params.per_page));
    }
    const qs = queryParams.toString();
    return this.request(`/api/collection-management/vehicles${qs ? `?${qs}` : ''}`);
  }

  async getCollectionVehicleById(id) {
    return this.request(`/api/collection-management/vehicles/${id}`);
  }

  async lookupVehicleByPlate(plateNo) {
    const qs = new URLSearchParams({plate_no: String(plateNo || '').trim()}).toString();
    return this.request(`/api/vehicles/lookup?${qs}`);
  }

  async getRecentVehicleTransactions() {
    return this.request('/api/vehicles/recent-transactions');
  }

  async createCollectionVehicle(payload = {}) {
    // Same shape as update: send JSON (base64 image inline) by default.
    // If a File is provided under `vehicle_image`, fall back to multipart.
    const endpoint = `/api/vehicles/create`;
    const hasFile =
      typeof File !== 'undefined' && payload.vehicle_image instanceof File;

    if (hasFile) {
      const fd = new FormData();
      Object.entries(payload).forEach(([key, value]) => {
        if (value === undefined || value === null) return;
        if (value instanceof File) {
          fd.append(key, value);
        } else if (typeof value === 'boolean') {
          fd.append(key, value ? '1' : '0');
        } else {
          fd.append(key, String(value));
        }
      });
      return this.request(endpoint, {method: 'POST', body: fd});
    }

    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async updateCollectionVehicle(id, payload = {}) {
    // PHP often drops file uploads on PUT, so this endpoint accepts POST.
    // - If `vehicle_image` is a File, send multipart so the file is preserved.
    // - Otherwise send JSON (supports `vehicle_image_base64` and `clear_vehicle_image`).
    const endpoint = `/api/collection-management/vehicles/${id}/update`;
    const hasFile =
      typeof File !== 'undefined' && payload.vehicle_image instanceof File;

    if (hasFile) {
      const fd = new FormData();
      Object.entries(payload).forEach(([key, value]) => {
        if (value === undefined || value === null) return;
        if (value instanceof File) {
          fd.append(key, value);
        } else if (typeof value === 'boolean') {
          fd.append(key, value ? '1' : '0');
        } else {
          fd.append(key, String(value));
        }
      });
      return this.request(endpoint, {method: 'POST', body: fd});
    }

    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async getVehicleData(plateNo) {
    return this.request('/api/get-vehicle-data', {
      method: 'POST',
      body: JSON.stringify({ plate_no: plateNo })
    });
  }

  async createVehicle(vehicleData) {
    return this.request('/api/vehicles/create', {
      method: 'POST',
      body: JSON.stringify(vehicleData)
    });
  }

  async updateVehicle(vehicleData) {
    return this.request('/api/vehicles/update', {
      method: 'PUT',
      body: JSON.stringify(vehicleData)
    });
  }

  /** Staff / enrollment: plate_num, optional account_no, rfid_tag_no, card_number, user_id */
  async associateVehicleWithAccount(vehicleData) {
    return this.request('/api/vehicles/associate-vehicle', {
      method: 'POST',
      body: JSON.stringify(vehicleData)
    });
  }

  /** Staff / enrollment: plate_num, optional account_no, user_id */
  async disassociateStaffVehicle(payload) {
    return this.request('/api/vehicles/disassociate-vehicle', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  /** Typeahead: optional search query, max 50 chars on server */
  async searchUnassociatedVehicles(search = '') {
    const q = String(search || '').trim().slice(0, 50);
    const qs = new URLSearchParams();
    if (q) qs.set('search', q);
    return this.request(`/api/vehicles/search-unassociated?${qs.toString()}`);
  }

  /** Customer self-service: account inferred from session */
  async associateVehicleCustomer({ plate_no }) {
    return this.request('/api/vehicles/associate', {
      method: 'POST',
      body: JSON.stringify({ plate_no: String(plate_no || '').trim() })
    });
  }

  async disassociateVehicleCustomer({ plate_no }) {
    return this.request('/api/vehicles/disassociate', {
      method: 'POST',
      body: JSON.stringify({ plate_no: String(plate_no || '').trim() })
    });
  }

  async activateVehicle(id) {
    return this.request('/api/vehicles/activate', {
      method: 'POST',
      body: JSON.stringify({ id })
    });
  }

  async deactivateVehicle(id) {
    return this.request('/api/vehicles/deactivate', {
      method: 'POST',
      body: JSON.stringify({ id })
    });
  }

  async getNormalPassages(plateNo, params) {
    const queryParams = new URLSearchParams();

    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '' && value !== 0) {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/vehicles/fetch-normal-passages${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify({ plate_no: plateNo })
    });
  }

  async getBundlePassages(plateNo, params) {
    const queryParams = new URLSearchParams();

    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '' && value !== 0) {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/vehicles/fetch-bundle-passages${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify({ plate_no: plateNo })
    });
  }

  async getBundleSubscriptions(plateNo, params) {
    const queryParams = new URLSearchParams();

    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '' && value !== 0) {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/vehicles/fetch-bundle-subscriptions${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify({ plate_no: plateNo })
    });
  }

  async getTollBundleBills(payload = {}) {
    return this.request('/api/toll-bundle-bills', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async getTollBundleBillOrderForm(id) {
    return this.request('/api/toll-bundle-bills/order-form', {
      method: 'POST',
      body: JSON.stringify({ id })
    });
  }

  async getAdvertBills(payload = {}) {
    return this.request('/api/advert-bills', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async createAdvertBill(payload = {}) {
    return this.request('/api/advert-bills/create', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async getAdvertOrderForm({ id } = {}) {
    return this.request('/api/advert-bills/order-form', {
      method: 'POST',
      body: JSON.stringify({ id })
    });
  }

  async cancelAdvertBill({ id, cancel_reason } = {}) {
    return this.request('/api/advert-bills/cancel', {
      method: 'POST',
      body: JSON.stringify({ id, cancel_reason })
    });
  }

  getAdvertBillPrintReceipt(id) {
    if (id == null || id === '') {
      return { success: false, message: 'Advert bill id is required' };
    }

    // Top-level navigation avoids CORS (fetch is blocked cross-origin on PDF responses).
    const url = `${this.baseURL}/api/advert-bills/print-receipt/${encodeURIComponent(id)}`;
    return { success: true, message: 'Receipt ready', data: { url } };
  }

  async getEventBills(payload = {}) {
    return this.request('/api/event-bills', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async createEventBill(payload = {}) {
    return this.request('/api/event-bills/create', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async cancelEventBill({ id, cancel_reason } = {}) {
    return this.request('/api/event-bills/cancel', {
      method: 'POST',
      body: JSON.stringify({ id, cancel_reason })
    });
  }

  async getIncidentBills(payload = {}) {
    return this.request('/api/incident-bills', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async createIncidentBill(payload = {}) {
    return this.request('/api/incident-bills/create', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async cancelIncidentBill({ id, cancel_reason } = {}) {
    return this.request('/api/incident-bills/cancel', {
      method: 'POST',
      body: JSON.stringify({ id, cancel_reason })
    });
  }

  async getFineChargeBills(payload = {}) {
    return this.request('/api/fine-charge-bills', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async createFineChargeBill(payload = {}) {
    return this.request('/api/fine-charge-bills/create', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async cancelFineChargeBill({ id, cancel_reason } = {}) {
    return this.request('/api/fine-charge-bills/cancel', {
      method: 'POST',
      body: JSON.stringify({ id, cancel_reason })
    });
  }

  async getFineChargeReceipt({ id } = {}) {
    if (!id) {
      return { success: false, message: 'Fine charge bill id is required' };
    }

    const url = `${this.baseURL}/printer/print-fine-charge-receipt?id=${encodeURIComponent(id)}`;
    return { success: true, message: 'Receipt ready', data: { url } };
  }

  async repostIncidentBill({ id } = {}) {
    return this.request('/api/incident-fine/repost-bill', {
      method: 'POST',
      body: JSON.stringify({ id })
    });
  }

  async getEndOfShiftShifts() {
    return this.request('/api/end-of-shift/shifts', { method: 'GET' });
  }

  async queryShiftRecords({ user_id } = {}) {
    return this.request('/api/shift-record/query-shift', {
      method: 'POST',
      body: JSON.stringify({ user_id })
    });
  }

  async getShiftRecord(id) {
    return this.request(`/api/shift-record/get-shift/${id}`, { method: 'GET' });
  }

  async getWrongShiftAmount(payload = {}) {
    return this.request('/api/shift/get-wrong-shift-amount', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async updateWrongShift(payload = {}) {
    return this.request('/api/shift/update-wrong-shift', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async getEndOfShiftShiftAmount(payload = {}) {
    return this.request('/api/end-of-shift/shift-amount', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async postShiftErpReceipt(payload = {}) {
    return this.request('/api/end-of-shift/process', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async getEndOfShiftReportDetails(payload = {}) {
    return this.getEndOfShiftShiftAmount(payload);
  }

  async getEndOfShiftBills(payload = {}) {
    return this.request('/api/end-of-shift-bills', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async createEndOfShiftBill(payload = {}) {
    return this.request('/api/end-of-shift-bills/create', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async cancelEndOfShiftBill({ id, cancel_reason } = {}) {
    return this.request('/api/end-of-shift-bills/cancel', {
      method: 'POST',
      body: JSON.stringify({ id, cancel_reason })
    });
  }

  async reuseEndOfShiftBill({ id, reuse_reason } = {}) {
    return this.request('/api/end-of-shift-bills/reuse', {
      method: 'POST',
      body: JSON.stringify({ id, reuse_reason })
    });
  }

  async getEndOfShiftOrderForm({ id } = {}) {
    return this.request('/api/end-of-shift-bills/order-form', {
      method: 'POST',
      body: JSON.stringify({ id })
    });
  }

  async getEndOfShiftReceipts(payload = {}) {
    return this.request('/api/end-of-shift-receipts', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async getEndOfShiftReceiptPrint({ id } = {}) {
    if (!id) {
      return { success: false, message: 'End of shift receipt id is required' };
    }

    const endpoint = `/api/end-of-shift-receipts/${id}/print-receipt`;
    const url = `${this.baseURL}${endpoint}`;
    const headers = this.getHeaders();
    delete headers['Content-Type'];
    headers['Accept'] = 'application/pdf';

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers,
        mode: 'cors',
        credentials: 'include'
      });

      if (!response.ok) {
        const text = await response.text().catch(() => '');
        return { success: false, message: text || `HTTP error! status: ${response.status}` };
      }

      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('application/json')) {
        const jsonData = await response.json().catch(() => ({}));
        return {
          success: false,
          message: jsonData.message || 'Failed to fetch end of shift receipt'
        };
      }

      const blob = await response.blob();
      const objectUrl = URL.createObjectURL(blob);
      return { success: true, message: 'Receipt fetched successfully', data: { url: objectUrl } };
    } catch (error) {
      return {
        success: false,
        message: error instanceof Error ? error.message : 'Failed to fetch end of shift receipt'
      };
    }
  }

  async getIncidentPaymentReceipt({ id } = {}) {
    if (!id) {
      return { success: false, message: 'Incident bill id is required' };
    }

    // Public printer route — top-level navigation avoids CORS on the PDF response.
    const url = `${this.baseURL}/printer/print-receipt?id=${encodeURIComponent(id)}`;
    return { success: true, message: 'Receipt ready', data: { url } };
  }

  async getEventPaymentReceipt({ id } = {}) {
    if (!id) {
      return { success: false, message: 'Event bill id is required' };
    }

    // Public printer route — top-level navigation avoids CORS on the PDF response.
    const url = `${this.baseURL}/printer/print-event-receipt?id=${encodeURIComponent(id)}`;
    return { success: true, message: 'Receipt ready', data: { url } };
  }

  async cancelBillingBill({ bill_id, bill_canc_date, bill_canc_by, cancel_reason } = {}) {
    return this.request('/api/billing/bill-cancellation', {
      method: 'POST',
      body: JSON.stringify({ bill_id, bill_canc_date, bill_canc_by, cancel_reason }),
    });
  }

  async repostTollBundleBill(id) {
    return this.request('/api/toll-bundle-bills/repost', {
      method: 'POST',
      body: JSON.stringify({ id })
    });
  }

  async getTollBundleSubscriptions(payload = {}) {
    return this.request('/api/toll-bundle-subscriptions', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async transferBundleSubscription(payload = {}) {
    return this.request('/api/bundle-subscriptions/transfer-vehicle', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async getBundles() {
    return this.request('/api/list-bundle-types');
  }

  // Account methods
  async getAccounts() {
    return this.request('/api/accounts');
  }

  async searchAccount(accountNo) {
    return this.request('/api/search-account-details', {
      method: 'POST',
      body: JSON.stringify({ account_no: accountNo })
    });
  }

  // Report methods
  async getTollCollectionReport(filters) {
    return this.request('/api/toll-collection', {
      method: 'POST',
      body: JSON.stringify(filters)
    });
  }

  async getIncidentCollectionReport(filters) {
    return this.request('/api/incident-collection', {
      method: 'POST',
      body: JSON.stringify(filters)
    });
  }

  async getOverloadCollectionReport(filters) {
    return this.request('/api/overload-collection', {
      method: 'POST',
      body: JSON.stringify(filters)
    });
  }

  async getEventCollectionReport(filters) {
    return this.request('/api/event-collection', {
      method: 'POST',
      body: JSON.stringify(filters)
    });
  }

  async getShiftCollectionReport(filters) {
    return this.request('/api/shift-collection-report', {
      method: 'POST',
      body: JSON.stringify(filters)
    });
  }

  async runCollectionReport(slug, filters = {}) {
    return this.request(`/api/collection-reports/${slug}`, {
      method: 'POST',
      body: JSON.stringify(filters)
    });
  }

  async getReportEngineCatalog(moduleSlug) {
    return this.request(`/api/report-engine/${moduleSlug}/catalog`);
  }

  async getReportEngineRegistrations() {
    return this.request('/api/report-engine/registrations');
  }

  async getReportEngineActiveRegistrations() {
    return this.request('/api/report-engine/registrations/active');
  }

  async createReportEngineRegistration(payload) {
    return this.request('/api/report-engine/registrations', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async updateReportEngineRegistration(id, payload) {
    return this.request(`/api/report-engine/registrations/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  }

  async getReportEngineDefinitions(registrationId) {
    return this.request(`/api/report-engine/registrations/${registrationId}/definitions`);
  }

  async getReportEngineDefinition(definitionId) {
    return this.request(`/api/report-engine/definitions/${definitionId}`);
  }

  async createReportEngineDefinition(registrationId, payload) {
    return this.request(`/api/report-engine/registrations/${registrationId}/definitions`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async updateReportEngineDefinition(definitionId, payload) {
    return this.request(`/api/report-engine/definitions/${definitionId}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  }

  async deleteReportEngineDefinition(definitionId) {
    return this.request(`/api/report-engine/definitions/${definitionId}`, {
      method: 'DELETE',
    });
  }

  async restoreReportEngineDefinition(definitionId) {
    return this.request(`/api/report-engine/definitions/${definitionId}/restore`, {
      method: 'POST',
    });
  }

  async generateReportEngineReport(moduleSlug, reportSlug, filters = {}) {
    return this.request(`/api/report-engine/${moduleSlug}/reports/${reportSlug}/generate`, {
      method: 'POST',
      body: JSON.stringify(filters),
    });
  }

  async logReportEngineExport(moduleSlug, reportSlug, payload = {}) {
    return this.request(`/api/report-engine/${moduleSlug}/reports/${reportSlug}/export-audit`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async getCollectionDashboardKpis() {
    return this.request('/api/collection-dashboard/kpis');
  }

  async getCollectionDashboardBodyTypes(period = 'today') {
    const query = new URLSearchParams({ period }).toString();
    return this.request(`/api/collection-dashboard/body-types?${query}`);
  }

  async getCollectionDashboardLanePerformance() {
    return this.request('/api/collection-dashboard/lane-performance');
  }

  async getCollectionDashboardTollTrends(fyStartYear) {
    const query = new URLSearchParams({ fy_start_year: String(fyStartYear) }).toString();
    return this.request(`/api/collection-dashboard/toll-trends?${query}`);
  }

  async getCollectionDashboardFinancialYears() {
    return this.request('/api/collection-dashboard/financial-years');
  }

  async getTopUpBills(payload = {}) {
    return this.request('/api/top-up-bills', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async repostTopUpBill(id) {
    return this.request('/api/top-up-bills/repost', {
      method: 'POST',
      body: JSON.stringify({ id })
    });
  }

  // Receipt is embedded via a direct iframe URL (iframe rendering is not subject
  // to CORS, unlike a fetch of the cross-origin PDF response).
  getPrepaymentPrintReceipt(id) {
    if (id == null || id === '') {
      return { success: false, message: 'Prepayment receipt id is required' };
    }

    const url = `${this.baseURL}/api/prepayment/print-receipt/${encodeURIComponent(id)}`;
    return { success: true, message: 'Receipt ready', data: { url } };
  }

  async cancelTopUpBill({ id, cancel_reason } = {}) {
    return this.request('/api/top-up-bills/cancel', {
      method: 'POST',
      body: JSON.stringify({ id, cancel_reason })
    });
  }

  getPrepaymentReceiptUrl(id) {
    return `${this.baseURL}/prepayment/print-receipt/${encodeURIComponent(id)}`;
  }

  // User Management methods
  async getUsers(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/users${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getUserById(id) {
    return this.request(`/api/users/${id}`);
  }

  async createUser(userData) {
    return this.request('/api/add-user', {
      method: 'POST',
      body: JSON.stringify(userData)
    });
  }

  async updateUser(id, userData) {
    return this.request(`/api/users/${id}`, {
      method: 'PUT',
      body: JSON.stringify(userData)
    });
  }

  async updateUserStatus(id, status) {
    return this.request(`/api/users/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ status })
    });
  }

  async updateUserRoles(id, roleIds) {
    return this.request(`/api/users/${id}/roles`, {
      method: 'PUT',
      body: JSON.stringify({ role_ids: roleIds })
    });
  }

  /**
   * Assign a single BCMS role to a user (POST). Returns updated user with roles[].
   * @param {number|string} userId
   * @param {{ roleId: number|string, startDate?: string|null, endDate?: string|null }} payload
   */
  async assignBcmsUserRole(userId, { roleId, startDate = null, endDate = null }) {
    return this.request(`/api/users/${userId}/roles`, {
      method: 'POST',
      body: JSON.stringify({
        role_id: Number(roleId),
        start_date: startDate ?? null,
        end_date: endDate ?? null,
      })
    });
  }

  async getRoles() {
    return this.request('/api/roles');
  }

  // Role Management methods
  async getRolesList(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/roles/list${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getRoleById(id) {
    return this.request(`/api/roles/${id}`);
  }

  async createRole(roleData) {
    return this.request('/api/roles', {
      method: 'POST',
      body: JSON.stringify(roleData)
    });
  }

  async updateRole(id, roleData) {
    return this.request(`/api/roles/${id}`, {
      method: 'PUT',
      body: JSON.stringify(roleData)
    });
  }

  async updateAuthRoleStatus(id, status) {
    return this.request(`/api/roles/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ status: Number(status) })
    });
  }

  /** @deprecated Use updateAuthRoleStatus — kept for callers still on legacy signature */
  async toggleRoleStatus(id, status) {
    if (status !== undefined && status !== null) {
      return this.updateAuthRoleStatus(id, status);
    }
    return this.request(`/api/roles/${id}/status`, { method: 'PUT' });
  }

  async getAuthRoleModules(roleId) {
    return this.request(`/api/roles/${roleId}/modules`);
  }

  async saveAuthRoleModules(roleId, moduleIds) {
    const ids = (Array.isArray(moduleIds) ? moduleIds : []).map((id) => Number(id)).filter(Number.isFinite);
    const body = ids.length === 1 ? { module_id: ids[0] } : { module_ids: ids };
    return this.request(`/api/roles/${roleId}/modules`, {
      method: 'POST',
      body: JSON.stringify(body)
    });
  }

  // Permission Management methods
  async getPermissions(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/permissions${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getPermissionById(id) {
    return this.request(`/api/permissions/${id}`);
  }

  async createPermission(permissionData) {
    return this.request('/api/permissions', {
      method: 'POST',
      body: JSON.stringify(permissionData)
    });
  }

  async updatePermission(id, permissionData) {
    return this.request(`/api/permissions/${id}`, {
      method: 'PUT',
      body: JSON.stringify(permissionData)
    });
  }

  async togglePermissionStatus(id) {
    return this.request(`/api/permissions/${id}/status`, {
      method: 'PUT'
    });
  }

  async getAvailablePermissions() {
    return this.request('/api/permissions/available');
  }

  async getRolePermissions(roleId) {
    return this.request(`/api/roles/${roleId}/permissions`);
  }

  async assignRolePermissions(roleId, permissionIds) {
    return this.request(`/api/roles/${roleId}/permissions`, {
      method: 'POST',
      body: JSON.stringify({ permission_ids: permissionIds })
    });
  }

  // Action Management methods
  async getActionsList(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/actions${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getActionById(id) {
    return this.request(`/api/actions/${id}`);
  }

  async createAction(actionData) {
    return this.request('/api/actions', {
      method: 'POST',
      body: JSON.stringify(actionData)
    });
  }

  async updateAction(id, actionData) {
    return this.request(`/api/actions/${id}`, {
      method: 'PUT',
      body: JSON.stringify(actionData)
    });
  }

  async toggleActionStatus(id) {
    return this.request(`/api/actions/${id}/status`, {
      method: 'PUT'
    });
  }

  async getAvailableActions() {
    return this.request('/api/actions/available');
  }

  async getRoleActions(roleId) {
    return this.request(`/api/roles/${roleId}/actions`);
  }

  async assignRoleActions(roleId, actionIds) {
    return this.request(`/api/roles/${roleId}/actions`, {
      method: 'POST',
      body: JSON.stringify({ action_ids: actionIds })
    });
  }

  async getUserMenuItems() {
    return this.request('/api/user/menu-items');
  }

  // Lane Management methods
  async getLanesList(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/lanes${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getLaneById(id) {
    return this.request(`/api/lanes/${id}`);
  }

  async createLane(laneData) {
    return this.request('/api/lanes', {
      method: 'POST',
      body: JSON.stringify(laneData)
    });
  }

  async updateLane(id, laneData) {
    return this.request(`/api/lanes/${id}`, {
      method: 'PUT',
      body: JSON.stringify(laneData)
    });
  }

  async toggleLaneStatus(id) {
    return this.request(`/api/lanes/${id}/status`, {
      method: 'PUT'
    });
  }

  async deleteLane(id) {
    return this.request(`/api/lanes/${id}`, {
      method: 'DELETE'
    });
  }

  async getPaymentMethods() {
    return this.request('/api/payment-methods/dropdown');
  }

  async getActiveLanes() {
    return this.request('/api/lanes/active');
  }

  async manualOpenGate(data) {
    return this.request('/api/lanes/manual-open-gate', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  // Price Management methods
  async getPricesList(params) {
    const queryParams = new URLSearchParams();

    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/prices${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getPriceById(id) {
    return this.request(`/api/prices/${id}`);
  }

  async createPrice(priceData) {
    return this.request('/api/prices', {
      method: 'POST',
      body: JSON.stringify(priceData)
    });
  }

  async updatePrice(id, priceData) {
    return this.request(`/api/prices/${id}`, {
      method: 'PUT',
      body: JSON.stringify(priceData)
    });
  }

  async togglePriceStatus(id) {
    return this.request(`/api/prices/${id}/status`, {
      method: 'PUT'
    });
  }

  async deletePrice(id) {
    return this.request(`/api/prices/${id}`, {
      method: 'DELETE'
    });
  }

  async getActiveBodyTypes() {
    return this.request('/api/prices/body-types');
  }

  async getActivePrices() {
    return this.request('/api/prices/active');
  }

  // Account Management methods
  async getAccountsList(params) {
    const queryParams = new URLSearchParams();

    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/accounts${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getAccountById(id) {
    return this.request(`/api/accounts/${id}`);
  }

  async sendAccountCreationOtp(accountData) {
    return this.request('/api/accounts/send-otp', {
      method: 'POST',
      body: JSON.stringify(accountData)
    });
  }

  async createAccount(accountData) {
    return this.request('/api/accounts', {
      method: 'POST',
      body: JSON.stringify(accountData)
    });
  }

  async updateAccount(id, accountData) {
    return this.request(`/api/accounts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(accountData)
    });
  }

  async activateAccount(id) {
    return this.request(`/api/accounts/${id}/activate`, {
      method: 'PUT'
    });
  }

  async deactivateAccount(id) {
    return this.request(`/api/accounts/${id}/deactivate`, {
      method: 'PUT'
    });
  }

  async getFundTransfers(params = {}) {
    const queryParams = new URLSearchParams();

    const allowedKeys = ['page', 'per_page', 'search', 'sort_by', 'sort_order', 'status'];
    allowedKeys.forEach((key) => {
      const value = params[key];
      if (value !== undefined && value !== null && value !== '') {
        queryParams.append(key, value.toString());
      }
    });

    const endpoint = `/api/accounts/transfer/history${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getPendingFundTransfers(params = {}) {
    const queryParams = new URLSearchParams();

    const allowedKeys = ['page', 'per_page', 'search', 'sort_by', 'sort_order'];
    allowedKeys.forEach((key) => {
      const value = params[key];
      if (value !== undefined && value !== null && value !== '') {
        queryParams.append(key, value.toString());
      }
    });

    const endpoint = `/api/accounts/transfer/pending${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getFundTransferById(id) {
    return this.request(`/api/accounts/transfer/history/${id}`);
  }

  async submitFundTransfer(data, files = []) {
    const fileList = Array.isArray(files) ? files.filter((f) => f instanceof File) : [];
    const approvalDocument = fileList[0];

    if (approvalDocument) {
      const fd = new FormData();
      Object.entries(data || {}).forEach(([key, value]) => {
        if (value === undefined || value === null) return;
        fd.append(key, String(value));
      });
      fd.append('approval_document', approvalDocument);
      return this.request('/api/accounts/transfer', {
        method: 'POST',
        body: fd
      });
    }

    return this.request('/api/accounts/transfer', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async approveFundTransfer(id, data = {}) {
    return this.request(`/api/accounts/transfer/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async rejectFundTransfer(id, data = {}) {
    return this.request(`/api/accounts/transfer/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async returnFundTransfer(id, data = {}) {
    return this.request(`/api/accounts/transfer/${id}/return`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async resubmitFundTransfer(id, data = {}, files = []) {
    const fileList = Array.isArray(files) ? files.filter((f) => f instanceof File) : [];
    const approvalDocument = fileList[0];

    if (approvalDocument) {
      const fd = new FormData();
      Object.entries(data || {}).forEach(([key, value]) => {
        if (value === undefined || value === null) return;
        fd.append(key, String(value));
      });
      fd.append('approval_document', approvalDocument);
      return this.request(`/api/accounts/transfer/${id}/resubmit`, {
        method: 'POST',
        body: fd
      });
    }

    return this.request(`/api/accounts/transfer/${id}/resubmit`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async getFundTransferApprovalDocument(transferId) {
    if (!transferId) {
      return { success: false, message: 'Transfer ID is required' };
    }

    const endpoint = `/api/accounts/transfer/${transferId}/approval-document`;
    return this.fetchFundTransferDocumentBlob(endpoint, `approval-document-${transferId}`);
  }

  async fetchFundTransferDocumentBlob(endpoint, fallbackFileName) {
    const url = `${this.baseURL}${endpoint}`;
    const headers = this.getHeaders();
    delete headers['Content-Type'];

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers,
        mode: 'cors',
        credentials: 'include'
      });

      if (!response.ok) {
        const text = await response.text().catch(() => '');
        if (response.status === UNAUTHORIZED_STATUS) {
          this.handleUnauthorized(endpoint);
        }
        throw new Error(text || `HTTP error! status: ${response.status}`);
      }

      const blob = await response.blob();
      const disposition = response.headers.get('content-disposition') || '';
      const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
      const fileName = match ? match[1].replace(/['"]/g, '') : fallbackFileName;

      return { success: true, blob, fileName, mimeType: blob.type || response.headers.get('content-type') };
    } catch (error) {
      return { success: false, message: error?.message || 'Failed to load document' };
    }
  }

  async getVehiclesByAccountId(accountId, params) {
    const queryParams = new URLSearchParams();

    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/vehicles/account/${accountId}${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async fetchVehicle(plateNo) {
    return this.request('/api/vehicles/fetch', {
      method: 'POST',
      body: JSON.stringify({
        plate_no: plateNo,
        source: 'Bridge-Portal'
      })
    });
  }

  // Registration Request Methods
  async getRegistrationRequests(filters) {
    const queryParams = new URLSearchParams();

    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/registration-requests${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getRegistrationRequest(id) {
    return this.request(`/api/registration-requests/${id}`);
  }

  async createRegistrationRequest(data) {
    console.log('Creating registration request with method POST');
    return this.request('/api/registration-requests', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async updateRegistrationRequest(id, data) {
    return this.request(`/api/registration-requests/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async reviewRegistrationRequest(id, data) {
    return this.request(`/api/registration-requests/${id}/review`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async deleteRegistrationRequest(id) {
    return this.request(`/api/registration-requests/${id}`, {
      method: 'DELETE'
    });
  }

  async getRegistrationStatistics() {
    return this.request('/api/registration-requests/statistics');
  }

  async downloadRegistrationCard(id) {
    return this.request(`/api/registration-requests/${id}/download-card`);
  }

  async getBodyTypes() {
    return this.request('/api/body-types');
  }

  async checkPendingRequests(plateNumber) {
    return this.request(`/api/registration-requests/check-pending/${encodeURIComponent(plateNumber)}`);
  }

  // POS Terminal Management methods
  async getPosTerminals(params) {
    const queryParams = new URLSearchParams();

    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/pos-terminals${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getPosTerminal(id) {
    return this.request(`/api/pos-terminals/${id}`);
  }

  async registerPosTerminal(terminalData) {
    return this.request('/api/pos-terminals', {
      method: 'POST',
      body: JSON.stringify(terminalData)
    });
  }

  async updatePosTerminal(id, terminalData) {
    return this.request(`/api/pos-terminals/${id}`, {
      method: 'PUT',
      body: JSON.stringify(terminalData)
    });
  }

  async updatePosTerminalStatus(id, status) {
    return this.request(`/api/pos-terminals/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ status })
    });
  }

  // Utility methods
  setToken(token) {
    this.token = token;
    setAuthToken(token);
  }

  getToken() {
    return this.token;
  }

  refreshToken() {
    this.token = this.getTokenFromStorage();
  }

  // Card Registration methods
  async searchAccounts(params) {
    const queryParams = new URLSearchParams();

    if (params?.search) queryParams.append('search', params.search);
    if (params?.status !== undefined) queryParams.append('status', params.status.toString());
    if (params?.sort_by) queryParams.append('sort_by', params.sort_by);
    if (params?.sort_order) queryParams.append('sort_order', params.sort_order);
    if (params?.per_page) queryParams.append('per_page', params.per_page.toString());
    if (params?.page) queryParams.append('page', params.page.toString());

    const endpoint = `/api/accounts${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async searchVehicles(params) {
    const queryParams = new URLSearchParams();

    if (params?.search) queryParams.append('search', params.search);
    if (params?.per_page) queryParams.append('per_page', params.per_page.toString());
    if (params?.page) queryParams.append('page', params.page.toString());

    const endpoint = `/api/vehicles/all${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async registerCardToAccount(data) {
    const url = `${this.baseURL}/api/accounts/register-card`;
    const config = {
      method: 'POST',
      headers: {
        ...this.getHeaders(),
        'Content-Type': 'application/json'
      },
      mode: 'cors',
      credentials: 'include',
      body: JSON.stringify(data)
    };

    try {
      const response = await fetch(url, config);

      let responseData;
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        responseData = await response.json();
      } else {
        responseData = await response.text();
      }

      if (!response.ok) {
        if (response.status === UNAUTHORIZED_STATUS) {
          this.handleUnauthorized('/api/accounts/register-card');
        }

        throw new Error(responseData.message || responseData || `HTTP error! status: ${response.status}`);
      }

      return {
        success: true,
        message: responseData.message || 'Card registered successfully',
        data: responseData.data || responseData
      };
    } catch (error) {
      console.error('Card registration error:', error);
      return {
        success: false,
        message: error.message || 'Failed to register card',
        data: null
      };
    }
  }

  // Account History methods
  async getBalanceHistory(params) {
    // Refresh token from localStorage in case it was updated
    this.refreshToken();

    const queryParams = new URLSearchParams();

    if (params.per_page) queryParams.append('per_page', params.per_page.toString());
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.transaction_type) queryParams.append('transaction_type', params.transaction_type);
    if (params.start_date) queryParams.append('start_date', params.start_date);
    if (params.end_date) queryParams.append('end_date', params.end_date);
    if (params.lane_number) queryParams.append('lane_number', params.lane_number);

    const endpoint = `/api/accounts/${params.account_id}/balance-history${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;

    return this.request(endpoint);
  }

  async getCardHistory(params) {
    const queryParams = new URLSearchParams();

    if (params.per_page) queryParams.append('per_page', params.per_page.toString());
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.action_type) queryParams.append('action_type', params.action_type);
    if (params.start_date) queryParams.append('start_date', params.start_date);
    if (params.end_date) queryParams.append('end_date', params.end_date);

    const endpoint = `/api/accounts/${params.account_id}/card-history${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getAccountHistoryStats(accountId) {
    return this.request(`/api/accounts/${accountId}/history-stats`);
  }

  // Account prepayments (top-ups) - preferred non-portal endpoint
  async getAccountTopUps(params = {}) {
    const queryParams = new URLSearchParams();

    if (params.account_no) queryParams.append('account_no', String(params.account_no).trim());
    if (params.page) queryParams.append('page', String(params.page));
    if (params.per_page) queryParams.append('per_page', String(params.per_page));

    const endpoint = `/api/accounts/top-ups${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  // Request a top-up bill (GePG)
  async requestTopUpBill({ bill_amount, account_no, phone, tin } = {}) {
    const body = { bill_amount };
    if (account_no) body.account_no = String(account_no).trim();
    if (phone) body.phone = String(phone).trim();
    if (tin) body.tin = String(tin).trim();
    return this.request('/api/billing/post-top-up-bill', {
      method: 'POST',
      body: JSON.stringify(body),
    });
  }

  // Request a bundle bill by plate + bundle tier (1=daily, 2=weekly, 3=monthly)
  async requestBundleBill({ plate_no, bundle_id, source = 'portal' } = {}) {
    return this.request('/api/post-bill', {
      method: 'POST',
      body: JSON.stringify({ plate_no, bundle_id, source }),
    });
  }

  // Eligible bundle types (daily/weekly/monthly) for a specific plate
  async getEligibleBundlesByPlate(plate_no) {
    const qs = new URLSearchParams({ plate_no: String(plate_no || '').trim() });
    return this.request(`/api/bundles/eligible-by-plate?${qs.toString()}`);
  }

  /** Paginated bundle subscriptions / purchases (Sanctum). */
  async getBundlePurchases(params = {}) {
    const queryParams = new URLSearchParams();
    const allowed = [
      'account_id',
      'plate_no',
      'vehicle_id',
      'bundle_id',
      'status',
      'date_from',
      'date_to',
      'per_page',
      'page',
      'sort_by',
      'sort_order',
    ];
    allowed.forEach((key) => {
      const v = params[key];
      if (v !== undefined && v !== null && v !== '') {
        queryParams.append(key, String(v));
      }
    });
    const qs = queryParams.toString();
    return this.request(`/api/bundle-purchases${qs ? `?${qs}` : ''}`);
  }

  isAuthenticated() {
    return !!this.token;
  }

  // Fingerprint Management methods
  async captureFingerprint() {
    return this.request('/api/fingerprint/capture', {
      method: 'POST',
      body: JSON.stringify({})
    });
  }

  // BMS User Management methods
  async registerBmsUser(userData) {
    return this.request('/api/register-bms-user', {
      method: 'POST',
      body: JSON.stringify(userData)
    });
  }

  async getBridgeEmployees(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-employees${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getBridgeUsers(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-users${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getBridgeUserById(nationalId) {
    return this.request(`/api/bridge-users/${nationalId}`);
  }

  async createBridgeUser(userData) {
    return this.request('/api/bridge-users', {
      method: 'POST',
      body: JSON.stringify(userData),
    });
  }

  async updateBridgeUser(nationalId, userData) {
    return this.request(`/api/bridge-users/${nationalId}`, {
      method: 'PUT',
      body: JSON.stringify(userData),
    });
  }

  async toggleBridgeUserStatus(nationalId) {
    return this.request(`/api/bridge-users/${nationalId}/toggle-status`, {
      method: 'PUT',
    });
  }

  async getBridgeEmployeeById(id) {
    return this.request(`/api/bridge-employees/${id}`);
  }

  async updateBridgeEmployee(nationalId, employeeData) {
    return this.request(`/api/bridge-employees/${nationalId}`, {
      method: 'PUT',
      body: JSON.stringify(employeeData)
    });
  }

  // Bridge User Role Management methods
  async assignRoleToBridgeUsers(nationalId, roleId, options = {}) {
    const payload = { role_id: roleId };
    if (options?.from_date) payload.from_date = options.from_date;
    if (options?.to_date) payload.to_date = options.to_date;

    return this.request(`/api/bridge-users/${nationalId}/assign-role`, {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  async revokeRoleFromBridgeUsers(nationalId, roleId) {
    return this.request(`/api/bridge-users/${nationalId}/revoke-role`, {
      method: 'POST',
      body: JSON.stringify({ role_id: roleId })
    });
  }

  async getBridgeEmployeeRoles(nationalId) {
    return this.request(`/api/bridge-employees/${nationalId}/roles`);
  }

  async getActiveBridgeEmployees(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-employees/active${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async updateBmsUser(id, userData) {
    return this.request(`/api/update-bms-user/${id}`, {
      method: 'PUT',
      body: JSON.stringify(userData)
    });
  }

  async deleteBmsUser(id) {
    return this.request(`/api/bms-user/${id}`, {
      method: 'DELETE'
    });
  }

  async toggleBmsUserStatus(id) {
    return this.request(`/api/bms-user/${id}/status`, {
      method: 'PUT'
    });
  }

  // Employee Approval/Rejection methods (Maker-Checker)
  async approveEmployee(id, data = {}) {
    return this.request(`/api/employee-approvals/${id}/approve-creation`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async rejectEmployee(id, data = {}) {
    return this.request(`/api/employee-approvals/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async getPendingEmployeeApprovals(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-employees/pending-approvals${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  // Submit termination request (goes to pending approval)
  async submitTerminationRequest(nationalId, data = {}) {
    return this.request(`/api/bridge-employees/${nationalId}/request-termination`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  // Approve termination request (for approvers)
  async approveTermination(id, data = {}) {
    return this.request(`/api/employee-approvals/${id}/approve-termination`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  // Reject termination request (for approvers)
  async rejectTermination(id, data = {}) {
    return this.request(`/api/employee-approvals/${id}/reject-termination`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  // Legacy method - kept for backward compatibility, now calls approveTermination
  async terminateEmployee(id, data = {}) {
    return this.approveTermination(id, data);
  }

  async getBridgeLoggedUserRole(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bms-loggedUser-role${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  // BMS Role Management methods
  async registerBmsRole(roleData) {
    return this.request('/api/register-bms-role', {
      method: 'POST',
      body: JSON.stringify(roleData)
    });
  }

  async getBmsRoles(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bms-roles${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getActiveBmsRoles() {
    return this.request('/api/bms-roles/active');
  }

  async getBmsRoleById(id) {
    return this.request(`/api/bms-role/${id}`);
  }

  async updateBmsRole(id, roleData) {
    return this.request(`/api/update-bms-role/${id}`, {
      method: 'PUT',
      body: JSON.stringify(roleData)
    });
  }

  async deleteBmsRole(id) {
    return this.request(`/api/bms-role/${id}`, {
      method: 'DELETE'
    });
  }

  async toggleBmsRoleStatus(id) {
    return this.request(`/api/update-bms-role-status/${id}`, {
      method: 'PUT'
    });
  }

  // Department Management methods
  async registerBmsDepartment(departmentData) {
    return this.request('/api/departments', {
      method: 'POST',
      body: JSON.stringify(departmentData)
    });
  }

  async getBmsDepartments(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/departments${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getActiveBmsDepartments() {
    return this.request('/api/departments/active');
  }

  async getBmsDepartmentById(id) {
    return this.request(`/api/departments/${id}`);
  }

  async updateBmsDepartment(id, departmentData) {
    return this.request(`/api/departments/${id}`, {
      method: 'PUT',
      body: JSON.stringify(departmentData)
    });
  }

  async deleteBmsDepartment(id) {
    return this.request(`/api/departments/${id}`, {
      method: 'DELETE'
    });
  }

  async toggleBmsDepartmentStatus(id) {
    return this.request(`/api/departments/${id}/status`, {
      method: 'PUT'
    });
  }

  // Bank Management methods
  async createBank(bankData) {
    return this.request('/api/banks', {
      method: 'POST',
      body: JSON.stringify(bankData)
    });
  }

  async getBanks(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/banks${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getActiveBanks() {
    return this.request('/api/banks/active');
  }

  async getBankById(id) {
    return this.request(`/api/banks/${id}`);
  }

  async updateBank(id, bankData) {
    return this.request(`/api/banks/${id}`, {
      method: 'PUT',
      body: JSON.stringify(bankData)
    });
  }

  async deleteBank(id) {
    return this.request(`/api/banks/${id}`, {
      method: 'DELETE'
    });
  }

  async toggleBankStatus(id) {
    if (!id || id === 'undefined') {
      return {
        success: false,
        message: 'Bank ID is required'
      };
    }
    return this.request(`/api/banks/${id}/status`, {
      method: 'PUT'
    });
  }

  // Bridge Employment Type Management methods
  async createBridgeEmploymentType(employmentTypeData) {
    return this.request('/api/bridge-employment-types', {
      method: 'POST',
      body: JSON.stringify(employmentTypeData)
    });
  }

  async getBridgeEmploymentTypes(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-employment-types${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getAllBridgeEmploymentTypes() {
    return this.request('/api/bridge-employment-types/all');
  }

  async getActiveBridgeEmploymentTypes() {
    return this.request('/api/bridge-employment-types/active');
  }

  async getBridgeEmploymentTypeById(id) {
    return this.request(`/api/bridge-employment-types/${id}`);
  }

  async updateBridgeEmploymentType(id, employmentTypeData) {
    return this.request(`/api/bridge-employment-types/${id}`, {
      method: 'PUT',
      body: JSON.stringify(employmentTypeData)
    });
  }

  async deleteBridgeEmploymentType(id) {
    return this.request(`/api/bridge-employment-types/${id}`, {
      method: 'DELETE'
    });
  }

  // Scheme Management methods
  async createScheme(schemeData) {
    return this.request('/api/schemes', {
      method: 'POST',
      body: JSON.stringify(schemeData)
    });
  }

  async getSchemes(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/schemes${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getActiveSchemes() {
    return this.request('/api/schemes/active');
  }

  async getSchemeById(id) {
    return this.request(`/api/schemes/${id}`);
  }

  async updateScheme(id, schemeData) {
    return this.request(`/api/schemes/${id}`, {
      method: 'PUT',
      body: JSON.stringify(schemeData)
    });
  }

  async deleteScheme(id) {
    return this.request(`/api/schemes/${id}`, {
      method: 'DELETE'
    });
  }

  // Bridge Office Management methods
  async getBridgeOffices(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-offices${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getBridgeOfficeById(id) {
    return this.request(`/api/bridge-offices/${id}`);
  }

  // BMS Permission Management methods
  async registerBmsPermission(permissionData) {
    return this.request('/api/register-bms-permission', {
      method: 'POST',
      body: JSON.stringify(permissionData)
    });
  }

  async getBmsPermissions(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bms-permissions${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getBmsPermissionById(id) {
    return this.request(`/api/bms-permission/${id}`);
  }

  async updateBmsPermission(id, permissionData) {
    return this.request(`/api/update-bms-permission/${id}`, {
      method: 'PUT',
      body: JSON.stringify(permissionData)
    });
  }

  async deleteBmsPermission(id) {
    return this.request(`/api/bms-permission/${id}`, {
      method: 'DELETE'
    });
  }

  async toggleBmsPermissionStatus(id) {
    return this.request(`/api/update-bms-permission-status/${id}`, {
      method: 'PUT'
    });
  }

  // BMS Role-Permission Assignment methods
  async assignBmsPermissionRole(data) {
    return this.request('/api/assign-bms-permission-role', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async fetchBmsRolePermissions(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bms-role-permissions${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async showBmsRolePermission(rolePermissionId) {
    return this.request(`/api/bms-role-permission/${rolePermissionId}`);
  }

  async updateBmsRolePermission(rolePermissionId, data) {
    return this.request(`/api/update-bms-role-permission/${rolePermissionId}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async deleteBmsRolePermission(rolePermissionId) {
    return this.request(`/api/bms-role-permission/${rolePermissionId}`, {
      method: 'DELETE'
    });
  }

  async toggleBmsRolePermissionStatus(rolePermissionId) {
    return this.request(`/api/update-bms-role-permission-status/${rolePermissionId}`, {
      method: 'PUT'
    });
  }

  async fetchUserLoggedRole(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bms-loggedUser-role${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  // BMS Module Management methods
  async registerBmsModule(moduleData) {
    return this.request('/api/register-bridge-modules', {
      method: 'POST',
      body: JSON.stringify(moduleData)
    });
  }

  async getBmsModules(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-modules${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getActiveBmsModules() {
    return this.request('/api/bridge-modules/active');
  }

  async getActiveBmsRoleModules() {
    return this.request('/api/bridge-module-roles/active');
  }

  async getUserModules() {
    return this.request('/api/bridge-module-roles/user-modules');
  }

  async getUserMenus(moduleId) {
    return this.request('/api/bridge-module-role-menus/user-menus', {
      method: 'POST',
      body: JSON.stringify({ module_id: moduleId })
    });
  }

  async getBmsModuleById(id) {
    return this.request(`/api/bridge-modules/${id}`);
  }

  async updateBmsModule(id, moduleData) {
    return this.request(`/api/bridge-modules/${id}`, {
      method: 'PUT',
      body: JSON.stringify(moduleData)
    });
  }

  async deleteBmsModule(id) {
    return this.request(`/api/bridge-modules/${id}`, {
      method: 'DELETE'
    });
  }

  async toggleBmsModuleStatus(id, data = {}) {
    return this.request(`/api/bridge-modules/${id}/toggle-status`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  // BMS Module Menu Management methods
  async registerBmsModuleMenu(menuData) {
    return this.request('/api/bridge-module-menus', {
      method: 'POST',
      body: JSON.stringify(menuData)
    });
  }

  async getBmsModuleMenus(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-module-menus${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  // Fetch only active module menus, optionally filtered by module_id
  async getActiveBmsModuleMenus(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-module-menus/active${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getBmsModuleMenuById(id) {
    return this.request(`/api/bms-module-menu/${id}`);
  }

  async updateBmsModuleMenu(id, menuData) {
    return this.request(`/api/update-bms-module-menu/${id}`, {
      method: 'PUT',
      body: JSON.stringify(menuData)
    });
  }

  async deleteBmsModuleMenu(id) {
    return this.request(`/api/bms-module-menu/${id}`, {
      method: 'DELETE'
    });
  }

  async toggleBmsModuleMenuStatus(id) {
    return this.request(`/api/update-bms-module-menu-status/${id}`, {
      method: 'PUT'
    });
  }

  async toggleBridgeModuleMenuStatus(id) {
    return this.request(`/api/bridge-module-menus/${id}/toggle-status`, {
      method: 'PUT'
    });
  }

  // BMS Role-Module-Menu Assignment methods
  async assignBmsRoleModuleMenu(data) {
    return this.request('/api/bridge-module-role-menus/assign', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async bulkAssignBmsRoleModuleMenu(data) {
    return this.request('/api/bridge-module-role-menus/bulk-assign', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async getBmsRoleModuleMenus(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-module-role-menus${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getBmsRoleModuleMenuById(id) {
    return this.request(`/api/bridge-module-role-menus/${id}`);
  }

  async updateBmsRoleModuleMenu(id, data) {
    return this.request(`/api/bridge-module-role-menus/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async deleteBmsRoleModuleMenu(id) {
    return this.request(`/api/bridge-module-role-menus/${id}`, {
      method: 'DELETE'
    });
  }

  async toggleBmsRoleModuleMenuStatus(id) {
    return this.request(`/api/bridge-module-role-menus/${id}/toggle-status`, {
      method: 'PUT'
    });
  }

  // Fetch active role-based module menus for the logged-in user (or optionally by role)
  async getActiveBmsRoleModuleMenus(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-module-role-menus/active${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  // BMS Role-Module Assignment methods
  async assignBmsModuleRole(data) {
    return this.request('/api/assign-bms-module-role', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async getBmsRoleModules(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bms-role-modules${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getBmsRoleModuleById(id) {
    return this.request(`/api/bms-role-module/${id}`);
  }

  async updateBmsRoleModule(id, data) {
    return this.request(`/api/update-bms-role-module/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async deleteBmsRoleModule(id) {
    return this.request(`/api/bms-role-module/${id}`, {
      method: 'DELETE'
    });
  }

  async toggleBmsRoleModuleStatus(id) {
    return this.request(`/api/update-bms-role-module-status/${id}`, {
      method: 'PUT'
    });
  }

  // Bridge Module-Role Management methods
  async getBridgeModuleRoles(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-module-roles${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getActiveBridgeModuleRoles() {
    return this.request('/api/bridge-module-roles/active');
  }

  async getBridgeModuleRoleById(id) {
    return this.request(`/api/bridge-module-roles/${id}`);
  }

  async createBridgeModuleRole(data) {
    return this.request('/api/bridge-module-roles/assign', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async updateBridgeModuleRole(id, data) {
    return this.request(`/api/bridge-module-roles/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async toggleBridgeModuleRoleStatus(id) {
    return this.request(`/api/bridge-module-roles/${id}/toggle-status`, {
      method: 'PUT'
    });
  }

  async deleteBridgeModuleRole(id) {
    return this.request(`/api/bridge-module-roles/${id}`, {
      method: 'DELETE'
    });
  }

  async assignModuleToRole(data) {
    return this.request('/api/bridge-module-roles/assign', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async revokeModuleFromRole(data) {
    return this.request('/api/bridge-module-roles/revoke', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async getModulesByRole(roleId, params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-module-roles/role/${roleId}${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getRolesByModule(moduleId, params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-module-roles/module/${moduleId}${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async bulkAssignModules(data) {
    return this.request('/api/bridge-module-roles/bulk-assign', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  // Bridge Shift Management methods
  async getBridgeShifts(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-shifts${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async createBridgeShift(shiftData) {
    return this.request('/api/bridge-shifts', {
      method: 'POST',
      body: JSON.stringify(shiftData)
    });
  }

  async updateBridgeShift(id, shiftData) {
    return this.request(`/api/bridge-shifts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(shiftData)
    });
  }

  async toggleBridgeShiftStatus(id) {
    return this.request(`/api/bridge-shifts/${id}/status`, {
      method: 'PUT'
    });
  }

  async getActiveBridgeShifts(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-shifts/active${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async deleteBridgeShift(id) {
    return this.request(`/api/bridge-shifts/${id}`, {
      method: 'DELETE'
    });
  }

  // Bridge Shift Department Management methods
  async getBridgeShiftDepartments(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-shift-departments${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getActiveBridgeShiftDepartments(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/bridge-shift-departments/active${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getBridgeShiftDepartmentById(id) {
    return this.request(`/api/bridge-shift-departments/${id}`);
  }

  async createBridgeShiftDepartment(shiftDepartmentData) {
    return this.request('/api/bridge-shift-departments', {
      method: 'POST',
      body: JSON.stringify(shiftDepartmentData)
    });
  }

  async updateBridgeShiftDepartment(id, shiftDepartmentData) {
    return this.request(`/api/bridge-shift-departments/${id}`, {
      method: 'PUT',
      body: JSON.stringify(shiftDepartmentData)
    });
  }

  async toggleBridgeShiftDepartmentStatus(id) {
    return this.request(`/api/bridge-shift-departments/${id}/status`, {
      method: 'PUT'
    });
  }

  async deleteBridgeShiftDepartment(id) {
    return this.request(`/api/bridge-shift-departments/${id}`, {
      method: 'DELETE'
    });
  }

  clearToken() {
    this.token = null;
    clearAuthSession();
  }

  // Attendance Management methods
  async getUserSessions(params) {
    const queryParams = new URLSearchParams();
    
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    // Use query parameter for pf_number to avoid route conflicts
    const endpoint = `/api/attendance/sessions${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getUserSessionsById(userId, params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/attendance/sessions/user/${userId}${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getAllSessions(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/attendance/sessions/all${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getSessionById(id) {
    return this.request(`/api/attendance/sessions/${id}`);
  }

  async calculateHoursWorked(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/attendance/hours-worked${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async calculateHoursWorkedByUser(userId, params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/attendance/hours-worked/user/${userId}${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getAttendanceManagement(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/attendance/management${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  /**
   * @param {string} date - YYYY-MM-DD
   * @param {number|string} [returnedRequestId] - optional; pass when resubmitting a returned overtime request
   */
  async getAttendanceByDate(date, returnedRequestId) {
    const payload = {};
    if (date !== undefined && date !== null && date !== '') {
      payload.date = date;
    }
    if (returnedRequestId !== undefined && returnedRequestId !== null && returnedRequestId !== '') {
      payload.returned_request_id = returnedRequestId;
    }
    return this.request('/api/attendance/by-date', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // Educational Level Management methods
  async getEducationalLevels(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/educational-levels${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getEducationalLevelById(id) {
    return this.request(`/api/educational-levels/${id}`);
  }

  async createEducationalLevel(educationalLevelData) {
    return this.request('/api/educational-levels', {
      method: 'POST',
      body: JSON.stringify(educationalLevelData)
    });
  }

  async updateEducationalLevel(id, educationalLevelData) {
    return this.request(`/api/educational-levels/${id}`, {
      method: 'PUT',
      body: JSON.stringify(educationalLevelData)
    });
  }

  async toggleEducationalLevelStatus(id) {
    return this.request(`/api/educational-levels/${id}/status`, {
      method: 'PUT'
    });
  }

  async deleteEducationalLevel(id) {
    return this.request(`/api/educational-levels/${id}`, {
      method: 'DELETE'
    });
  }

  async getActiveEducationalLevels() {
    return this.request('/api/educational-levels/active');
  }

  // Overtime Rate Management methods
  async getOvertimeRates(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/overtime-rates${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getOvertimeRateById(id) {
    return this.request(`/api/overtime-rates/${id}`);
  }

  async createOvertimeRate(overtimeRateData) {
    return this.request('/api/overtime-rates', {
      method: 'POST',
      body: JSON.stringify(overtimeRateData)
    });
  }

  async updateOvertimeRate(id, overtimeRateData) {
    return this.request(`/api/overtime-rates/${id}`, {
      method: 'PUT',
      body: JSON.stringify(overtimeRateData)
    });
  }

  async toggleOvertimeRateStatus(id) {
    return this.request(`/api/overtime-rates/${id}/status`, {
      method: 'PUT'
    });
  }

  async deleteOvertimeRate(id) {
    return this.request(`/api/overtime-rates/${id}`, {
      method: 'DELETE'
    });
  }

  // Overtime Management methods
  async getOvertimeRecords(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/overtime${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getOvertimeRecordById(id) {
    return this.request(`/api/overtime/${id}`);
  }

  async getMyActionedOvertime() {
    return this.request('/api/overtime/my-actions');
  }

  async createOvertime(overtimeData) {
    return this.request('/api/overtime', {
      method: 'POST',
      body: JSON.stringify(overtimeData)
    });
  }

  async updateOvertime(id, overtimeData) {
    return this.request(`/api/overtime/${id}`, {
      method: 'PUT',
      body: JSON.stringify(overtimeData)
    });
  }

  // Overtime Approval Workflow methods (Two-level workflow)
  async validatorApproveOvertime(id, data) {
    return this.request(`/api/overtime/${id}/validator-approve`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async reviewerApproveOvertime(id, data) {
    return this.request(`/api/overtime/${id}/reviewer-approve`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async rejectOvertime(id, data) {
    return this.request(`/api/overtime/${id}/reject`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async returnOvertime(id, data) {
    return this.request(`/api/overtime/${id}/return`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async checkOvertimeDate(dateData) {
    return this.request('/api/overtime/check-date', {
      method: 'POST',
      body: JSON.stringify(dateData)
    });
  }

  // Validate overtime amount against salary cap (gross must not exceed half salary)
  async validateOvertimeAmount(payload) {
    // payload: { estimated_days: number }
    return this.request('/api/overtime/validate-amount', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }

  // Special Tasks Management methods
  async getSpecialTasks(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/special-tasks${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getSpecialTaskById(id) {
    return this.request(`/api/special-tasks/${id}`);
  }

  async createSpecialTask(taskData) {
    return this.request('/api/special-tasks', {
      method: 'POST',
      body: JSON.stringify(taskData)
    });
  }

  async updateSpecialTask(id, taskData) {
    return this.request(`/api/special-tasks/${id}`, {
      method: 'PUT',
      body: JSON.stringify(taskData)
    });
  }

  async deleteSpecialTask(id) {
    return this.request(`/api/special-tasks/${id}`, {
      method: 'DELETE'
    });
  }

  // Employee Special Task Applications
  async getMySpecialTasks(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/special-tasks/employee${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getEmployeeSpecialTasksByPf(pfNumber, params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/special-tasks/employee/${pfNumber}${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async createSpecialTaskApplication(applicationData) {
    return this.request('/api/special-tasks/apply', {
      method: 'POST',
      body: JSON.stringify(applicationData)
    });
  }

  async approveSpecialTask(id, data = {}) {
    return this.request(`/api/special-tasks/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async rejectSpecialTask(id, data = {}) {
    return this.request(`/api/special-tasks/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  // Public Holidays Management methods
  async getPublicHolidays(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/public-holidays${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getPublicHolidayById(id) {
    return this.request(`/api/public-holidays/${id}`);
  }

  async createPublicHoliday(holidayData) {
    return this.request('/api/public-holidays', {
      method: 'POST',
      body: JSON.stringify(holidayData)
    });
  }

  async updatePublicHoliday(id, holidayData) {
    return this.request(`/api/public-holidays/${id}`, {
      method: 'PUT',
      body: JSON.stringify(holidayData)
    });
  }

  async deletePublicHoliday(id) {
    return this.request(`/api/public-holidays/${id}`, {
      method: 'DELETE'
    });
  }

  async checkPublicHolidayDate(dateData) {
    return this.request('/api/public-holidays/check-date', {
      method: 'POST',
      body: JSON.stringify(dateData)
    });
  }

  async bulkCreatePublicHolidays(holidaysData) {
    return this.request('/api/public-holidays/bulk-create', {
      method: 'POST',
      body: JSON.stringify(holidaysData)
    });
  }

  async getPublicHolidaysByYear(year) {
    return this.request(`/api/public-holidays/year/${year}`);
  }

  async togglePublicHolidayStatus(id) {
    return this.request(`/api/public-holidays/${id}/toggle-status`, {
      method: 'POST'
    });
  }

  // Get overtime for logged in user or specific employee by PF number
  async getEmployeeOvertime(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/overtime/employee${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  // Overtime Batch Management methods
  async getReadyForBatch(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/overtime/batches/ready${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async listBatches(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/overtime/batches${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async createBatch(batchData) {
    return this.request('/api/overtime/batches', {
      method: 'POST',
      body: JSON.stringify(batchData)
    });
  }

  async getBatch(batchId) {
    return this.request(`/api/overtime/batches/${batchId}`);
  }

  async saveAndSubmitBatch(batchId, batchData) {
    return this.request(`/api/overtime/batches/${batchId}/submit`, {
      method: 'POST',
      body: JSON.stringify(batchData)
    });
  }

  async getOvertimeInvoiceDocument(batchNumber) {
    return this.request(`/api/overtime/invoice-document/${batchNumber}`);
  }

  async repostOvertimeBatchErms(batchId) {
    if (!batchId && batchId !== 0) {
      return { success: false, message: 'Batch ID is required' };
    }
    return this.request(`/api/overtime/batches/${encodeURIComponent(batchId)}/erms-repost`, {
      method: 'POST',
    });
  }

  async submitOvertimeBatchErms(batchId) {
    if (!batchId && batchId !== 0) {
      return { success: false, message: 'Batch ID is required' };
    }
    return this.request(`/api/overtime/batches/${encodeURIComponent(batchId)}/erms-submit`, {
      method: 'POST',
    });
  }

  // Overtime submission documents
  async getSubmissionDocumentTypes() {
    return this.request('/api/overtime/submission-documents/types');
  }

  async listSubmissionDocuments(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }
    const endpoint = `/api/overtime/submission-documents${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getSubmissionDocument(id) {
    return this.request(`/api/overtime/submission-documents/${encodeURIComponent(id)}`);
  }

  async createSubmissionDocument(data, file) {
    const fd = new FormData();
    Object.entries(data || {}).forEach(([key, value]) => {
      if (value === undefined || value === null || value === '') return;
      fd.append(key, String(value));
    });
    if (file instanceof File) {
      fd.append('file', file);
    }
    return this.request('/api/overtime/submission-documents', {
      method: 'POST',
      body: fd,
    });
  }

  async updateSubmissionDocument(id, data, file) {
    const fd = new FormData();
    Object.entries(data || {}).forEach(([key, value]) => {
      if (value === undefined || value === null || value === '') return;
      fd.append(key, String(value));
    });
    if (file instanceof File) {
      fd.append('file', file);
    }
    return this.request(`/api/overtime/submission-documents/${encodeURIComponent(id)}`, {
      method: 'PUT',
      body: fd,
    });
  }

  async toggleSubmissionDocumentStatus(id) {
    return this.request(`/api/overtime/submission-documents/${encodeURIComponent(id)}/toggle-status`, {
      method: 'POST',
    });
  }

  async downloadSubmissionDocument(id) {
    if (!id && id !== 0) {
      return { success: false, message: 'Document ID is required' };
    }

    const endpoint = `/api/overtime/submission-documents/${encodeURIComponent(id)}/download`;
    const url = `${this.baseURL}${endpoint}`;
    const headers = this.getHeaders();
    delete headers['Content-Type'];

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers,
        mode: 'cors',
        credentials: 'include',
      });

      if (!response.ok) {
        const text = await response.text().catch(() => '');
        if (response.status === UNAUTHORIZED_STATUS) {
          this.handleUnauthorized(endpoint);
        }
        throw new Error(text || `HTTP error! status: ${response.status}`);
      }

      const blob = await response.blob();
      const disposition = response.headers.get('content-disposition') || '';
      const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
      const fileName = match
        ? match[1].replace(/['"]/g, '')
        : `submission-document-${id}.pdf`;

      return { success: true, blob, fileName };
    } catch (error) {
      return { success: false, message: error?.message || 'Failed to download document' };
    }
  }

  // Bridge Employee Registration methods
  async registerBridgeEmployee(employeeData) {
    return this.request('/api/bridge-employees/register', {
      method: 'POST',
      body: JSON.stringify(employeeData)
    });
  }

  // Referral Request Methods (Module-Based Maker-Checker)
  async getReferralRequests(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/referral-requests${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getReferralRequestById(id) {
    return this.request(`/api/referral-requests/${id}`);
  }

  async getPendingReferralRequests(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/referral-requests/pending${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async approveReferralRequest(id, data = {}) {
    return this.request(`/api/referral-requests/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async rejectReferralRequest(id, data = {}) {
    return this.request(`/api/referral-requests/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async getReferralRequestStatistics(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/referral-requests/statistics${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  // Region Management methods
  async getRegions(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/regions${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getRegionById(id) {
    return this.request(`/api/regions/${id}`);
  }

  async createRegion(regionData) {
    return this.request('/api/regions', {
      method: 'POST',
      body: JSON.stringify(regionData)
    });
  }

  async updateRegion(id, regionData) {
    return this.request(`/api/regions/${id}`, {
      method: 'PUT',
      body: JSON.stringify(regionData)
    });
  }

  async toggleRegionStatus(id) {
    return this.request(`/api/regions/${id}/status`, {
      method: 'PUT'
    });
  }

  async deleteRegion(id) {
    return this.request(`/api/regions/${id}`, {
      method: 'DELETE'
    });
  }

  async getActiveRegions() {
    return this.request('/api/regions/active');
  }

  // District Management methods
  async getDistricts(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/districts${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getDistrictById(id) {
    return this.request(`/api/districts/${id}`);
  }

  async createDistrict(districtData) {
    return this.request('/api/districts', {
      method: 'POST',
      body: JSON.stringify(districtData)
    });
  }

  async updateDistrict(id, districtData) {
    return this.request(`/api/districts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(districtData)
    });
  }

  async toggleDistrictStatus(id) {
    return this.request(`/api/districts/${id}/status`, {
      method: 'PUT'
    });
  }

  async deleteDistrict(id) {
    return this.request(`/api/districts/${id}`, {
      method: 'DELETE'
    });
  }

  async getActiveDistricts() {
    return this.request('/api/districts/active');
  }

  async getDistrictsByRegion(regionId, params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/regions/${regionId}/districts${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  // Notification Management methods
  async getNotifications(params) {
    const queryParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString());
        }
      });
    }

    const endpoint = `/api/notifications${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return this.request(endpoint);
  }

  async getNotificationById(id) {
    return this.request(`/api/notifications/${id}`);
  }

  async createNotification(notificationData) {
    return this.request('/api/notifications', {
      method: 'POST',
      body: JSON.stringify(notificationData)
    });
  }

  async updateNotification(id, notificationData) {
    return this.request(`/api/notifications/${id}`, {
      method: 'PUT',
      body: JSON.stringify(notificationData)
    });
  }

  async deleteNotification(id) {
    return this.request(`/api/notifications/${id}`, {
      method: 'DELETE'
    });
  }

  async markNotificationAsRead(id) {
    return this.request(`/api/notifications/${id}/mark-read`, {
      method: 'PUT'
    });
  }

  async toggleNotificationStatus(id) {
    return this.request(`/api/notifications/${id}/toggle-status`, {
      method: 'PUT'
    });
  }

  async getUnreadNotificationsCount() {
    return this.request('/api/notifications/unread-count');
  }
}

export const apiService = new ApiService();