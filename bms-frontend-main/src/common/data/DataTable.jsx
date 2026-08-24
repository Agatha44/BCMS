import { Table, Input, Space, Button } from 'antd';
import { SearchOutlined, ReloadOutlined } from '@ant-design/icons';
import { useState, useMemo } from 'react';
import PropTypes from 'prop-types';
import MoneyText from '../components/MoneyText.jsx';
import { formatCount, isMoneyField } from '../utils/numberFormat.js';

const DataTable = ({
  columns = [],
  data = [],
  loading = false,
  pagination = true,
  pageSize = 15,
  showSearch = true,
  showRefresh = true,
  onRefresh,
  rightAction,
  searchPlaceholder = 'Search...',
  rowKey = 'id',
  scroll = {},
  size = 'middle',
  bordered = false,
  className = '',
  onRow,
  rowSelection,
  onSearchChange,
  serverSideSearch = false,
  moneyDecimals,
  ...restProps
}) => {
  const [searchText, setSearchText] = useState('');
  const [searchedColumn, setSearchedColumn] = useState('');

  const handleSearch = (selectedKeys, confirm, dataIndex) => {
    confirm();
    setSearchText(selectedKeys[0]);
    setSearchedColumn(dataIndex);
  };

  const handleReset = (clearFilters) => {
    clearFilters();
    setSearchText('');
  };

  const getColumnSearchProps = (dataIndex, title) => ({
    filterDropdown: ({ setSelectedKeys, selectedKeys, confirm, clearFilters, close }) => (
      <div
        style={{
          padding: 8,
        }}
        onKeyDown={(e) => e.stopPropagation()}
      >
        <Input
          placeholder={`Search ${title}`}
          value={selectedKeys[0]}
          onChange={(e) => setSelectedKeys(e.target.value ? [e.target.value] : [])}
          onPressEnter={() => handleSearch(selectedKeys, confirm, dataIndex)}
          style={{
            marginBottom: 8,
            display: 'block',
          }}
        />
        <Space>
          <Button
            key="search"
            type="primary"
            onClick={() => handleSearch(selectedKeys, confirm, dataIndex)}
            icon={<SearchOutlined />}
            size="small"
            style={{
              width: 90,
            }}
          >
            Search
          </Button>
          <Button
            key="reset"
            onClick={() => clearFilters && handleReset(clearFilters)}
            size="small"
            style={{
              width: 90,
            }}
          >
            Reset
          </Button>
          <Button
            key="filter"
            type="link"
            size="small"
            onClick={() => {
              confirm({ closeDropdown: false });
              setSearchText(selectedKeys[0]);
              setSearchedColumn(dataIndex);
            }}
          >
            Filter
          </Button>
          <Button
            key="close"
            type="link"
            size="small"
            onClick={() => {
              close();
            }}
          >
            close
          </Button>
        </Space>
      </div>
    ),
    filterIcon: false,
    onFilter: (value, record) =>
      record[dataIndex]
        ? record[dataIndex].toString().toLowerCase().includes(value.toLowerCase())
        : '',
    filterDropdownProps: {
      onOpenChange: (open) => {
        // Handle dropdown open change if needed
      }
    },
    render: (text) =>
      searchedColumn === dataIndex
        ? (
          <span style={{ color: '#962E32', fontWeight: 500 }}>
            {text}
          </span>
        )
        : text,
  });

  // Enhanced columns with search functionality
  const enhancedColumns = useMemo(() => {
    const processedColumns = showSearch 
      ? columns.map((col) => {
          if (col.searchable && col.dataIndex) {
            return {
              ...col,
              ...getColumnSearchProps(col.dataIndex, col.title),
            };
          }
          return col;
        })
      : columns;
    
    // Ensure all columns have a key prop; auto-format money / count columns
    return processedColumns.map((col, index) => {
      let next = col;
      if (!col.key) {
        const key = col.dataIndex || col.title || `column-${index}`;
        next = {
          ...col,
          key: typeof key === 'string' ? key : `column-${index}`,
        };
      }

      const field = next.dataIndex ?? next.key;
      const fieldName = typeof field === 'string' ? field : String(field ?? '');

      if (!next.render && fieldName) {
        if (next.money === true || (next.money !== false && isMoneyField(fieldName))) {
          return {
            ...next,
            align: next.align ?? 'right',
            render: (value, record) => {
              const raw = next.dataIndex != null ? record[next.dataIndex] : value;
              return <MoneyText value={raw} decimals={moneyDecimals} />;
            },
          };
        }

        const isCountCol =
          next.count === true ||
          /^(count|vehicles|vehiclecount|total_vehicle|daily_bundle|weekly_bundle|monthly_bundle)$/i.test(
            fieldName
          );
        if (isCountCol) {
          return {
            ...next,
            align: next.align ?? 'right',
            render: (value, record) => {
              const raw = next.dataIndex != null ? record[next.dataIndex] : value;
              return <span className="text-sm font-mono tabular-nums text-black dark:text-slate-100">{formatCount(raw)}</span>;
            },
          };
        }
      }

      return next;
    });
  }, [columns, showSearch, searchedColumn, searchText, moneyDecimals]);

  // Filter data based on global search (skipped when search is handled server-side)
  const filteredData = useMemo(() => {
    if (serverSideSearch || !searchText || !showSearch) return data;

    return data.filter((record) =>
      columns.some((col) => {
        if (col.dataIndex && record[col.dataIndex]) {
          return record[col.dataIndex]
            .toString()
            .toLowerCase()
            .includes(searchText.toLowerCase());
        }
        return false;
      })
    );
  }, [data, searchText, columns, showSearch, serverSideSearch]);

  // Convert rowKey to a function that handles missing keys
  const getRowKey = useMemo(() => {
    if (typeof rowKey === 'function') {
      return rowKey;
    }
    // If rowKey is a string, create a function that handles missing keys
    return (record) => {
      if (record && record[rowKey] != null) {
        return record[rowKey];
      }
      // Fallback: try common unique identifiers
      if (record?.id != null) return record.id;
      if (record?.key != null) return record.key;
      if (record?._id != null) return record._id;
      // Last resort: create a key from multiple fields or use a timestamp-based key
      const fields = Object.keys(record || {}).slice(0, 3).map(key => record[key]).filter(Boolean);
      if (fields.length > 0) {
        return fields.join('-');
      }
      // Generate a unique key using timestamp and random number
      return `row-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    };
  }, [rowKey]);

  // Handle pagination configuration
  // If pagination is an object, use it directly (for server-side pagination)
  // If pagination is true, create default config (for client-side pagination)
  // If pagination is false, disable pagination
  const paginationConfig = useMemo(() => {
    if (pagination === false) return false;
    
    if (typeof pagination === 'object' && pagination !== null) {
      // Use pagination object directly (supports server-side pagination)
      return {
        ...pagination,
        showTotal: pagination.showTotal || ((total, range) => `${range[0]}-${range[1]} of ${total} items`),
        pageSizeOptions: pagination.pageSizeOptions || ['10', '15', '20', '50', '100'],
        showSizeChanger: pagination.showSizeChanger !== undefined ? pagination.showSizeChanger : true,
        showQuickJumper: pagination.showQuickJumper !== undefined ? pagination.showQuickJumper : true,
      };
    }
    
    // Default pagination config for client-side pagination
    return {
      pageSize: pageSize || 15,
      showSizeChanger: true,
      showQuickJumper: true,
      showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
      pageSizeOptions: ['10', '15', '20', '50', '100'],
    };
  }, [pagination, pageSize]);

  return (
    <div className={`data-table-wrapper ${className}`}>
      <style>{`
        .data-table-wrapper {
          width: 100%;
          max-width: 100%;
        }
        /* Ensure tables never overflow parent cards (e.g. browser zoom). */
        .data-table-wrapper .ant-table-wrapper,
        .data-table-wrapper .ant-table,
        .data-table-wrapper .ant-table-container {
          max-width: 100%;
        }
        .data-table-wrapper .ant-table-thead > tr > th {
          background-color: #962E32 !important;
          color: #ffffff !important;
          font-weight: 600;
          border-bottom: 2px solid #7a2528 !important;
          /* Keep header text on a single line */
          white-space: nowrap;
        }
        .data-table-wrapper .ant-table-thead > tr > th:not(:last-child) {
          border-right: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        .data-table-wrapper .ant-table-tbody > tr > td {
          border-bottom: 1px solid #f0f0f0 !important;
          /* Keep cell content on a single line to align with headers */
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
        .data-table-wrapper .ant-table-tbody > tr > td:not(:last-child) {
          border-right: 1px solid #f0f0f0 !important;
        }
        .data-table-wrapper .ant-table-bordered .ant-table-thead > tr > th,
        .data-table-wrapper .ant-table-bordered .ant-table-tbody > tr > td {
          border-right: 1px solid #e8e8e8 !important;
        }
        .data-table-wrapper .ant-table-bordered .ant-table-thead > tr > th {
          border-right: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        .data-table-wrapper .ant-table-thead > tr > th .ant-table-column-sorter,
        .data-table-wrapper .ant-table-thead > tr > th .ant-table-filter-trigger {
          color: #ffffff !important;
        }
        .data-table-wrapper .ant-table-thead > tr > th .ant-table-column-sorter:hover,
        .data-table-wrapper .ant-table-thead > tr > th .ant-table-filter-trigger:hover {
          color: rgba(255, 255, 255, 0.8) !important;
        }
        .data-table-wrapper .ant-table-tbody > tr:hover > td {
          background-color: #fff5f5 !important;
        }
        /* ---- Brand pagination (shared across all tables) ---- */
        .data-table-wrapper .ant-table-pagination.ant-pagination {
          margin: 18px 16px 8px;
          padding: 14px 16px 0;
          border-top: 1px solid #f0f0f0;
          display: flex;
          align-items: center;
          justify-content: flex-end;
          gap: 8px;
        }
        .data-table-wrapper .ant-pagination .ant-pagination-item,
        .data-table-wrapper .ant-pagination .ant-pagination-prev .ant-pagination-item-link,
        .data-table-wrapper .ant-pagination .ant-pagination-next .ant-pagination-item-link,
        .data-table-wrapper .ant-pagination .ant-pagination-jump-prev,
        .data-table-wrapper .ant-pagination .ant-pagination-jump-next {
          border-radius: 10px !important;
          border: 1px solid #e2e8f0 !important;
          color: #334155 !important;
          min-width: 34px;
          height: 34px;
          line-height: 32px;
          transition: all 150ms ease;
        }
        .data-table-wrapper .ant-pagination .ant-pagination-item a {
          color: inherit !important;
        }
        .data-table-wrapper .ant-pagination .ant-pagination-item:hover,
        .data-table-wrapper .ant-pagination .ant-pagination-prev:hover .ant-pagination-item-link,
        .data-table-wrapper .ant-pagination .ant-pagination-next:hover .ant-pagination-item-link {
          border-color: #962E32 !important;
          color: #962E32 !important;
          background: #fff5f5 !important;
        }
        .data-table-wrapper .ant-pagination .ant-pagination-item-active,
        .data-table-wrapper .ant-pagination .ant-pagination-item-active:hover {
          background: #962E32 !important;
          border-color: #962E32 !important;
        }
        .data-table-wrapper .ant-pagination .ant-pagination-item-active a {
          color: var(--bms-on-brand, #ffffff) !important;
        }
        .data-table-wrapper .ant-pagination .ant-pagination-disabled .ant-pagination-item-link {
          color: #cbd5e1 !important;
          border-color: #e2e8f0 !important;
          background: #f8fafc !important;
        }
        .data-table-wrapper .ant-pagination-options .ant-select-selector {
          border-radius: 10px !important;
          border-color: #e2e8f0 !important;
          height: 34px !important;
        }
        .data-table-wrapper .ant-pagination-options .ant-select-focused .ant-select-selector {
          border-color: #962E32 !important;
          box-shadow: 0 0 0 3px rgba(150, 46, 50, 0.12) !important;
        }
      `}</style>
      {/* Search and Action Bar */}
      {(showSearch || showRefresh || rightAction) && (
        <div className="mb-4 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 sm:gap-4">
          {showSearch && (
            <Input
              placeholder={searchPlaceholder}
              prefix={<SearchOutlined />}
              value={searchText}
              onChange={(e) => {
                const value = e.target.value;
                setSearchText(value);
                if (onSearchChange) {
                  onSearchChange(value);
                }
              }}
              allowClear
              className="w-full sm:flex-1 sm:max-w-[300px]"
            />
          )}
          <div className="flex justify-end sm:justify-start">
            {rightAction || (showRefresh && onRefresh && (
              <Button
                icon={<ReloadOutlined />}
                onClick={onRefresh}
                loading={loading}
                className="w-full sm:w-auto"
              >
                Refresh
              </Button>
            ))}
          </div>
        </div>
      )}

      {/* Table */}
      <div className="w-full overflow-x-auto">
        <Table
          columns={enhancedColumns}
          dataSource={filteredData}
          loading={loading}
          pagination={paginationConfig}
          rowKey={getRowKey}
          scroll={Object.keys(scroll).length > 0 ? scroll : undefined}
          size={size}
          bordered={bordered}
          onRow={onRow}
          rowSelection={rowSelection}
          onChange={(paginationInfo, filters, sorter) => {
            // Call onChange from restProps if provided (for server-side pagination)
            if (restProps.onChange) {
              restProps.onChange(paginationInfo, filters, sorter);
            }
          }}
          {...restProps}
        />
      </div>
    </div>
  );
};

DataTable.propTypes = {
  columns: PropTypes.arrayOf(
    PropTypes.shape({
      title: PropTypes.string.isRequired,
      dataIndex: PropTypes.string,
      key: PropTypes.string,
      render: PropTypes.func,
      searchable: PropTypes.bool,
      sorter: PropTypes.oneOfType([PropTypes.bool, PropTypes.func]),
      filters: PropTypes.array,
      width: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
      fixed: PropTypes.oneOf(['left', 'right']),
      align: PropTypes.oneOf(['left', 'center', 'right']),
    })
  ).isRequired,
  data: PropTypes.array.isRequired,
  loading: PropTypes.bool,
  pagination: PropTypes.oneOfType([PropTypes.bool, PropTypes.object]),
  pageSize: PropTypes.number,
  showSearch: PropTypes.bool,
  showRefresh: PropTypes.bool,
  onRefresh: PropTypes.func,
  rightAction: PropTypes.node,
  searchPlaceholder: PropTypes.string,
  rowKey: PropTypes.oneOfType([PropTypes.string, PropTypes.func]),
  scroll: PropTypes.object,
  size: PropTypes.oneOf(['small', 'middle', 'large']),
  bordered: PropTypes.bool,
  className: PropTypes.string,
  onRow: PropTypes.func,
  rowSelection: PropTypes.object,
  moneyDecimals: PropTypes.number,
  onSearchChange: PropTypes.func,
  serverSideSearch: PropTypes.bool,
};

export default DataTable;
