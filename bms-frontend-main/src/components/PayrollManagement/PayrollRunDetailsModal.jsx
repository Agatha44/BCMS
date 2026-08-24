import { App, Button, Modal, Tabs } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSelector } from 'react-redux';

import ApprovalActionForm from '../../common/components/ApprovalActionForm.jsx';
import MinutesPanel from '../../common/components/MinutesPanel.jsx';
import { formatMonthYearLabel } from '../../common/utils/dateFormat.js';
import {
  canPerformPayrollWorkflowAction,
  hasPayrollInitiatorRole,
} from '../../common/utils/employeeUtils.jsx';
import { payrollService } from '../../services/payrollService.js';
import PayrollDocumentTab from './PayrollDocumentTab.jsx';
import PayrollErmsTab from './PayrollErmsTab.jsx';
import PayrollJournalTab from './PayrollJournalTab.jsx';
import PayrollSummaryTab from './PayrollSummaryTab.jsx';
import { extractRunAndHistory, normalizeHistoryMinutes } from './payrollRunUtils.js';

// Backend workflow: prepared -> initiated -> examined -> verified -> approved -> posted
const WORKFLOW_BY_STATUS = {
  prepared:  { approveText: 'Submit for Approval', approveAction: 'initiate' },
  initiated: { approveText: 'Examine Payroll',     approveAction: 'examine' },
  examined:  { approveText: 'Verify Payroll',      approveAction: 'verify'  },
  verified:  { approveText: 'Approve Payroll',     approveAction: 'approve' },
};
const DEFAULT_WORKFLOW = { approveText: 'Approve', approveAction: 'approve' };
const FINAL_STATUSES = new Set(['approved', 'posted']);

const lowerStatus = (run) => String(run?.status || '').toLowerCase().trim();

function formatPeriodRange(summary) {
  const from = formatMonthYearLabel(summary?.from?.month, summary?.from?.year);
  const to = formatMonthYearLabel(summary?.to?.month, summary?.to?.year);
  if (!from && !to) return '';
  if (!from) return to;
  if (!to) return from;
  return `${from} \u2194 ${to}`;
}

function DocumentsTab({ runId, active }) {
  const { message } = App.useApp();
  const [state, setState] = useState({ loading: false, url: null });

  useEffect(() => {
    if (!active || (!runId && runId !== 0)) return undefined;

    let cancelled = false;
    let createdUrl = null;

    (async () => {
      setState({ loading: true, url: null });
      const res = await payrollService.getPayrollRunTransactionsDocument(runId);
      if (cancelled) return;

      if (!res?.success) {
        setState({ loading: false, url: null });
        message.error(res?.message || 'Could not load payroll transactions document.');
        return;
      }
      createdUrl = res.data || null;
      setState({ loading: false, url: createdUrl });
    })();

    return () => {
      cancelled = true;
      if (createdUrl && typeof createdUrl === 'string' && createdUrl.startsWith('blob:')) {
        URL.revokeObjectURL(createdUrl);
      }
    };
  }, [active, runId, message]);

  const slots = useMemo(
    () => [
      {
        id: 'transactions',
        label: 'Payroll Transactions',
        loading: state.loading,
        url: state.url,
      },
    ],
    [state.loading, state.url]
  );

  return <PayrollDocumentTab slots={slots} />;
}

DocumentsTab.propTypes = {
  runId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  active: PropTypes.bool,
};

