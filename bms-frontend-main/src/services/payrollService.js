import { apiService } from './api.jsx';
import { getAuthToken } from '../modules/auth/authSession.js';

export const payrollService = {
  // Loan Types
  getLoanTypes(params) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));

    const endpoint = `/api/loan-types${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getLoanType(loanTypeId) {
    if (!loanTypeId) {
      return Promise.resolve({
        success: false,
        message: 'Loan type ID is required',
      });
    }
    return apiService.request(`/api/loan-types/${encodeURIComponent(loanTypeId)}`, { method: 'GET' });
  },

  createLoanType(payload) {
    return apiService.request('/api/loan-types', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateLoanType(loanTypeId, payload) {
    if (!loanTypeId) {
      return Promise.resolve({
        success: false,
        message: 'Loan type ID is required',
      });
    }
    return apiService.request(`/api/loan-types/${encodeURIComponent(loanTypeId)}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  toggleLoanTypeStatus(loanTypeId, payload = {}) {
    if (!loanTypeId) {
      return Promise.resolve({
        success: false,
        message: 'Loan type ID is required',
      });
    }

    return apiService.request(`/api/loan-types/${encodeURIComponent(loanTypeId)}`, {
      method: 'PATCH',
      body: JSON.stringify(payload || {}),
    });
  },

  getActiveLoanTypes() {
    return apiService.request('/api/loan-types/active', { method: 'GET' });
  },

  // Employee loans
  getEmployeeLoans(params) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));

    const endpoint = `/api/employee-loans${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getEmployeeLoan(employeeLoanId) {
    if (!employeeLoanId) {
      return Promise.resolve({
        success: false,
        message: 'Employee loan ID is required',
      });
    }
    return apiService.request(`/api/employee-loans/${encodeURIComponent(employeeLoanId)}`, { method: 'GET' });
  },

  createEmployeeLoan(payload) {
    return apiService.request('/api/employee-loans', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateEmployeeLoan(employeeLoanId, payload) {
    if (!employeeLoanId) {
      return Promise.resolve({
        success: false,
        message: 'Employee loan ID is required',
      });
    }
    return apiService.request(`/api/employee-loans/${encodeURIComponent(employeeLoanId)}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  toggleEmployeeLoanStatus(employeeLoanId, payload = {}) {
    if (!employeeLoanId) {
      return Promise.resolve({
        success: false,
        message: 'Employee loan ID is required',
      });
    }

    return apiService.request(`/api/employee-loans/${encodeURIComponent(employeeLoanId)}`, {
      method: 'PATCH',
      body: JSON.stringify(payload || {}),
    });
  },

  // Deduction Types
  getDeductionTypes(params) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));

    const endpoint = `/api/deduction-types${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getActiveDeductionTypes() {
    const endpoint = `/api/deduction-types/active`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getDeductionType(deductionTypeId) {
    if (!deductionTypeId) {
      return Promise.resolve({
        success: false,
        message: 'Deduction type ID is required',
      });
    }
    return apiService.request(`/api/deduction-types/${encodeURIComponent(deductionTypeId)}`, { method: 'GET' });
  },

  createDeductionType(payload) {
    return apiService.request('/api/deduction-types', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateDeductionType(deductionTypeId, payload) {
    return apiService.request(`/api/deduction-types/${encodeURIComponent(deductionTypeId)}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  toggleDeductionTypeStatus(deductionTypeId, payload = {}) {
    if (!deductionTypeId) {
      return Promise.resolve({
        success: false,
        message: 'Deduction type ID is required',
      });
    }

    return apiService.request(`/api/deduction-types/${encodeURIComponent(deductionTypeId)}`, {
      method: 'PATCH',
      body: JSON.stringify(payload || {}),
    });
  },

  // Benefit Types
  getBenefitTypes(params) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));

    const endpoint = `/api/benefit-types${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  createBenefitType(payload) {
    return apiService.request('/api/benefit-types', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  getActiveBenefitTypes() {
    const endpoint = `/api/benefit-types/active`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getBenefitType(benefitTypeId) {
    if (!benefitTypeId) {
      return Promise.resolve({
        success: false,
        message: 'Benefit type ID is required',
      });
    }
    return apiService.request(`/api/benefit-types/${encodeURIComponent(benefitTypeId)}`, { method: 'GET' });
  },

  updateBenefitType(benefitTypeId, payload) {
    if (!benefitTypeId) {
      return Promise.resolve({
        success: false,
        message: 'Benefit type ID is required',
      });
    }
    return apiService.request(`/api/benefit-types/${encodeURIComponent(benefitTypeId)}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  toggleBenefitTypeStatus(benefitTypeId, payload = {}) {
    if (!benefitTypeId) {
      return Promise.resolve({
        success: false,
        message: 'Benefit type ID is required',
      });
    }

    return apiService.request(`/api/benefit-types/${encodeURIComponent(benefitTypeId)}`, {
      method: 'PATCH',
      body: JSON.stringify(payload || {}),
    });
  },

  // Employee deductions
  getEmployeeDeductions(params) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));
    if (params?.search) queryParams.append('search', String(params.search));

    const endpoint = `/api/employee-deductions${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getEmployeeDeduction(employeeDeductionId) {
    if (!employeeDeductionId) {
      return Promise.resolve({
        success: false,
        message: 'Employee deduction ID is required',
      });
    }
    return apiService.request(`/api/employee-deductions/${encodeURIComponent(employeeDeductionId)}`, { method: 'GET' });
  },

  createEmployeeDeduction(payload) {
    return apiService.request('/api/employee-deductions', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateEmployeeDeduction(employeeDeductionId, payload) {
    return apiService.request(`/api/employee-deductions/${encodeURIComponent(employeeDeductionId)}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  toggleEmployeeDeductionStatus(employeeDeductionId, payload = {}) {
    if (!employeeDeductionId) {
      return Promise.resolve({
        success: false,
        message: 'Employee deduction ID is required',
      });
    }

    return apiService.request(`/api/employee-deductions/${encodeURIComponent(employeeDeductionId)}`, {
      method: 'PATCH',
      body: JSON.stringify(payload || {}),
    });
  },

  // Employee benefits
  getEmployeeBenefits(params) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));

    const endpoint = `/api/employee-benefits${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getEmployeeBenefit(employeeBenefitId) {
    if (!employeeBenefitId) {
      return Promise.resolve({
        success: false,
        message: 'Employee benefit ID is required',
      });
    }
    return apiService.request(`/api/employee-benefits/${encodeURIComponent(employeeBenefitId)}`, { method: 'GET' });
  },

  updateEmployeeBenefit(employeeBenefitId, payload) {
    if (!employeeBenefitId) {
      return Promise.resolve({
        success: false,
        message: 'Employee benefit ID is required',
      });
    }
    return apiService.request(`/api/employee-benefits/${encodeURIComponent(employeeBenefitId)}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  toggleEmployeeBenefitStatus(employeeBenefitId, payload = {}) {
    if (!employeeBenefitId) {
      return Promise.resolve({
        success: false,
        message: 'Employee benefit ID is required',
      });
    }

    return apiService.request(`/api/employee-benefits/${encodeURIComponent(employeeBenefitId)}`, {
      method: 'PATCH',
      body: JSON.stringify(payload || {}),
    });
  },

  createEmployeeBenefit(payload) {
    return apiService.request('/api/employee-benefits', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  // Arrears Reasons
  getArrearsReasons(params) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));

    const endpoint = `/api/arrears-reasons${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getArrearsReason(arrearsReasonId) {
    if (!arrearsReasonId) {
      return Promise.resolve({
        success: false,
        message: 'Arrears reason ID is required',
      });
    }
    return apiService.request(`/api/arrears-reasons/${encodeURIComponent(arrearsReasonId)}`, { method: 'GET' });
  },

  getActiveArrearsReasons() {
    const endpoint = `/api/arrears-reasons/active`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  createArrearsReason(payload) {
    return apiService.request('/api/arrears-reasons', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateArrearsReason(arrearsReasonId, payload) {
    if (!arrearsReasonId) {
      return Promise.resolve({
        success: false,
        message: 'Arrears reason ID is required',
      });
    }
    return apiService.request(`/api/arrears-reasons/${encodeURIComponent(arrearsReasonId)}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  toggleArrearsReasonStatus(arrearsReasonId, payload = {}) {
    if (!arrearsReasonId) {
      return Promise.resolve({
        success: false,
        message: 'Arrears reason ID is required',
      });
    }
    return apiService.request(`/api/arrears-reasons/${encodeURIComponent(arrearsReasonId)}`, {
      method: 'PATCH',
      body: JSON.stringify(payload || {}),
    });
  },

  // Employee arrears
  getEmployeeArrears(params) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));

    const endpoint = `/api/employee-arrears${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getEmployeeArrear(employeeArrearsId) {
    if (!employeeArrearsId) {
      return Promise.resolve({
        success: false,
        message: 'Employee arrears ID is required',
      });
    }
    return apiService.request(`/api/employee-arrears/${encodeURIComponent(employeeArrearsId)}`, { method: 'GET' });
  },

  getActiveEmployeeArrears() {
    const endpoint = `/api/employee-arrears/active`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  createEmployeeArrear(payload) {
    return apiService.request('/api/employee-arrears', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateEmployeeArrear(employeeArrearsId, payload) {
    if (!employeeArrearsId) {
      return Promise.resolve({
        success: false,
        message: 'Employee arrears ID is required',
      });
    }
    return apiService.request(`/api/employee-arrears/${encodeURIComponent(employeeArrearsId)}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  toggleEmployeeArrearStatus(employeeArrearsId, payload = {}) {
    if (!employeeArrearsId) {
      return Promise.resolve({
        success: false,
        message: 'Employee arrears ID is required',
      });
    }

    return apiService.request(`/api/employee-arrears/${encodeURIComponent(employeeArrearsId)}`, {
      method: 'PATCH',
      body: JSON.stringify(payload || {}),
    });
  },

  // Payroll Management
  getPayrollRuns(params) {
    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));
    if (params?.search) queryParams.append('search', String(params.search));

    const endpoint = `/api/payroll${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getPayrollSummary() {
    const endpoint = `/api/payroll/summary`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getPayrollRun(runId) {
    if (!runId && runId !== 0) {
      return Promise.resolve({ success: false, message: 'Payroll run ID is required' });
    }
    const endpoint = `/api/payroll/${encodeURIComponent(runId)}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getPayrollJournal(runId) {
    if (!runId && runId !== 0) {
      return Promise.resolve({ success: false, message: 'Payroll run ID is required' });
    }
    const endpoint = `/api/payroll/${encodeURIComponent(runId)}/journal`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  getPayrollRunTransactions(runId, params) {
    if (!runId && runId !== 0) {
      return Promise.resolve({ success: false, message: 'Payroll run ID is required' });
    }

    const queryParams = new URLSearchParams();
    if (params?.page) queryParams.append('page', String(params.page));
    if (params?.per_page) queryParams.append('per_page', String(params.per_page));
    if (params?.search) queryParams.append('search', String(params.search));

    const endpoint = `/api/payroll/${encodeURIComponent(runId)}/transactions${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  async getPayrollRunTransactionsDocument(runId) {
    if (!runId && runId !== 0) {
      return { success: false, message: 'Payroll run ID is required' };
    }

    // Backend produces PDF when format=download
    const endpoint = `/api/payroll/${encodeURIComponent(runId)}/transactions?format=download`;
    const url = `${apiService.baseURL}${endpoint}`;

    const token = getAuthToken();
    const headers = {
      Accept: 'application/pdf',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    };

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers,
        mode: 'cors',
        credentials: 'include',
      });

      if (!response.ok) {
        const text = await response.text().catch(() => '');
        throw new Error(text || `HTTP error! status: ${response.status}`);
      }

      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('application/json')) {
        const jsonData = await response.json().catch(() => ({}));
        return {
          success: jsonData.success !== false,
          message: jsonData.message || 'Failed to fetch payroll transactions document',
          data: jsonData.data || jsonData,
          status_code: jsonData.status_code,
        };
      }

      const blob = await response.blob();
      const objectUrl = URL.createObjectURL(blob);
      return {
        success: true,
        message: 'Payroll transactions document fetched successfully',
        data: objectUrl,
      };
    } catch (error) {
      return {
        success: false,
        message: error instanceof Error ? error.message : 'Failed to fetch payroll transactions document',
        errors: error,
      };
    }
  },

  updatePayrollWorkflow(runId, payload = {}) {
    if (!runId && runId !== 0) {
      return Promise.resolve({ success: false, message: 'Payroll run ID is required' });
    }
    const endpoint = `/api/payroll/${encodeURIComponent(runId)}/workflow`;
    return apiService.request(endpoint, {
      method: 'PATCH',
      body: JSON.stringify(payload || {}),
    });
  },

  processPayroll(runId) {
    if (!runId && runId !== 0) {
      return Promise.resolve({ success: false, message: 'Payroll run ID is required' });
    }
    // Backend: PayrollWorkflowService::process()
    // Endpoint naming varies across deployments; this matches the common convention.
    const endpoint = `/api/payroll-runs/${encodeURIComponent(runId)}/process`;
    return apiService.request(endpoint, { method: 'POST' });
  },

  getPayrollErmsExecutions(runId) {
    if (!runId && runId !== 0) {
      return Promise.resolve({ success: false, message: 'Payroll run ID is required' });
    }
    const endpoint = `/api/payroll/${encodeURIComponent(runId)}/erms-executions`;
    return apiService.request(endpoint, { method: 'GET' });
  },

  repostPayrollErms(runId) {
    if (!runId && runId !== 0) {
      return Promise.resolve({ success: false, message: 'Payroll run ID is required' });
    }
    const endpoint = `/api/payroll/${encodeURIComponent(runId)}/erms-repost`;
    return apiService.request(endpoint, { method: 'POST' });
  },

  preparePayroll() {
    return apiService.request('/api/payroll-runs/prepare', { method: 'POST' });
  },

  // Payslips
  // Fetch payslip by month, optionally for a specific PF number.
  // If pf_number is omitted, backend should infer the logged-in user.
  getPayslip(params = {}) {
    const month = params?.month;
    if (!month) {
      return Promise.resolve({ success: false, message: 'Month is required' });
    }

    const queryParams = new URLSearchParams();
    queryParams.append('month', String(month));
    if (params?.pf_number) queryParams.append('pf_number', String(params.pf_number));

    const endpoint = `/api/payslips/pdf${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
    return apiService.request(endpoint, { method: 'POST' });
  },

  getPayrollReportTypes() {
    return apiService.request('/api/payroll/reports/types', { method: 'GET' });
  },

  getPayrollReport(body = {}) {
    return apiService.request('/api/payroll/reports', {
      method: 'POST',
      body: JSON.stringify(body),
    });
  },

  async downloadPayrollReport(body = {}, filename) {
    const url = `${apiService.baseURL}/api/payroll/reports/download`;
    const token = getAuthToken();
    const headers = {
      'Content-Type': 'application/json',
      Accept: 'application/pdf',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    };

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers,
        body: JSON.stringify(body),
        mode: 'cors',
        credentials: 'include',
      });

      if (!response.ok) {
        const text = await response.text().catch(() => '');
        throw new Error(text || `HTTP error! status: ${response.status}`);
      }

      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('application/json')) {
        const jsonData = await response.json().catch(() => ({}));
        return {
          success: jsonData.success !== false,
          message: jsonData.message || 'Failed to download payroll report',
          data: jsonData.data || jsonData,
          status_code: jsonData.status_code,
        };
      }

      const blob = await response.blob();
      return {
        success: true,
        message: 'Payroll report downloaded successfully',
        data: {
          blob,
          contentType,
          filename:
            filename ||
            `${body.report_type || 'payroll'}-report-${body.month || 'export'}.pdf`,
        },
      };
    } catch (error) {
      return {
        success: false,
        message: error instanceof Error ? error.message : 'Failed to download payroll report',
        errors: error,
      };
    }
  },

};

