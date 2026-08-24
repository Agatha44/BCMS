import { App, Alert, Button, Card, Col, Empty, Radio, Row, Tag, Tooltip } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';

import { DataTable } from '../../common/data';
import CollectionLoader from '../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { payrollService } from '../../services/payrollService.js';
import {
  ermsOverallTag,
  extractErmsFromExecutionsResponse,
  extractErmsFromPayrollRunResponse,
  formatErmsDateTime,
} from './payrollErmsUtils.js';

const BRAND = '#962E32';
const BRAND_HOVER = '#7A2326';

const PayrollErmsTab = ({
  active = false,
  runId,
  run = null,
  initialErms = null,
  canRepost = false,
  onErmsUpdated,
}) => {
  const { message, modal } = App.useApp();
  const [ermsState, setErmsState] = useState({ loading: false, erms: initialErms, error: null });
  const [listFilter, setListFilter] = useState('failed');
  const [reposting, setReposting] = useState(false);

  useEffect(() => {
    if (initialErms) {
      setErmsState((p) => ({ ...p, erms: initialErms }));
      if (Number(initialErms.failed_count) > 0) setListFilter('failed');
    }
  }, [initialErms]);

  const loadErms = useCallback(async () => {
    if (!runId && runId !== 0) return;

    setErmsState((p) => ({ ...p, loading: true, error: null }));
    try {
      const execRes = await payrollService.getPayrollErmsExecutions(runId);
      if (execRes?.success) {
        const erms = extractErmsFromExecutionsResponse(execRes);
        setErmsState({ loading: false, erms, error: null });
        return erms;
      }

      const runRes = await payrollService.getPayrollRun(runId);
      if (!runRes?.success) {
        setErmsState({ loading: false, erms: null, error: runRes?.message || 'Failed to load ERMS data.' });
        return null;
      }

      const erms = extractErmsFromPayrollRunResponse(runRes);
      setErmsState({ loading: false, erms, error: null });
      return erms;
    } catch (e) {
      setErmsState({
        loading: false,
        erms: null,
        error: e?.message || 'Failed to load ERMS data.',
      });
      return null;
    }
  }, [runId]);

  useEffect(() => {
    if (!active) return;
    loadErms();
  }, [active, loadErms]);

  const erms = ermsState.erms;
  const failedCount = Number(erms?.failed_count) || 0;
  const overall = ermsOverallTag(erms?.erms_status ?? run?.erms_status);
  const submittedAt = formatErmsDateTime(run?.erms_submitted_at) || '—';
  const ermsReference = run?.erms_reference || erms?.erms_reference;

  const tableRows = useMemo(() => {
    const list = erms?.executions || [];
    if (listFilter === 'failed') {
      return list.filter((e) => e.status_label === 'failed' || Number(e.status) === 2);
    }
    return list;
  }, [erms?.executions, listFilter]);

  const handleRepost = () => {
    if (!canRepost || !erms?.can_repost) return;

    const payrollNo = run?.payroll_number ? ` for ${run.payroll_number}` : '';
    modal.confirm({
      title: 'Repost failed ERMS executions',
      content: (
        <div className="space-y-2">
          <div>
            This will retry <span className="font-semibold">{failedCount}</span> failed ERMS execution
            {failedCount === 1 ? '' : 's'}
            {payrollNo}.
          </div>
          <div>Successful steps (e.g. miscellaneous journal) are not resent.</div>
        </div>
      ),
      okText: 'Repost to ERMS',
      cancelText: 'Cancel',
      okButtonProps: { style: { backgroundColor: BRAND, borderColor: BRAND } },
      async onOk() {
        setReposting(true);
        try {
          const res = await payrollService.repostPayrollErms(runId);
          if (!res?.success) {
            message.error(res?.message || 'Failed to repost to ERMS.');
            return;
          }
          message.success(res?.message || 'ERMS repost submitted.');
          await loadErms();
          onErmsUpdated?.();
        } catch (e) {
          message.error(e?.message || 'Failed to repost to ERMS.');
        } finally {
          setReposting(false);
        }
      },
    });
  };

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'sn',
        width: 70,
        align: 'center',
        render: (_, __, index) => index + 1,
      },
      {
        title: 'Label',
        dataIndex: 'label',
        key: 'label',
        ellipsis: true,
        render: (v, r) => (
          <div className="min-w-0">
            <div className="font-medium text-gray-900">{v || '—'}</div>
            {r?.execution_type ? (
              <div className="text-xs text-gray-500 truncate">{r.execution_type}</div>
            ) : null}
          </div>
        ),
      },
      {
        title: 'Source Ref',
        dataIndex: 'source_ref',
        key: 'source_ref',
        width: 200,
        ellipsis: true,
        render: (v) =>
          v ? (
            <Tooltip title={v}>
              <span>{v}</span>
            </Tooltip>
          ) : (
            '—'
          ),
      },
      {
        title: 'HTTP',
        dataIndex: 'http_status',
        key: 'http_status',
        width: 80,
        align: 'center',
        render: (v) => {
          const n = Number(v);
          if (!Number.isFinite(n)) return '—';
          return <span className={n >= 400 ? 'text-red-600 font-medium' : ''}>{n}</span>;
        },
      },
      {
        title: 'Status',
        dataIndex: 'status_label',
        key: 'status_label',
        width: 100,
        align: 'center',
        render: (v) => {
          const raw = String(v || '').toLowerCase();
          const color = raw === 'success' ? 'green' : raw === 'failed' ? 'red' : 'default';
          return <Tag color={color}>{raw === 'success' ? 'Success' : raw === 'failed' ? 'Failed' : v || '—'}</Tag>;
        },
      },
      {
        title: 'Error',
        dataIndex: 'error_message',
        key: 'error_message',
        ellipsis: true,
        render: (v) =>
          v ? (
            <Tooltip title={v}>
              <span className="text-red-700">{v}</span>
            </Tooltip>
          ) : (
            <span className="text-slate-400">—</span>
          ),
      },
      {
        title: 'Submitted',
        dataIndex: 'submitted_at',
        key: 'submitted_at',
        width: 160,
        render: (v) => formatErmsDateTime(v) || '—',
      },
    ],
    []
  );

  if (!active) return null;

  const showRepostButton = canRepost && Boolean(erms?.can_repost) && failedCount > 0;

  return (
    <div className="mt-2 space-y-3 p-3">
      {ermsState.error ? (
        <Alert type="error" showIcon message={ermsState.error} />
      ) : null}

      <Card size="small" className="border-slate-200">
        <h4
          className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
          style={{ color: BRAND }}
        >
          ERMS Submission Summary
        </h4>
        <Row gutter={[12, 12]}>
          <Col xs={12} sm={6}>
            <div className="rounded border border-slate-200 bg-white px-3 py-2">
              <div className="text-xs text-slate-500">Overall</div>
              <Tag color={overall.color} className="mt-1">
                {overall.text}
              </Tag>
            </div>
          </Col>
          <Col xs={12} sm={6}>
            <div className="rounded border border-slate-200 bg-white px-3 py-2">
              <div className="text-xs text-slate-500">Total Steps</div>
              <div className="mt-1 text-lg font-semibold text-black">{erms?.total_executions ?? 0}</div>
            </div>
          </Col>
          <Col xs={12} sm={6}>
            <div className="rounded border border-slate-200 bg-white px-3 py-2">
              <div className="text-xs text-slate-500">Failed</div>
              <div
                className={`mt-1 text-lg font-semibold ${failedCount > 0 ? 'text-red-600' : 'text-black'}`}
              >
                {failedCount}
              </div>
            </div>
          </Col>
          <Col xs={12} sm={6}>
            <div className="rounded border border-slate-200 bg-white px-3 py-2">
              <div className="text-xs text-slate-500">Miscellaneous</div>
              <div className="mt-1">
                {erms?.miscellaneous_ok === true ? (
                  <Tag color="green">OK</Tag>
                ) : erms?.miscellaneous_ok === false ? (
                  <Tag color="red">Failed</Tag>
                ) : (
                  <span className="text-slate-400">N/A</span>
                )}
              </div>
            </div>
          </Col>
        </Row>
        <div className="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-700">
          <span>
            <span className="font-semibold" style={{ color: BRAND }}>
              Submitted:{' '}
            </span>
            {submittedAt}
          </span>
          <span>
            <span className="font-semibold" style={{ color: BRAND }}>
              ERMS Ref:{' '}
            </span>
            {ermsReference ? (
              <span className="text-black">{ermsReference}</span>
            ) : (
              <span className="text-slate-400">N/A</span>
            )}
          </span>
        </div>
      </Card>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Radio.Group
          value={listFilter}
          onChange={(e) => setListFilter(e.target.value)}
          optionType="button"
          buttonStyle="solid"
          size="small"
        >
          <Radio.Button value="failed">Failed only</Radio.Button>
          <Radio.Button value="all">All</Radio.Button>
        </Radio.Group>

        <div className="flex justify-end gap-2">
          {showRepostButton ? (
            <Button
              type="primary"
              loading={reposting}
              onClick={handleRepost}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = BRAND_HOVER;
                e.currentTarget.style.borderColor = BRAND_HOVER;
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
                e.currentTarget.style.borderColor = BRAND;
              }}
            >
              Repost failed to ERMS
            </Button>
          ) : null}
        </div>
      </div>

      <Card size="small" title="Executions">
        {!ermsState.loading && tableRows.length === 0 ? (
          <Empty
            description={
              listFilter === 'failed'
                ? 'No failed ERMS executions'
                : 'No ERMS executions recorded for this payroll run'
            }
          />
        ) : (
          <div className="relative">
            {ermsState.loading || reposting ? (
              <div
                className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
                style={{ top: '4.75rem' }}
              >
                <CollectionLoader />
              </div>
            ) : null}
          <DataTable
            columns={columns}
            data={tableRows}
            loading={false}
            pagination={tableRows.length > 15 ? { pageSize: 15, showTotal: () => null, showQuickJumper: false } : false}
            showSearch={true}
            showRefresh={true}
            onRefresh={loadErms}
            searchPlaceholder="Search label, source ref, error..."
            rowKey={(record) => record.id ?? record.source_ref}
            size="middle"
            bordered
          />
          </div>
        )}
      </Card>

    </div>
  );
};

PayrollErmsTab.propTypes = {
  active: PropTypes.bool,
  runId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  run: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  initialErms: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  canRepost: PropTypes.bool,
  onErmsUpdated: PropTypes.func,
};

export default PayrollErmsTab;