const PayrollRunDetailsModal = ({ open, run, onClose, onUpdated }) => {
  const { message } = App.useApp();
  const selectedRole = useSelector((state) => state.app.selectedRole);

  const [summary, setSummary] = useState({ loading: false, data: null });
  const [details, setDetails] = useState({ run: null, history: [], erms: null });
  const [activeTab, setActiveTab] = useState('journal');
  const [busyAction, setBusyAction] = useState(null); // 'approve' | 'return' | 'reject' | 'process' | null

  const resolvedRun = details.run ?? run;
  const runId = resolvedRun?.id ?? run?.id;
  const status = lowerStatus(resolvedRun);
  const isFinalStatus = FINAL_STATUSES.has(status);
  const workflow = WORKFLOW_BY_STATUS[status] || DEFAULT_WORKFLOW;
  const canActOnWorkflow = !isFinalStatus && canPerformPayrollWorkflowAction(status, selectedRole);
  const canProcessPayroll = status === 'approved' && hasPayrollInitiatorRole(selectedRole);
  const canRepostErms = hasPayrollInitiatorRole(selectedRole);
  const showErmsTab = status === 'posted';
  const ermsFailedCount = Number(details.erms?.failed_count) || 0;
  const minutesItems = useMemo(() => normalizeHistoryMinutes(details.history), [details.history]);
  const headerRight = useMemo(() => formatPeriodRange(summary.data), [summary.data]);

  const loadDetails = useCallback(async (id) => {
    if (!id && id !== 0) return null;
    const res = await payrollService.getPayrollRun(id);
    if (!res?.success) return null;
    const { run: runDetails, history, erms } = extractRunAndHistory(res);
    setDetails((prev) => ({
      run: runDetails || prev.run,
      history,
      erms: erms ?? prev.erms,
    }));
    return { run: runDetails, erms };
  }, []);

  const handleErmsUpdated = useCallback(async () => {
    if (!runId && runId !== 0) return;
    await loadDetails(runId);
    onUpdated?.();
  }, [loadDetails, onUpdated, runId]);

  useEffect(() => {
    if (!open) return undefined;
    let cancelled = false;

    (async () => {
      setSummary({ loading: true, data: null });
      try {
        const [summaryRes, detailsRes] = await Promise.all([
          payrollService.getPayrollSummary(),
          run?.id ? payrollService.getPayrollRun(run.id) : Promise.resolve(null),
        ]);
        if (cancelled) return;

        setSummary({ loading: false, data: summaryRes?.success ? summaryRes.data : null });

        if (detailsRes?.success) {
          const { run: runDetails, history, erms } = extractRunAndHistory(detailsRes);
          setDetails({ run: runDetails || run, history, erms });
        } else {
          setDetails({ run, history: [], erms: null });
        }
      } catch {
        if (cancelled) return;
        setSummary({ loading: false, data: null });
        setDetails({ run, history: [], erms: null });
      }
    })();

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const submitWorkflow = async ({ action, comment }) => {
    if (!runId && runId !== 0) {
      message.error('Payroll run ID is missing.');
      return;
    }

    const needsComment = action === 'reject' || action === 'return';
    if (needsComment && !String(comment || '').trim()) {
      message.error(
        action === 'reject'
          ? 'Please provide a reason for rejection.'
          : 'Please provide a reason for return.'
      );
      return;
    }

    const busy = action === 'reject' ? 'reject' : action === 'return' ? 'return' : 'approve';
    setBusyAction(busy);
    try {
      const res = await payrollService.updatePayrollWorkflow(runId, { action, comment: comment ?? '' });
      if (!res?.success) {
        message.error(res?.message || 'Failed to update payroll workflow.');
        return;
      }
      message.success(res?.message || 'Workflow updated.');
      await loadDetails(runId);
      onClose?.();
      onUpdated?.();
    } catch (e) {
      message.error(e?.message || 'Failed to update payroll workflow.');
    } finally {
      setBusyAction(null);
    }
  };

  const processPayroll = async () => {
    if (!runId && runId !== 0) {
      message.error('Payroll run ID is missing.');
      return;
    }

    setBusyAction('process');
    try {
      const res = await payrollService.processPayroll(runId);
      if (!res?.success) {
        message.error(res?.message || 'Failed to process payroll.');
        return;
      }
      message.success(res?.message || 'Payroll processed.');
      await loadDetails(runId);
      onClose?.();
      onUpdated?.();
    } catch (e) {
      message.error(e?.message || 'Failed to process payroll.');
    } finally {
      setBusyAction(null);
    }
  };

  const tabs = useMemo(() => {
    const items = [
      {
        key: 'journal',
        label: 'Payroll Journal',
        children: (
          <div className="p-3">
            <PayrollJournalTab active={activeTab === 'journal'} runId={runId} />
          </div>
        ),
      },
      {
        key: 'summary',
        label: 'Payroll Summary',
        children: (
          <div className="p-3">
            <PayrollSummaryTab loading={summary.loading} summary={summary.data} headerRight={headerRight} />
          </div>
        ),
      },
      {
        key: 'documents',
        label: 'Documents',
        children: <DocumentsTab runId={runId} active={activeTab === 'documents'} />,
      },
    ];

    if (showErmsTab) {
      items.push({
        key: 'erms',
        label: ermsFailedCount > 0 ? `ERMS (${ermsFailedCount})` : 'ERMS',
        children: (
          <PayrollErmsTab
            active={activeTab === 'erms'}
            runId={runId}
            run={resolvedRun}
            initialErms={details.erms}
            canRepost={canRepostErms}
            onErmsUpdated={handleErmsUpdated}
          />
        ),
      });
    }

    return items;
  }, [
    activeTab,
    canRepostErms,
    details.erms,
    ermsFailedCount,
    handleErmsUpdated,
    headerRight,
    resolvedRun,
    runId,
    showErmsTab,
    summary.data,
    summary.loading,
  ]);

  const renderMinutesFooter = () => {
    if (canActOnWorkflow) {
      return (
        <ApprovalActionForm
          onClose={onClose}
          approveText={workflow.approveText}
          rejectText="Reject"
          returnText="Return"
          loadingApprove={busyAction === 'approve'}
          loadingReject={busyAction === 'reject'}
          loadingReturn={busyAction === 'return'}
          onApprove={(comment) => submitWorkflow({ action: workflow.approveAction, comment })}
          onReturn={(comment) => submitWorkflow({ action: 'return', comment })}
          onReject={(comment) => submitWorkflow({ action: 'reject', comment })}
        />
      );
    }

    if (status === 'approved' && canProcessPayroll) {
      return (
        <div className="flex justify-end">
          <Button
            type="primary"
            onClick={processPayroll}
            loading={busyAction === 'process'}
            style={{ backgroundColor: '#962E32', borderColor: '#962E32' }}
          >
            Payroll Process
          </Button>
        </div>
      );
    }

    return null;
  };

  return (
    <Modal
      title={`Payroll Details${resolvedRun?.payroll_number ? ` — ${resolvedRun.payroll_number}` : ''}`}
      open={open}
      onCancel={onClose}
      footer={null}
      width={1400}
      style={{ maxWidth: '96vw' }}
      destroyOnHidden
    >
      <div className="flex gap-3">
        <div className="flex-1 min-w-0">
          <Tabs activeKey={activeTab} onChange={setActiveTab} items={tabs} />
        </div>

        {activeTab === 'summary' && (
          <div className="hidden w-[400px] max-w-[36%] shrink-0 overflow-visible rounded border bg-white lg:mt-10 lg:block">
            <MinutesPanel items={minutesItems} footer={renderMinutesFooter()} />
          </div>
        )}
      </div>
    </Modal>
  );
};

PayrollRunDetailsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  run: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  onClose: PropTypes.func.isRequired,
  onUpdated: PropTypes.func,
};

export default PayrollRunDetailsModal;
