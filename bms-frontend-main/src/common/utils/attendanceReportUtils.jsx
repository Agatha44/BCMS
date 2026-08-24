import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
import dayjs from 'dayjs';
import jsPDF from 'jspdf';

// Generate PDF report for all users
export const generatePDFReportAllUsers = (
  filteredAttendanceData,
  filterType,
  dateFilter,
  monthFilter,
  searchText,
  message
) => {
  if (!message) {
    console.error('Message object is required for generatePDFReportAllUsers');
    return;
  }
  try {
    // Use static import of jsPDF
    const doc = new jsPDF();
      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      let yPos = 20;

      // Title
      doc.setFontSize(18);
      doc.setTextColor(150, 46, 50); // #962E32 color
      doc.text('Attendance Report - All Users', pageWidth / 2, yPos, { align: 'center' });
      yPos += 10;

      // Report date
      doc.setFontSize(10);
      doc.setTextColor(0, 0, 0);
      const reportDate = dayjs().format('DD-MMM-YYYY, dddd');
      doc.text(`Generated on: ${reportDate}`, pageWidth / 2, yPos, { align: 'center' });
      yPos += 10;

      // Filter info
      if (filterType === 'daily' && dateFilter) {
        const dateValue = dateFilter.format('DD-MMM-YYYY');
        doc.text(`Date: ${dateValue}`, 14, yPos);
        yPos += 7;
      } else if (filterType === 'monthly' && monthFilter) {
        const monthValue = monthFilter.format('MMMM YYYY');
        doc.text(`Month: ${monthValue}`, 14, yPos);
        yPos += 7;
      }

      // Search filter info
      if (searchText && searchText.trim()) {
        doc.text(`Search Filter: "${searchText}"`, 14, yPos);
        yPos += 7;
      }

      // Record count
      doc.text(`Total Records: ${String(filteredAttendanceData.length)}`, 14, yPos);
      yPos += 15;

      // Table headers
      doc.setFontSize(12);
      doc.setFont(undefined, 'bold');
      const headers = ['S.No', 'PF Number', 'Name', 'Date', 'Time In', 'Time Out'];
      const colWidths = [15, 30, 50, 40, 25, 25];
      let xPos = 14;

      headers.forEach((header, index) => {
        doc.text(header, xPos, yPos);
        xPos += colWidths[index];
      });
      yPos += 8;

      // Table data
      doc.setFont(undefined, 'normal');
      doc.setFontSize(10);
      filteredAttendanceData.forEach((record, index) => {
        if (yPos > pageHeight - 20) {
          doc.addPage();
          yPos = 20;
        }

        xPos = 14;
        const rowData = [
          (index + 1).toString(),
          record.pfNumber != null ? String(record.pfNumber) : '',
          record.employeeName != null ? String(record.employeeName) : '',
          record.dayDate ? dayjs(record.dayDate).format('DD-MMM-YYYY') : '',
          record.timeIn != null ? String(record.timeIn) : '',
          record.timeOut != null ? String(record.timeOut) : '',
        ];

        rowData.forEach((data, colIndex) => {
          // Ensure data is always a string
          const stringData = data != null ? String(data) : '';
          doc.text(stringData, xPos, yPos);
          xPos += colWidths[colIndex];
        });
        yPos += 7;
      });

      // Save PDF
      const dateStr = filterType === 'daily' && dateFilter
        ? dateFilter.format('YYYY-MM-DD')
        : (monthFilter ? monthFilter.format('YYYY-MM') : 'All');
    const fileName = `Attendance_Report_${dateStr}_${dayjs().format('YYYYMMDD')}.pdf`;
    doc.save(fileName);
    message.success('PDF report generated successfully!');
  } catch (error) {
    message.error('Error generating PDF report');
    console.error(error);
  }
};

