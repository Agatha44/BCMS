import { apiService } from './api.jsx';

// Overtime-related endpoints grouped behind a dedicated service.
// Internally this still delegates to apiService to avoid behaviour changes.

export const overtimeService = {
  // Overtime rates
  getOvertimeRates(params) {
    return apiService.getOvertimeRates(params);
  },

  getOvertimeRateById(id) {
    return apiService.getOvertimeRateById(id);
  },

  createOvertimeRate(payload) {
    return apiService.createOvertimeRate(payload);
  },

  updateOvertimeRate(id, payload) {
    return apiService.updateOvertimeRate(id, payload);
  },

  toggleOvertimeRateStatus(id) {
    return apiService.toggleOvertimeRateStatus(id);
  },

  deleteOvertimeRate(id) {
    return apiService.deleteOvertimeRate(id);
  },

  // Overtime requests
  getOvertimeRecords(params) {
    return apiService.getOvertimeRecords(params);
  },

  getOvertimeRecordById(id) {
    return apiService.getOvertimeRecordById(id);
  },

  getMyActionedOvertime() {
    return apiService.getMyActionedOvertime();
  },

  createOvertime(payload) {
    return apiService.createOvertime(payload);
  },

  updateOvertime(id, payload) {
    return apiService.updateOvertime(id, payload);
  },

  validatorApproveOvertime(id, payload) {
    return apiService.validatorApproveOvertime(id, payload);
  },

  reviewerApproveOvertime(id, payload) {
    return apiService.reviewerApproveOvertime(id, payload);
  },

  rejectOvertime(id, payload) {
    return apiService.rejectOvertime(id, payload);
  },

  returnOvertime(id, payload) {
    return apiService.returnOvertime(id, payload);
  },

  checkOvertimeDate(payload) {
    return apiService.checkOvertimeDate(payload);
  },

  validateOvertimeAmount(payload) {
    return apiService.validateOvertimeAmount(payload);
  },

  getEmployeeOvertime(params) {
    return apiService.getEmployeeOvertime(params);
  },

  // Batch-related operations
  getReadyForBatch(params) {
    return apiService.getReadyForBatch(params);
  },

  listBatches(params) {
    return apiService.listBatches(params);
  },

  createBatch(payload) {
    return apiService.createBatch(payload);
  },

  getBatch(batchId) {
    return apiService.getBatch(batchId);
  },

  saveAndSubmitBatch(batchId, payload) {
    return apiService.saveAndSubmitBatch(batchId, payload);
  },

  getOvertimeInvoiceDocument(batchNumber) {
    return apiService.getOvertimeInvoiceDocument(batchNumber);
  },

  repostOvertimeBatchErms(batchId) {
    return apiService.repostOvertimeBatchErms(batchId);
  },

  submitOvertimeBatchErms(batchId) {
    return apiService.submitOvertimeBatchErms(batchId);
  },

  // Submission documents
  getSubmissionDocumentTypes() {
    return apiService.getSubmissionDocumentTypes();
  },

  listSubmissionDocuments(params) {
    return apiService.listSubmissionDocuments(params);
  },

  getSubmissionDocument(id) {
    return apiService.getSubmissionDocument(id);
  },

  createSubmissionDocument(data, file) {
    return apiService.createSubmissionDocument(data, file);
  },

  updateSubmissionDocument(id, data, file) {
    return apiService.updateSubmissionDocument(id, data, file);
  },

  toggleSubmissionDocumentStatus(id) {
    return apiService.toggleSubmissionDocumentStatus(id);
  },

  downloadSubmissionDocument(id) {
    return apiService.downloadSubmissionDocument(id);
  },
};


