import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSelector } from 'react-redux';
import { AlertCircle, FileSpreadsheet, FileText, RotateCcw, Search } from 'lucide-react';
import AsyncSelect from 'react-select/async';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';

import { Select } from 'antd';
import DataTable from '../../../common/data/DataTable.jsx';
import BmsDatePicker, { BmsDatePickerField } from '../../../common/components/forms/BmsDatePicker.jsx';
import CollectionLoader from '../components/CollectionLoader.jsx';
import '../../../common/components/forms/BmsDatePicker.css';
import MoneyText from '../../../common/components/MoneyText.jsx';
import { apiService } from '../../../services/api.jsx';
import { extractApiList, extractShiftList } from '../../../common/utils/apiList.js';
import { formatCount, formatMoneyForExport, isMoneyField } from '../../../common/utils/numberFormat.js';
import { exportCollectionReportPdf } from '../../../common/utils/collectionReportPdfExport.js';
import { buildReportFilterDisplayLines, formatReportGeneratorName } from './reportExportUtils.js';
import { FILTER_LABELS } from './reportCatalog.js';
import {
  groupReportFilters,
  normalizeFiltersAfterChange,
  sortReportFilters,
  validateReportFilters,
} from './reportFilterUtils.js';

const BRAND = '#962E32';
const SELECT_POPUP = { popup: { root: 'bms-filter-select-popup' } };

const DEFAULT_TRANS_TYPE_OPTIONS = [
  { value: 'CASHLESS', label: 'Cashless' },
  { value: 'CASH', label: 'Cash' },
];

function resolveParamSelectOptions(paramDef, fallback = []) {
  const raw = paramDef?.options;
  if (!Array.isArray(raw) || raw.length === 0) return fallback;

  return raw
    .map((option) => {
      if (typeof option === 'string') {
        return { value: option, label: option };
      }
      if (option?.value != null) {
        return {
          value: String(option.value),
          label: option.label != null ? String(option.label) : String(option.value),
        };
      }
      const [value] = Object.values(option ?? {});
      if (value == null || value === '') return null;
      const label = String(value).charAt(0) + String(value).slice(1).toLowerCase();
      return { value: String(value), label };
    })
    .filter(Boolean);
}

const inputClassName =
  'w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-800 focus:border-[#962E32] focus:outline-none focus:ring-1 focus:ring-[#962E32]/20';

const asyncSelectStyles = {
  control: (base, state) => ({
    ...base,
    borderColor: state.isFocused ? BRAND : '#e2e8f0',
    borderRadius: '8px',
    minHeight: '40px',
    boxShadow: state.isFocused ? '0 0 0 3px rgba(150, 46, 50, 0.12)' : 'none',
    '&:hover': { borderColor: BRAND },
  }),
  menuPortal: (base) => ({ ...base, zIndex: 9999 }),
  menu: (base) => ({ ...base, zIndex: 9999 }),
};

const loadOperatorOptions = async (inputValue) => {
  const query = (inputValue || '').trim();
  if (query.length < 2) return [];

  try {
    const response = await apiService.getUsers({ search: query, per_page: 20, page: 1 });
    const users = response?.data?.users ?? extractApiList(response?.data);
    return (Array.isArray(users) ? users : []).map((user) => {
      const name = [user.first_name, user.middle_name, user.surname].filter(Boolean).join(' ');
      return {
        value: user.id,
        label: name ? `${name} (${user.username ?? user.id})` : String(user.username ?? user.id),
        user,
      };
    });
  } catch {
    return [];
  }
};

const formatShiftLabel = (shift) => {
  const name = shift?.name ?? `Shift ${shift?.id ?? ''}`;
  if (shift?.shift_start_time && shift?.shift_end_time) {
    return `${name} (${shift.shift_start_time} – ${shift.shift_end_time})`;
  }
  return name;
};

const formatApiErrorMessage = (err, labels = FILTER_LABELS) => {
  const validation = err?.validationErrors;
  if (validation && typeof validation === 'object' && !Array.isArray(validation)) {
    const lines = Object.entries(validation).flatMap(([field, messages]) => {
      const fieldLabel = labels[field] || field.replace(/_/g, ' ');
      const items = Array.isArray(messages) ? messages : [messages];
      return items.filter(Boolean).map((message) => `${fieldLabel}: ${message}`);
    });
    if (lines.length > 0) {
      return lines.join('\n');
    }
  }

  return err?.message || 'An error occurred while generating the report';
};