// Generate PDF report for single user
export const generatePDFReportSingleUser = (record, message) => {
  if (!message) {
    console.error('Message object is required for generatePDFReportSingleUser');
    return;
  }
  try {
    // Use static import of jsPDF
    const doc = new jsPDF();
      const pageWidth = doc.internal.pageSize.getWidth();
      let yPos = 20;

      // Title
      doc.setFontSize(18);
      doc.setTextColor(150, 46, 50);
      doc.text('Attendance Report - Individual', pageWidth / 2, yPos, { align: 'center' });
      yPos += 15;

      // Employee Info
      doc.setFontSize(12);
      doc.setFont(undefined, 'bold');
      doc.setTextColor(0, 0, 0);
      doc.text('Employee Information:', 14, yPos);
      yPos += 8;
      doc.setFont(undefined, 'normal');
      doc.setFontSize(10);
      doc.text(`PF Number: ${record.pfNumber != null ? String(record.pfNumber) : 'N/A'}`, 14, yPos);
      yPos += 7;
      doc.text(`Name: ${record.employeeName != null ? String(record.employeeName) : 'N/A'}`, 14, yPos);
      yPos += 7;
      doc.text(`Date: ${record.dayDate ? dayjs(record.dayDate).format('DD-MMM-YYYY') : 'N/A'}`, 14, yPos);
      yPos += 10;

      // Sessions table
      doc.setFont(undefined, 'bold');
      doc.text('Daily Sessions:', 14, yPos);
      yPos += 8;
      doc.setFont(undefined, 'normal');

      if (record.dailyRecords && record.dailyRecords.length > 0) {
        const sessionHeaders = ['S.No', 'Time In', 'Time Out'];
        const sessionColWidths = [20, 50, 50];
        let xPos = 14;

        sessionHeaders.forEach((header, index) => {
          doc.setFont(undefined, 'bold');
          doc.text(header, xPos, yPos);
          xPos += sessionColWidths[index];
        });
        yPos += 8;
        doc.setFont(undefined, 'normal');

        record.dailyRecords.forEach((session, index) => {
          xPos = 14;
          [index + 1, session.timeIn, session.timeOut].forEach((data, colIndex) => {
            // Ensure data is always a string
            const stringData = data != null ? String(data) : '';
            doc.text(stringData, xPos, yPos);
            xPos += sessionColWidths[colIndex];
          });
          yPos += 7;
        });
      }

    const fileName = `Attendance_Report_${record.employeeId}_${record.dayDate}.pdf`;
    doc.save(fileName);
    message.success('PDF report generated successfully!');
  } catch (error) {
    message.error('Error generating PDF report');
    console.error(error);
  }
};

// Generate Excel report for all users
export const generateExcelReportAllUsers = (
  filteredAttendanceData,
  filterType,
  dateFilter,
  monthFilter,
  searchText,
  message
) => {
  if (!message) {
    console.error('Message object is required for generateExcelReportAllUsers');
    return;
  }
  try {
    const worksheetData = [
      ['Attendance Report'],
      [],
      ['Generated on:', dayjs().format('DD-MMM-YYYY, dddd')],
      filterType === 'daily' && dateFilter ? ['Date:', dateFilter.format('DD-MMM-YYYY')] :
      filterType === 'monthly' && monthFilter ? ['Month:', monthFilter.format('MMMM YYYY')] : null,
      searchText && searchText.trim() ? ['Search Filter:', searchText] : null,
      ['Total Records:', filteredAttendanceData.length],
      [],
      ['S.No', 'PF Number', 'Employee Name', 'Date', 'Time In', 'Time Out'],
      ...filteredAttendanceData.map((record, index) => [
        index + 1,
        record.pfNumber || '',
        record.employeeName,
        record.dayDate ? dayjs(record.dayDate).format('DD-MMM-YYYY') : '',
        record.timeIn,
        record.timeOut,
      ]),
    ].filter(row => row !== null);

    const worksheet = XLSX.utils.aoa_to_sheet(worksheetData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Attendance Report');

    // Set column widths
    worksheet['!cols'] = [
      { wch: 8 },
      { wch: 15 },
      { wch: 25 },
      { wch: 30 },
      { wch: 12 },
      { wch: 12 },
    ];

    const excelBuffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
    const data = new Blob([excelBuffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const dateStr = filterType === 'daily' && dateFilter
      ? dateFilter.format('YYYY-MM-DD')
      : (monthFilter ? monthFilter.format('YYYY-MM') : 'All');
    const fileName = `Attendance_Report_${dateStr}_${dayjs().format('YYYYMMDD')}.xlsx`;
    saveAs(data, fileName);
    message.success('Excel report generated successfully!');
  } catch (error) {
    message.error('Error generating Excel report');
    console.error(error);
  }
};

// Generate Excel report for single user
export const generateExcelReportSingleUser = (record, message) => {
  if (!message) {
    console.error('Message object is required for generateExcelReportSingleUser');
    return;
  }
  try {
    const worksheetData = [
      ['Employee Information'],
      ['PF Number', record.pfNumber || 'N/A'],
      ['Employee Name', record.employeeName],
      ['Date', record.dayDate ? dayjs(record.dayDate).format('DD-MMM-YYYY') : 'N/A'],
      [],
      ['Daily Sessions'],
      ['S.No', 'Time In', 'Time Out'],
      ...(record.dailyRecords || []).map((session, index) => [
        index + 1,
        session.timeIn,
        session.timeOut,
      ]),
    ];

    const worksheet = XLSX.utils.aoa_to_sheet(worksheetData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Attendance Report');

    worksheet['!cols'] = [{ wch: 15 }, { wch: 25 }];

    const excelBuffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
    const data = new Blob([excelBuffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const fileName = `Attendance_Report_${record.employeeId}_${record.dayDate}.xlsx`;
    saveAs(data, fileName);
    message.success('Excel report generated successfully!');
  } catch (error) {
    message.error('Error generating Excel report');
    console.error(error);
  }
};

