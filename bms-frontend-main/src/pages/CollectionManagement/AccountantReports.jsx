import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, Download, Filter } from 'lucide-react';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
import { Table } from 'antd';

import DataTable from '../../common/data/DataTable.jsx';
import CollectionLoader from './components/CollectionLoader.jsx';
import BmsDatePicker, { BmsDatePickerField } from '../../common/components/forms/BmsDatePicker.jsx';
import MoneyText from '../../common/components/MoneyText.jsx';
import { formatCount, formatMoney, toNumber } from '../../common/utils/numberFormat.js';
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
  { key: 'event', label: 'Event Collection', fetcher: (payload) => apiService.getEventCollectionReport(payload) },
];

const toNum = (v) => toNumber(v) ?? 0;

export default function AccountantReports() {
  const [reportType, setReportType] = useState('toll');
  const [showFilters, setShowFilters] = useState(true);
  const [loading, setLoading] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState(null);

  const [filters, setFilters] = useState({
    dateFrom: '',
    dateTo: '',
    collectionType: '',
  });

  const [resultMeta, setResultMeta] = useState(null);
  const [rows, setRows] = useState([]);

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
  };

  const normalizeApiData = (responseData) => {
    // Support either { bodyType|incidentType, dateFrom, dateTo, collectionType }
    // or nested wrappers like { data: { ... } }.
    if (!responseData) return null;
    if (responseData.bodyType || responseData.incidentType) return responseData;
    if (responseData.data?.bodyType || responseData.data?.incidentType) return responseData.data;
    if (responseData.result?.bodyType || responseData.result?.incidentType) return responseData.result;
    return responseData;
  };

  useEffect(() => {
    // No-op. Kept for parity with other pages (and future enhancements).
  }, []);

  const fetchAccountantReport = async () => {
    setLoading(true);
    setError(null);
    try {
      const selected = REPORT_TYPES.find((r) => r.key === reportType) || REPORT_TYPES[0];

      // Send both naming styles to maximize compatibility with backend variants.
      const payload = Object.fromEntries(
        Object.entries({
          // "Accountant summary" style keys (as per your sample)
          dateFrom: filters.dateFrom || undefined,
          dateTo: filters.dateTo || undefined,
          collectionType: reportType === 'toll' ? filters.collectionType || undefined : undefined,
          // Legacy/report style keys (existing pages)
          from_date: filters.dateFrom || undefined,
          to_date: filters.dateTo || undefined,
          collection_type: reportType === 'toll' ? filters.collectionType || undefined : undefined,
        }).filter(([, v]) => v !== undefined)
      );

      const resp = await selected.fetcher(payload);
      if (!resp.success || !resp.data) {
        setRows([]);
        setResultMeta(null);
        setError(resp.message || 'Failed to generate accountant report');
        return;
      }

      const data = normalizeApiData(resp.data);
      let mapped = [];

      if (reportType === 'incident') {
        const it = Array.isArray(data?.incidentType)
          ? data.incidentType
          : Array.isArray(data?.incident_type)
            ? data.incident_type
            : [];

        mapped = it.map((i) => {
          const name = i?.name || '-';
          const incidentCount = toNumber(i?.incidentCount ?? i?.incident_count ?? i?.count);
          const totalAmount = toNumber(i?.totalAmount ?? i?.total_amount ?? i?.amount);
          return {
            key: name || `${Math.random()}`,
            name,
            incidentCount,
            totalAmount,
          };
        });
      } else if (reportType === 'overload') {
        const bt = Array.isArray(data?.bodyType) ? data.bodyType : Array.isArray(data?.body_type) ? data.body_type : [];
        mapped = bt.map((b) => {
          const name = b?.name || b?.body_type || b?.bodyType || '-';
          const overloadCount = toNumber(b?.overloadCount ?? b?.overload_count ?? b?.count);
          const amountCollected = toNumber(b?.amountCollected ?? b?.amount_collected ?? b?.amount);
          return {
            key: name || `${Math.random()}`,
            name,
            overloadCount,
            amountCollected,
          };
        });
      } else if (reportType === 'event') {
        const et = Array.isArray(data?.eventType) ? data.eventType : Array.isArray(data?.event_type) ? data.event_type : [];
        mapped = et.map((e) => {
          const name = e?.name || '-';
          const eventCount = toNumber(e?.eventCount ?? e?.event_count ?? e?.count);
          const amountCollected = toNumber(e?.amountCollected ?? e?.amount_collected ?? e?.amount);
          return {
            key: name || `${Math.random()}`,
            name,
            eventCount,
            amountCollected,
          };
        });
      } else {
        const bt = Array.isArray(data?.bodyType) ? data.bodyType : Array.isArray(data?.body_type) ? data.body_type : [];

        mapped = bt.map((b) => {
          const name = b?.name || b?.body_type || b?.bodyType || '-';
          const dailyAmount = toNumber(b?.daily?.amount ?? b?.daily_amount);
          const dailyPassages = toNumber(b?.daily?.passage ?? b?.daily_passage ?? b?.daily?.passages);
          const weeklyAmount = toNumber(b?.weekly?.amount ?? b?.weekly_amount);
          const weeklyPassages = toNumber(b?.weekly?.passage ?? b?.weekly_passage ?? b?.weekly?.passages);
          const monthlyAmount = toNumber(b?.monthly?.amount ?? b?.monthly_amount);
          const monthlyPassages = toNumber(b?.monthly?.passage ?? b?.monthly_passage ?? b?.monthly?.passages);

          return {
            key: name || `${Math.random()}`,
            name,
            dailyPassages,
            dailyAmount,
            weeklyPassages,
            weeklyAmount,
            monthlyPassages,
            monthlyAmount,
          };
        });
      }

      setResultMeta({
        reportType: selected.key,
        reportTypeLabel: selected.label,
        dateFrom: data?.dateFrom || data?.from_date || filters.dateFrom,
        dateTo: data?.dateTo || data?.to_date || filters.dateTo,
        collectionType: data?.collectionType || data?.collection_type || filters.collectionType,
      });
      setRows(mapped);
    } catch (e) {
      setRows([]);
      setResultMeta(null);
      setError('An error occurred while generating the accountant report');
      // eslint-disable-next-line no-console
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const totals = useMemo(() => {
    if (reportType === 'incident') {
      return rows.reduce(
        (acc, r) => {
          acc.incidentCount += toNumber(r.incidentCount);
          acc.totalAmount += toNumber(r.totalAmount);
          return acc;
        },
        { incidentCount: 0, totalAmount: 0 }
      );
    }

    if (reportType === 'overload') {
      return rows.reduce(
        (acc, r) => {
          acc.overloadCount += toNumber(r.overloadCount);
          acc.amountCollected += toNumber(r.amountCollected);
          return acc;
        },
        { overloadCount: 0, amountCollected: 0 }
      );
    }

    if (reportType === 'event') {
      return rows.reduce(
        (acc, r) => {
          acc.eventCount += toNumber(r.eventCount);
          acc.amountCollected += toNumber(r.amountCollected);
          return acc;
        },
        { eventCount: 0, amountCollected: 0 }
      );
    }

    return rows.reduce(
      (acc, r) => {
        acc.dailyPassages += toNumber(r.dailyPassages);
        acc.dailyAmount += toNumber(r.dailyAmount);
        acc.weeklyPassages += toNumber(r.weeklyPassages);
        acc.weeklyAmount += toNumber(r.weeklyAmount);
        acc.monthlyPassages += toNumber(r.monthlyPassages);
        acc.monthlyAmount += toNumber(r.monthlyAmount);
        return acc;
      },
      {
        dailyPassages: 0,
        dailyAmount: 0,
        weeklyPassages: 0,
        weeklyAmount: 0,
        monthlyPassages: 0,
        monthlyAmount: 0,
      }
    );
  }, [rows, reportType]);

  const exportToExcel = async () => {
    setExporting(true);
    try {
      const meta = resultMeta || filters;
      const reportLabel =
        resultMeta?.reportTypeLabel || REPORT_TYPES.find((r) => r.key === reportType)?.label || 'Accountant Reports';
      const summarySheet = [
        { Field: 'Report Type', Value: reportLabel },
        { Field: 'Date From', Value: meta.dateFrom || '' },
        { Field: 'Date To', Value: meta.dateTo || '' },
      ];

      if (shouldShowCollectionType) {
        summarySheet.push({ Field: 'Collection Type', Value: meta.collectionType || '' });
      }

      let tableSheet = [];

      if (reportType === 'incident') {
        summarySheet.push({ Field: 'Total Incidents', Value: totals.incidentCount });
        summarySheet.push({ Field: 'Total Amount', Value: totals.totalAmount });

        tableSheet = rows.map((r) => ({
          'Incident Type': r.name,
          'Incident Count': r.incidentCount,
          'Total Amount': r.totalAmount,
        }));
      } else if (reportType === 'overload') {
        summarySheet.push({ Field: 'Total Overloads', Value: totals.overloadCount });
        summarySheet.push({ Field: 'Total Amount Collected', Value: totals.amountCollected });

        tableSheet = rows.map((r) => ({
          'Body Type': r.name,
          'Overload Count': r.overloadCount,
          'Amount Collected': r.amountCollected,
        }));
      } else if (reportType === 'event') {
        summarySheet.push({ Field: 'Total Events', Value: totals.eventCount });
        summarySheet.push({ Field: 'Total Amount Collected', Value: totals.amountCollected });

        tableSheet = rows.map((r) => ({
          'Event Type': r.name,
          'Event Count': r.eventCount,
          'Amount Collected': r.amountCollected,
        }));
      } else {
        summarySheet.push({ Field: 'Daily Total Passages', Value: totals.dailyPassages });
        summarySheet.push({ Field: 'Daily Total Amount', Value: totals.dailyAmount });
        summarySheet.push({ Field: 'Weekly Total Passages', Value: totals.weeklyPassages });
        summarySheet.push({ Field: 'Weekly Total Amount', Value: totals.weeklyAmount });
        summarySheet.push({ Field: 'Monthly Total Passages', Value: totals.monthlyPassages });
        summarySheet.push({ Field: 'Monthly Total Amount', Value: totals.monthlyAmount });

        tableSheet = rows.map((r) => ({
          'Body Type': r.name,
          'Daily Passages': r.dailyPassages,
          'Daily Amount': r.dailyAmount,
          'Weekly Passages': r.weeklyPassages,
          'Weekly Amount': r.weeklyAmount,
          'Monthly Passages': r.monthlyPassages,
          'Monthly Amount': r.monthlyAmount,
        }));
      }

      const workbook = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(summarySheet), 'Summary');
      XLSX.utils.book_append_sheet(
        workbook,
        XLSX.utils.json_to_sheet(tableSheet),
        reportType === 'incident'
          ? 'Incidents'
          : reportType === 'overload'
            ? 'Overloads'
            : reportType === 'event'
              ? 'Events'
              : 'By Body Type'
      );

      const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
      const filename = `accountant_reports_${reportType}_${timestamp}.xlsx`;
      const excelBuffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
      saveAs(new Blob([excelBuffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), filename);

      await Swal.fire({
        icon: 'success',
        title: 'Export Successful!',
        text: `Exported to ${filename}`,
        timer: 2500,
        showConfirmButton: false,
      });
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Export error:', err);
      await Swal.fire({ icon: 'error', title: 'Export Failed', text: 'An error occurred while exporting. Please try again.' });
    } finally {
      setExporting(false);
    }
  };

  const columns = useMemo(
    () => {
      if (reportType === 'incident') {
        return [
          {
            title: 'S/N',
            key: 'sn',
            fixed: 'left',
            width: 80,
            align: 'center',
            render: (_, __, idx) => <span className="text-sm font-medium text-slate-600">{idx + 1}</span>,
          },
          {
            title: 'Incident Type',
            dataIndex: 'name',
            key: 'name',
            fixed: 'left',
            width: 320,
            render: (v) => <span className="text-sm font-medium">{v}</span>,
          },
          {
            title: 'Incident Count',
            dataIndex: 'incidentCount',
            key: 'incidentCount',
            align: 'right',
            width: 160,
            render: (v) => <span className="text-sm font-mono tabular-nums">{formatCount(v)}</span>,
          },
          {
            title: 'Total Amount',
            dataIndex: 'totalAmount',
            key: 'totalAmount',
            align: 'right',
            width: 200,
            render: (v) => <MoneyText value={v} />,
          },
        ];
      }

      if (reportType === 'overload') {
        return [
          {
            title: 'S/N',
            key: 'sn',
            fixed: 'left',
            width: 80,
            align: 'center',
            render: (_, __, idx) => <span className="text-sm font-medium text-slate-600">{idx + 1}</span>,
          },
          {
            title: 'Body Type',
            dataIndex: 'name',
            key: 'name',
            fixed: 'left',
            width: 280,
            render: (v) => <span className="text-sm font-medium">{v}</span>,
          },
          {
            title: 'Overload Count',
            dataIndex: 'overloadCount',
            key: 'overloadCount',
            align: 'right',
            width: 160,
            render: (v) => <span className="text-sm font-mono tabular-nums">{formatCount(v)}</span>,
          },
          {
            title: 'Amount Collected',
            dataIndex: 'amountCollected',
            key: 'amountCollected',
            align: 'right',
            width: 220,
            render: (v) => <MoneyText value={v} />,
          },
        ];
      }

      if (reportType === 'event') {
        return [
          {
            title: 'S/N',
            key: 'sn',
            fixed: 'left',
            width: 80,
            align: 'center',
            render: (_, __, idx) => <span className="text-sm font-medium text-slate-600">{idx + 1}</span>,
          },
          {
            title: 'Event Type',
            dataIndex: 'name',
            key: 'name',
            fixed: 'left',
            width: 320,
            render: (v) => <span className="text-sm font-medium">{v}</span>,
          },
          {
            title: 'Event Count',
            dataIndex: 'eventCount',
            key: 'eventCount',
            align: 'right',
            width: 160,
            render: (v) => <span className="text-sm font-mono tabular-nums">{formatCount(v)}</span>,
          },
          {
            title: 'Amount Collected',
            dataIndex: 'amountCollected',
            key: 'amountCollected',
            align: 'right',
            width: 220,
            render: (v) => <MoneyText value={v} />,
          },
        ];
      }

      return [
        {
          title: 'S/N',
          key: 'sn',
          fixed: 'left',
          width: 80,
          align: 'center',
          render: (_, __, idx) => <span className="text-sm font-medium text-slate-600">{idx + 1}</span>,
        },
        {
          title: 'Body Type',
          dataIndex: 'name',
          key: 'name',
          fixed: 'left',
          width: 220,
          render: (v) => <span className="text-sm font-medium">{v}</span>,
        },
        {
          title: 'Daily',
          key: 'dailyGroup',
          children: [
            {
              title: 'Passages',
              dataIndex: 'dailyPassages',
              key: 'dailyPassages',
              align: 'right',
              width: 120,
              render: (v) => <span className="text-sm font-mono tabular-nums">{formatCount(v)}</span>,
            },
            {
              title: 'Amount',
              dataIndex: 'dailyAmount',
              key: 'dailyAmount',
              align: 'right',
              width: 160,
              render: (v) => <MoneyText value={v} />,
            },
          ],
        },
        {
          title: 'Weekly',
          key: 'weeklyGroup',
          children: [
            {
              title: 'Passages',
              dataIndex: 'weeklyPassages',
              key: 'weeklyPassages',
              align: 'right',
              width: 120,
              render: (v) => <span className="text-sm font-mono tabular-nums">{formatCount(v)}</span>,
            },
            {
              title: 'Amount',
              dataIndex: 'weeklyAmount',
              key: 'weeklyAmount',
              align: 'right',
              width: 160,
              render: (v) => <MoneyText value={v} />,
            },
          ],
        },
        {
          title: 'Monthly',
          key: 'monthlyGroup',
          children: [
            {
              title: 'Passages',
              dataIndex: 'monthlyPassages',
              key: 'monthlyPassages',
              align: 'right',
              width: 120,
              render: (v) => <span className="text-sm font-mono tabular-nums">{formatCount(v)}</span>,
            },
            {
              title: 'Amount',
              dataIndex: 'monthlyAmount',
              key: 'monthlyAmount',
              align: 'right',
              width: 160,
              render: (v) => <MoneyText value={v} />,
            },
          ],
        },
      ];
    },
    [reportType]
  );

  const tableSummary = useMemo(() => {
    if (!rows.length) return undefined;
    if (reportType === 'incident') {
      return () => (
        <Table.Summary fixed>
          <Table.Summary.Row>
            <Table.Summary.Cell index={0}>
              <span className="text-sm font-semibold">TOTAL</span>
            </Table.Summary.Cell>
            <Table.Summary.Cell index={1} />
            <Table.Summary.Cell index={2} align="right">
              <span className="text-sm font-mono font-semibold">{totals.incidentCount.toLocaleString()}</span>
            </Table.Summary.Cell>
            <Table.Summary.Cell index={3} align="right">
              <span className="text-sm font-mono font-semibold">{formatMoney(totals.totalAmount)}</span>
            </Table.Summary.Cell>
          </Table.Summary.Row>
        </Table.Summary>
      );
    }

    if (reportType === 'overload') {
      return () => (
        <Table.Summary fixed>
          <Table.Summary.Row>
            <Table.Summary.Cell index={0}>
              <span className="text-sm font-semibold">TOTAL</span>
            </Table.Summary.Cell>
            <Table.Summary.Cell index={1} />
            <Table.Summary.Cell index={2} align="right">
              <span className="text-sm font-mono font-semibold">{totals.overloadCount.toLocaleString()}</span>
            </Table.Summary.Cell>
            <Table.Summary.Cell index={3} align="right">
              <span className="text-sm font-mono font-semibold">{formatMoney(totals.amountCollected)}</span>
            </Table.Summary.Cell>
          </Table.Summary.Row>
        </Table.Summary>
      );
    }

    if (reportType === 'event') {
      return () => (
        <Table.Summary fixed>
          <Table.Summary.Row>
            <Table.Summary.Cell index={0}>
              <span className="text-sm font-semibold">TOTAL</span>
            </Table.Summary.Cell>
            <Table.Summary.Cell index={1} />
            <Table.Summary.Cell index={2} align="right">
              <span className="text-sm font-mono font-semibold">{totals.eventCount.toLocaleString()}</span>
            </Table.Summary.Cell>
            <Table.Summary.Cell index={3} align="right">
              <span className="text-sm font-mono font-semibold">{formatMoney(totals.amountCollected)}</span>
            </Table.Summary.Cell>
          </Table.Summary.Row>
        </Table.Summary>
      );
    }

    return () => (
      <Table.Summary fixed>
        <Table.Summary.Row>
          <Table.Summary.Cell index={0}>
            <span className="text-sm font-semibold">TOTAL</span>
          </Table.Summary.Cell>
          <Table.Summary.Cell index={1} />
          <Table.Summary.Cell index={2} align="right">
            <span className="text-sm font-mono font-semibold">{totals.dailyPassages.toLocaleString()}</span>
          </Table.Summary.Cell>
          <Table.Summary.Cell index={3} align="right">
            <span className="text-sm font-mono font-semibold">{formatMoney(totals.dailyAmount)}</span>
          </Table.Summary.Cell>
          <Table.Summary.Cell index={4} align="right">
            <span className="text-sm font-mono font-semibold">{totals.weeklyPassages.toLocaleString()}</span>
          </Table.Summary.Cell>
          <Table.Summary.Cell index={5} align="right">
            <span className="text-sm font-mono font-semibold">{formatMoney(totals.weeklyAmount)}</span>
          </Table.Summary.Cell>
          <Table.Summary.Cell index={6} align="right">
            <span className="text-sm font-mono font-semibold">{totals.monthlyPassages.toLocaleString()}</span>
          </Table.Summary.Cell>
          <Table.Summary.Cell index={7} align="right">
            <span className="text-sm font-mono font-semibold">{formatMoney(totals.monthlyAmount)}</span>
          </Table.Summary.Cell>
        </Table.Summary.Row>
      </Table.Summary>
    );
  }, [rows.length, totals, reportType]);

  const selectedMeta = resultMeta || filters;
  const shouldShowCollectionType = reportType === 'toll';
  const isIncidentReport = reportType === 'incident';
  const isOverloadReport = reportType === 'overload';
  const isEventReport = reportType === 'event';

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
        onClick={fetchAccountantReport}
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

      <div className="rounded-lg border border-slate-200 bg-white p-6">
        <h4
          className="mb-4 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
          style={{ color: BRAND }}
        >
          Report summary
        </h4>
        <div className="flex flex-col gap-4">
          <div className="grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
            <div className="min-w-0 py-1">
              <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                Report Type
              </span>
              <div className="mt-0.5 font-medium text-black">
                {resultMeta?.reportTypeLabel || REPORT_TYPES.find((r) => r.key === reportType)?.label || (
                  <span className="text-slate-400">N/A</span>
                )}
              </div>
            </div>
            <div className="min-w-0 py-1">
              <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                Period
              </span>
              <div className="mt-0.5 font-medium text-black">
                {selectedMeta.dateFrom || <span className="text-slate-400">N/A</span>} →{' '}
                {selectedMeta.dateTo || <span className="text-slate-400">N/A</span>}
              </div>
            </div>
            {shouldShowCollectionType && (
              <div className="min-w-0 py-1">
                <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Collection Type
                </span>
                <div className="mt-0.5 font-medium text-black">
                  {selectedMeta.collectionType || <span className="text-slate-400">N/A</span>}
                </div>
              </div>
            )}
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            {isIncidentReport ? (
              <>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Incident Types
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Types</span>
                    <span className="font-medium text-black">{rows.length.toLocaleString()}</span>
                  </div>
                </div>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Incidents
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Count</span>
                    <span className="font-medium text-black">{totals.incidentCount.toLocaleString()}</span>
                  </div>
                </div>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Total Amount
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Amount</span>
                    <span className="font-medium text-black">{formatMoney(totals.totalAmount)}</span>
                  </div>
                </div>
              </>
            ) : isOverloadReport ? (
              <>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Body Types
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Types</span>
                    <span className="font-medium text-black">{rows.length.toLocaleString()}</span>
                  </div>
                </div>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Overloads
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Count</span>
                    <span className="font-medium text-black">{totals.overloadCount.toLocaleString()}</span>
                  </div>
                </div>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Amount Collected
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Amount</span>
                    <span className="font-medium text-black">{formatMoney(totals.amountCollected)}</span>
                  </div>
                </div>
              </>
            ) : isEventReport ? (
              <>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Event Types
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Types</span>
                    <span className="font-medium text-black">{rows.length.toLocaleString()}</span>
                  </div>
                </div>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Events
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Count</span>
                    <span className="font-medium text-black">{totals.eventCount.toLocaleString()}</span>
                  </div>
                </div>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Amount Collected
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Amount</span>
                    <span className="font-medium text-black">{formatMoney(totals.amountCollected)}</span>
                  </div>
                </div>
              </>
            ) : (
              <>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Daily Totals
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Passages</span>
                    <span className="font-medium text-black">{totals.dailyPassages.toLocaleString()}</span>
                  </div>
                  <div className="mt-1 flex justify-between text-sm">
                    <span className="text-slate-600">Amount</span>
                    <span className="font-medium text-black">{formatMoney(totals.dailyAmount)}</span>
                  </div>
                </div>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Weekly Totals
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Passages</span>
                    <span className="font-medium text-black">{totals.weeklyPassages.toLocaleString()}</span>
                  </div>
                  <div className="mt-1 flex justify-between text-sm">
                    <span className="text-slate-600">Amount</span>
                    <span className="font-medium text-black">{formatMoney(totals.weeklyAmount)}</span>
                  </div>
                </div>
                <div className="rounded-lg border border-slate-200 p-3">
                  <div className="mb-1 text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Monthly Totals
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-600">Passages</span>
                    <span className="font-medium text-black">{totals.monthlyPassages.toLocaleString()}</span>
                  </div>
                  <div className="mt-1 flex justify-between text-sm">
                    <span className="text-slate-600">Amount</span>
                    <span className="font-medium text-black">{formatMoney(totals.monthlyAmount)}</span>
                  </div>
                </div>
              </>
            )}
          </div>
        </div>
      </div>

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
                  setResultMeta(null);
                  setError(null);
                  if (e.target.value !== 'toll') {
                    setFilters((prev) => ({ ...prev, collectionType: '' }));
                  }
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
            <BmsDatePickerField label="Date From">
              <BmsDatePicker value={filters.dateFrom} onChange={(v) => handleFilterChange('dateFrom', v)} size="large" />
            </BmsDatePickerField>
            <BmsDatePickerField label="Date To">
              <BmsDatePicker
                value={filters.dateTo}
                onChange={(v) => handleFilterChange('dateTo', v)}
                size="large"
                minDate={filters.dateFrom || undefined}
              />
            </BmsDatePickerField>
            {shouldShowCollectionType && (
              <div>
                <FormLabel>Collection Type</FormLabel>
                <select
                  value={filters.collectionType}
                  onChange={(e) => handleFilterChange('collectionType', e.target.value)}
                  className={INPUT_CLASS}
                >
                  <option value="">Select collection type</option>
                  <option value="bundle">Bundle</option>
                  <option value="cash">Cash</option>
                  <option value="topUp">Top Up</option>
                </select>
              </div>
            )}
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
        pagination={false}
        showSearch={false}
        showRefresh={false}
        rightAction={actionButtons}
        scroll={{ x: reportType === 'incident' ? 760 : reportType === 'overload' ? 820 : reportType === 'event' ? 820 : 1100 }}
        summary={tableSummary}
      />
      )}
    </div>
  );
}

