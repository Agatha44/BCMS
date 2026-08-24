import { Modal, Tag } from 'antd';
import PropTypes from 'prop-types';
import { DataTable } from '../../common/data';
import CollectionLoader from '../../pages/CollectionManagement/components/CollectionLoader.jsx';

const transactionColumns = [
  {
    title: 'S/N',
    key: 'sn',
    width: 80,
    align: 'center',
    render: (_, __, index) => index + 1,
  },
  { title: 'PF No.', dataIndex: 'pf_number', key: 'pf_number', width: 120 },
  { title: 'Employee Name', dataIndex: 'employee_name', key: 'employee_name', width: 220 },
  { title: 'Month/Year', dataIndex: 'monthYear', key: 'monthYear', width: 120, align: 'center' },
  { title: 'Bank', dataIndex: 'bank', key: 'bank', width: 110, align: 'center' },
  { title: 'Account No.', dataIndex: 'account_number', key: 'account_number', width: 160 },
  {
    title: 'Basic Salary',
    dataIndex: 'basic_salary',
    key: 'basic_salary',
    align: 'right',
    width: 160,
    render: (v) =>
      Number.isFinite(Number(v))
        ? `TZS ${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '—',
  },
  {
    title: 'Gross Pay',
    dataIndex: 'gross_pay',
    key: 'gross_pay',
    align: 'right',
    width: 160,
    render: (v) =>
      Number.isFinite(Number(v))
        ? `TZS ${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '—',
  },
  {
    title: 'PAYE',
    dataIndex: 'paye',
    key: 'paye',
    align: 'right',
    width: 140,
    render: (v) =>
      Number.isFinite(Number(v))
        ? `TZS ${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '—',
  },
  {
    title: 'Net Pay',
    dataIndex: 'net_pay',
    key: 'net_pay',
    align: 'right',
    width: 160,
    render: (v) =>
      Number.isFinite(Number(v))
        ? `TZS ${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '—',
  },
  {
    title: 'eRMS Status',
    dataIndex: 'erms_status',
    key: 'erms_status',
    width: 140,
    align: 'center',
    render: (value) => {
      const v = Number(value);
      const label = Number.isFinite(v) ? (v === 1 ? 'Submitted' : v === 2 ? 'Failed' : 'Not Submitted') : '—';
      const color = v === 1 ? 'green' : v === 2 ? 'red' : v === 0 ? 'gold' : 'default';
      return <Tag color={color}>{label}</Tag>;
    },
  },
];

const PayrollTransactionsModal = ({
  open,
  loading,
  run = null,
  rows,
  pagination = false,
  onPageChange = null,
  onSearchChange = null,
  searchValue = '',
  onClose,
}) => {
  return (
    <Modal
      title={`Payroll Transactions${run?.month ? ` — ${run.month}` : ''}`}
      open={open}
      onCancel={onClose}
      footer={null}
      width={1500}
      style={{ maxWidth: '96vw' }}
      destroyOnHidden
    >
      <div className="relative">
        {loading ? (
          <div
            className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
            style={{ top: '4.75rem' }}
          >
            <CollectionLoader />
          </div>
        ) : null}
      <DataTable
        columns={transactionColumns}
        data={rows}
        loading={false}
        pagination={pagination}
        showSearch={true}
        showRefresh={false}
        searchPlaceholder="Search transactions..."
        onSearchChange={onSearchChange || undefined}
        rowKey="key"
        size="middle"
        bordered={true}
        onChange={(paginationInfo) => {
          if (!onPageChange) return;
          const page = paginationInfo?.current || 1;
          const pageSize = Number(paginationInfo?.pageSize) || 15;
          onPageChange(page, pageSize);
        }}
      />
      </div>
    </Modal>
  );
};

PayrollTransactionsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  loading: PropTypes.bool.isRequired,
  run: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  rows: PropTypes.array.isRequired,
  pagination: PropTypes.oneOfType([PropTypes.bool, PropTypes.object]),
  onPageChange: PropTypes.func,
  onSearchChange: PropTypes.func,
  searchValue: PropTypes.string,
  onClose: PropTypes.func.isRequired,
};

export default PayrollTransactionsModal;

