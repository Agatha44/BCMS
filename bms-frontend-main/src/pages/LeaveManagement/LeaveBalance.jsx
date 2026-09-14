import { useMemo, useState } from 'react';
import { Scale } from 'lucide-react';
import { DataTable } from '../../common/data/index.jsx';
import { LEAVE_TYPES } from './leaveUtils.js';
import '../../styles/common.css';

const BRAND = '#962E32';

const LeaveBalance = () => {
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(15);

  const balances = useMemo(
    () =>
      LEAVE_TYPES.map((leaveType, index) => ({
        id: index + 1,
        leaveType,
        entitled: 0,
        used: 0,
        remaining: 0,
      })),
    []
  );

  const columns = [
    {
      title: 'S.No',
      key: 'serialNumber',
      width: 80,
      align: 'center',
      render: (_, __, index) => (page - 1) * pageSize + index + 1,
    },
    {
      title: 'Leave Type',
      dataIndex: 'leaveType',
      key: 'leaveType',
      searchable: true,
    },
    {
      title: 'Entitled',
      dataIndex: 'entitled',
      key: 'entitled',
      width: 130,
      align: 'center',
    },
    {
      title: 'Used',
      dataIndex: 'used',
      key: 'used',
      width: 130,
      align: 'center',
    },
    {
      title: 'Remaining',
      dataIndex: 'remaining',
      key: 'remaining',
      width: 140,
      align: 'center',
      render: (value) => <span className="font-semibold text-black">{value}</span>,
    },
  ];

  return (
    <div>
      <div className="mb-4 flex items-center gap-2">
        <Scale size={20} color={BRAND} />
        <h1 className="m-0 text-lg font-semibold text-slate-900">Manage Balance</h1>
      </div>

      <DataTable
        columns={columns}
        data={balances}
        loading={false}
        pagination={{
          current: page,
          pageSize,
          total: balances.length,
          showTotal: () => null,
          showQuickJumper: false,
          onChange: (nextPage, nextSize) => {
            setPage(nextPage);
            setPageSize(nextSize);
          },
        }}
        showSearch
        showRefresh={false}
        searchPlaceholder="Search leave balances..."
        rowKey="id"
      />
    </div>
  );
};

export default LeaveBalance;
