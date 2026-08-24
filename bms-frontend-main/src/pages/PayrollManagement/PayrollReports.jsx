import { useState, useEffect, useRef, useCallback } from 'react';
import { App, DatePicker, Form, Input, Select } from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { Loader2 } from 'lucide-react';
import DataTable from '../../common/data/DataTable.jsx';
import '../../common/components/forms/BmsDatePicker.css';
import { parseActiveLoanTypeOptions } from '../../components/PayrollManagement/Loans/employeeLoanUtils.js';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import { payrollService } from '../../services/payrollService.js';
import {
  buildLoanTypeSelectOptions,
  getReportTableProps,
  resolveReportTypeKey,
} from './payrollReports/payrollReportConfig.js';
import {
  buildReportPayload,
  buildSummaryCards,
  buildTableColumns,
  filtersFromForm,
  mapReportTypes,
  parsePeriod,
  reportRowKey,
  unwrapPayload,
} from './payrollReports/payrollReportUtils.js';
import '../../styles/common.css';

const BRAND = '#962E32';
const PANEL = 'rounded-lg border border-slate-200 bg-white p-6';
const HEAD = 'mb-4 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]';
const LBL = 'mb-1.5 block text-xs font-semibold tracking-[0.01em]';
const SELECT_POPUP = { popup: { root: 'bms-filter-select-popup' } };
const DATE_POPUP = { popup: { root: 'bms-date-picker-popup' } };
const FILTER_FORM =
  '[&_.ant-form-item-label>label]:text-xs [&_.ant-form-item-label>label]:font-semibold [&_.ant-form-item-label>label]:tracking-[0.01em] [&_.ant-form-item-label>label]:text-[#962E32]';

const OptionalFilterSelect = ({ label, name, options, loading = false, disabled = false }) => (
  <Form.Item
    name={name}
    label={label}
    className="mb-0 min-w-[200px] flex-1"
    initialValue=""
    getValueProps={(v) => ({ value: v ?? '' })}
  >
    <Select
      className="bms-filter-select w-full"
      size="large"
      disabled={disabled}
      loading={loading}
      options={options}
      showSearch
      optionFilterProp="label"
      classNames={SELECT_POPUP}
    />
  </Form.Item>
);

