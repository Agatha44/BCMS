import { useCallback, useEffect, useMemo, useState } from 'react';
import { ArrowRight, CheckCircle, RotateCcw, Undo2, XCircle } from 'lucide-react';
import { Button, Input, Modal, Tabs, Tag, message } from 'antd';
import { useSelector } from 'react-redux';
import Swal from 'sweetalert2';

import DataTable from '../../../../common/data/DataTable.jsx';
import CollectionLoader from '../../components/CollectionLoader.jsx';
import { formatCurrency } from '../../../../common/utils/numberFormat.js';
import { apiService } from '../../../../services/api.jsx';
import BrandModalHeader from './BrandModalHeader.jsx';
import FundTransferApprovalDocumentTab from './FundTransferApprovalDocumentTab.jsx';
import FundTransferModal from './FundTransferModal.jsx';
import {
  canUserApproveTransfer,
  canUserResubmitTransfer,
  canUserReturnTransfer,
  formatApiValidationErrors,
} from '../utils/fundTransferUtils.js';
import {
  FUND_TRANSFER_STATUS,
  getTransferStatusTagColor,
  isTransferPending,
  isTransferReturned,
  normalizeTransferStatus,
} from '../utils/fundTransferStatus.js';

const WORKFLOW_ACTION = Object.freeze({
  APPROVE: 'approve',
  REJECT: 'reject',
  RETURN: 'return',
});

const WORKFLOW_ACTION_META = {
  [WORKFLOW_ACTION.APPROVE]: {
    errorTitle: 'Approval failed',
    successTitle: 'Approved',
    successText: 'approved',
    confirmLabel: 'Confirm approval',
    commentLabel: 'Approval comment (required)',
    placeholder: 'Enter your approval comment',
    emptyCommentWarning: 'Please provide an approval comment',
  },
  [WORKFLOW_ACTION.REJECT]: {
    errorTitle: 'Rejection failed',
    successTitle: 'Rejected',
    successText: 'rejected',
    confirmLabel: 'Confirm rejection',
    commentLabel: 'Rejection comment (required)',
    placeholder: 'Enter reason for rejecting this transfer',
    emptyCommentWarning: 'Please provide a reason for rejection',
  },
  [WORKFLOW_ACTION.RETURN]: {
    errorTitle: 'Return failed',
    successTitle: 'Returned',
    successText: 'returned to the requester',
    confirmLabel: 'Confirm return',
    commentLabel: 'Return comment (required)',
    placeholder: 'Enter reason for returning this transfer to the requester',
    emptyCommentWarning: 'Please provide a return comment',
  },
};

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const DETAIL_TAB = Object.freeze({
  DETAILS: 'details',
  DOCUMENT: 'document',
});

const hasFieldValue = (value) => value != null && value !== '';

