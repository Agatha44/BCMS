import {useMemo, useState} from 'react';
import {useSelector} from 'react-redux';
import {App, Button, Form, Input, Modal, Tag} from 'antd';
import {Ban, Copy, FileText, RefreshCw} from 'lucide-react';
import Swal from 'sweetalert2';

import BrandModalHeader from '../../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import {apiService} from '../../../../services/api.jsx';
import BundleBillOrderFormModal from './BundleBillOrderFormModal.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const formatDateTime = (value) => {
    if (value == null || value === '') return null;
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

const ReadOnlyField = ({label, value, mono = false}) => (
    <div className="min-w-0 py-1.5">
        <span
            className="block text-xs font-semibold tracking-[0.01em]"
            style={{color: BRAND}}
        >
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

const SectionHeading = ({children}) => (
    <h4
        className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
        style={{color: BRAND}}
    >
        {children}
    </h4>
);

const formatBillCancellationDate = () => {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

const buildBillingBillId = (bill) => {
    const id = bill?.id;
    const prefix = String(bill?.source ?? '').trim();
    if (id == null || id === '' || !prefix) return null;
    return `${prefix}${id}`;
};

const billStatusTag = (bill) => {
    const raw = String(bill?.bill_status_display ?? bill?.bill_status ?? '').trim();
    const lower = raw.toLowerCase();
    if (lower === 'active' || lower === '1' || lower === 'paid') {
        return {color: 'green', label: raw === '1' ? 'Active' : raw || 'Active'};
    }
    if (lower === 'inactive' || lower === '0' || lower === 'cancelled') {
        return {color: 'red', label: raw === '0' ? 'Inactive' : raw || 'Inactive'};
    }
    if (lower === 'pending' || lower === 'unpaid') {
        return {color: 'gold', label: raw};
    }
    return {color: 'default', label: raw || EMPTY_VALUE};
};

export default function BundleBillDetailsModal({isOpen, bill, onClose, onActionSuccess}) {
    const {message} = App.useApp();
    const currentUser = useSelector((state) => state.auth.user);
    const staffUserId = currentUser?.id ?? currentUser?.user_id ?? null;
    const staffUserIdStr =
        staffUserId != null && String(staffUserId).trim() !== '' ? String(staffUserId) : null;
    const [showCancelModal, setShowCancelModal] = useState(false);
    const [cancelSubmitting, setCancelSubmitting] = useState(false);
    const [cancelForm] = Form.useForm();
    const [loadingRepost, setLoadingRepost] = useState(false);
    const [loadingOrderForm, setLoadingOrderForm] = useState(false);
    const [orderFormOpen, setOrderFormOpen] = useState(false);
    const [orderFormPayload, setOrderFormPayload] = useState(null);

    const anyLoading = cancelSubmitting || loadingRepost || loadingOrderForm;

    const summary = useMemo(() => {
        if (!bill) return null;
        const status = billStatusTag(bill);
        return {
            controlNumber: bill.control_number,
            amount: bill.amount_display || bill.bill_amount,
            description: bill.bill_description,
            status,
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

    const closeCancelModal = () => {
        if (cancelSubmitting) return;
        setShowCancelModal(false);
        cancelForm.resetFields();
    };

    const handleConfirmCancel = async (values) => {
        if (!bill?.id || !bill?.can_cancel) return;

        const bill_id = buildBillingBillId(bill);
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
            handleClose();
        } catch (e) {
            message.error(e?.message || 'An error occurred while cancelling the bill.');
        } finally {
            setCancelSubmitting(false);
        }
    };

    const handleRepost = async () => {
        if (!bill?.can_repost) return;
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
            const resp = await apiService.repostTollBundleBill(bill.id);
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
            handleClose();
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

    const handleOrderForm = async () => {
        if (!bill?.can_print) return;
        setOrderFormPayload(null);
        setOrderFormOpen(true);
        setLoadingOrderForm(true);
        try {
            const resp = await apiService.getTollBundleBillOrderForm(bill.id);
            if (resp?.success && resp.data) {
                const url = resp.data?.url ?? resp.data?.file_url;
                if (typeof url === 'string' && (url.startsWith('http') || url.startsWith('blob:'))) {
                    window.open(url, '_blank', 'noopener,noreferrer');
                    setOrderFormOpen(false);
                    return;
                }
                setOrderFormPayload(resp.data);
            }
        } catch {
            // Fall back to bill fields only (preview still opens).
        } finally {
            setLoadingOrderForm(false);
        }
    };

    const handleCloseOrderForm = () => {
        setOrderFormOpen(false);
        setOrderFormPayload(null);
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
            <BrandModalHeader title="Bundle Bill" onClose={handleClose} />

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
                                        <Tag
                                            color={summary.status.color}
                                            className="!m-0 text-xs"
                                        >
                                            {summary.status.label}
                                        </Tag>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <ReadOnlyField
                                        label="Bill Amount"
                                        value={summary.amount}
                                        mono
                                    />
                                    <ReadOnlyField
                                        label="Bill ID"
                                        value={buildBillingBillId(bill)}
                                        mono
                                    />
                                </div>
                            </div>
                            {summary.description && (
                                <p className="mt-3 border-t border-slate-200 pt-2 text-sm text-slate-700">
                                    {summary.description}
                                </p>
                            )}
                        </div>

                        <SectionHeading>Account &amp; Vehicle</SectionHeading>
                        <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                            <ReadOnlyField label="Account No" value={bill.account_no} mono />
                            <ReadOnlyField label="Customer" value={bill.customer_name} />
                            <ReadOnlyField
                                label="Plate Number"
                                value={bill.plate_no ? String(bill.plate_no).trim().toUpperCase() : bill.plate_no}
                                mono
                            />
                            <ReadOnlyField
                                label="PSP Receipt"
                                value={bill.psp_receipt_num ?? bill.receipt_number}
                                mono
                            />
                        </div>

                        <SectionHeading>Bill Details</SectionHeading>
                        <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                            <ReadOnlyField
                                label="Bill Status"
                                value={bill.bill_status_display ?? bill.bill_status}
                            />
                            <ReadOnlyField label="Description" value={bill.bill_description} />
                        </div>

                        <SectionHeading>Timeline</SectionHeading>
                        <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-3">
                            <ReadOnlyField
                                label="Generated"
                                value={formatDateTime(bill.bill_generated_at)}
                            />
                            <ReadOnlyField
                                label="Expires"
                                value={formatDateTime(bill.bill_expiry_at)}
                            />
                            <ReadOnlyField
                                label="Created"
                                value={formatDateTime(bill.created_at)}
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
                        <Button
                            icon={<FileText size={14} />}
                            disabled={!bill.can_print || anyLoading}
                            loading={loadingOrderForm}
                            onClick={handleOrderForm}
                            style={{borderColor: BRAND, color: BRAND}}
                            className="hover:!bg-[#fff5f5]"
                        >
                            Order Form
                        </Button>
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
            <Form
                form={cancelForm}
                layout="vertical"
                onFinish={handleConfirmCancel}
                requiredMark={false}
            >
                <div className="px-6 py-5">
                    <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
                        <p className="text-sm text-amber-900">
                            This will cancel the bundle bill. Continue?
                        </p>
                    </div>
                    <Form.Item
                        name="cancel_reason"
                        label={
                            <span className="text-xs font-semibold tracking-[0.01em]" style={{color: BRAND}}>
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

        <BundleBillOrderFormModal
            open={orderFormOpen}
            bill={bill}
            orderFormPayload={orderFormPayload}
            loading={loadingOrderForm}
            onClose={handleCloseOrderForm}
        />
        </>
    );
}