export default function PayrollReportsPage() {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [types, setTypes] = useState([]);
  const [typesLoading, setTypesLoading] = useState(false);
  const [applied, setApplied] = useState(null);
  const [rows, setRows] = useState([]);
  const [summary, setSummary] = useState(null);
  const [reportMeta, setReportMeta] = useState(null);
  const [loading, setLoading] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [page, setPage] = useState({ current: 1, pageSize: 15, total: 0 });
  const [search, setSearch] = useState('');
  const [loanTypeOptions, setLoanTypeOptions] = useState([]);
  const [loanTypesLoading, setLoanTypesLoading] = useState(false);
  const skipSearch = useRef(false);

  const reportType = Form.useWatch('reportType', form);
  const periodValue = Form.useWatch('period', form);
  const reportKey = resolveReportTypeKey(applied?.reportType ?? reportType);
  const selectedType = types.find((t) => t.id === reportType);
  const isLoansReport = reportKey === 'loans';
  const banks = selectedType?.bankFilter?.options ?? [];
  const cards = buildSummaryCards(summary, rows, reportKey);
  const tableProps = getReportTableProps(reportKey);
  const showDownload = Boolean(reportMeta?.download?.available);

  const clearResults = useCallback((pageInfo = { current: 1, pageSize: 15, total: 0 }) => {
    setRows([]);
    setSummary(null);
    setReportMeta(null);
    setPage((prev) => ({ ...prev, ...pageInfo }));
  }, []);

  useEffect(() => {
    let dead = false;
    (async () => {
      setTypesLoading(true);
      try {
        const res = await payrollService.getPayrollReportTypes();
        if (dead) return;
        if (res?.success) setTypes(mapReportTypes(res.data));
        else {
          setTypes([]);
          message.error(res?.message || 'Failed to load report types.');
        }
      } catch {
        if (!dead) {
          setTypes([]);
          message.error('Failed to load report types.');
        }
      } finally {
        if (!dead) setTypesLoading(false);
      }
    })();
    return () => { dead = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- load report types once on mount
  }, []);

  useEffect(() => {
    if (!isLoansReport) {
      setLoanTypeOptions([]);
      return undefined;
    }

    let dead = false;
    (async () => {
      setLoanTypesLoading(true);
      try {
        const res = await payrollService.getActiveLoanTypes();
        if (dead) return;
        setLoanTypeOptions(
          res?.success
            ? parseActiveLoanTypeOptions(res.data).map((o) => ({
                value: String(o.value),
                label: o.label,
              }))
            : []
        );
      } catch {
        if (!dead) setLoanTypeOptions([]);
      } finally {
        if (!dead) setLoanTypesLoading(false);
      }
    })();

    return () => { dead = true; };
  }, [isLoansReport]);

  const load = async (filters, opts = {}) => {
    const { page: nextPage = 1, pageSize = 15, search: query = '' } = opts;
    if (!filters?.reportType || !parsePeriod(filters.period)) {
      message.error('Report type and period are required.');
      return;
    }

    setLoading(true);
    try {
      const body = buildReportPayload(filters, { search: query, page: nextPage, pageSize });
      const res = await payrollService.getPayrollReport(body);
      if (!res?.success) {
        clearResults({ current: nextPage, pageSize, total: 0 });
        message.error(res?.message || 'Failed to generate report.');
        return;
      }

      const data = unwrapPayload(res.data);
      const nextRows = data.rows ?? [];
      setRows(nextRows);
      setSummary(data.summary ?? data.totals ?? null);
      setReportMeta(data.meta ?? null);
      setPage({
        current: Number(data.pagination?.current_page ?? nextPage),
        pageSize: Number(data.pagination?.per_page ?? pageSize),
        total: Number(data.pagination?.total ?? nextRows.length),
      });
    } catch (e) {
      clearResults({ current: nextPage, pageSize, total: 0 });
      message.error(e?.message || 'An error occurred while generating the report.');
    } finally {
      setLoading(false);
    }
  };

  const generate = async () => {
    let values;
    try {
      values = await form.validateFields();
    } catch {
      return;
    }

    if (!values.reportType || !parsePeriod(values.period)) return;

    skipSearch.current = true;
    setSearch('');
    setPage({ current: 1, pageSize: 15, total: 0 });
    const filters = filtersFromForm(values);
    setApplied(filters);
    await load(filters);
    skipSearch.current = false;
  };

  useEffect(() => {
    if (!applied || skipSearch.current) return;
    const timer = setTimeout(
      () => load(applied, { page: 1, pageSize: page.pageSize, search }),
      450
    );
    return () => clearTimeout(timer);
  }, [search, page.pageSize, applied]);

  const downloadPdf = async () => {
    if (!applied || !showDownload) return;

    const month = parsePeriod(applied.period)?.format('YYYY-MM') ?? '';
    const filename = `${applied.reportType}-report-${month}.pdf`;
    setExporting(true);
    try {
      const res = await payrollService.downloadPayrollReport(
        buildReportPayload(applied, { forDownload: true }),
        filename
      );
      if (res?.success && res?.data?.blob) {
        const url = URL.createObjectURL(res.data.blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = res.data.filename || filename;
        link.click();
        URL.revokeObjectURL(url);
        return;
      }
      message.error(res?.message || 'Failed to download PDF report.');
    } catch (e) {
      message.error(e?.message || 'Failed to download PDF report.');
    } finally {
      setExporting(false);
    }
  };

  const handleReportTypeChange = (nextTypeId) => {
    const next = types.find((t) => t.id === nextTypeId);
    if (next?.bankFilter) form.setFieldValue('bank', '');
    else form.resetFields(['bank']);

    if (resolveReportTypeKey(nextTypeId) === 'loans') form.setFieldValue('loan_type_id', '');
    else form.resetFields(['loan_type_id']);
  };

  const emptyText = !reportType
    ? 'Select a report type, then click Generate.'
    : !applied
      ? 'Click Generate to load report data.'
      : loading
        ? 'Loading report data...'
        : search.trim() && !rows.length
          ? 'No records match your search.'
          : 'No records match the current selection.';

  return (
    <div className="space-y-4">
      <div className={PANEL}>
        <h4 className={HEAD} style={{ color: BRAND }}>Filters</h4>
        <Form
          form={form}
          layout="vertical"
          colon={false}
          requiredMark={false}
          className={FILTER_FORM}
          initialValues={{ period: dayjs().startOf('month').format('YYYY-MM-DD'), loan_type_id: '' }}
        >
          <div className="flex flex-wrap items-end gap-4">
            <Form.Item name="reportType" label="Report Type" className="mb-0 min-w-[200px] flex-1">
              <Select
                className="bms-filter-select w-full"
                size="large"
                onChange={handleReportTypeChange}
                loading={typesLoading}
                disabled={typesLoading}
                placeholder={typesLoading ? 'Loading...' : 'Select report type'}
                options={types.map((t) => ({ value: t.id, label: t.label }))}
                classNames={SELECT_POPUP}
              />
            </Form.Item>

            <Form.Item
              name="period"
              label="Period"
              className="mb-0 min-w-[200px] flex-1"
              getValueFromEvent={(d) => (d ? d.format('YYYY-MM-DD') : '')}
              getValueProps={(v) => ({ value: parsePeriod(v) })}
            >
              <DatePicker
                picker="month"
                className="bms-date-picker w-full"
                size="large"
                allowClear={false}
                format="MMMM YYYY"
                classNames={DATE_POPUP}
              />
            </Form.Item>

            {banks.length > 0 && (
              <OptionalFilterSelect
                name="bank"
                label={selectedType?.bankFilter?.label ?? 'Bank'}
                options={banks}
                disabled={typesLoading}
              />
            )}

            {isLoansReport && loanTypeOptions.length > 0 && (
              <OptionalFilterSelect
                name="loan_type_id"
                label="Loan Type"
                options={buildLoanTypeSelectOptions(loanTypeOptions)}
                loading={loanTypesLoading}
                disabled={typesLoading || loanTypesLoading}
              />
            )}

            <Form.Item label=" " colon={false} className="mb-0 ml-auto shrink-0">
              <button
                type="button"
                onClick={generate}
                disabled={!reportType || !periodValue || loading}
                className="btn-standard-primary flex items-center space-x-2 rounded-lg px-4 py-2 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {loading && <Loader2 size={16} className="animate-spin" />}
                <span>{loading ? 'Generating...' : 'Generate'}</span>
              </button>
            </Form.Item>
          </div>
        </Form>
      </div>

      {applied && cards.length > 0 && (
        <div className={PANEL}>
          <h4 className={HEAD} style={{ color: BRAND }}>Report summary</h4>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-4">
            {cards.map((card) => (
              <div key={card.label} className="min-w-0 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                <span className={`${LBL} mt-0`} style={{ color: BRAND }}>{card.label}</span>
                <div className="mt-0.5 text-sm font-medium text-black">{card.value}</div>
              </div>
            ))}
          </div>
        </div>
      )}

      {applied && (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <Input
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage((prev) => ({ ...prev, current: 1 }));
              }}
              placeholder="Search report records..."
              allowClear
              prefix={<SearchOutlined />}
              className="w-full sm:max-w-xs"
            />
            {showDownload && (
              <button
                type="button"
                onClick={downloadPdf}
                disabled={exporting}
                className="btn-standard-primary flex shrink-0 items-center space-x-2 rounded-lg px-4 py-2 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {exporting && <Loader2 size={16} className="animate-spin" />}
                <span>{exporting ? 'Downloading...' : 'Download'}</span>
              </button>
            )}
          </div>

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
            columns={buildTableColumns(reportKey, page)}
            data={rows}
            loading={false}
            pagination={{
              current: page.current,
              pageSize: page.pageSize,
              total: page.total || rows.length,
              showTotal: () => null,
              showQuickJumper: false,
              onChange: (nextPage, pageSize) => load(applied, { page: nextPage, pageSize, search }),
            }}
            showSearch={false}
            showRefresh={false}
            rowKey={reportRowKey}
            moneyDecimals={2}
            tableLayout={tableProps.tableLayout}
            scroll={tableProps.scroll}
            locale={{ emptyText }}
          />
          </div>
        </>
      )}
    </div>
  );
}
