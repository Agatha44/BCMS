import { Empty } from 'antd';
import PropTypes from 'prop-types';
import { useMemo } from 'react';
import DataTable from '../../common/data/DataTable.jsx';
import { formatMonthYearLabel } from '../../common/utils/dateFormat.js';
import { formatCurrency, toNumberOrNull } from '../../common/utils/numberFormat.js';
import CollectionLoader from '../../pages/CollectionManagement/components/CollectionLoader.jsx';

const PayrollSummaryTab = ({
  loading = false,
  summary = null,
  headerRight = '',
  showHeader = false,
  title = 'Payroll Summary',
  mapRow = null,
  onRowClick = null,
  isRowClickable = null,
}) => {
  const summaryRows = Array.isArray(summary?.rows) ? summary.rows : [];

  const tableRows = useMemo(
    () =>
      summaryRows.map((r, idx) => {
        const base = {
          key: r?.component ?? `${idx}`,
          component: r?.component,
          fromCases: toNumberOrNull(r?.from?.cases),
          fromAmount: toNumberOrNull(r?.from?.amount),
          toCases: toNumberOrNull(r?.to?.cases),
          toAmount: toNumberOrNull(r?.to?.amount),
          varianceCases: toNumberOrNull(r?.variance?.cases),
          varianceAmount: toNumberOrNull(r?.variance?.amount),
        };
        const extra = typeof mapRow === 'function' ? mapRow(r, base) : null;
        return extra && typeof extra === 'object' ? { ...base, ...extra } : base;
      }),
    [mapRow, summaryRows]
  );

  const fromLabel = formatMonthYearLabel(summary?.from?.month, summary?.from?.year);
  const toLabel = formatMonthYearLabel(summary?.to?.month, summary?.to?.year);

  const clickableCheck = useMemo(() => {
    if (typeof isRowClickable === 'function') return isRowClickable;
    return (row) => Boolean(row?.route);
  }, [isRowClickable]);

  const columns = useMemo(
    () => [
      {
        title: 'Component',
        dataIndex: 'component',
        key: 'component',
        render: (value, record) => {
          const clickable = Boolean(onRowClick) && clickableCheck(record);
          return (
            <span className={clickable ? 'font-medium text-[#962E32]' : 'font-medium text-gray-900'}>
              {value}
            </span>
          );
        },
      },
      {
        title: fromLabel || 'From',
        key: 'fromGroup',
        children: [
          {
            title: 'Cases',
            dataIndex: 'fromCases',
            key: 'fromCases',
            width: 110,
            align: 'center',
            render: (value) => (
              <span className="text-gray-700">{Number.isFinite(Number(value)) ? Number(value).toLocaleString() : ''}</span>
            ),
          },
          {
            title: 'Amount',
            dataIndex: 'fromAmount',
            key: 'fromAmount',
            width: 170,
            align: 'right',
            render: (value) => <span className="text-gray-700">{formatCurrency(value)}</span>,
          },
        ],
      },
      {
        title: toLabel || 'To',
        key: 'toGroup',
        children: [
          {
            title: 'Cases',
            dataIndex: 'toCases',
            key: 'toCases',
            width: 110,
            align: 'center',
            render: (value) => (
              <span className="text-gray-700">{Number.isFinite(Number(value)) ? Number(value).toLocaleString() : ''}</span>
            ),
          },
          {
            title: 'Amount',
            dataIndex: 'toAmount',
            key: 'toAmount',
            width: 170,
            align: 'right',
            render: (value) => <span className="text-gray-700">{formatCurrency(value)}</span>,
          },
        ],
      },
      {
        title: 'Variance',
        key: 'varianceGroup',
        children: [
          {
            title: 'Cases',
            dataIndex: 'varianceCases',
            key: 'varianceCases',
            width: 110,
            align: 'center',
            render: (value) => {
              const num = Number(value);
              if (!Number.isFinite(num)) return <span className="text-gray-500"></span>;
              const cls =
                num > 0 ? 'text-green-700 font-medium' : num < 0 ? 'text-red-700 font-medium' : 'text-gray-700';
              return <span className={cls}>{num.toLocaleString()}</span>;
            },
          },
          {
            title: 'Amount',
            dataIndex: 'varianceAmount',
            key: 'varianceAmount',
            width: 170,
            align: 'right',
            render: (value) => {
              const num = Number(value);
              if (!Number.isFinite(num)) return <span className="text-gray-500"></span>;
              const cls =
                num > 0 ? 'text-green-700 font-medium' : num < 0 ? 'text-red-700 font-medium' : 'text-gray-700';
              return <span className={cls}>{formatCurrency(num)}</span>;
            },
          },
        ],
      },
    ],
    [clickableCheck, fromLabel, onRowClick, toLabel]
  );

  return (
    <div>
      {showHeader ? (
        <div className="flex items-center justify-between gap-3 mb-2">
          <div className="text-base font-semibold text-gray-900">{title}</div>
          {headerRight ? <div className="text-xs text-gray-500">{headerRight}</div> : null}
        </div>
      ) : headerRight ? (
        <div className="text-sm text-gray-500 mb-2 text-right">{headerRight}</div>
      ) : null}

      {loading ? (
        <CollectionLoader size={64} compact />
      ) : tableRows.length === 0 ? (
        <Empty description="No summary available" />
      ) : (
        <DataTable
          columns={columns}
          data={tableRows}
          pagination={false}
          showSearch={false}
          showRefresh={false}
          bordered
          size="middle"
          scroll={{ x: 900 }}
          rowKey="key"
          onRow={
            onRowClick
              ? (record) => ({
                  onClick: () => {
                    if (clickableCheck(record)) onRowClick(record);
                  },
                  style: clickableCheck(record) ? { cursor: 'pointer' } : undefined,
                })
              : undefined
          }
        />
      )}
    </div>
  );
};

PayrollSummaryTab.propTypes = {
  loading: PropTypes.bool,
  summary: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  headerRight: PropTypes.string,
  showHeader: PropTypes.bool,
  title: PropTypes.string,
  mapRow: PropTypes.func,
  onRowClick: PropTypes.func,
  isRowClickable: PropTypes.func,
};

export default PayrollSummaryTab;

