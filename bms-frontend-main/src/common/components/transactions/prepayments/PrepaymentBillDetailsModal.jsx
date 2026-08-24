import {useMemo, useState} from 'react';
import {useSelector} from 'react-redux';
import {App, Button, Form, Input, Modal, Tag} from 'antd';
import {Ban, Copy, Receipt, RefreshCw} from 'lucide-react';
import PropTypes from 'prop-types';
import Swal from 'sweetalert2';

import BrandModalHeader from '../../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import {apiService} from '../../../../services/api.jsx';
import {formatMoney} from '../../../utils/numberFormat.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const cleanName = (value) =>
    String(value ?? '')
        .replace(/_/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

const formatDateTime = (value) => {
    if (value == null || value === '') return null;
    const d = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatBillCancellationDate = () => {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

const buildBillId = (bill) => {
    const id = bill?.id;
    const prefix = String(bill?.source ?? '').trim();
    if (id == null || id === '') return null;
    return prefix ? `${prefix}${id}` : String(id);
};

const billStatusTag = (bill) => {
    if (bill?.is_cancelled != null && bill.is_cancelled !== false) {
        return {color: 'red', label: 'Cancelled'};
    }
    const raw = String(bill?.bill_status ?? '').trim();
    const lower = raw.toLowerCase();
    if (lower === 'paid') return {color: 'green', label: 'Paid'};
    if (lower === 'unpaid' || lower === 'pending') return {color: 'gold', label: raw || 'Unpaid'};
    if (lower === 'expired') return {color: 'volcano', label: 'Expired'};
    if (lower === 'cancelled') return {color: 'red', label: 'Cancelled'};
    return {color: 'default', label: raw || EMPTY_VALUE};
};

const ReadOnlyField = ({label, value, mono = false}) => (
    <div className="min-w-0 py-1.5">
        <span className="block text-xs font-semibold tracking-[0.01em]" style={{color: BRAND}}>
            {label}
        </span>
        <div className={`mt-0.5 text-sm font-medium text-black ${mono ? 'font-mono' : ''}`}>
            {value != null && value !== '' && value !== '—' ? (
                value
            ) : (
                <span className="font-normal text-slate-400">{EMPTY_VALUE}</span>
            )}
        </div>
    </div>
);

ReadOnlyField.propTypes = {
    label: PropTypes.string.isRequired,
    value: PropTypes.oneOfType([PropTypes.string, PropTypes.number, PropTypes.node]),
    mono: PropTypes.bool,
};

const SectionHeading = ({children}) => (
    <h4
        className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
        style={{color: BRAND}}
    >
        {children}
    </h4>
);

SectionHeading.propTypes = {children: PropTypes.node};

export default function PrepaymentBillDetailsModal({
    isOpen,
    bill = null,
    onClose,
    onActionSuccess = undefined,
}) {
    const {message} = App.useApp();
    const currentUser = useSelector((state) => state.auth.user);
    const staffUserId = currentUser?.id ?? currentUser?.user_id ?? null;
    const staffUserIdStr =
        staffUserId != null && String(staffUserId).trim() !== '' ? String(staffUserId) : null;

    const [showCancelModal, setShowCancelModal] = useState(false);
    const [cancelSubmitting, setCancelSubmitting] = useState(false);
    const [cancelForm] = Form.useForm();
    const [loadingRepost, setLoadingRepost] = useState(false);

    const anyLoading = cancelSubmitting || loadingRepost;

    const isCancelled =
        (bill?.is_cancelled != null && bill.is_cancelled !== false) ||
        String(bill?.bill_status ?? '').trim().toLowerCase() === 'cancelled';

    const summary = useMemo(() => {
        if (!bill) return null;
        return {
            controlNumber: bill.contr_num,
            amount: formatMoney(bill.bill_amount),
            billId: buildBillId(bill),
            description: bill.bill_desc,
            customer: cleanName(bill.cust_name),
            status: billStatusTag(bill),
        };
    }, [bill]);

    const copyControlNumber = async () => {
        if (!summary?.controlNumber) return;
        try {
            await navigator.clipboard.writeText(String(summary.controlNumber));
            message.success('Control number copied');
        } catch {
            message.error('Could not copy');
        }
    };

    const handleClose = () => {
        if (anyLoading) return;
        setShowCancelModal(false);
        cancelForm.resetFields();
        onClose?.();
    };

    // The receipt PDF is served cross-origin, so it opens in a new browser window.
    const handleOpenReceipt = () => {
        if (!bill?.can_print || bill?.id == null) return;
        const resp = apiService.getPrepaymentPrintReceipt(bill.id);
        if (resp?.success && resp.data?.url) {
            window.open(resp.data.url, '_blank', 'noopener,noreferrer');
        } else {
            message.error(resp?.message || 'Could not open receipt.');
        }
    };

    const closeCancelModal = () => {
        if (cancelSubmitting) return;
        setShowCancelModal(false);
        cancelForm.resetFields();
    };

    const handleConfirmCancel = async (values) => {
        if (!bill?.id || !bill?.can_cancel) return;
        const bill_id = buildBillId(bill);
        if (!bill_id) {
            message.error('Bill source or id is missing.');
            return;
        }
        if (!staffUserIdStr) {
            message.error('Could not determine the current user.');
            return;
        }

        setCancelSubmitting(true);
        try {
            const resp = await apiService.cancelBillingBill({
                bill_id,
                bill_canc_date: formatBillCancellationDate(),
                bill_canc_by: staffUserIdStr,
                cancel_reason: values.cancel_reason?.trim() || '',
            });
            if (!resp?.success) {
                message.error(resp?.message || 'Could not cancel bill.');
                return;
            }
            message.success(resp?.message || 'Bill cancelled.');
            setShowCancelModal(false);
            cancelForm.resetFields();
            onActionSuccess?.();
            onClose?.();
        } catch (e) {
            message.error(e?.message || 'An error occurred while cancelling the bill.');
        } finally {
            setCancelSubmitting(false);
        }
    };

    const handleRepost = async () => {
        if (!bill?.can_repost || bill?.id == null) return;
        const confirm = await Swal.fire({
            title: 'Repost bill?',
            text: 'This will attempt to repost the bill. Continue?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, repost',
            cancelButtonText: 'Back',
            confirmButtonColor: BRAND,
        });
        if (!confirm.isConfirmed) return;

        setLoadingRepost(true);
        try {
            const resp = await apiService.repostTopUpBill(bill.id);
            if (!resp?.success) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: resp?.message || 'Could not repost bill.',
                });
                return;
            }
            await Swal.fire({
                icon: 'success',
                title: 'Done',
                text: resp?.message || 'Bill reposted.',
                timer: 1600,
                showConfirmButton: false,
            });
            onActionSuccess?.();
            onClose?.();
        } catch (e) {
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: e?.message || 'An error occurred while reposting the bill.',
            });
        } finally {
            setLoadingRepost(false);
        }
    };

    return (
        <>
            <Modal
                open={isOpen && !!bill}
                onCancel={handleClose}
                footer={null}
                width={680}
                centered
                destroyOnHidden
                title={null}
                closable={false}
                maskClosable={!anyLoading}
                keyboard={!anyLoading}
                className="brand-modal"
                styles={{body: {padding: 0}, content: {padding: 0, overflow: 'hidden'}}}
            >
                <BrandModalHeader title="Prepayment Bill" onClose={handleClose} />

                {bill && summary && (
                    <>
                        <div className="max-h-[70vh] overflow-y-auto px-6 py-5">
                            <div className="mb-5 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <span
                                            className="text-[11px] font-semibold uppercase tracking-[0.06em]"
                                            style={{color: BRAND}}
                                        >
                                            Control Number
                                        </span>
                                        <div className="mt-0.5 flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-sm font-semibold text-black">
                                                {summary.controlNumber || EMPTY_VALUE}
                                            </span>
                                            {summary.controlNumber && (
                                                <Button
                                                    size="small"
                                                    icon={<Copy size={12} />}
                                                    onClick={copyControlNumber}
                                                >
                                                    Copy
                                                </Button>
                                            )}
                                            <Tag color={summary.status.color} className="!m-0 text-xs">
                                                {summary.status.label}
                                            </Tag>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <ReadOnlyField label="Bill Amount" value={summary.amount} mono />
                                        <ReadOnlyField label="Bill ID" value={summary.billId} mono />
                                    </div>
                                </div>
                                {summary.description && (
                                    <p className="mt-3 border-t border-slate-200 pt-2 text-sm text-slate-700">
                                        {summary.description}
                                    </p>
                                )}
                            </div>

                            <SectionHeading>Account &amp; Customer</SectionHeading>
                            <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                <ReadOnlyField label="Account No" value={bill.account_no} mono />
                                <ReadOnlyField label="Customer" value={summary.customer} />
                                <ReadOnlyField label="PSP Receipt" value={bill.psp_receipt_num} mono />
                                <ReadOnlyField label="Transaction Status" value={bill.t_status} />
                            </div>

                            <SectionHeading>Bill Details</SectionHeading>
                            <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                <ReadOnlyField label="Bill Status" value={summary.status.label} />
                                <ReadOnlyField label="Source" value={bill.source} />
                                <ReadOnlyField label="Description" value={bill.bill_desc} />
                            </div>

                            <SectionHeading>Timeline</SectionHeading>
                            <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                <ReadOnlyField label="Expires" value={formatDateTime(bill.bill_exp_dt)} />
                                <ReadOnlyField
                                    label="Transaction Date"
                                    value={formatDateTime(bill.trx_dt_tm)}
                                />
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
                            <Button
                                danger
                                icon={<Ban size={14} />}
                                disabled={!bill.can_cancel || anyLoading}
                                onClick={() => {
                                    cancelForm.resetFields();
                                    setShowCancelModal(true);
                                }}
                                className="!border-red-300 !text-red-700 hover:!bg-red-50"
                            >
                                Cancel Bill
                            </Button>
                            {!isCancelled && (
                                <Button
                                    icon={<Receipt size={14} />}
                                    disabled={!bill.can_print || anyLoading}
                                    onClick={handleOpenReceipt}
                                    style={{borderColor: BRAND, color: BRAND}}
                                    className="hover:!bg-[#fff5f5]"
                                >
                                    Receipt
                                </Button>
                            )}
                            <Button
                                icon={<RefreshCw size={14} />}
                                disabled={!bill.can_repost || anyLoading}
                                loading={loadingRepost}
                                onClick={handleRepost}
                                type="primary"
                                style={{backgroundColor: BRAND, borderColor: BRAND}}
                                onMouseEnter={(e) => {
                                    if (!e.currentTarget.disabled) {
                                        e.currentTarget.style.backgroundColor = BRAND_DARK;
                                        e.currentTarget.style.borderColor = BRAND_DARK;
                                    }
                                }}
                                onMouseLeave={(e) => {
                                    if (!e.currentTarget.disabled) {
                                        e.currentTarget.style.backgroundColor = BRAND;
                                        e.currentTarget.style.borderColor = BRAND;
                                    }
                                }}
                            >
                                Repost
                            </Button>
                            <Button onClick={handleClose} disabled={anyLoading}>
                                Close
                            </Button>
                        </div>
                    </>
                )}
            </Modal>

            <Modal
                open={showCancelModal}
                onCancel={closeCancelModal}
                footer={null}
                width={480}
                centered
                destroyOnHidden
                title={null}
                closable={false}
                maskClosable={!cancelSubmitting}
                keyboard={!cancelSubmitting}
                className="brand-modal"
                styles={{body: {padding: 0}, content: {padding: 0, overflow: 'hidden'}}}
                zIndex={1100}
            >
                <BrandModalHeader title="Cancel Bill" onClose={closeCancelModal} />
                <Form form={cancelForm} layout="vertical" onFinish={handleConfirmCancel} requiredMark={false}>
                    <div className="px-6 py-5">
                        <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
                            <p className="text-sm text-amber-900">
                                This will cancel the prepayment bill. Continue?
                            </p>
                        </div>
                        <Form.Item
                            name="cancel_reason"
                            label={
                                <span
                                    className="text-xs font-semibold tracking-[0.01em]"
                                    style={{color: BRAND}}
                                >
                                    Cancellation Reason
                                </span>
                            }
                        >
                            <Input.TextArea
                                rows={3}
                                placeholder="Enter reason for cancellation (optional)"
                                disabled={cancelSubmitting}
                                className="!rounded-lg"
                            />
                        </Form.Item>
                    </div>
                    <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
                        <Button
                            type="primary"
                            htmlType="submit"
                            loading={cancelSubmitting}
                            style={{backgroundColor: BRAND, borderColor: BRAND}}
                            onMouseEnter={(e) => {
                                e.currentTarget.style.backgroundColor = BRAND_DARK;
                                e.currentTarget.style.borderColor = BRAND_DARK;
                            }}
                            onMouseLeave={(e) => {
                                e.currentTarget.style.backgroundColor = BRAND;
                                e.currentTarget.style.borderColor = BRAND;
                            }}
                        >
                            Yes, cancel
                        </Button>
                        <Button onClick={closeCancelModal} disabled={cancelSubmitting}>
                            Close
                        </Button>
                    </div>
                </Form>
            </Modal>
        </>
    );
}

PrepaymentBillDetailsModal.propTypes = {
    isOpen: PropTypes.bool.isRequired,
    bill: PropTypes.object,
    onClose: PropTypes.func.isRequired,
    onActionSuccess: PropTypes.func,
};
