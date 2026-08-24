import { Button, Input, Space, Tag, Tooltip } from 'antd';
import PropTypes from 'prop-types';
import { SearchOutlined } from '@ant-design/icons';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DataTable } from '../../../../common/data';
import CollectionLoader from '../../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { ermsOverallTag } from '../../payrollErmsUtils.js';
import { resolvePerformerDisplay } from '../../payrollRunUtils.js';
import { payrollRunStatusTag, STATUS_TAG_CLASS } from '../../payrollStatusUtils.js';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const PayrollProcessingTab = ({
  active = false,
  isPreparing = false,
  canPreparePayroll = false,
  preparePayroll = null,
  payrollRuns = [],
  runsLoading = false,
  pagination,
  searchValue = '',
  onSearch,
  onPageChange,
  onViewRun,
}) => {
  if (!active) return null;

  const [q, setQ] = useState(searchValue || '');
  const onSearchRef = useRef(onSearch);

  useEffect(() => {
    onSearchRef.current = onSearch;
  }, [onSearch]);

  useEffect(() => {
    setQ(searchValue || '');
  }, [searchValue]);

  useEffect(() => {
    const handle = setTimeout(() => {
      onSearchRef.current?.(q);
    }, 450);
    return () => clearTimeout(handle);
  }, [q]);

  const runColumns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'sn',
        width: 80,
        align: 'center',
        render: (_, __, index) => index + 1,
      },
      {
        title: 'Payroll No.',
        dataIndex: 'payroll_number',
        key: 'payroll_number',
        width: 170,
        ellipsis: true,
      },
      {
        title: 'Month/Year',
        key: 'month_year',
        width: 130,
        align: 'center',
        render: (_, r) => {
          const m = Number(r?.payroll_month);
          const y = r?.payroll_year;
          if (!Number.isFinite(m) || !y) return '—';
          const name = MONTHS[m - 1] || String(m);
          return `${name} ${y}`;
        },
      },
      {
        title: 'Prepared',
        key: 'prepared',
        width: 160,
        render: (_, r) => (
          <div className="space-y-0.5">
            <div className="text-sm text-gray-900">
              {resolvePerformerDisplay(r?.prepared_by_name, r?.prepared_by)}
            </div>
          </div>
        ),
      },
      {
        title: 'Status',
        dataIndex: 'status',
        key: 'status',
        width: 140,
        align: 'center',
        render: (value) => {
          const { color, text } = payrollRunStatusTag(value);
          return (
            <Tag color={color} className={STATUS_TAG_CLASS}>
              {text}
            </Tag>
          );
        },
      },
      {
        title: 'ERMS',
        dataIndex: 'erms_status',
        key: 'erms_status',
        width: 120,
        align: 'center',
        render: (v, r) => {
          const { color, text } = ermsOverallTag(v);
          const failed = Number(v) === 2;
          const tag = (
            <Tag color={color} className={STATUS_TAG_CLASS}>
              {text}
            </Tag>
          );
          const tooltip = failed
            ? 'ERMS submission has failures. Open payroll details → ERMS tab to review and repost.'
            : r?.erms_reference
              ? `Ref: ${r.erms_reference}`
              : null;
          return tooltip ? <Tooltip title={tooltip}>{tag}</Tooltip> : tag;
        },
      },
      {
        title: 'Action',
        key: 'action',
        width: 150,
        align: 'center',
        render: (_, record) => (
          <Button
            type="primary"
            size="small"
            onClick={() => onViewRun?.(record)}
            style={{ backgroundColor: '#962E32', borderColor: '#962E32' }}
          >
            View
          </Button>
        ),
      },
    ],
    [onViewRun]
  );

  return (
    <div className="mt-2 space-y-3">
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div className="flex gap-2">
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Search payroll runs..."
            allowClear
            prefix={<SearchOutlined />}
            className="w-full sm:w-[320px]"
          />
        </div>

        {canPreparePayroll ? (
          <Space className="justify-end">
            <Button
              type="primary"
              onClick={() => preparePayroll?.()}
              loading={isPreparing}
              disabled={!preparePayroll}
              style={{ backgroundColor: '#962E32', borderColor: '#962E32' }}
            >
              Prepare Payroll
            </Button>
          </Space>
        ) : null}
      </div>

      <div className="relative">
        {runsLoading ? (
          <div
            className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
            style={{ top: '4.75rem' }}
          >
            <CollectionLoader />
          </div>
        ) : null}
      <DataTable
        columns={runColumns}
        data={payrollRuns}
        loading={false}
        pagination={pagination}
        showSearch={false}
        showRefresh={false}
        rowKey="key"
        size="middle"
        bordered={true}
        onChange={(paginationInfo) => {
          const page = paginationInfo?.current || 1;
          const pageSize = Number(paginationInfo?.pageSize) || 15;
          onPageChange?.(page, pageSize);
        }}
      />
      </div>
    </div>
  );
};

PayrollProcessingTab.propTypes = {
  active: PropTypes.bool,
  isPreparing: PropTypes.bool,
  canPreparePayroll: PropTypes.bool,
  preparePayroll: PropTypes.func,
  payrollRuns: PropTypes.array,
  runsLoading: PropTypes.bool,
  pagination: PropTypes.oneOfType([PropTypes.bool, PropTypes.object]),
  searchValue: PropTypes.string,
  onSearch: PropTypes.func.isRequired,
  onPageChange: PropTypes.func.isRequired,
  onViewRun: PropTypes.func.isRequired,
};

export default PayrollProcessingTab;

