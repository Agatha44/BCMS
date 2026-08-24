import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, Download, Filter } from 'lucide-react';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';

import DataTable from '../../common/data/DataTable.jsx';
import CollectionLoader from './components/CollectionLoader.jsx';
import BmsDatePicker, { BmsDatePickerField } from '../../common/components/forms/BmsDatePicker.jsx';
import MoneyText from '../../common/components/MoneyText.jsx';
import { apiService } from '../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const INPUT_CLASS =
  'w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20';

const FormLabel = ({ children }) => (
  <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
    {children}
  </label>
);

const REPORT_TYPES = [
  { key: 'toll', label: 'Toll Collection', fetcher: (payload) => apiService.getTollCollectionReport(payload) },
  { key: 'incident', label: 'Incident Collection', fetcher: (payload) => apiService.getIncidentCollectionReport(payload) },
  { key: 'overload', label: 'Overload Collection', fetcher: (payload) => apiService.getOverloadCollectionReport(payload) },
];

const safeArray = (v) => (Array.isArray(v) ? v : []);

const extractRowsAndPagination = (payload) => {
  if (!payload) return { rows: [], pagination: null };
  const rows =
    safeArray(payload.rows) ||
    safeArray(payload.data) ||
    safeArray(payload.records) ||
    safeArray(payload.items) ||
    safeArray(payload.transactions) ||
    safeArray(payload.passages) ||
    [];
  return { rows, pagination: payload.pagination || null };
};

