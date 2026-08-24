import { useEffect, useMemo, useState } from 'react';
import { useSelector } from 'react-redux';
import { App, Card, Form, Button, DatePicker, Divider, Empty, Alert, Select } from 'antd';
import dayjs from 'dayjs';
import '../../styles/common.css';
import { payrollService } from '../../services/payrollService';
import { apiService } from '../../services/api.jsx';
import { extractArrayFromResponse, formatEmployeeName } from '../../common/utils/employeeUtils.jsx';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';

const { MonthPicker } = DatePicker;

const normalizeRole = (role) => String(role || '').trim().toLowerCase();

const getUserPfNumber = (user) => {
  if (!user) return '';
  const pf =
    user?.pf_number ||
    user?.pfNumber ||
    user?.pfno ||
    user?.pf_no ||
    user?.pf ||
    '';
  return String(pf || '').trim();
};

const extractPayslipPdf = (data) => {
  // Allow common wrapper shapes, but we expect:
  // { pdf_base64: string, file_name: string }
  if (!data || typeof data !== 'object') return null;
  const payload = data.data || data.payslip || data;
  if (!payload || typeof payload !== 'object') return null;
  if (!payload.pdf_base64) return null;
  return {
    pdf_base64: String(payload.pdf_base64),
    file_name: payload.file_name ? String(payload.file_name) : 'payslip.pdf',
  };
};

const base64ToPdfBlob = (base64) => {
  const clean = String(base64).replace(/^data:application\/pdf;base64,/, '');
  const binary = atob(clean);
  const len = binary.length;
  const bytes = new Uint8Array(len);
  for (let i = 0; i < len; i += 1) bytes[i] = binary.charCodeAt(i);
  return new Blob([bytes], { type: 'application/pdf' });
};

/**
 * @param {{ selfOnly?: boolean }} props
 * When `selfOnly` is true (employee-management self-service), the page always
 * scopes to the logged-in user's PF and never shows the employee picker.
 */