const resolveOperatorCell = (record) => {
  const name =
    record?.operator_name ||
    [record?.first_name, record?.middle_name, record?.surname].filter(Boolean).join(' ').trim();
  return name || null;
};

const showReportError = async (err, fallbackMessage, { modal = true } = {}) => {
  const message = err ? formatApiErrorMessage(err) : fallbackMessage;
  if (modal) {
    await Swal.fire({
      icon: 'error',
      title: 'Report failed',
      text: message,
    });
  }
  return message;
};

const hasValidationErrors = (validationErrors) =>
  validationErrors &&
  typeof validationErrors === 'object' &&
  !Array.isArray(validationErrors) &&
  Object.keys(validationErrors).length > 0;

const formatCell = (value, columnKey) => {
  if (value === null || value === undefined || value === '') {
    return <span className="text-slate-400">N/A</span>;
  }
  if (columnKey && isMoneyField(columnKey)) {
    return <MoneyText value={value} />;
  }
  if (
    columnKey &&
    /^(count|vehicles|vehiclecount|total_vehicle|daily_bundle|weekly_bundle|monthly_bundle)$/i.test(
      String(columnKey)
    )
  ) {
    return <span className="text-sm font-mono tabular-nums text-black">{formatCount(value)}</span>;
  }
  if (typeof value === 'number') {
    return <span className="text-sm font-mono tabular-nums text-black">{formatCount(value)}</span>;
  }
  return <span className="text-sm text-black">{String(value)}</span>;
};

const extractRows = (report, response) => {
  if (!response?.success) return { rows: [], meta: null, cancelled: [] };

  const data = response.data;
  if (Array.isArray(data)) {
    return { rows: data, meta: null, cancelled: [] };
  }
  if (!data) return { rows: [], meta: null, cancelled: [] };

  if (report.nested) {
    const main = Array.isArray(data.data) ? data.data : [];
    const cancelled = Array.isArray(data.cancelled_receipts) ? data.cancelled_receipts : [];
    return { rows: main, meta: data.meta || null, cancelled, operatorName: data.operator_name };
  }

  if (report.paginated && data.data) {
    return { rows: data.data, pagination: data.pagination, meta: null, cancelled: [] };
  }

  const list = data.rows ?? data.records ?? (Array.isArray(data.data) ? data.data : null);
  if (data.pagination && list) {
    return {
      rows: Array.isArray(list) ? list : [],
      pagination: data.pagination,
      meta: data.meta || null,
      cancelled: [],
    };
  }

  const rows =
    data.rows ??
    data.records ??
    (Array.isArray(data) ? data : Array.isArray(data.data) ? data.data : []);

  return { rows: Array.isArray(rows) ? rows : [], meta: data.meta || null, cancelled: [] };
};

const buildPayload = (report, filterValues) => {
  const payload = {};
  report.filters.forEach((key) => {
    const val = filterValues[key];
    if (val !== undefined && val !== null && val !== '') {
      payload[key] = val;
    }
  });
  payload.page = filterValues.page || 1;
  payload.per_page = filterValues.per_page || 15;
  if (report.paginated && filterValues.search) {
    payload.search = filterValues.search;
  }
  return payload;
};