export default function Reports() {
  const [reportType, setReportType] = useState('toll');
  const [showFilters, setShowFilters] = useState(true);
  const [loading, setLoading] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState(null);

  const [rows, setRows] = useState([]);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  });

  const [filters, setFilters] = useState({
    from_date: '',
    to_date: '',
    collection_type: '',
    lane_no: '',
    plate_no: '',
    status: '',
    per_page: 15,
    page: 1,
  });

  const [isInitialLoad, setIsInitialLoad] = useState(true);

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({
      ...prev,
      [key]: value,
      page: key === 'page' ? value : 1,
    }));
  };

  const fetchReport = async () => {
    setLoading(true);
    setError(null);
    try {
      const selected = REPORT_TYPES.find((r) => r.key === reportType);
      const payload = Object.fromEntries(
        Object.entries({
          from_date: filters.from_date || undefined,
          to_date: filters.to_date || undefined,
          collection_type: reportType === 'toll' ? filters.collection_type || undefined : undefined,
          lane_no: filters.lane_no || undefined,
          plate_no: filters.plate_no || undefined,
          status: filters.status || undefined,
          per_page: filters.per_page,
          page: filters.page,
        }).filter(([, value]) => value !== undefined)
      );

      const response = await selected.fetcher(payload);
      if (response.success && response.data) {
        const { rows: extractedRows, pagination: extractedPagination } = extractRowsAndPagination(response.data);
        setRows(extractedRows);
        if (extractedPagination) {
          setPagination(extractedPagination);
        } else {
          setPagination((prev) => ({
            ...prev,
            current_page: 1,
            last_page: 1,
            total: extractedRows.length,
            from: extractedRows.length > 0 ? 1 : 0,
            to: extractedRows.length,
          }));
        }
      } else {
        setRows([]);
        setError(response.message || 'Failed to generate report');
      }
    } catch (err) {
      setRows([]);
      setError('An error occurred while generating the report');
      // eslint-disable-next-line no-console
      console.error('Report generation error:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    // initial load: keep empty until user hits Generate, but set initialLoad flag
    setIsInitialLoad(false);
  }, []);

  useEffect(() => {
    if (!isInitialLoad && rows.length > 0) {
      // refresh report when paging changes after at least one generation
      fetchReport();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.page]);

  const exportToExcel = async () => {
    setExporting(true);
    try {
      const sheetName = REPORT_TYPES.find((r) => r.key === reportType)?.label || 'Report';
      const exportData = rows.map((r, index) => ({
        'Serial No.': index + 1,
        'Plate No.': r.plate_no || r.plate_number || '',
        'Lane No.': r.lane_no || r.lane_number || '',
        Date: r.passage_time || r.pass_date || r.created_at || r.incident_date || '',
        Receipt: r.receipt_number || r.payment_receipt || r.receipt_no || '',
        Amount: r.amount ?? r.payment_amount ?? r.total_amount ?? '',
        Status: r.status || r.status_text || '',
        'Payment Method': r.payment_method || r.payment_method_text || '',
      }));

      const workbook = XLSX.utils.book_new();
      const worksheet = XLSX.utils.json_to_sheet(exportData);
      XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);

      const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
      const filename = `${reportType}_report_${timestamp}.xlsx`;
      const excelBuffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
      saveAs(new Blob([excelBuffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), filename);

      await Swal.fire({ icon: 'success', title: 'Export Successful!', text: `Exported to ${filename}`, timer: 2500, showConfirmButton: false });
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Export error:', err);
      await Swal.fire({ icon: 'error', title: 'Export Failed', text: 'An error occurred while exporting. Please try again.' });
    } finally {
      setExporting(false);
    }
  };

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'serial',
        width: 80,
        align: 'center',
        render: (_, __, idx) => (
          <span className="text-sm font-medium text-slate-600">
            {(pagination.current_page - 1) * (filters.per_page || 15) + idx + 1}
          </span>
        ),
      },
      {
        title: 'Plate No.',
        key: 'plate_no',
        render: (_, r) => (
          <span className="font-mono text-sm text-black">{r.plate_no || r.plate_number || <span className="text-slate-400">N/A</span>}</span>
        ),
      },
      {
        title: 'Lane No.',
        key: 'lane_no',
        render: (_, r) => (
          <span className="font-mono text-sm text-black">{r.lane_no || r.lane_number || <span className="text-slate-400">N/A</span>}</span>
        ),
      },
      {
        title: 'Date/Time',
        key: 'date',
        render: (_, r) => (
          <span className="text-sm text-black">
            {r.passage_time || r.pass_date || r.created_at || r.incident_date || <span className="text-slate-400">N/A</span>}
          </span>
        ),
      },
      {
        title: 'Receipt',
        key: 'receipt',
        render: (_, r) => (
          <span className="font-mono text-sm text-black">
            {r.receipt_number || r.payment_receipt || r.receipt_no || <span className="text-slate-400">N/A</span>}
          </span>
        ),
      },
      {
        title: 'Amount',
        key: 'amount',
        align: 'right',
        money: true,
        render: (_, r) => (
          <MoneyText value={r.amount ?? r.payment_amount ?? r.total_amount ?? r.charged_amount} />
        ),
      },
      {
        title: 'Status',
        key: 'status',
        render: (_, r) => (
          <span className="text-sm text-black">{r.status || r.status_text || <span className="text-slate-400">N/A</span>}</span>
        ),
      },
      {
        title: 'Payment Method',
        key: 'payment_method',
        render: (_, r) => (
          <span className="text-sm text-black">{r.payment_method || r.payment_method_text || <span className="text-slate-400">N/A</span>}</span>
        ),
      },
    ],
    [filters.per_page, pagination]
  );

  const paginationConfig = useMemo(
    () => ({
      current: pagination.current_page,
      pageSize: pagination.per_page,
      total: pagination.total,
      showTotal: () => null,
      showQuickJumper: false,
      showSizeChanger: true,
      pageSizeOptions: ['10', '15', '25', '50'],
      onChange: (page, pageSize) => {
        if (pageSize !== filters.per_page) handleFilterChange('per_page', pageSize);
        handleFilterChange('page', page);
      },
    }),
    [filters.per_page, pagination, filters]
  );

  const actionButtons = (
    <div className="flex flex-wrap items-center gap-2">
      <button type="button" onClick={() => setShowFilters(!showFilters)} className="btn-secondary flex items-center space-x-2">
        <Filter size={16} />
        <span>Filters</span>
      </button>
      <button
        type="button"
        onClick={exportToExcel}
        disabled={exporting || rows.length === 0}
        className="btn-secondary flex items-center space-x-2 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <Download size={16} />
        <span>{exporting ? 'Exporting...' : 'Export'}</span>
      </button>
      <button
        type="button"
        onClick={fetchReport}
        disabled={loading}
        className="flex items-center space-x-2 rounded-lg px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50"
        style={{ backgroundColor: BRAND }}
        onMouseEnter={(e) => {
          if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.backgroundColor = BRAND;
        }}
      >
        <span>{loading ? 'Generating...' : 'Generate'}</span>
      </button>
    </div>
  );

  return (
    <div className="space-y-4">
      {error && (
        <div className="rounded-lg border border-red-200 bg-red-50 p-4">
          <div className="flex">
            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-red-400" />
            <div className="ml-3">
              <h3 className="text-sm font-medium text-red-800">Error</h3>
              <p className="mt-1 text-sm text-red-700">{error}</p>
            </div>
          </div>
        </div>
      )}

      {showFilters && (
        <div className="rounded-lg border border-slate-200 bg-white p-6">
          <h4
            className="mb-4 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
            style={{ color: BRAND }}
          >
            Filters
          </h4>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
              <FormLabel>Report Type</FormLabel>
              <select
                value={reportType}
                onChange={(e) => {
                  setReportType(e.target.value);
                  setRows([]);
                  setError(null);
                  setPagination({
                    current_page: 1,
                    last_page: 1,
                    per_page: 15,
                    total: 0,
                    from: 0,
                    to: 0,
                  });
                  setFilters((prev) => ({ ...prev, page: 1 }));
                }}
                className={INPUT_CLASS}
              >
                {REPORT_TYPES.map((t) => (
                  <option key={t.key} value={t.key}>
                    {t.label}
                  </option>
                ))}
              </select>
            </div>
            <BmsDatePickerField label="Start Date">
              <BmsDatePicker value={filters.from_date} onChange={(v) => handleFilterChange('from_date', v)} size="large" />
            </BmsDatePickerField>
            <BmsDatePickerField label="End Date">
              <BmsDatePicker
                value={filters.to_date}
                onChange={(v) => handleFilterChange('to_date', v)}
                size="large"
                minDate={filters.from_date || undefined}
              />
            </BmsDatePickerField>
            {reportType === 'toll' && (
              <div>
                <FormLabel>Collection Type</FormLabel>
                <select
                  value={filters.collection_type}
                  onChange={(e) => handleFilterChange('collection_type', e.target.value)}
                  className={INPUT_CLASS}
                >
                  <option value="cash">Cash</option>
                  <option value="bundle">Bundle</option>
                  <option value="topUp">Top Up</option>
                </select>
              </div>
            )}
            <div>
              <FormLabel>Plate No.</FormLabel>
              <input
                type="text"
                value={filters.plate_no}
                onChange={(e) => handleFilterChange('plate_no', e.target.value)}
                className={INPUT_CLASS}
                placeholder="e.g. T123ABC"
              />
            </div>
            <div>
              <FormLabel>Lane No.</FormLabel>
              <input
                type="text"
                value={filters.lane_no}
                onChange={(e) => handleFilterChange('lane_no', e.target.value)}
                className={INPUT_CLASS}
                placeholder="e.g. 1"
              />
            </div>
            <div>
              <FormLabel>Status</FormLabel>
              <input
                type="text"
                value={filters.status}
                onChange={(e) => handleFilterChange('status', e.target.value)}
                className={INPUT_CLASS}
                placeholder="e.g. paid"
              />
            </div>
            <div>
              <FormLabel>Per Page</FormLabel>
              <select
                value={filters.per_page}
                onChange={(e) => handleFilterChange('per_page', Number(e.target.value))}
                className={INPUT_CLASS}
              >
                <option value={10}>10</option>
                <option value={15}>15</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
              </select>
            </div>
          </div>
        </div>
      )}

      {loading ? (
        <CollectionLoader />
      ) : (
      <DataTable
        columns={columns}
        data={rows || []}
        loading={false}
        pagination={paginationConfig}
        rowKey={(record, index) => record.id ?? record.key ?? index}
        showSearch={false}
        showRefresh={false}
        rightAction={actionButtons}
      />
      )}
    </div>
  );
}