const Payslips = ({ selfOnly = false }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const currentUser = useSelector((state) => state.auth.user);
  const selectedRole = useSelector((state) => state.app.selectedRole);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [pdfUrl, setPdfUrl] = useState('');

  const userPfNumber = useMemo(() => getUserPfNumber(currentUser), [currentUser]);

  const [employees, setEmployees] = useState([]);
  const [employeesLoading, setEmployeesLoading] = useState(false);

  // Roles allowed to view other employees' payslips (payroll module only).
  // Self-service under employee-management always locks to the current user.
  const canViewOthers = useMemo(() => {
    if (selfOnly) return false;
    const role = normalizeRole(selectedRole || currentUser?.role);
    return (
      role.includes('payroll') ||
      role.includes('initiator') ||
      role.includes('examiner') ||
      role.includes('verifier') ||
      role.includes('approver')
    );
  }, [selfOnly, selectedRole, currentUser]);

  const employeeSelectOptions = useMemo(() => {
    const toPfNumber = (emp) => emp?.pf_number ?? emp?.pfno ?? emp?.pfNumber ?? emp?.pf;
    return (employees || [])
      .map((emp) => {
        const pfNumber = toPfNumber(emp);
        const pf = pfNumber != null ? String(pfNumber).trim() : '';
        if (!pf) return null;
        const fullName = formatEmployeeName(emp);
        return { label: `${pf} - ${fullName}`, value: pf };
      })
      .filter(Boolean);
  }, [employees]);

  const onFinish = async (values) => {
    setError('');
    setPdfUrl('');
    setLoading(true);

    try {
      const monthValue = values.month;
      const month = monthValue ? dayjs(monthValue).format('YYYY-MM') : '';

      const pf_number = canViewOthers ? String(values.pf_number || '').trim() : userPfNumber;

      if (!month) {
        setError('Please select a month.');
        setLoading(false);
        return;
      }

      if (canViewOthers && !pf_number) {
        setError('Please select PF or Employee Name.');
        setLoading(false);
        return;
      }

      if (!canViewOthers && !pf_number) {
        setError('Your account has no PF Number. Please contact the administrator.');
        setLoading(false);
        return;
      }

      const response = await payrollService.getPayslip({ month, pf_number });
      if (!response?.success) {
        setError(response?.message || 'Failed to fetch payslip.');
        setLoading(false);
        return;
      }

      const pdfPayload = extractPayslipPdf(response.data);
      if (!pdfPayload) {
        setError('Payslip was generated, but the response did not include pdf_base64.');
        setLoading(false);
        return;
      }

      const blob = base64ToPdfBlob(pdfPayload.pdf_base64);
      const url = URL.createObjectURL(blob);
      setPdfUrl(url);
    } catch (e) {
      setError(e?.message || 'Failed to fetch payslip.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    return () => {
      if (pdfUrl) URL.revokeObjectURL(pdfUrl);
    };
  }, [pdfUrl]);

  useEffect(() => {
    let cancelled = false;
    if (!canViewOthers) return undefined;

    const loadEmployees = async () => {
      setEmployeesLoading(true);
      try {
        // We rely on the backend's employee fields, but we only show (and submit) PF number.
        const response = await apiService.getBridgeEmployees({ per_page: 1000 });
        if (!response?.success) {
          setEmployees([]);
          message.error(response?.message || 'Failed to load employees for payslip search.');
          return;
        }

        const extracted = extractArrayFromResponse(response.data);
        const list = Array.isArray(extracted?.items) ? extracted.items : [];
        if (!cancelled) setEmployees(list);
      } catch (e) {
        if (!cancelled) setEmployees([]);
        message.error(e?.message || 'Failed to load employees for payslip search.');
      } finally {
        if (!cancelled) setEmployeesLoading(false);
      }
    };

    loadEmployees();
    return () => {
      cancelled = true;
    };
  }, [canViewOthers, message]);

  return (
    <Card title={selfOnly ? 'My Payslips' : 'Payslips'}>
      <Form
        form={form}
        layout="vertical"
        onFinish={onFinish}
        initialValues={{
          month: dayjs(),
          pf_number: '',
        }}
      >
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {canViewOthers && (
            <Form.Item
              label="PF Number / Employee Name"
              name="pf_number"
              rules={[{ required: true, message: 'Please select PF or Employee Name' }]}
            >
              <Select
                placeholder="Search PF number or name"
                showSearch
                allowClear
                loading={employeesLoading}
                optionFilterProp="label"
                filterOption={(input, option) =>
                  String(option?.label ?? '')
                    .toLowerCase()
                    .includes(String(input ?? '').toLowerCase())
                }
                options={employeeSelectOptions}
              />
            </Form.Item>
          )}

          <Form.Item
            label="Month"
            name="month"
            rules={[{ required: true, message: 'Month is required' }]}
          >
            <MonthPicker format="YYYY-MM" placeholder="Select month" className="w-full" />
          </Form.Item>

          <Form.Item label=" " colon={false}>
            <Button type="primary" htmlType="submit" loading={loading} className="w-full">
              Search
            </Button>
          </Form.Item>
        </div>
      </Form>

      {error && (
        <Alert
          type="error"
          showIcon
          message="Unable to retrieve payslip"
          description={error}
          className="mb-4"
        />
      )}

      <Divider />

      {loading ? (
        <CollectionLoader size={64} compact />
      ) : !pdfUrl ? (
        <Empty
          description={
            selfOnly
              ? 'Select a month to view your payslip PDF'
              : 'Select a month to generate and view the payslip PDF'
          }
        />
      ) : (
        <div className="border rounded overflow-hidden" style={{ height: 700 }}>
          <iframe
            title="Payslip PDF"
            src={pdfUrl}
            style={{ width: '100%', height: '100%', border: 0 }}
          />
        </div>
      )}
    </Card>
  );
};

export default Payslips;

