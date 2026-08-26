import {useMemo, useState} from 'react';
import {useSelector} from 'react-redux';
import {App, Button, Form, Input, Modal, Tag} from 'antd';
import {Ban, Copy, FileText, Receipt} from 'lucide-react';
import PropTypes from 'prop-types';

import BrandModalHeader from '../../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import AdvertBillOrderFormModal from './AdvertBillOrderFormModal.jsx';
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

// Advert bills prefix the id with the `source` column, defaulting to `ADV`.
const buildBillId = (bill) => {
    const id = bill?.id;
    if (id == null || id === '') return null;
    const prefix = String(bill?.source ?? '').trim() || 'ADV';
    return `${prefix}${id}`;
};

const billStatusTag = (bill) => {
    const receipt = String(bill?.psp_receipt_num ?? bill?.receipt_number ?? '').trim();
    const hasReceipt =
        receipt &&
        receipt !== '-' &&
        !receipt.toUpperCase().startsWith('CANC') &&
        !receipt.toUpperCase().startsWith('BILL FAILED') &&
        !receipt.toUpperCase().startsWith('BILL EXPIRED');
    const raw = String(bill?.bill_status ?? bill?.status ?? '').trim().toUpperCase();
    if (hasReceipt || raw === 'PAID') return {color: 'green', label: 'PAID'};
    if (Number(bill?.is_cancelled) === 1 || raw === 'CANCELLED') return {color: 'red', label: 'Cancelled'};
    if (raw === 'UNPAID' || raw === 'PENDING' || raw === '1') return {color: 'gold', label: 'Pending'};
    if (raw === 'EXPIRED') return {color: 'volcano', label: 'Expired'};
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

export default function AdvertBillDetailsModal({
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
    const [orderFormOpen, setOrderFormOpen] = useState(false);
    const [orderFormBill, setOrderFormBill] = useState(null);
    const [loadingOrderForm, setLoadingOrderForm] = useState(false);

    const anyLoading = cancelSubmitting || loadingOrderForm;

    const isCancelled = Number(bill?.is_cancelled) === 1
        || String(bill?.bill_status ?? bill?.status ?? '').trim().toLowerCase() === 'cancelled';

    const status = useMemo(
        () => String(bill?.bill_status ?? bill?.status ?? '').trim().toUpperCase(),
        [bill]
    );
    const canCancel = !isCancelled && status !== 'PAID';
    const canPrintReceipt = !isCancelled && status === 'PAID';

    const summary = useMemo(() => {
        if (!bill) return null;
        return {
            controlNumber: bill.contr_num ?? bill.control_number ?? bill.control_num,
            amount: formatMoney(bill.bill_amount ?? bill.amount ?? bill.amount_collected),
            billId: bill.id,
            description: bill.bill_desc,
            customer: cleanName(bill.payer_name ?? bill.cust_name ?? bill.customer ?? bill.customer_name),
            receipt: bill.psp_receipt_num ?? bill.receipt_number ?? bill.receipt,
            phone: bill.phone_number ?? bill.payer_phone,
            advertType: bill.advertisement_type ?? bill.dist_param,
            billDate: formatDateTime(bill.bill_generated_at ?? bill.bill_gen_at),
            expiry: formatDateTime(bill.bill_expiry_at ?? bill.bill_exp_dt),
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
        if (!canPrintReceipt || bill?.id == null) return;
        const resp = apiService.getAdvertBillPrintReceipt(bill.id);
        if (resp?.success && resp.data?.url) {
            window.open(resp.data.url, '_blank', 'noopener,noreferrer');
        } else {
            message.error(resp?.message || 'Could not open receipt.');
        }
    };

    const handleOpenOrderForm = async () => {
        if (bill?.id == null) return;
        setOrderFormBill(bill);
        setOrderFormOpen(true);
        setLoadingOrderForm(true);
        try {
            const resp = await apiService.getAdvertOrderForm({id: bill.id});
            if (!resp?.success) {
                message.error(resp?.message || 'Failed to fetch order form');
                setOrderFormOpen(false);
                setOrderFormBill(null);
                return;
            }
            setOrderFormBill({...bill, ...(resp.data || {})});
        } catch (e) {
            message.error(e?.message || 'Failed to fetch order form');
            setOrderFormOpen(false);
            setOrderFormBill(null);
        } finally {
            setLoadingOrderForm(false);
        }
    };

    const closeOrderForm = () => {
        if (loadingOrderForm) return;
        setOrderFormOpen(false);
        setOrderFormBill(null);
    };

    const closeCancelModal = () => {
        if (cancelSubmitting) return;
        setShowCancelModal(false);
        cancelForm.resetFields();
    };

    const handleConfirmCancel = async (values) => {
        if (!bill?.id || !canCancel) return;
        const bill_id = buildBillId(bill);
        if (!bill_id) {
            message.error('Bill id is missing.');
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
                message.error(resp?.message || 'Failed to cancel bill');
                return;
            }
            message.success(resp?.message || 'Bill cancelled');
            setShowCancelModal(false);
            cancelForm.resetFields();
            onActionSuccess?.();
            onClose?.();
        } catch (e) {
            message.error(e?.message || 'Failed to cancel bill');
        } finally {
            setCancelSubmitting(false);
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
                <BrandModalHeader title="Advertisement Bill" onClose={handleClose} />

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

                            <SectionHeading>Customer &amp; Payment</SectionHeading>
                            <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                <ReadOnlyField label="Customer" value={summary.customer} />
                                <ReadOnlyField label="Phone Number" value={summary.phone} mono />
                                <ReadOnlyField label="PSP Receipt" value={summary.receipt} mono />
                                <ReadOnlyField label="Bill Status" value={summary.status.label} />
                            </div>

                            <SectionHeading>Advertisement Details</SectionHeading>
                            <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                <ReadOnlyField label="Advertisement Type" value={summary.advertType} />
                                <ReadOnlyField label="Bill Amount" value={summary.amount} mono />
                                <ReadOnlyField label="Description" value={summary.description} />
                            </div>

                            <SectionHeading>Timeline</SectionHeading>
                            <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                <ReadOnlyField label="Bill Date" value={summary.billDate} />
                                <ReadOnlyField label="Expiry Date" value={summary.expiry} />
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
                            {canCancel && (
                                <Button
                                    danger
                                    icon={<Ban size={14} />}
                                    disabled={anyLoading}
                                    onClick={() => {
                                        cancelForm.resetFields();
                                        setShowCancelModal(true);
                                    }}
                                    className="!border-red-300 !text-red-700 hover:!bg-red-50"
                                >
                                    Cancel Bill
                                </Button>
                            )}
                            {canPrintReceipt && (
                                <Button
                                    icon={<Receipt size={14} />}
                                    disabled={anyLoading}
                                    onClick={handleOpenReceipt}
                                    style={{borderColor: BRAND, color: BRAND}}
                                    className="hover:!bg-[#fff5f5]"
                                >
                                    Receipt
                                </Button>
                            )}
                            <Button
                                icon={<FileText size={14} />}
                                disabled={bill?.id == null || anyLoading}
                                loading={loadingOrderForm}
                                onClick={handleOpenOrderForm}
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
                                Order Form
                            </Button>
                            <Button onClick={handleClose} disabled={anyLoading}>
                                Close
                            </Button>
                        </div>
                    </>
                )}
            </Modal>

            <AdvertBillOrderFormModal
                open={orderFormOpen}
                bill={orderFormBill}
                loading={loadingOrderForm}
                onClose={closeOrderForm}
            />

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
                                This will cancel the advert bill. Continue?
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
                            rules={[
                                {required: true, message: 'Please provide a cancellation reason'},
                                {whitespace: true, message: 'Please provide a cancellation reason'},
                            ]}
                        >
                            <Input.TextArea
                                rows={3}
                                placeholder="Enter reason for cancellation"
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

AdvertBillDetailsModal.propTypes = {
    isOpen: PropTypes.bool.isRequired,
    bill: PropTypes.object,
    onClose: PropTypes.func.isRequired,
    onActionSuccess: PropTypes.func,
};