export default function CollectionReportRunner({
  reports,
  selectedReportId,
  onSelectReport,
  moduleSlug = 'collection-management',
  useReportEngine = true,
  hideReportList = false,
  embedded = false,
}) {
  const { user } = useSelector((state) => state.auth);
  const generatedByName = useMemo(() => formatReportGeneratorName(user), [user]);

  const selectedId = selectedReportId || reports[0]?.id || '';
  const [reportSearch, setReportSearch] = useState('');
  const [hasGenerated, setHasGenerated] = useState(false);
  const [loading, setLoading] = useState(false);
  const [exportingFormat, setExportingFormat] = useState(null);
  const [error, setError] = useState(null);
  const [rows, setRows] = useState([]);
  const [cancelledRows, setCancelledRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [pagination, setPagination] = useState({ current_page: 1, per_page: 15, total: 0, last_page: 1 });
  const [serverPaginated, setServerPaginated] = useState(false);
  const [shifts, setShifts] = useState([]);
  const [lanes, setLanes] = useState([]);
  const [bodyTypes, setBodyTypes] = useState([]);
  const [lookupsLoading, setLookupsLoading] = useState(false);
  const [selectedOperators, setSelectedOperators] = useState({});
  const [fieldErrors, setFieldErrors] = useState({});

  const report = useMemo(() => reports.find((r) => r.id === selectedId) ?? reports[0], [reports, selectedId]);

  const orderedFilters = useMemo(() => sortReportFilters(report?.filters ?? []), [report]);
  const filterGroups = useMemo(() => groupReportFilters(report?.filters ?? []), [report]);

  const filteredReportList = useMemo(() => {
    const q = reportSearch.trim().toLowerCase();
    if (!q) return reports;
    return reports.filter(
      (r) =>
        r.label.toLowerCase().includes(q) ||
        (r.description && r.description.toLowerCase().includes(q))
    );
  }, [reports, reportSearch]);

  const initialFilters = useMemo(() => {
    const base = { page: 1, per_page: 15, search: '' };
    sortReportFilters(report?.filters ?? []).forEach((f) => {
      base[f] = '';
    });
    if (report?.filters?.includes('collection_type')) base.collection_type = 'cash';
    if (report?.filters?.includes('options')) base.options = '1';
    if (report?.filters?.includes('year')) base.year = String(new Date().getFullYear());
    return base;
  }, [report]);

  const [filters, setFilters] = useState(initialFilters);

  useEffect(() => {
    setFilters(initialFilters);
    setRows([]);
    setCancelledRows([]);
    setError(null);
    setMeta(null);
    setHasGenerated(false);
    setServerPaginated(false);
    setReportSearch('');
    setSelectedOperators({});
    setFieldErrors({});
  }, [selectedId, initialFilters]);

  const resetFilters = () => {
    setFilters(initialFilters);
    setRows([]);
    setCancelledRows([]);
    setError(null);
    setMeta(null);
    setHasGenerated(false);
    setSelectedOperators({});
    setFieldErrors({});
  };

  useEffect(() => {
    const loadLookups = async () => {
      setLookupsLoading(true);
      try {
        const [shiftsRes, lanesRes, bodyRes] = await Promise.all([
          apiService.getEndOfShiftShifts(),
          apiService.getLanesList(),
          apiService.getBodyTypes(),
        ]);
        setShifts(extractShiftList(shiftsRes));
        if (lanesRes?.success) setLanes(extractApiList(lanesRes.data));
        if (bodyRes?.success) setBodyTypes(extractApiList(bodyRes.data));
      } catch (lookupError) {
        console.error('Failed to load report filter lookups', lookupError);
        setShifts([]);
      } finally {
        setLookupsLoading(false);
      }
    };
    loadLookups();
  }, []);

  const shiftOptions = useMemo(
    () =>
      (Array.isArray(shifts) ? shifts : []).map((s) => ({
        value: String(s.id),
        label: formatShiftLabel(s),
      })),
    [shifts]
  );

  const laneOptions = useMemo(
    () =>
      (Array.isArray(lanes) ? lanes : []).map((l) => ({
        value: String(l.id),
        label: String(l.lane_no ?? l.name ?? l.id),
      })),
    [lanes]
  );

  const bodyTypeOptions = useMemo(
    () =>
      (Array.isArray(bodyTypes) ? bodyTypes : []).map((b) => ({
        value: String(b.id ?? b.body_type_id ?? b.name),
        id: String(b.id ?? b.body_type_id),
        label: String(b.name ?? b.body_type ?? b.id),
      })),
    [bodyTypes]
  );

  const handleFilterChange = (key, value) => {
    setFilters((prev) => normalizeFiltersAfterChange(key, value, prev));
    setFieldErrors((prev) => {
      const next = { ...prev };
      delete next[key];
      if (key === 'from_date' || key === 'to_date') {
        delete next.from_date;
        delete next.to_date;
      }
      if (key === 'open_counter' || key === 'close_counter') {
        delete next.open_counter;
        delete next.close_counter;
      }
      return next;
    });
  };

  const applyValidationErrors = (validationErrors) => {
    if (!hasValidationErrors(validationErrors)) return false;
    const mapped = {};
    Object.entries(validationErrors).forEach(([field, messages]) => {
      const text = Array.isArray(messages) ? messages[0] : messages;
      if (text) mapped[field] = String(text);
    });
    if (Object.keys(mapped).length > 0) {
      setFieldErrors(mapped);
      return true;
    }
    return false;
  };

  const fetchReport = useCallback(async () => {
    if (!report) return;

    const validationErrors = validateReportFilters(filters);
    if (Object.keys(validationErrors).length > 0) {
      setFieldErrors(validationErrors);
      setError(null);
      return;
    }

    setLoading(true);
    setError(null);
    setFieldErrors({});
    try {
      const payload = buildPayload(report, filters);
      const response =
        useReportEngine && moduleSlug
          ? await apiService.generateReportEngineReport(moduleSlug, report.apiSlug, payload)
          : await apiService.runCollectionReport(report.apiSlug, payload);

      const { rows: extracted, pagination: pag, meta: reportMeta, cancelled, operatorName } =
        extractRows(report, response);

      if (!response.success) {
        setRows([]);
        setCancelledRows([]);
        const apiErr = {
          message: response.message || 'Failed to generate report',
          validationErrors: response.validationErrors || response.data,
        };
        const isFieldValidation = applyValidationErrors(apiErr.validationErrors);
        const message = isFieldValidation
          ? 'Please correct the highlighted filter fields.'
          : await showReportError(apiErr);
        setError(message);
        return;
      }

      setRows(extracted);
      setCancelledRows(cancelled);
      setHasGenerated(true);
      setMeta(reportMeta ? { ...reportMeta, operator_name: operatorName } : operatorName ? { operator_name: operatorName } : null);
      if (pag) {
        setServerPaginated(true);
        setPagination({
          current_page: pag.current_page ?? 1,
          per_page: pag.per_page ?? 15,
          total: pag.total ?? 0,
          last_page: pag.last_page ?? 1,
        });
      } else {
        setServerPaginated(false);
        setPagination({
          current_page: 1,
          per_page: filters.per_page || 15,
          total: extracted.length,
          last_page: 1,
        });
      }

      if (extracted.length === 0 && cancelled.length === 0) {
        setError('No records found for the selected criteria.');
      }
    } catch (err) {
      setRows([]);
      setCancelledRows([]);
      const isFieldValidation = applyValidationErrors(err?.validationErrors);
      const message = isFieldValidation
        ? 'Please correct the highlighted filter fields.'
        : await showReportError(err);
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [report, filters, moduleSlug, useReportEngine]);

  useEffect(() => {
    if (hasGenerated && serverPaginated) {
      fetchReport();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.page, filters.per_page]);

  const tableColumns = useMemo(() => {
    const defs = report?.columns ?? [];
    return [
      {
        title: '#',
        key: 'serial',
        width: 60,
        render: (_, __, idx) => {
          const page = serverPaginated ? pagination.current_page : filters.page || 1;
          const perPage = filters.per_page || 15;
          return <span className="text-sm text-slate-600">{(page - 1) * perPage + idx + 1}</span>;
        },
      },
      ...defs.map((col) => ({
        title: col.title,
        key: col.key,
        dataIndex: col.key,
        ellipsis: true,
        render: (_, record) => {
          if (col.key === 'operator_name') {
            return formatCell(resolveOperatorCell(record));
          }
          return formatCell(record[col.key], col.key);
        },
        ...(isMoneyField(col.key) ? { money: true, align: 'right' } : {}),
      })),
    ];
  }, [report, pagination, filters.page, filters.per_page, serverPaginated]);

  const logExportAudit = useCallback(
    async (format) => {
      if (!useReportEngine || !moduleSlug || !report?.apiSlug) return;

      try {
        const payload = buildPayload(report, filters);
        await apiService.logReportEngineExport(moduleSlug, report.apiSlug, {
          ...payload,
          format,
          row_count: rows.length,
        });
      } catch {
        // Export audit is best-effort and must not block the download.
      }
    },
    [useReportEngine, moduleSlug, report, filters, rows.length]
  );

  const exportToExcel = async () => {
    setExportingFormat('excel');
    try {
      const sheetRows = rows.map((r, i) => {
        const row = { '#': i + 1 };
        (report.columns ?? []).forEach((c) => {
          let raw = r[c.key];
          if (c.key === 'operator_name') {
            raw = resolveOperatorCell(r);
          }
          if (isMoneyField(c.key)) {
            row[c.title] = formatMoneyForExport(raw);
          } else if (
            /^(count|vehicles|vehiclecount|total_vehicle|daily_bundle|weekly_bundle|monthly_bundle)$/i.test(
              c.key
            )
          ) {
            row[c.title] = raw ?? '';
          } else {
            row[c.title] = raw ?? '';
          }
        });
        return row;
      });
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(sheetRows), report.label.slice(0, 31));
      if (cancelledRows.length > 0) {
        const cancelSheet = cancelledRows.map((r, i) => ({
          '#': i + 1,
          Plate: r.plate_no,
          Receipt: r.receipt_num,
          Amount: formatMoneyForExport(r.charged_amount),
          Reason: r.reason,
        }));
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(cancelSheet), 'Cancelled');
      }
      const buf = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
      const ts = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
      saveAs(
        new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }),
        `${report.id}_${ts}.xlsx`
      );
      await logExportAudit('excel');
      await Swal.fire({ icon: 'success', title: 'Exported', timer: 2000, showConfirmButton: false });
    } catch {
      await Swal.fire({ icon: 'error', title: 'Export failed' });
    } finally {
      setExportingFormat(null);
    }
  };

  const exportToPdf = async () => {
    setExportingFormat('pdf');
    try {
      const filterLines = buildReportFilterDisplayLines(filters, report, {
        shiftOptions,
        laneOptions,
        bodyTypeOptions,
        selectedOperators,
      });

      await exportCollectionReportPdf({
        report,
        rows,
        cancelledRows,
        filterLines,
        meta,
        resolveOperatorCell,
        generatedBy: generatedByName,
      });

      await logExportAudit('pdf');
      await Swal.fire({ icon: 'success', title: 'PDF exported', timer: 2000, showConfirmButton: false });
    } catch {
      await Swal.fire({ icon: 'error', title: 'PDF export failed' });
    } finally {
      setExportingFormat(null);
    }
  };

  const renderFilterInput = (key) => {
    const label = FILTER_LABELS[key] || key;
    const fieldError = fieldErrors[key];

    const wrapField = (content) => (
      <div key={key} className="min-w-0">
        {content}
        {fieldError ? <p className="mt-1 text-xs font-medium text-red-600">{fieldError}</p> : null}
      </div>
    );

    if (key === 'shift_id') {
      return wrapField(
        <BmsDatePickerField label={label}>
          <Select
            className="bms-filter-select w-full"
            size="large"
            value={filters.shift_id ? String(filters.shift_id) : undefined}
            placeholder={lookupsLoading ? 'Loading shifts…' : 'Select shift'}
            onChange={(value) => handleFilterChange('shift_id', value ?? '')}
            options={shiftOptions}
            loading={lookupsLoading}
            allowClear
            showSearch
            optionFilterProp="label"
            notFoundContent={lookupsLoading ? 'Loading…' : 'No shifts found'}
            classNames={SELECT_POPUP}
            getPopupContainer={(trigger) => trigger.parentElement ?? document.body}
          />
        </BmsDatePickerField>
      );
    }

    if (key === 'lane' || key === 'lane_id') {
      const laneOptions = (Array.isArray(lanes) ? lanes : []).map((l) => ({
        value: String(l.id),
        label: String(l.lane_no ?? l.name ?? l.id),
      }));

      return wrapField(
        <BmsDatePickerField label={label}>
          <Select
            className="bms-filter-select w-full"
            size="large"
            value={filters[key] ? String(filters[key]) : undefined}
            placeholder="All lanes"
            onChange={(value) => handleFilterChange(key, value ?? '')}
            options={laneOptions}
            allowClear
            showSearch
            optionFilterProp="label"
            classNames={SELECT_POPUP}
          />
        </BmsDatePickerField>
      );
    }

    if (key === 'body_type' || key === 'body_type_id') {
      const bodyTypeOptions = (Array.isArray(bodyTypes) ? bodyTypes : []).map((bt) => ({
        value: String(bt.id),
        label: bt.name,
      }));

      return wrapField(
        <BmsDatePickerField label={label}>
          <Select
            className="bms-filter-select w-full"
            size="large"
            value={filters[key] ? String(filters[key]) : undefined}
            placeholder="All body types"
            onChange={(value) => handleFilterChange(key, value ?? '')}
            options={bodyTypeOptions}
            allowClear
            showSearch
            optionFilterProp="label"
            classNames={SELECT_POPUP}
          />
        </BmsDatePickerField>
      );
    }

    if (key === 'collection_type') {
      return wrapField(
        <BmsDatePickerField label={label}>
          <Select
            className="bms-filter-select w-full"
            size="large"
            value={filters.collection_type || undefined}
            onChange={(value) => handleFilterChange('collection_type', value ?? 'cash')}
            options={[
              { value: 'cash', label: 'Cash' },
              { value: 'bundle', label: 'Bundle' },
              { value: 'topUp', label: 'Top Up' },
            ]}
            classNames={SELECT_POPUP}
          />
        </BmsDatePickerField>
      );
    }

    if (key === 'trans_type') {
      const transTypeOptions = resolveParamSelectOptions(
        report?.params?.trans_type,
        DEFAULT_TRANS_TYPE_OPTIONS
      );

      return wrapField(
        <BmsDatePickerField label={label}>
          <Select
            className="bms-filter-select w-full"
            size="large"
            value={filters.trans_type || undefined}
            onChange={(value) => handleFilterChange('trans_type', value ?? '')}
            options={transTypeOptions}
            allowClear
            placeholder="All transaction types"
            classNames={SELECT_POPUP}
          />
        </BmsDatePickerField>
      );
    }

    if (key === 'options') {
      const isReg = report.id === 'bundle-registration';
      const optionList = isReg
        ? [
            { value: '1', label: 'New Registration' },
            { value: '2', label: 'Updated Registration' },
          ]
        : [
            { value: '', label: 'All statuses' },
            { value: '1', label: 'Active' },
            { value: '0', label: 'Inactive' },
          ];

      return wrapField(
        <BmsDatePickerField label={label}>
          <Select
            className="bms-filter-select w-full"
            size="large"
            value={filters.options ?? (isReg ? '1' : '')}
            onChange={(value) => handleFilterChange('options', value ?? '')}
            options={optionList}
            classNames={SELECT_POPUP}
          />
        </BmsDatePickerField>
      );
    }

    if (key === 'open_counter' || key === 'close_counter') {
      return wrapField(
        <BmsDatePickerField label={label}>
          <BmsDatePicker
            showTime
            value={filters[key] ?? ''}
            onChange={(v) => handleFilterChange(key, v)}
            size="large"
          />
        </BmsDatePickerField>
      );
    }

    if (key === 'user_id' || key === 'operator') {
      return wrapField(
        <BmsDatePickerField label={label}>
          <AsyncSelect
            cacheOptions
            defaultOptions
            loadOptions={loadOperatorOptions}
            value={selectedOperators[key] ?? null}
            onChange={(option) => {
              setSelectedOperators((prev) => ({ ...prev, [key]: option }));
              handleFilterChange(key, option?.value ?? '');
            }}
            placeholder="Search operator by name or username…"
            isClearable
            styles={asyncSelectStyles}
            menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
          />
        </BmsDatePickerField>
      );
    }

    if (key.includes('date') || key === 'shift_date') {
      return wrapField(
        <BmsDatePickerField label={label}>
          <BmsDatePicker
            value={filters[key] ?? ''}
            onChange={(v) => handleFilterChange(key, v)}
            size="large"
          />
        </BmsDatePickerField>
      );
    }

    if (key === 'year') {
      return wrapField(
        <div>
          <label className="mb-1 block text-xs font-semibold tracking-wide" style={{ color: BRAND }}>
            {label}
          </label>
          <input
            type="number"
            min={2000}
            max={2100}
            value={filters.year ?? ''}
            onChange={(e) => handleFilterChange('year', e.target.value)}
            className={inputClassName}
          />
        </div>
      );
    }

    return wrapField(
      <div>
        <label className="mb-1 block text-xs font-semibold tracking-wide" style={{ color: BRAND }}>
          {label}
        </label>
        <input
          type="text"
          value={filters[key] ?? ''}
          onChange={(e) => handleFilterChange(key, e.target.value)}
          className={inputClassName}
        />
      </div>
    );
  };

  const paginationConfig = useMemo(() => {
    if (!hasGenerated) return false;

    const perPage = filters.per_page || 15;

    if (serverPaginated) {
      return {
        current: pagination.current_page,
        pageSize: perPage,
        total: pagination.total,
        showTotal: () => null,
        showQuickJumper: false,
        onChange: (page, pageSize) => {
          if (pageSize !== perPage) handleFilterChange('per_page', pageSize);
          handleFilterChange('page', page);
        },
      };
    }

    return {
      current: filters.page || 1,
      pageSize: perPage,
      total: rows.length,
      showTotal: () => null,
      showQuickJumper: false,
      onChange: (page, pageSize) => {
        if (pageSize !== perPage) handleFilterChange('per_page', pageSize);
        handleFilterChange('page', page);
      },
    };
  }, [
    hasGenerated,
    serverPaginated,
    pagination.current_page,
    pagination.total,
    filters.page,
    filters.per_page,
    rows.length,
  ]);

  const resultSummary = hasGenerated
    ? `${(serverPaginated ? pagination.total : rows.length).toLocaleString()} row${
        (serverPaginated ? pagination.total : rows.length) === 1 ? '' : 's'
      }`
    : null;

  return (
    <div
      className={
        embedded
          ? 'collection-report-runner-embedded flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-white'
          : 'rounded-lg border border-slate-200 bg-white'
      }
    >
      <div
        className={`flex flex-col ${
          hideReportList
            ? 'h-full min-h-0 flex-1'
            : 'lg:flex-row lg:min-h-[480px]'
        }`}
      >
        {!hideReportList ? (
        <aside className="border-b border-slate-200 bg-slate-50 lg:w-64 lg:shrink-0 lg:border-b-0 lg:border-r">
          <div className="border-b border-slate-200 p-3">
            <div className="relative">
              <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                type="search"
                value={reportSearch}
                onChange={(e) => setReportSearch(e.target.value)}
                placeholder="Search…"
                className={`${inputClassName} pl-8 text-sm`}
              />
            </div>
          </div>
          <ul className="max-h-[240px] overflow-y-auto p-2 lg:max-h-[calc(480px-3.5rem)]">
            {filteredReportList.length === 0 ? (
              <li className="px-2 py-4 text-center text-sm text-slate-500">No match</li>
            ) : (
              filteredReportList.map((r) => {
                const active = r.id === selectedId;
                return (
                  <li key={r.id}>
                    <button
                      type="button"
                      onClick={() => onSelectReport?.(r.id)}
                      className={`w-full rounded-md px-2.5 py-2 text-left text-sm ${
                        active
                          ? 'bg-white font-medium text-slate-900 ring-1 ring-slate-200'
                          : 'text-slate-700 hover:bg-white/80'
                      }`}
                      style={active ? { borderLeft: `2px solid ${BRAND}` } : undefined}
                    >
                      {r.label}
                    </button>
                  </li>
                );
              })
            )}
          </ul>
        </aside>
        ) : null}

        <div className={`flex min-w-0 flex-1 flex-col bg-white ${embedded ? 'min-h-0' : ''}`}>
          <div
            className={
              embedded
                ? 'report-runner-toolbar flex flex-wrap items-center justify-end gap-2 border-b border-slate-100 px-4 py-2'
                : 'flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3'
            }
          >
            {!embedded ? <h2 className="text-sm font-semibold text-slate-900">{report?.label}</h2> : null}
            <div className="flex flex-wrap gap-2">
                <button
                  type="button"
                  onClick={exportToExcel}
                  disabled={Boolean(exportingFormat) || rows.length === 0}
                  className="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                >
                  <FileSpreadsheet size={14} />
                  {exportingFormat === 'excel' ? 'Exporting…' : 'Excel'}
                </button>
                <button
                  type="button"
                  onClick={exportToPdf}
                  disabled={Boolean(exportingFormat) || rows.length === 0}
                  className="inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50"
                  style={{ backgroundColor: BRAND, borderColor: BRAND }}
                >
                  <FileText size={14} />
                  {exportingFormat === 'pdf' ? 'Exporting…' : 'PDF'}
                </button>
              </div>
          </div>

          <div className={embedded ? 'min-h-0 flex-1 overflow-y-auto' : ''}>
          {filterGroups.length > 0 && (
            <div
              className={`report-runner-filters space-y-4 px-4 py-3 ${
                embedded ? '' : 'border-b border-slate-200'
              }`}
            >
              {filterGroups.map((group) => (
                <div key={group.key}>
                  {group.title ? (
                    <div className={`mb-2 pb-1.5 ${embedded ? '' : 'border-b border-slate-100'}`}>
                      <h4
                        className="text-xs font-semibold uppercase tracking-[0.12em]"
                        style={{ color: BRAND }}
                      >
                        {group.title}
                      </h4>
                      {group.hint ? (
                        <p className="mt-1 text-xs text-slate-500">{group.hint}</p>
                      ) : null}
                    </div>
                  ) : null}
                  <div
                    className={
                      group.columns === 1
                        ? 'grid grid-cols-1 gap-3'
                        : group.columns === 2
                          ? 'grid grid-cols-1 gap-3 sm:grid-cols-2'
                          : 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3'
                    }
                  >
                    {group.filters.map((f) => renderFilterInput(f))}
                  </div>
                </div>
              ))}
            </div>
          )}

          {(error || meta) && (
            <div className="space-y-2 border-b border-slate-100 px-4 py-2 text-sm">
              {error && (
                <div className="flex gap-2 text-amber-800">
                  <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                  <span>{error}</span>
                </div>
              )}
              {meta &&
                (meta.open_counter ||
                  meta.close_counter ||
                  meta.operator_name ||
                  meta.shift ||
                  meta.shift_date ||
                  meta.billing_amount) && (
                <p className="text-slate-600">
                  {[
                    meta.shift && `Shift: ${meta.shift}`,
                    meta.shift_date && `Date: ${meta.shift_date}`,
                    meta.billing_amount && `Shift total: TZS ${meta.billing_amount}`,
                    meta.open_counter && meta.close_counter
                      ? `Window: ${meta.open_counter} – ${meta.close_counter}`
                      : null,
                    meta.operator_name && `Operator: ${meta.operator_name}`,
                  ]
                    .filter(Boolean)
                    .join(' · ')}
                </p>
              )}
            </div>
          )}

          <div className="p-4">
            {hasGenerated && (
              <p className="mb-2 text-xs text-slate-500">
                {resultSummary}
                {(filters.from_date || filters.to_date) &&
                  ` · ${[filters.from_date, filters.to_date].filter(Boolean).join(' – ')}`}
              </p>
            )}

            {loading ? (
              <CollectionLoader />
            ) : !hasGenerated ? (
              <p className="py-8 text-center text-sm text-slate-500">Set filters and click Generate.</p>
            ) : (
              <DataTable
                columns={tableColumns}
                data={rows}
                loading={false}
                pagination={paginationConfig}
                showSearch={report?.paginated}
                showRefresh={false}
                rowKey={(record, index) =>
                  record.id ?? record.receipt_num ?? `${report?.id}-${index}`
                }
              />
            )}

            {cancelledRows.length > 0 && (
              <div className="mt-4">
                <p className="mb-2 text-xs font-medium text-slate-600">Cancelled receipts</p>
                <DataTable
                  columns={[
                    { title: 'Plate', key: 'plate_no', dataIndex: 'plate_no' },
                    { title: 'Receipt', key: 'receipt_num', dataIndex: 'receipt_num' },
                    { title: 'Amount', key: 'charged_amount', dataIndex: 'charged_amount', money: true },
                    { title: 'Reason', key: 'reason', dataIndex: 'reason' },
                  ]}
                  data={cancelledRows}
                  showSearch={false}
                  showRefresh={false}
                  pagination={{
                    pageSize: 15,
                    showTotal: () => null,
                    showQuickJumper: false,
                  }}
                  rowKey={(r, i) => `c-${r.receipt_num}-${i}`}
                />
              </div>
            )}
          </div>
          </div>

          <div className="report-runner-footer flex shrink-0 items-center justify-end gap-2 border-t border-slate-200 bg-white px-4 py-3">
            <button
              type="button"
              onClick={fetchReport}
              disabled={loading}
              className="rounded-md px-3 py-1.5 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50"
              style={{ backgroundColor: BRAND }}
            >
              {loading ? 'Generating…' : 'Generate'}
            </button>
            <button
              type="button"
              onClick={resetFilters}
              disabled={loading}
              className="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
            >
              <RotateCcw size={14} />
              Reset
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
