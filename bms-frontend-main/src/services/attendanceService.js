import { apiService } from './api.jsx';

// Thin attendance-focused wrapper around apiService.
// This makes it easy to migrate away from the big ApiService later
// without changing call-sites again.

export const attendanceService = {
  getUserSessions(params) {
    return apiService.getUserSessions(params);
  },

  getUserSessionsById(userId, params) {
    return apiService.getUserSessionsById(userId, params);
  },

  getAllSessions(params) {
    return apiService.getAllSessions(params);
  },

  getSessionById(id) {
    return apiService.getSessionById(id);
  },

  calculateHoursWorked(params) {
    return apiService.calculateHoursWorked(params);
  },

  calculateHoursWorkedByUser(userId, params) {
    return apiService.calculateHoursWorkedByUser(userId, params);
  },

  getAttendanceManagement(params) {
    return apiService.getAttendanceManagement(params);
  },

  getAttendanceByDate(date, returnedRequestId) {
    return apiService.getAttendanceByDate(date, returnedRequestId);
  }
};


