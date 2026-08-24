import { useState, useEffect, useRef } from 'react';
import { Button, Col, Form, Row, DatePicker, Input, Alert, Table, Tag, Space, App, Tooltip, Card } from 'antd';
import { ClockCircleOutlined, CalendarOutlined, DeleteOutlined, PlusOutlined, StarOutlined, FlagOutlined, CheckCircleOutlined, FileTextOutlined } from '@ant-design/icons';
import PropTypes from 'prop-types';
import dayjs from 'dayjs';
import { overtimeService } from '../../../services/overtimeService.js';

const OvertimeForm = ({ onSubmit, onCancel, mode = 'create', overtimeId, onOpenResubmit }) => {
  const { message } = App.useApp();
  const isResubmit = mode === 'resubmit';
  const [form] = Form.useForm();

  const [loadingAttendance, setLoadingAttendance] = useState(false);
  const [selectedDate, setSelectedDate] = useState(null);
  const [attendanceData, setAttendanceData] = useState(null);
  const [overtimeDaysList, setOvertimeDaysList] = useState([]);
  const [submitting, setSubmitting] = useState(false);
  const [formValues, setFormValues] = useState({ dayDate: null, overtimeHours: '' });
  const [errorMessage, setErrorMessage] = useState(null);
  const [overtimeInfo, setOvertimeInfo] = useState(null);
  const [summary, setSummary] = useState(null);
  const [isSummaryLoading, setIsSummaryLoading] = useState(false);
  const [summaryError, setSummaryError] = useState(null);
  const [loadingRecord, setLoadingRecord] = useState(false);
  const validationTimeoutRef = useRef(null);

  useEffect(() => {
    if (!isResubmit || !overtimeId) return;

    const loadReturnedRecord = async () => {
      setLoadingRecord(true);
      try {
        const response = await overtimeService.getOvertimeRecordById(overtimeId);
        if (response.success && response.data) {
          const days = (response.data.days || []).map((day) => ({
            id: day.id,
            dayDate: day.dayDate,
            signInTime: day.timeIn,
            signOutTime: day.timeOut,
            overtimeHours: Number(day.overtimeHours),
            specialTaskId: day.specialTaskId,
            dailyRate: day.daily_rate,
            amount: day.amount,
            overtimeType: day.overtimeType,
            isHoliday: day.isHoliday,
            holidayName: day.holidayName,
            hasSpecialTask: Boolean(day.specialTaskId),
            specialTask: day.specialTask,
            overtimeReason: day.overtimeReason,
          }));
          setOvertimeDaysList(days.filter((day) => day.dayDate));
        } else {
          message.error(response.message || 'Failed to load overtime record for resubmit');
        }
      } catch (error) {
        console.error('Error loading overtime for resubmit:', error);
        message.error('Failed to load overtime record. Please try again.');
      } finally {
        setLoadingRecord(false);
      }
    };

    loadReturnedRecord();
  }, [isResubmit, overtimeId, message]);

  const formatApiTime = (value) => {
    if (value == null || value === '') return '';
    return String(value).substring(0, 5);
  };

  const applyOvertimeApiData = (overtimeData) => {
    const overtimeHours = Number(overtimeData.overtimeHours) || 0;
    const timeIn = formatApiTime(overtimeData.timeIn);
    const timeOut = formatApiTime(overtimeData.timeOut);

    setAttendanceData({
      signInTime: timeIn || null,
      signOutTime: timeOut || null,
    });

    form.setFieldsValue({
      signInTime: timeIn || '',
      signOutTime: timeOut || '',
      overtimeHours: overtimeHours.toFixed(2),
    });
    setFormValues((prev) => ({ ...prev, overtimeHours: overtimeHours.toFixed(2) }));
    setErrorMessage(null);
  };

  const clearDayFields = () => {
    setAttendanceData(null);
    form.setFieldsValue({ signInTime: '', signOutTime: '', overtimeHours: '' });
    setFormValues((prev) => ({ ...prev, overtimeHours: '' }));
  };

  const checkOvertimeDate = async (date) => {
    if (!date) {
      return { valid: false, message: 'Please select a date', data: null };
    }

    try {
      const dateString = date.format('YYYY-MM-DD');
      const response = await overtimeService.checkOvertimeDate({ date: dateString });

      if (response.success && response.data) {
        setOvertimeInfo(response.data);

        if (response.data.canApply && Number(response.data.overtimeHours) > 0) {
          return {
            valid: true,
            message: response.message || 'Date is valid for overtime submission',
            data: response.data,
          };
        }

        return {
          valid: false,
          message:
            response.data.reason ||
            response.message ||
            'This date is not eligible for overtime submission',
          data: response.data,
        };
      }

      setOvertimeInfo(null);
      return {
        valid: false,
        message: response.message || 'This date is not eligible for overtime submission',
        data: null,
      };
    } catch (error) {
      console.error('Error checking overtime date:', error);
      setOvertimeInfo(null);
      return {
        valid: false,
        message: error?.message || 'Could not load overtime information for this date',
        data: null,
      };
    }
  };

  const handleDateChange = async (date) => {
    setSelectedDate(date);
    setErrorMessage(null);
    setFormValues((prev) => ({ ...prev, dayDate: date }));

    if (!date) {
      clearDayFields();
      setOvertimeInfo(null);
      return;
    }

    setLoadingAttendance(true);

    const dateCheck = await checkOvertimeDate(date);

    if (!dateCheck.valid || !dateCheck.data) {
      setErrorMessage(dateCheck.message);
      if (dateCheck.message) {
        message.error(dateCheck.message);
      }
      clearDayFields();
      setLoadingAttendance(false);
      return;
    }

    // Populate form strictly from check-date API values (no client-side OT calculation)
    applyOvertimeApiData(dateCheck.data);
    setLoadingAttendance(false);
  };

  const getOvertimeTypeBadge = (isHoliday, hasSpecialTask) => {
    if (isHoliday) {
      return (
        <Tag color="gold" icon={<FlagOutlined />}>
          Holiday
        </Tag>
      );
    }
    if (hasSpecialTask) {
      return (
        <Tag color="purple" icon={<FileTextOutlined />}>
          Special Task
        </Tag>
      );
    }
    return (
      <Tag color="blue" icon={<ClockCircleOutlined />}>
        Regular
      </Tag>
    );
  };

  const getPreApprovalIndicator = (specialTask) => {
    if (specialTask?.isPreApproved) {
      return (
        <Tooltip title="This task is pre-approved">
          <Tag color="green" icon={<CheckCircleOutlined />} style={{ marginLeft: 8 }}>
            Pre-approved
          </Tag>
        </Tooltip>
      );
    }
    return null;
  };

  const handleAddToTable = async () => {
    try {
      // Validate form fields
      await form.validateFields(['dayDate', 'overtimeHours']);
      
      const values = form.getFieldsValue();
      
      if (!values.dayDate) {
        message.error('Please select a date');
        return;
      }

      // Check if date is valid for overtime before adding to list
      const dateCheck = await checkOvertimeDate(values.dayDate);
      if (!dateCheck.valid) {
        message.error(dateCheck.message);
        return;
      }

      // Use API overtime hours; sign-out may be null for special tasks (all_hours).
      const overtimeHours = Number(dateCheck.data?.overtimeHours ?? values.overtimeHours) || 0;
      if (
        !attendanceData ||
        overtimeHours <= 0 ||
        (!attendanceData.signInTime && !(overtimeInfo?.canApply && overtimeHours > 0))
      ) {
        const errorMsg = errorMessage || 'No overtime record for this day.';
        message.error(errorMsg);
        return;
      }

      // Check if date already exists in the list
      const dateString = values.dayDate.format('YYYY-MM-DD');
      if (overtimeDaysList.some(item => item.dayDate === dateString)) {
        message.error('This date has already been added to the list. Please select a different date.');
        return;
      }

      // Persist API values on the day row
      const apiData = dateCheck.data || overtimeInfo || {};
      const newDay = {
        id: Date.now(),
        dayDate: dateString,
        signInTime: apiData.timeIn ? formatApiTime(apiData.timeIn) : attendanceData.signInTime || null,
        signOutTime: apiData.timeOut ? formatApiTime(apiData.timeOut) : attendanceData.signOutTime || null,
        overtimeHours,
        overtimeType: apiData.overtimeType || 'regular',
        isHoliday: apiData.isHoliday || false,
        holidayName: apiData.holiday_name || apiData.holidayName || null,
        hasSpecialTask: apiData.hasSpecialTask || false,
        specialTask: apiData.specialTask || null,
        specialTaskId: apiData.specialTask?.id || null,
        overtimeReason: apiData.reason || null,
      };

      setOvertimeDaysList([...overtimeDaysList, newDay]);
      
      // Reset form for next entry
      form.setFieldsValue({ dayDate: null, overtimeHours: '' });
      setFormValues({ dayDate: null, overtimeHours: '' });
      setSelectedDate(null);
      setAttendanceData(null);
      setOvertimeInfo(null);
      
      message.success('Day added to overtime list');
    } catch (error) {
      // Form validation failed
      if (error.errorFields) {
        message.error('Please fill in all required fields');
      }
    }
  };

  const handleRemoveDay = (id) => {
    setOvertimeDaysList(overtimeDaysList.filter(item => item.id !== id));
    message.success('Day removed from list');
  };

  // Validate overtime amount against salary cap whenever the list of days changes
  useEffect(() => {
    // Clear any pending validation when the list changes
    if (validationTimeoutRef.current) {
      clearTimeout(validationTimeoutRef.current);
    }

    const daysCount = overtimeDaysList.length;

    // If no days, clear summary and stop
    if (daysCount === 0) {
      setSummary(null);
      setSummaryError(null);
      setIsSummaryLoading(false);
      return;
    }

    // Debounce actual API call to avoid spamming backend while user is editing
    validationTimeoutRef.current = setTimeout(async () => {
      setIsSummaryLoading(true);
      setSummaryError(null);

      const response = await overtimeService.validateOvertimeAmount({
        estimated_days: daysCount
      });

      if (!response.success || !response.data) {
        // Treat any failure or missing data as "unknown validity" and block submit
        setSummary(null);
        setSummaryError(
          response.message ||
          'Unable to validate overtime amount at the moment. Please try again.'
        );
      } else {
        const d = response.data || {};

        const isValid =
          typeof d.isValid === 'boolean'
            ? d.isValid
            : (typeof d.valid === 'boolean' ? d.valid : true);

        const mappedSummary = {
          salary: d.salary ?? d.basicSalary ?? 0,
          halfSalary: d.halfSalary ?? d.half_salary ?? 0,
          grossPay: d.estimatedGrossPay ?? d.estimated_gross_pay ?? d.grossPay ?? 0,
          netPay: d.estimatedNetPay ?? d.estimated_net_pay ?? d.netPay ?? 0,
          tax: d.estimatedTax ?? d.estimated_tax ?? d.tax ?? 0,
          overtimeDailyRate: d.dailyRate ?? d.overtimeDailyRate ?? d.daily_rate ?? 0,
          days: d.estimatedDays ?? d.estimated_days ?? daysCount,
          isValid: isValid,
          message:
            d.message ||
            response.message ||
            (isValid
              ? 'Overtime amount is within allowed limit.'
              : 'Overtime amount exceeds maximum allowed. Please reduce the number of days.'),
        };

        setSummary(mappedSummary);
        setSummaryError(null);
      }

      setIsSummaryLoading(false);
    }, 400);

    // Cleanup on unmount / next change
    return () => {
      if (validationTimeoutRef.current) {
        clearTimeout(validationTimeoutRef.current);
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [overtimeDaysList]);

  const canSubmit =
    overtimeDaysList.length > 0 &&
    !isSummaryLoading &&
    !!summary &&
    summary.isValid === true &&
    !submitting;

  const handleSubmitMonth = async () => {
    if (overtimeDaysList.length === 0) {
      message.error('Please add at least one day to submit');
      return;
    }

    // Do not allow submission if validation summary is missing or invalid
    if (isSummaryLoading) {
      message.error('Please wait for overtime validation to complete before submitting.');
      return;
    }

    if (!summary) {
      message.error('Unable to validate overtime amount. Please try again before submitting.');
      return;
    }

    if (summary.isValid === false) {
      message.error(summary.message || 'Overtime amount exceeds the allowed limit. Please reduce the number of days.');
      return;
    }

    setSubmitting(true);
    try {
      // Group by month to ensure we're submitting one month at a time
      const months = {};
      overtimeDaysList.forEach(day => {
        const monthKey = dayjs(day.dayDate).format('YYYY-MM');
        if (!months[monthKey]) {
          months[monthKey] = [];
        }
        months[monthKey].push(day);
      });

      // Check if all days are in the same month
      const monthKeys = Object.keys(months);
      if (monthKeys.length > 1) {
        message.error('All overtime days must be in the same month');
        setSubmitting(false);
        return;
      }

      // Prepare data for API submission
      // Attendance validation is enabled - send attendance data with overtime
      // Backend automatically detects overtime type, but we can include context
      const daysPayload = overtimeDaysList.map(day => ({
        dayDate: day.dayDate, // Backend expects camelCase
        signInTime: day.signInTime,
        signOutTime: day.signOutTime,
        overtimeHours: day.overtimeHours,
        // Enhanced fields (backend will validate and use these)
        specialTaskId: day.specialTaskId || null,
        // dailyRate and amount will be calculated by backend if not provided
        dailyRate: day.dailyRate || null,
        amount: day.amount || null,
      }));

      const overtimePayload = {
        month: monthKeys[0],
        days: daysPayload,
        totalOvertimeHours: overtimeDaysList.reduce((sum, day) => sum + day.overtimeHours, 0),
      };

      if (isResubmit && overtimeId) {
        overtimePayload.id = overtimeId;
      }

      const response = await overtimeService.createOvertime(overtimePayload);

      if (response.success) {
        message.success(
          response.message
            || (isResubmit
              ? 'Overtime request resubmitted successfully!'
              : 'Overtime request submitted successfully!'),
        );

        setOvertimeDaysList([]);

        if (onSubmit) {
          await onSubmit(overtimePayload);
        }
      } else {
        const returnedId = response.data?.id;

        if (!isResubmit && returnedId && onOpenResubmit) {
          message.warning(response.message || 'An open overtime request exists for this month. Please resubmit it.');
          onOpenResubmit(returnedId);
          return;
        }

        message.error(response.message || `Failed to ${isResubmit ? 'resubmit' : 'submit'} overtime request`);
      }
    } catch (error) {
      console.error('Form submission error:', error);
      message.error(error.message || 'Error submitting overtime request. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  // Table columns for the days list
  const daysTableColumns = [
    {
      title: 'Date',
      dataIndex: 'dayDate',
      key: 'dayDate',
      width: 120,
      render: (date) => dayjs(date).format('DD-MMM-YYYY'),
    },
    {
      title: 'Type',
      key: 'overtimeType',
      width: 150,
      render: (_, record) => (
        <Space direction="vertical" size={4}>
          {getOvertimeTypeBadge(record.isHoliday, record.hasSpecialTask)}
          {(record.holidayName || record.holiday_name) && (
            <span style={{ fontSize: '11px', color: '#666' }}>
              {record.holiday_name || record.holidayName}
            </span>
          )}
          {record.specialTask && (
            <span style={{ fontSize: '11px', color: '#666' }}>
              {record.specialTask.name}
            </span>
          )}
        </Space>
      ),
    },
    {
      title: 'Sign In',
      dataIndex: 'signInTime',
      key: 'signInTime',
      align: 'center',
      width: 100,
      render: (time) => time && time !== 'N/A' ? time : <span style={{ color: '#999' }}>N/A</span>,
    },
    {
      title: 'Sign Out',
      dataIndex: 'signOutTime',
      key: 'signOutTime',
      align: 'center',
      width: 100,
      render: (time) => time && time !== 'N/A' ? time : <span style={{ color: '#999' }}>N/A</span>,
    },
    {
      title: 'Overtime Hours',
      dataIndex: 'overtimeHours',
      key: 'overtimeHours',
      align: 'center',
      width: 120,
      render: (hours) => (
        <Tag color="orange">{parseFloat(hours).toFixed(2)} hrs</Tag>
      ),
    },
    {
      title: 'Action',
      key: 'action',
      align: 'center',
      width: 80,
      render: (_, record) => (
        <Button
          type="text"
          danger
          icon={<DeleteOutlined />}
          size="small"
          onClick={() => handleRemoveDay(record.id)}
        />
      ),
    },
  ];

  return (
    <div>
      {loadingRecord && (
        <div style={{ textAlign: 'center', padding: '24px 0' }}>Loading overtime days...</div>
      )}
      <Form
        form={form}
        layout="vertical"
        autoComplete="off"
        style={{ textAlign: 'left', display: loadingRecord ? 'none' : undefined }}
      >
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={24} md={12} lg={12}>
            <Form.Item
              name="dayDate"
              label={
                <span>
                  <CalendarOutlined style={{ marginRight: 8 }} />
                  Select Date
                </span>
              }
              rules={[
                {
                  required: true,
                  message: 'Please select a date',
                },
              ]}
            >
              <DatePicker
                style={{ width: '100%' }}
                placeholder="Select date"
                format="YYYY-MM-DD"
                onChange={(date) => {
                  form.setFieldsValue({ dayDate: date });
                  handleDateChange(date);
                }}
                disabledDate={(current) => {
                  // Disable future dates
                  if (current && current > dayjs().endOf('day')) {
                    return true;
                  }
                  // Disable dates that are already in the list
                  if (current) {
                    const dateString = current.format('YYYY-MM-DD');
                    return overtimeDaysList.some(item => item.dayDate === dateString);
                  }
                  return false;
                }}
              />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={12} lg={12}>
            <Form.Item
              name="overtimeHours"
              label="Overtime Hours"
              rules={[
                {
                  required: true,
                  message: 'Overtime hours are provided by the system for the selected date',
                },
              ]}
            >
              <Input
                readOnly
                placeholder="Loaded from overtime check"
                suffix="hours"
                style={{ backgroundColor: '#f5f5f5', cursor: 'not-allowed' }}
              />
            </Form.Item>
          </Col>
        </Row>

        {loadingAttendance && (
          <Row gutter={[16, 16]}>
            <Col span={24}>
              <Alert
                message="Loading overtime information..."
                type="info"
                showIcon
                style={{ marginBottom: 16 }}
              />
            </Col>
          </Row>
        )}

        {/* Display Overtime Type and Context Information */}
        {overtimeInfo && !loadingAttendance && (
          <Row gutter={[16, 16]}>
            <Col span={24}>
              <Alert
                message={
                  <Space direction="vertical" size={8} style={{ width: '100%' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                      <span style={{ fontWeight: 500 }}>Overtime Type:</span>
                      {getOvertimeTypeBadge(
                        overtimeInfo.isHoliday,
                        overtimeInfo.hasSpecialTask
                      )}
                      {getPreApprovalIndicator(overtimeInfo.specialTask)}
                    </div>
                    {(overtimeInfo.holiday_name || overtimeInfo.holidayName) && (
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <StarOutlined style={{ color: '#faad14' }} />
                        <span><strong>Holiday:</strong> {overtimeInfo.holiday_name || overtimeInfo.holidayName}</span>
                      </div>
                    )}
                    {overtimeInfo.hasSpecialTask && overtimeInfo.specialTask && (
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <FileTextOutlined style={{ color: '#722ed1' }} />
                        <span><strong>Special Task:</strong> {overtimeInfo.specialTask.name}</span>
                        {overtimeInfo.specialTask.overtimeRule && (
                          <span style={{ fontSize: '12px', color: '#666', marginLeft: 8 }}>
                            (Rule: {overtimeInfo.specialTask.overtimeRule === 'all_hours' ? 'All hours count' : 'Standard'})
                          </span>
                        )}
                      </div>
                    )}
                    {overtimeInfo.reason && (
                      <div style={{ 
                        padding: '8px 12px', 
                        backgroundColor: '#f0f0f0', 
                        borderRadius: '4px',
                        fontSize: '13px',
                        color: '#595959'
                      }}>
                        {overtimeInfo.reason}
                      </div>
                    )}
                  </Space>
                }
                type="info"
                showIcon
                style={{ marginBottom: 16 }}
              />
            </Col>
          </Row>
        )}

        {attendanceData && !loadingAttendance && (
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={24} md={12} lg={12}>
              <Form.Item
                name="signInTime"
                label={
                  <span>
                    <ClockCircleOutlined style={{ marginRight: 8, color: '#52c41a' }} />
                    Sign In Time
                  </span>
                }
              >
                <Input readOnly style={{ backgroundColor: '#f5f5f5' }} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={24} md={12} lg={12}>
              <Form.Item
                name="signOutTime"
                label={
                  <span>
                    <ClockCircleOutlined style={{ marginRight: 8, color: '#ff4d4f' }} />
                    Sign Out Time
                  </span>
                }
              >
                <Input readOnly style={{ backgroundColor: '#f5f5f5' }} />
              </Form.Item>
            </Col>
            {overtimeInfo && (
              <Col xs={24} sm={24} md={12} lg={12}>
                <Form.Item label="Total Worked Hours">
                  <Input
                    readOnly
                    value={Number(overtimeInfo.totalWorkedHours || 0).toFixed(2)}
                    suffix="hours"
                    style={{ backgroundColor: '#f5f5f5' }}
                  />
                </Form.Item>
              </Col>
            )}
          </Row>
        )}

        {overtimeInfo && overtimeInfo.canApply && overtimeInfo.overtimeHours > 0 && attendanceData && (
          <Row gutter={[16, 16]}>
            <Col span={24}>
              <Alert
                message={`Overtime hours: ${Number(overtimeInfo.overtimeHours || 0).toFixed(2)} hours (from system). This value cannot be edited.`}
                type="success"
                showIcon
                style={{ marginBottom: 16 }}
              />
            </Col>
          </Row>
        )}

        {!attendanceData && selectedDate && !loadingAttendance && errorMessage && (
          <Row gutter={[16, 16]}>
            <Col span={24}>
              <Alert
                message={errorMessage}
                type="error"
                showIcon
                style={{ marginBottom: 16 }}
              />
            </Col>
          </Row>
        )}

        {formValues.dayDate && overtimeDaysList.some(item => item.dayDate === formValues.dayDate.format('YYYY-MM-DD')) && (
          <Row gutter={[16, 16]}>
            <Col span={24}>
              <Alert
                message="This date has already been added to the list. Please select a different date."
                type="error"
                showIcon
                style={{ marginBottom: 16 }}
              />
            </Col>
          </Row>
        )}

        <Row gutter={[16, 16]}>
          <Col span={24}>
            <Form.Item style={{ marginBottom: 16 }}>
              <Button
                type="primary"
                icon={<PlusOutlined />}
                onClick={handleAddToTable}
                disabled={
                  !formValues.dayDate ||
                  !attendanceData ||
                  !formValues.overtimeHours ||
                  parseFloat(formValues.overtimeHours) <= 0 ||
                  (formValues.dayDate &&
                    overtimeDaysList.some(
                      (item) => item.dayDate === formValues.dayDate.format('YYYY-MM-DD')
                    )) ||
                  (overtimeInfo && !overtimeInfo.canApply) ||
                  loadingAttendance
                }
                style={{
                  backgroundColor: '#962E32',
                  borderColor: '#962E32',
                }}
              >
                Add to List
              </Button>
            </Form.Item>
          </Col>
        </Row>
      </Form>

      {/* Table showing added days */}
      {overtimeDaysList.length > 0 && (
        <div style={{ marginTop: 24 }}>
          <div style={{ marginBottom: 16, fontWeight: 500, fontSize: 16 }}>
            Overtime Days Added ({overtimeDaysList.length} day{overtimeDaysList.length !== 1 ? 's' : ''})
          </div>
          <Table
            columns={daysTableColumns}
            dataSource={overtimeDaysList}
            pagination={false}
            rowKey="id"
            size="small"
            bordered
            style={{ marginBottom: 16 }}
          />

          {/* Payment Summary Panel - reflects latest backend validation */}
          <Card
            title="Payment Summary"
            size="small"
            style={{ marginBottom: 16 }}
          >
            {isSummaryLoading && (
              <Alert
                message="Calculating overtime payment summary..."
                type="info"
                showIcon
                style={{ marginBottom: 12 }}
              />
            )}

            {summaryError && !isSummaryLoading && (
              <Alert
                message={summaryError}
                type="warning"
                showIcon
                style={{ marginBottom: 12 }}
              />
            )}

            {!isSummaryLoading && summary && (
              <>
                <div style={{ marginBottom: 8, fontWeight: 500 }}>Overtime selection</div>
                <Row gutter={16}>
                  <Col xs={12} sm={8}>
                    <div style={{ fontSize: 12, color: '#555' }}>Overtime days</div>
                    <div style={{ fontWeight: 500 }}>
                      {summary.days}
                    </div>
                  </Col>
                  <Col xs={12} sm={8}>
                    <div style={{ fontSize: 12, color: '#555' }}>Overtime daily rate</div>
                    <div style={{ fontWeight: 500 }}>
                      {new Intl.NumberFormat('en-TZ', { minimumFractionDigits: 0 }).format(summary.overtimeDailyRate || 0)}
                    </div>
                  </Col>
                </Row>

                <div style={{ margin: '12px 0 4px', fontWeight: 500 }}>Salary information</div>
                <Row gutter={16}>
                  <Col xs={12} sm={8}>
                    <div style={{ fontSize: 12, color: '#555' }}>Basic salary</div>
                    <div style={{ fontWeight: 500 }}>
                      {new Intl.NumberFormat('en-TZ', { minimumFractionDigits: 0 }).format(summary.salary || 0)}
                    </div>
                  </Col>
                  <Col xs={12} sm={8}>
                    <div style={{ fontSize: 12, color: '#555' }}>Half salary (max allowed gross)</div>
                    <div style={{ fontWeight: 500 }}>
                      {new Intl.NumberFormat('en-TZ', { minimumFractionDigits: 0 }).format(summary.halfSalary || 0)}
                    </div>
                  </Col>
                </Row>

                <div style={{ margin: '12px 0 4px', fontWeight: 500 }}>Payment breakdown</div>
                <Row gutter={16}>
                  <Col xs={12} sm={8}>
                    <div style={{ fontSize: 12, color: '#555' }}>Gross pay</div>
                    <div style={{ fontWeight: 500 }}>
                      {new Intl.NumberFormat('en-TZ', { minimumFractionDigits: 0 }).format(summary.grossPay || 0)}
                    </div>
                  </Col>
                  <Col xs={12} sm={8}>
                    <div style={{ fontSize: 12, color: '#555' }}>Net pay (you receive)</div>
                    <div style={{ fontWeight: 500 }}>
                      {new Intl.NumberFormat('en-TZ', { minimumFractionDigits: 0 }).format(summary.netPay || 0)}
                    </div>
                  </Col>
                  <Col xs={12} sm={8}>
                    <div style={{ fontSize: 12, color: '#555' }}>Withholding tax (30%)</div>
                    <div style={{ fontWeight: 500 }}>
                      {new Intl.NumberFormat('en-TZ', { minimumFractionDigits: 0 }).format(summary.tax || 0)}
                    </div>
                  </Col>
                </Row>

                <div style={{ marginTop: 12 }}>
                  {summary.isValid ? (
                    <Alert
                      message={summary.message || 'Overtime amount is within allowed limit.'}
                      type="success"
                      showIcon
                    />
                  ) : (
                    <Alert
                      message={summary.message || 'Overtime gross pay exceeds half of your salary. Please reduce the number of overtime days.'}
                      type="error"
                      showIcon
                    />
                  )}
                </div>
              </>
            )}
          </Card>

          <div style={{ 
            display: 'flex', 
            justifyContent: 'space-between', 
            alignItems: 'center',
            padding: '12px',
            backgroundColor: '#f5f5f5',
            borderRadius: '4px',
            marginBottom: 16
          }}>
            <span style={{ fontWeight: 500 }}>
              Total Overtime Hours: <Tag color="orange" style={{ fontSize: 14 }}>
                {overtimeDaysList.reduce((sum, day) => sum + day.overtimeHours, 0).toFixed(2)} hrs
              </Tag>
            </span>
            <Space>
              <Button onClick={onCancel}>
                Close
              </Button>
              <Button
                type="primary"
                onClick={handleSubmitMonth}
                loading={submitting}
                disabled={!canSubmit}
                style={{
                  backgroundColor: '#962E32',
                  borderColor: '#962E32',
                }}
              >
                {isResubmit ? 'Resubmit' : 'Submit'}
              </Button>
            </Space>
          </div>
        </div>
      )}

      {overtimeDaysList.length === 0 && (
        <Row gutter={[16, 16]}>
          <Col span={24}>
            <Form.Item style={{ marginBottom: 0 }}>
              <div style={{ width: '100%', display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                <Button onClick={onCancel}>
                  Cancel
                </Button>
              </div>
            </Form.Item>
          </Col>
        </Row>
      )}
    </div>
  );
};

OvertimeForm.propTypes = {
  onSubmit: PropTypes.func,
  onCancel: PropTypes.func,
  mode: PropTypes.oneOf(['create', 'resubmit']),
  overtimeId: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
  onOpenResubmit: PropTypes.func,
};

export default OvertimeForm;
