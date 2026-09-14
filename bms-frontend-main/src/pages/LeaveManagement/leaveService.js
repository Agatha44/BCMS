import { apiService } from '../../services/api.jsx';

export const leaveService = {
  listApplications(params = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        query.append(key, String(value));
      }
    });
    const suffix = query.toString() ? `?${query.toString()}` : '';
    return apiService.request(`/api/leave/applications${suffix}`);
  },

  createApplication({ leaveType, startDate, endDate, reason, file }) {
    const fd = new FormData();
    fd.append('leave_type', leaveType);
    fd.append('start_date', startDate);
    fd.append('end_date', endDate);
    fd.append('reason', reason);
    if (file instanceof File) {
      fd.append('supportive_document', file);
    }
    return apiService.request('/api/leave/applications', {
      method: 'POST',
      body: fd,
    });
  },

  verifyApplication(id, comment) {
    return apiService.request(`/api/leave/applications/${encodeURIComponent(id)}/verify`, {
      method: 'POST',
      body: JSON.stringify({ comment: comment || '' }),
    });
  },

  approveApplication(id, comment) {
    return apiService.request(`/api/leave/applications/${encodeURIComponent(id)}/approve`, {
      method: 'POST',
      body: JSON.stringify({ comment: comment || '' }),
    });
  },

  rejectApplication(id, reason) {
    return apiService.request(`/api/leave/applications/${encodeURIComponent(id)}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  },
};