const formatDateTime = (value) => {
  if (!hasFieldValue(value)) return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const extractTransfer = (payload) => {
  if (!payload) return null;
  if (payload.transfer) return payload.transfer;
  if (payload.id != null) return payload;
  return null;
};

const ReadOnlyField = ({ label, value, mono = false }) => (
  <div className="min-w-0 py-1.5">
    <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <div className={`mt-0.5 text-sm font-medium text-black ${mono ? 'font-mono' : ''}`}>
      {hasFieldValue(value) ? value : <span className="font-normal text-slate-400">{EMPTY_VALUE}</span>}
    </div>
  </div>
);

const SectionHeading = ({ children }) => (
  <h4
    className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
    style={{ color: BRAND }}
  >
    {children}
  </h4>
);

const AccountCard = ({ label, accountNo, accountName }) => (
  <div className="min-w-0 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <p className="m-0 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">{label}</p>
    <p className="m-0 mt-2 font-mono text-lg font-semibold text-black">{accountNo ?? EMPTY_VALUE}</p>
    <p className="m-0 mt-1 truncate text-sm text-slate-600" title={accountName ?? undefined}>
      {accountName ?? <span className="text-slate-400">{EMPTY_VALUE}</span>}
    </p>
  </div>
);

const TransferHero = ({ transfer, statusLabel }) => (
  <div className="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-br from-[#fffafa] via-white to-slate-50">
    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
      <div className="min-w-0 space-y-2">
        <div className="flex flex-wrap items-center gap-2">
          <Tag
            color={getTransferStatusTagColor(transfer)}
            className="!m-0 px-2.5 py-0.5 text-xs font-semibold uppercase"
          >
            {statusLabel}
          </Tag>
          {transfer.transfer_uuid ? (
            <span
              className="truncate font-mono text-[11px] text-slate-400"
              title={transfer.transfer_uuid}
            >
              {transfer.transfer_uuid}
            </span>
          ) : null}
        </div>
        <p className="m-0 text-3xl font-bold tracking-tight text-black">{formatCurrency(transfer.amount)}</p>
        {hasFieldValue(transfer.narration) ? (
          <p className="m-0 max-w-2xl text-sm leading-relaxed text-slate-600">{transfer.narration}</p>
        ) : null}
      </div>
      {formatDateTime(transfer.posted_at) ? (
        <div className="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-right">
          <p className="m-0 text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-700">Posted At</p>
          <p className="m-0 mt-0.5 text-sm font-medium text-emerald-900">{formatDateTime(transfer.posted_at)}</p>
        </div>
      ) : null}
    </div>

    <div className="grid items-stretch gap-3 p-5 md:grid-cols-[1fr_auto_1fr]">
      <AccountCard
        label="From Account"
        accountNo={transfer.from_account_no}
        accountName={transfer.from_account_name}
      />
      <div className="flex items-center justify-center py-1 md:py-0">
        <div
          className="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white shadow-sm"
          style={{ color: BRAND }}
        >
          <ArrowRight size={18} strokeWidth={2.25} />
        </div>
      </div>
      <AccountCard label="To Account" accountNo={transfer.to_account_no} accountName={transfer.to_account_name} />
    </div>
  </div>
);

const DetailSection = ({ title, children }) => (
  <div className="mb-6">
    <SectionHeading>{title}</SectionHeading>
    <div className="rounded-lg border border-slate-200 bg-white p-4">{children}</div>
  </div>
);

export default function FundTransferDetailsModal({
  isOpen,
  transferId,
  initialTransfer,
  onClose,
  onUpdated,
}) {
  const currentUser = useSelector((state) => state.auth.user);
  const selectedRole = useSelector((state) => state.app.selectedRole);

  const [transfer, setTransfer] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [approvalAction, setApprovalAction] = useState(null);
  const [comment, setComment] = useState('');
  const [approvalLoading, setApprovalLoading] = useState(false);
  const [activeDetailTab, setActiveDetailTab] = useState(DETAIL_TAB.DETAILS);
  const [resubmitModalOpen, setResubmitModalOpen] = useState(false);

  const loadTransfer = useCallback(async () => {
    if (transferId == null) return;
    setLoading(true);
    setError(null);
    try {
      const res = await apiService.getFundTransferById(transferId);
      if (res?.success) {
        const row = extractTransfer(res.data);
        setTransfer(row || null);
        if (!row) setError('Transfer details not found');
      } else {
        setError(res?.message || 'Failed to load transfer details');
      }
    } catch (err) {
      setError(err?.message || 'Failed to load transfer details');
    } finally {
      setLoading(false);
    }
  }, [transferId]);

  useEffect(() => {
    if (!isOpen || transferId == null) {
      setTransfer(null);
      setError(null);
      setLoading(false);
      setApprovalAction(null);
      setComment('');
      setActiveDetailTab(DETAIL_TAB.DETAILS);
      setResubmitModalOpen(false);
      return undefined;
    }

    setTransfer(initialTransfer ? extractTransfer(initialTransfer) : null);
    setApprovalAction(null);
    setComment('');
    setActiveDetailTab(DETAIL_TAB.DETAILS);
    setResubmitModalOpen(false);
    loadTransfer();
    return undefined;
  }, [isOpen, transferId, initialTransfer, loadTransfer]);

  const statusLabel = transfer?.status ?? EMPTY_VALUE;
  const statusKey = normalizeTransferStatus(transfer);
  const pending = isTransferPending(transfer);
  const returned = isTransferReturned(transfer);
  const canApprove = canUserApproveTransfer(transfer, currentUser, selectedRole);
  const canReturn = canUserReturnTransfer(transfer, currentUser, selectedRole);
  const canResubmit = canUserResubmitTransfer(transfer, currentUser);

  const showApprovalSection =
    hasFieldValue(transfer?.approved_by) ||
    hasFieldValue(transfer?.approved_at) ||
    hasFieldValue(transfer?.approval_comment);

  const showRejectionSection =
    statusKey === FUND_TRANSFER_STATUS.REJECTED ||
    hasFieldValue(transfer?.rejected_by) ||
    hasFieldValue(transfer?.rejected_at) ||
    hasFieldValue(transfer?.rejection_reason);

  const showReturnSection =
    statusKey === FUND_TRANSFER_STATUS.RETURNED ||
    hasFieldValue(transfer?.returned_by) ||
    hasFieldValue(transfer?.returned_at) ||
    hasFieldValue(transfer?.return_comment);

  const showClientReference = hasFieldValue(transfer?.client_reference);

  const details = useMemo(() => {
    const list = transfer?.details;
    return Array.isArray(list) ? list : [];
  }, [transfer]);

  const handleWorkflowSubmit = async () => {
    if (!transfer?.id || !approvalAction) return;

    const actionMeta = WORKFLOW_ACTION_META[approvalAction];
    const trimmedComment = comment.trim();
    if (!trimmedComment) {
      message.warning(actionMeta?.emptyCommentWarning || 'Please provide a comment');
      return;
    }

    setApprovalLoading(true);
    try {
      const payload = { comment: trimmedComment };

      const res =
        approvalAction === WORKFLOW_ACTION.APPROVE
          ? await apiService.approveFundTransfer(transfer.id, payload)
          : approvalAction === WORKFLOW_ACTION.REJECT
            ? await apiService.rejectFundTransfer(transfer.id, payload)
            : await apiService.returnFundTransfer(transfer.id, payload);

      if (!res?.success) {
        await Swal.fire({
          icon: 'error',
          title: actionMeta.errorTitle,
          text: formatApiValidationErrors(
            { responseData: res, validationErrors: res?.data },
            res?.message || 'Could not process this request.'
          ),
        });
        return;
      }

      await Swal.fire({
        icon: 'success',
        title: actionMeta.successTitle,
        text: res.message || `Fund transfer ${actionMeta.successText} successfully.`,
        timer: 2200,
        showConfirmButton: false,
      });

      setApprovalAction(null);
      setComment('');
      await loadTransfer();
      onUpdated?.();
    } catch (err) {
      await Swal.fire({
        icon: 'error',
        title: 'Error',
        text: formatApiValidationErrors(err, err?.message || 'An unexpected error occurred.'),
      });
    } finally {
      setApprovalLoading(false);
    }
  };

  const handleResubmitted = async () => {
    setResubmitModalOpen(false);
    await loadTransfer();
    onUpdated?.();
  };

  const detailColumns = useMemo(
    () => [
      {
        title: '#',
        key: 'serial',
        width: 56,
        align: 'center',
        render: (_, __, idx) => (
          <span className="text-sm font-medium text-slate-600">{idx + 1}</span>
        ),
      },
      {
        title: 'Account No',
        dataIndex: 'account_no',
        key: 'account_no',
        render: (v) => <span className="font-mono text-sm font-medium">{v ?? EMPTY_VALUE}</span>,
      },
      {
        title: 'Account Name',
        dataIndex: 'account_name',
        key: 'account_name',
        render: (v) => <span className="text-sm font-medium">{v ?? EMPTY_VALUE}</span>,
      },
      {
        title: 'Entry',
        dataIndex: 'entry_type',
        key: 'entry_type',
        align: 'center',
        render: (v) => {
          const type = String(v ?? '').toLowerCase();
          const color = type === 'credit' ? 'green' : type === 'debit' ? 'red' : 'default';
          return (
            <Tag color={color} className="!m-0 px-2.5 py-0.5 text-xs font-medium uppercase">
              {v ?? EMPTY_VALUE}
            </Tag>
          );
        },
      },
      {
        title: 'Amount',
        dataIndex: 'amount',
        key: 'amount',
        align: 'right',
        render: (v) => <span className="font-mono text-sm font-medium">{formatCurrency(v)}</span>,
      },
      {
        title: 'Previous Balance',
        dataIndex: 'previous_balance',
        key: 'previous_balance',
        align: 'right',
        render: (v) => <span className="font-mono text-sm">{formatCurrency(v)}</span>,
      },
      {
        title: 'New Balance',
        dataIndex: 'new_balance',
        key: 'new_balance',
        align: 'right',
        render: (v) => <span className="font-mono text-sm font-semibold">{formatCurrency(v)}</span>,
      },
      {
        title: 'Reference',
        dataIndex: 'reference_number',
        key: 'reference_number',
        render: (v) => (
          <span className="block max-w-[180px] truncate font-mono text-xs" title={v ?? ''}>
            {v ?? EMPTY_VALUE}
          </span>
        ),
      },
      {
        title: 'Description',
        dataIndex: 'description',
        key: 'description',
        render: (v) => (
          <span className="block max-w-[160px] truncate text-sm" title={v ?? ''}>
            {v ?? EMPTY_VALUE}
          </span>
        ),
      },
    ],
    []
  );

  const renderFooter = () => {
    const actionMeta = approvalAction ? WORKFLOW_ACTION_META[approvalAction] : null;

    if (approvalAction && actionMeta) {
      return (
        <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div className="flex-1">
            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
              {actionMeta.commentLabel}
            </label>
            <Input.TextArea
              rows={2}
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              placeholder={actionMeta.placeholder}
              disabled={approvalLoading}
              className="!rounded-lg"
            />
          </div>
          <div className="flex shrink-0 items-center justify-end gap-2">
            <Button
              onClick={() => {
                setApprovalAction(null);
                setComment('');
              }}
              disabled={approvalLoading}
            >
              Cancel
            </Button>
            <Button
              type="primary"
              loading={approvalLoading}
              onClick={handleWorkflowSubmit}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
            >
              {actionMeta.confirmLabel}
            </Button>
          </div>
        </div>
      );
    }

    return (
      <div className="flex w-full flex-wrap items-center justify-end gap-2">
        {canApprove ? (
          <>
            <Button
              type="primary"
              icon={<CheckCircle size={14} />}
              onClick={() => {
                setApprovalAction(WORKFLOW_ACTION.APPROVE);
                setComment('');
              }}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
                e.currentTarget.style.borderColor = BRAND_DARK;
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
                e.currentTarget.style.borderColor = BRAND;
              }}
            >
              Approve
            </Button>
            <Button
              danger
              type="primary"
              icon={<XCircle size={14} />}
              onClick={() => {
                setApprovalAction(WORKFLOW_ACTION.REJECT);
                setComment('');
              }}
            >
              Reject
            </Button>
            <Button
              icon={<Undo2 size={14} />}
              onClick={() => {
                setApprovalAction(WORKFLOW_ACTION.RETURN);
                setComment('');
              }}
              style={{ borderColor: '#d97706', color: '#d97706' }}
            >
              Return
            </Button>
          </>
        ) : null}
        {canResubmit ? (
          <Button
            type="primary"
            icon={<RotateCcw size={14} />}
            onClick={() => setResubmitModalOpen(true)}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => {
              e.currentTarget.style.backgroundColor = BRAND_DARK;
              e.currentTarget.style.borderColor = BRAND_DARK;
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
              e.currentTarget.style.borderColor = BRAND;
            }}
          >
            Resubmit
          </Button>
        ) : null}
        {pending && !canApprove && !canReturn ? (
          <span className="mr-auto text-xs text-amber-800">
            {transfer?.submitted_by
              ? 'You cannot approve your own transfer request.'
              : 'Awaiting approver action.'}
          </span>
        ) : null}
        {returned && !canResubmit ? (
          <span className="mr-auto text-xs text-amber-800">
            This returned transfer can only be resubmitted by the original requester.
          </span>
        ) : null}
        <Button onClick={onClose}>Close</Button>
      </div>
    );
  };

  return (
    <Modal
      open={isOpen}
      onCancel={onClose}
      footer={null}
      width={1280}
      style={{ maxWidth: '95vw' }}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      maskClosable={!approvalLoading}
      keyboard={!approvalLoading}
      className="brand-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="Fund Transfer Details" onClose={approvalLoading ? undefined : onClose} />

      <style>{`
        .fund-transfer-detail-tabs .ant-tabs-tab-active .ant-tabs-tab-btn {
          color: #962E32;
        }
        .fund-transfer-detail-tabs .ant-tabs-ink-bar {
          background: #962E32;
        }
        .fund-transfer-detail-tabs .ant-tabs-nav {
          margin-bottom: 0;
          padding: 0 24px;
        }
        .fund-transfer-detail-tabs .ant-tabs-content-holder {
          max-height: calc(75vh - 88px);
          overflow-y: auto;
          padding: 20px 24px;
        }
      `}</style>

      {loading && !transfer ? (
        <CollectionLoader size={64} compact />
      ) : error && !transfer ? (
        <div className="px-6 py-5">
          <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        </div>
      ) : transfer ? (
        <Tabs
          activeKey={activeDetailTab}
          onChange={setActiveDetailTab}
          className="fund-transfer-detail-tabs border-b border-slate-200"
          items={[
            {
              key: DETAIL_TAB.DETAILS,
              label: 'Details',
              children: (
                <>
                  <TransferHero transfer={transfer} statusLabel={statusLabel} />

                  <DetailSection title="Request Information">
                        <div className="grid gap-x-6 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                          <ReadOnlyField label="Submitted By" value={transfer.submitted_by} />
                          <ReadOnlyField label="Created By" value={transfer.created_by} />
                          <ReadOnlyField label="Created At" value={formatDateTime(transfer.created_at)} />
                          {showClientReference ? (
                            <ReadOnlyField label="Client Reference" value={transfer.client_reference} mono />
                          ) : null}
                        </div>
                      </DetailSection>

                      {showApprovalSection ? (
                        <DetailSection title="Approval Details">
                          <div className="grid gap-x-6 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                            <ReadOnlyField label="Approved By" value={transfer.approved_by} />
                            <ReadOnlyField label="Approved At" value={formatDateTime(transfer.approved_at)} />
                            <ReadOnlyField label="Approval Comment" value={transfer.approval_comment} />
                          </div>
                        </DetailSection>
                      ) : null}

                      {showRejectionSection ? (
                        <DetailSection title="Rejection Details">
                          <div className="grid gap-x-6 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                            <ReadOnlyField label="Rejected By" value={transfer.rejected_by} />
                            <ReadOnlyField label="Rejected At" value={formatDateTime(transfer.rejected_at)} />
                            <ReadOnlyField label="Rejection Reason" value={transfer.rejection_reason} />
                          </div>
                        </DetailSection>
                      ) : null}

                      {showReturnSection ? (
                        <DetailSection title="Return Details">
                          <div className="grid gap-x-6 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                            <ReadOnlyField label="Returned By" value={transfer.returned_by} />
                            <ReadOnlyField label="Returned At" value={formatDateTime(transfer.returned_at)} />
                            <ReadOnlyField label="Return Comment" value={transfer.return_comment} />
                          </div>
                        </DetailSection>
                      ) : null}

                      <SectionHeading>Ledger Entries</SectionHeading>
                      {loading ? (
                        <CollectionLoader size={64} compact />
                      ) : details.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-500">
                          {pending
                            ? 'No ledger entries yet. Balances will update after approval.'
                            : 'No ledger entries for this transfer.'}
                        </p>
                      ) : (
                        <div className="rounded-md border border-slate-200 bg-white">
                          <DataTable
                            columns={detailColumns}
                            data={details}
                            loading={false}
                            pagination={{ pageSize: 10, showSizeChanger: true, pageSizeOptions: ['5', '10', '20'] }}
                            showSearch={false}
                            showRefresh={false}
                            size="small"
                          />
                        </div>
                      )}
                </>
              ),
            },
            {
              key: DETAIL_TAB.DOCUMENT,
              label: 'Document',
              children: (
                <FundTransferApprovalDocumentTab
                  transferId={transferId}
                  transfer={transfer}
                  active={activeDetailTab === DETAIL_TAB.DOCUMENT && isOpen}
                />
              ),
            },
          ]}
        />
      ) : null}

      <div className="border-t border-slate-200 bg-white px-6 py-3">{renderFooter()}</div>

      <FundTransferModal
        isOpen={resubmitModalOpen}
        mode="resubmit"
        transferId={transferId}
        initialTransfer={transfer}
        onClose={() => setResubmitModalOpen(false)}
        onSubmitted={handleResubmitted}
      />
    </Modal>
  );
}
