import { useEffect, useState } from 'react';
import { PauseCircleOutlined, PlusOutlined, TeamOutlined, UserOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { payrollService } from '../../../services/payrollService.js';
import PayrollSummaryTab from '../../../components/PayrollManagement/PayrollSummaryTab.jsx';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';

const PayrollSingleMetricCard = ({ title, icon, value, subtitle }) => {
  const num = Number(value);
  const label = Number.isFinite(num) ? num.toLocaleString() : '—';

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="text-sm font-medium text-gray-600 truncate">{title}</div>
          <div className="mt-1 text-2xl sm:text-3xl font-bold text-gray-900">{label}</div>
          {subtitle ? <div className="mt-1 text-xs sm:text-sm text-gray-500 truncate">{subtitle}</div> : null}
        </div>
        <div className="shrink-0 h-10 w-10 rounded-lg bg-[#fff5f5] text-[#962E32] flex items-center justify-center">
          {icon}
        </div>
      </div>
    </div>
  );
};

const getRow = (rows, componentName) => rows?.find((r) => String(r?.component || '').toLowerCase() === componentName.toLowerCase());

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const formatMonthYear = (month, year) => {
  const m = Number(month);
  const y = Number(year);
  if (!Number.isFinite(m) || !Number.isFinite(y)) return '';
  const mm = MONTHS[m - 1] || String(m);
  return `${mm}-${y}`;
};

const PayrollManagementDashboard = () => {
  const navigate = useNavigate();

  const [state, setState] = useState(() => ({
    loading: true,
    summary: null,
  }));

  useEffect(() => {
    const run = async () => {
      setState((p) => ({ ...p, loading: true }));

      try {
        const response = await payrollService.getPayrollSummary();

        if (!response?.success) throw new Error(response?.message || 'Failed to load payroll summary');

        // Expected shape:
        // { success, message, data: { from: {...}, to: {...}, rows: [...] } }
        const summary = response.data;
        setState({ loading: false, summary });
      } catch (e) {
        setState({ loading: false, summary: null });
      }
    };

    run();
  }, []);

  if (state.loading) {
    return (
      <div className="flex min-h-[240px] items-center justify-center">
        <CollectionLoader />
      </div>
    );
  }

  const summaryRows = Array.isArray(state.summary?.rows) ? state.summary.rows : [];

  const mapRow = (raw) => ({
    route:
      String(raw?.component || '').toLowerCase() === 'deductions'
        ? '/payroll-management/deductions'
        : String(raw?.component || '').toLowerCase() === 'benefits'
          ? '/payroll-management/benefits'
          : String(raw?.component || '').toLowerCase() === 'loans'
            ? '/payroll-management/loan-management'
            : String(raw?.component || '').toLowerCase() === 'total employees'
              ? '/employee-management/manage-employee'
              : undefined,
  });

  const fromLabel = formatMonthYear(state.summary?.from?.month, state.summary?.from?.year);
  const toLabel = formatMonthYear(state.summary?.to?.month, state.summary?.to?.year);

  const totalEmployees = getRow(summaryRows, 'Total Employees')?.to?.cases ?? getRow(summaryRows, 'Total Employees')?.from?.cases;
  const suspendedEmployees = null; // not provided by payroll summary response
  const terminatedEmployees = null; // not provided by payroll summary response
  const newEmployees = null; // not provided by payroll summary response

  return (
    <div className="space-y-4 sm:space-y-6">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        <PayrollSingleMetricCard title="Employees" icon={<TeamOutlined />} value={totalEmployees} subtitle={toLabel} />
        <PayrollSingleMetricCard title="Employees" icon={<TeamOutlined />} value={totalEmployees} subtitle={fromLabel} />
        <PayrollSingleMetricCard title="Suspended Employees" icon={<PauseCircleOutlined />} value={suspendedEmployees} subtitle={toLabel} />
        <PayrollSingleMetricCard title="Terminated Employees" icon={<UserOutlined />} value={terminatedEmployees} subtitle={toLabel} />
        <PayrollSingleMetricCard title="New Employees" icon={<PlusOutlined />} value={newEmployees} subtitle={toLabel} />
      </div>

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div className="px-4 sm:px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <div className="text-base font-semibold text-gray-900">Payroll Summary</div>
          <div className="text-xs text-gray-500">
            {fromLabel} → {toLabel}
          </div>
        </div>
        <div className="p-4 sm:p-5">
          <PayrollSummaryTab
            loading={false}
            summary={state.summary}
            mapRow={mapRow}
            onRowClick={(row) => {
              if (row?.route) navigate(row.route);
            }}
          />
        </div>
      </div>
    </div>
  );
};

export default PayrollManagementDashboard;

