import dayjs from 'dayjs';

/**
 * Format date with day name
 */
export const formatDateWithDay = (dateString) => {
  if (!dateString) return '';

  const dateParts = dateString.split('-');
  if (dateParts.length !== 3) {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    dateParts[0] = date.getFullYear();
    dateParts[1] = date.getMonth() + 1;
    dateParts[2] = date.getDate();
  }

  const year = parseInt(dateParts[0], 10);
  const month = parseInt(dateParts[1], 10) - 1;
  const day = parseInt(dateParts[2], 10);

  const date = new Date(year, month, day);
  const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  const dayName = days[date.getDay()];
  const monthName = months[date.getMonth()];

  return `${day}-${monthName}-${year}, ${dayName}`;
};

/**
 * Format time from API response (handles various formats)
 */
export const formatTime = (timeString) => {
  if (!timeString) return '';

  if (timeString.includes('AM') || timeString.includes('PM')) {
    return timeString;
  }

  try {
    const date = new Date(timeString);
    if (!isNaN(date.getTime())) {
      const hours = date.getHours();
      const minutes = date.getMinutes();
      const ampm = hours >= 12 ? 'PM' : 'AM';
      const displayHours = hours % 12 || 12;
      const displayMinutes = minutes.toString().padStart(2, '0');
      return `${displayHours}:${displayMinutes} ${ampm}`;
    }
  } catch (e) {
    // If parsing fails, return as is
  }

  return timeString;
};

/**
 * Transform API response to component format
 */
export const transformAttendanceData = (apiData) => {
  if (!Array.isArray(apiData) || apiData.length === 0) {
    return [];
  }

  const groupedData = {};

  apiData.forEach((record) => {
    const recordDate = record.time_in || record.timeIn || record.time_out || record.timeOut || record.date || record.dayDate || record.created_at || record.createdAt;
    if (!recordDate) return;

    let normalizedDate;
    try {
      const dateObj = new Date(recordDate);
      if (isNaN(dateObj.getTime())) return;
      normalizedDate = dayjs(dateObj).format('YYYY-MM-DD');
    } catch (e) {
      return;
    }

    const actualUserId = record.user_id;
    const pfNumber = record.pf_number;
    const employeeId = pfNumber;
    const employeeName = record.employeeName || 'N/A';

    const groupKey = employeeId ? `${employeeId}-${normalizedDate}` : `${record.id}-${normalizedDate}`;

    if (!groupedData[groupKey]) {
      groupedData[groupKey] = {
        id: record.id || groupKey,
        employeeId: employeeId,
        userId: actualUserId,
        pfNumber: pfNumber,
        employeeName: employeeName,
        dayDate: normalizedDate,
        timeIn: '',
        timeOut: '',
        overtimeStatus: record.overtime_status || null,
        dailyRecords: [],
      };
    } else {
      // Update overtime_status if not already set or if current record has a non-null value
      if (!groupedData[groupKey].overtimeStatus && record.overtime_status) {
        groupedData[groupKey].overtimeStatus = record.overtime_status;
      }
    }

    const timeIn = formatTime(record.time_in || record.timeIn || record.sign_in_time);
    const timeOut = formatTime(record.time_out || record.timeOut || record.sign_out_time);

    groupedData[groupKey].dailyRecords.push({
      sessionId: record.session_id || record.id || `${record.id || groupedData[groupKey].dailyRecords.length + 1}`,
      timeIn: timeIn || (timeOut ? 'N/A' : ''),
      timeOut: timeOut || (timeIn ? 'N/A' : ''),
    });
  });

  const transformedData = Object.values(groupedData).map((group) => {
    if (group.dailyRecords.length > 0) {
      const recordsWithTimeIn = group.dailyRecords.filter(r => r.timeIn && r.timeIn !== 'N/A' && r.timeIn.trim() !== '');
      if (recordsWithTimeIn.length > 0) {
        const sortedByTimeIn = [...recordsWithTimeIn].sort((a, b) => {
          const timeA = a.timeIn.replace(/[^\d:]/g, '');
          const timeB = b.timeIn.replace(/[^\d:]/g, '');
          return timeA.localeCompare(timeB);
        });
        group.timeIn = sortedByTimeIn[0].timeIn;
      } else {
        group.timeIn = group.dailyRecords[0]?.timeIn || '';
      }

      const recordsWithTimeOut = group.dailyRecords.filter(r => r.timeOut && r.timeOut !== 'N/A' && r.timeOut.trim() !== '');
      if (recordsWithTimeOut.length > 0) {
        const sortedByTimeOut = [...recordsWithTimeOut].sort((a, b) => {
          const timeA = a.timeOut.replace(/[^\d:]/g, '');
          const timeB = b.timeOut.replace(/[^\d:]/g, '');
          return timeB.localeCompare(timeA);
        });
        group.timeOut = sortedByTimeOut[0].timeOut;
      } else {
        const activeSession = group.dailyRecords.find(r => r.timeIn && !r.timeOut);
        group.timeOut = activeSession ? '' : (group.dailyRecords[0]?.timeOut || '');
      }

      const sortedRecords = [...group.dailyRecords].sort((a, b) => {
        const timeA = (a.timeIn || '').replace(/[^\d:]/g, '');
        const timeB = (b.timeIn || '').replace(/[^\d:]/g, '');
        if (!timeA && !timeB) return 0;
        if (!timeA) return 1;
        if (!timeB) return -1;
        return timeA.localeCompare(timeB);
      });
      group.dailyRecords = sortedRecords;
    }

    return group;
  });

  console.log(transformedData);

  return transformedData;
};
