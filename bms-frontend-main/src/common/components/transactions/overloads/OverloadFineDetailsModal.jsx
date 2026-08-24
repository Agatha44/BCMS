import {useEffect, useMemo, useState} from 'react';
import {useSelector} from 'react-redux';
import {App, Button, Form, Input, InputNumber, Modal, Tag} from 'antd';
import {Ban, Copy, Pencil, Printer, RotateCcw, Save} from 'lucide-react';
import PropTypes from 'prop-types';
import Swal from 'sweetalert2';

import BrandModalHeader from '../../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import {apiService} from '../../../../services/api.jsx';
import {overloadFineService} from '../../../../services/overloadFineService.js';
import {API_CONFIG} from '../../../../config/api.jsx';
import {formatMoney} from '../../../utils/numberFormat.js';
import {
    getOverloadStatus,
    isExpiredOverloadBill,
} from '../../../../pages/CollectionManagement/transactions/overloadFine.status.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';
const OVERLOAD_BILL_PREFIX_FALLBACK = 'OLF';

const cleanName = (value) =>
    String(value ?? '')
        .replace(/_/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

const formatBillCancellationDate = () => {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

const buildBillId = (o) => {
    const id = o?.id;
    if (id == null || id === '') return null;
    const prefix = String(o?.source ?? '').trim() || OVERLOAD_BILL_PREFIX_FALLBACK;
    return `${prefix}${id}`;
};

const buildOwnerName = (o) =>
    [o?.first_name, o?.middle_name, o?.surname].map(cleanName).filter(Boolean).join(' ');

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

const fieldLabel = (text) => (
    <span className="text-xs font-semibold tracking-[0.01em]" style={{color: BRAND}}>
        {text}
    </span>
);

const mapOverloadToForm = (o = {}) => ({
    first_name: o.first_name || '',
    middle_name: o.middle_name || '',
    surname: o.surname || '',
    pyr_cell_num: o.pyr_cell_num || '',
    pyr_email: o.pyr_email || '',
    tin_number: o.tin_number || '',
    bill_amount: o.bill_amount ?? 0,
    ticket_num: o.ticket_num || '',
    vehicle_num: o.vehicle_num ? String(o.vehicle_num).trim().toUpperCase() : '',
    bill_desc: o.bill_desc || '',
});

export default function OverloadFineDetailsModal({
    isOpen,
    overload = null,
    onClose,
    onActionSuccess = undefined,
}) {
    const {message} = App.useApp();
    const currentUser = useSelector((state) => state.auth.user);
    const staffUserId = currentUser?.id ?? currentUser?.user_id ?? null;
    const staffUserIdStr =
        staffUserId != null && String(staffUserId).trim() !== '' ? String(staffUserId) : null;

    const [mode, setMode] = useState('view');
    const [editSubmitting, setEditSubmitting] = useState(false);
    const [cancelSubmitting, setCancelSubmitting] = useState(false);
    const [repostSubmitting, setRepostSubmitting] = useState(false);
    const [showCancelModal, setShowCancelModal] = useState(false);
    const [editForm] = Form.useForm();
    const [cancelForm] = Form.useForm();

    const anyLoading = editSubmitting || cancelSubmitting || repostSubmitting;

    useEffect(() => {
        if (isOpen) {
            setMode('view');
            setShowCancelModal(false);
        }
    }, [isOpen, overload]);

    useEffect(() => {
        if (mode === 'edit' && overload) {
            editForm.setFieldsValue(mapOverloadToForm(overload));
        }
    }, [mode, overload, editForm]);

    const isCancelled = overload?.is_cancelled === 1 || overload?.is_cancelled === true;
    const isPaid = !!overload?.psp_receipt_num;
    const isExpired = isExpiredOverloadBill(overload);

    const canEdit = !!overload && !isCancelled && !isPaid && !isExpired;
    const canCancel = !!overload && !isCancelled && !isPaid;
    const canRepost = !!overload && overload?.t_status === 'GF' && !isCancelled;
    const canReceipt = !!overload?.pay_ref_id;

    const summary = useMemo(() => {
        if (!overload) return null;
        return {
            controlNumber: overload.contr_num,
            amount: formatMoney(overload.bill_amount),
            billId: overload.id,
            owner: buildOwnerName(overload),
            phone: overload.pyr_cell_num,
            email: overload.pyr_email,
            tin: overload.tin_number,
            vehicle: overload.vehicle_num,
            ticket: overload.ticket_num,
            description: overload.bill_desc,
            receipt: overload.psp_receipt_num,
            pspName: overload.psp_name,
            payRef: overload.pay_ref_id,
            payChannel: overload.usd_pay_chn,
            billDate: formatDateTime(overload.bill_gen_at ?? overload.created_at),
            expiry: formatDateTime(overload.bill_exp_dt),
            transactionDate: formatDateTime(overload.trx_dt_tm),
            cancelDate: formatDateTime(overload.bill_cancel_date),
            cancelReason: overload.cancel_reason,
            status: getOverloadStatus(overload),
        };
    }, [overload]);

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
        setMode('view');
        setShowCancelModal(false);
        cancelForm.resetFields();
        onClose?.();
    };

    const handleOpenReceipt = () => {
        if (!canReceipt || overload?.id == null) return;
        const url = `${API_CONFIG.BASE_URL}/printer/print-receipt?id=${encodeURIComponent(overload.id)}`;
        window.open(url, '_blank', 'noopener,noreferrer');
    };

    const handleSaveEdit = async (values) => {
        if (!overload?.id || !canEdit) return;
        setEditSubmitting(true);
        try {
            await overloadFineService.update({
                id: overload.id,
                first_name: values.first_name.trim(),
                middle_name: values.middle_name?.trim() || '',
                surname: values.surname.trim(),
                pyr_cell_num: values.pyr_cell_num.trim(),
                pyr_email: values.pyr_email?.trim() || '',
                tin_number: values.tin_number?.trim() || '',
                bill_amount: Number(values.bill_amount),
                ticket_num: values.ticket_num?.trim() || '',
                vehicle_num: String(values.vehicle_num || '').trim().toUpperCase(),
                bill_desc: values.bill_desc?.trim() || '',
            });
            message.success('Overload fine updated');
            setMode('view');
            onActionSuccess?.();
            onClose?.();
        } catch (e) {
            await Swal.fire({icon: 'error', title: 'Failed', text: e?.message || 'Failed to update overload fine'});
        } finally {
            setEditSubmitting(false);
        }
    };

    const handleRepost = async () => {
        if (!canRepost || overload?.id == null) return;
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

        setRepostSubmitting(true);
        try {
            await overloadFineService.repostBill(overload.id);
            await Swal.fire({
                icon: 'success',
                title: 'Done',
                text: 'Bill reposted.',
                timer: 1600,
                showConfirmButton: false,
            });
            onActionSuccess?.();
            onClose?.();
        } catch (e) {
            await Swal.fire({icon: 'error', title: 'Error', text: e?.message || 'Failed to repost bill.'});
        } finally {
            setRepostSubmitting(false);
        }
    };

    const closeCancelModal = () => {
        if (cancelSubmitting) return;
        setShowCancelModal(false);
        cancelForm.resetFields();
    };

    const handleConfirmCancel = async (values) => {
        if (!overload?.id || !canCancel) return;
        const bill_id = buildBillId(overload);
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
                cancel_reason: values.cancel_reason?.trim() || 'Cancelled from BMS',
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

    const brandButtonHandlers = {
        onMouseEnter: (e) => {
            if (!e.currentTarget.disabled) {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
                e.currentTarget.style.borderColor = BRAND_DARK;
            }
        },
        onMouseLeave: (e) => {
            if (!e.currentTarget.disabled) {
                e.currentTarget.style.backgroundColor = BRAND;
                e.currentTarget.style.borderColor = BRAND;
            }
        },
    };

    return (
        <>
            <Modal
                open={isOpen && !!overload}
                onCancel={handleClose}
                footer={null}
                width={720}
                centered
                destroyOnHidden
                title={null}
                closable={false}
                maskClosable={!anyLoading}
                keyboard={!anyLoading}
                className="brand-modal"
                styles={{body: {padding: 0}, content: {padding: 0, overflow: 'hidden'}}}
            >
                <BrandModalHeader
                    title={mode === 'edit' ? 'Edit Overload Fine' : 'Overload Fine'}
                    onClose={handleClose}
                />

                {overload && summary && mode === 'view' && (
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

                            <SectionHeading>Owner &amp; Contact</SectionHeading>
                            <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                <ReadOnlyField label="Owner Name" value={summary.owner} />
                                <ReadOnlyField label="Phone Number" value={summary.phone} mono />
                                <ReadOnlyField label="Email" value={summary.email} />
                                <ReadOnlyField label="TIN Number" value={summary.tin} mono />
                            </div>

                            <SectionHeading>Vehicle &amp; Charge</SectionHeading>
                            <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                <ReadOnlyField label="Vehicle Number" value={summary.vehicle} mono />
                                <ReadOnlyField label="Ticket Number" value={summary.ticket} mono />
                                <ReadOnlyField label="Charge Amount" value={summary.amount} mono />
                                <ReadOnlyField label="Description" value={summary.description} />
                            </div>

                            <SectionHeading>Transaction</SectionHeading>
                            <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                <ReadOnlyField label="PSP Receipt" value={summary.receipt} mono />
                                <ReadOnlyField label="PSP Name" value={summary.pspName} />
                                <ReadOnlyField label="Payment Reference" value={summary.payRef} mono />
                                <ReadOnlyField label="Payment Channel" value={summary.payChannel} />
                                <ReadOnlyField label="Bill Status" value={summary.status.label} />
                            </div>

                            <SectionHeading>Timeline</SectionHeading>
                            <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                <ReadOnlyField label="Bill Date" value={summary.billDate} />
                                <ReadOnlyField label="Expiry Date" value={summary.expiry} />
                                <ReadOnlyField label="Transaction Date" value={summary.transactionDate} />
                                {isCancelled && (
                                    <ReadOnlyField label="Cancelled On" value={summary.cancelDate} />
                                )}
                            </div>

                            {isCancelled && summary.cancelReason && (
                                <div className="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3">
                                    <span
                                        className="block text-xs font-semibold tracking-[0.01em]"
                                        style={{color: BRAND}}
                                    >
                                        Cancellation Reason
                                    </span>
                                    <p className="mt-0.5 text-sm text-slate-700">{summary.cancelReason}</p>
                                </div>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
                            {canEdit && (
                                <Button
                                    icon={<Pencil size={14} />}
                                    disabled={anyLoading}
                                    onClick={() => setMode('edit')}
                                    style={{borderColor: BRAND, color: BRAND}}
                                    className="hover:!bg-[#fff5f5]"
                                >
                                    Edit
                                </Button>
                            )}
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
                            {canRepost && (
                                <Button
                                    icon={<RotateCcw size={14} />}
                                    disabled={anyLoading}
                                    loading={repostSubmitting}
                                    onClick={handleRepost}
                                    type="primary"
                                    style={{backgroundColor: BRAND, borderColor: BRAND}}
                                    {...brandButtonHandlers}
                                >
                                    Repost
                                </Button>
                            )}
                            {canReceipt && (
                                <Button
                                    icon={<Printer size={14} />}
                                    disabled={anyLoading}
                                    onClick={handleOpenReceipt}
                                    type="primary"
                                    style={{backgroundColor: BRAND, borderColor: BRAND}}
                                    {...brandButtonHandlers}
                                >
                                    Receipt
                                </Button>
                            )}
                            <Button onClick={handleClose} disabled={anyLoading}>
                                Close
                            </Button>
                        </div>
                    </>
                )}

                {overload && mode === 'edit' && (
                    <Form form={editForm} layout="vertical" onFinish={handleSaveEdit} requiredMark={false}>
                        <div className="max-h-[70vh] overflow-y-auto px-6 py-5">
                            <SectionHeading>Charge Information</SectionHeading>
                            <div className="grid grid-cols-1 gap-x-5 md:grid-cols-2">
                                <Form.Item
                                    name="first_name"
                                    label={fieldLabel('First Name')}
                                    rules={[{required: true, message: 'First name is required'}]}
                                >
                                    <Input size="large" placeholder="First name" disabled={editSubmitting} />
                                </Form.Item>

                                <Form.Item name="middle_name" label={fieldLabel('Middle Name')}>
                                    <Input size="large" placeholder="Middle name" disabled={editSubmitting} />
                                </Form.Item>

                                <Form.Item
                                    name="surname"
                                    label={fieldLabel('Surname')}
                                    rules={[{required: true, message: 'Surname is required'}]}
                                >
                                    <Input size="large" placeholder="Surname" disabled={editSubmitting} />
                                </Form.Item>

                                <Form.Item
                                    name="pyr_cell_num"
                                    label={fieldLabel('Phone Number')}
                                    rules={[{required: true, message: 'Phone number is required'}]}
                                >
                                    <Input size="large" placeholder="255712345678" disabled={editSubmitting} />
                                </Form.Item>

                                <Form.Item
                                    name="pyr_email"
                                    label={fieldLabel('Email')}
                                    rules={[{type: 'email', message: 'Enter a valid email address'}]}
                                >
                                    <Input size="large" placeholder="customer@example.com" disabled={editSubmitting} />
                                </Form.Item>

                                <Form.Item name="tin_number" label={fieldLabel('TIN Number')}>
                                    <Input size="large" placeholder="TIN number" disabled={editSubmitting} />
                                </Form.Item>

                                <Form.Item
                                    name="bill_amount"
                                    label={fieldLabel('Charge Amount')}
                                    rules={[
                                        {required: true, message: 'Charge amount is required'},
                                        {type: 'number', min: 0, message: 'Amount must be zero or greater'},
                                    ]}
                                >
                                    <InputNumber
                                        size="large"
                                        min={0}
                                        step={1000}
                                        style={{width: '100%'}}
                                        placeholder="0"
                                        disabled={editSubmitting}
                                        formatter={(value) => `${value ?? ''}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                                        parser={(value) => (value || '').replace(/[^\d.]/g, '')}
                                    />
                                </Form.Item>

                                <Form.Item name="ticket_num" label={fieldLabel('Ticket Number')}>
                                    <Input size="large" placeholder="Ticket number" disabled={editSubmitting} />
                                </Form.Item>

                                <Form.Item
                                    name="vehicle_num"
                                    label={fieldLabel('Vehicle Number')}
                                    rules={[{required: true, message: 'Vehicle number is required'}]}
                                    normalize={(value) => (value ? String(value).toUpperCase() : value)}
                                    className="md:col-span-2"
                                >
                                    <Input
                                        size="large"
                                        placeholder="Plate number"
                                        disabled={editSubmitting}
                                        className="font-mono uppercase"
                                    />
                                </Form.Item>

                                <Form.Item
                                    name="bill_desc"
                                    label={fieldLabel('Charge Description')}
                                    className="md:col-span-2"
                                >
                                    <Input.TextArea
                                        rows={3}
                                        placeholder="Describe the overload charge"
                                        disabled={editSubmitting}
                                    />
                                </Form.Item>
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
                            <Button
                                type="primary"
                                htmlType="submit"
                                loading={editSubmitting}
                                icon={<Save size={14} />}
                                style={{backgroundColor: BRAND, borderColor: BRAND}}
                                {...brandButtonHandlers}
                            >
                                Update
                            </Button>
                            <Button onClick={() => setMode('view')} disabled={editSubmitting}>
                                Back
                            </Button>
                            <Button onClick={handleClose} disabled={editSubmitting}>
                                Close
                            </Button>
                        </div>
                    </Form>
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
                                This will cancel the overload fine bill. Continue?
                            </p>
                        </div>
                        <Form.Item
                            name="cancel_reason"
                            label={fieldLabel('Cancellation Reason')}
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
                            {...brandButtonHandlers}
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

OverloadFineDetailsModal.propTypes = {
    isOpen: PropTypes.bool.isRequired,
    overload: PropTypes.object,
    onClose: PropTypes.func.isRequired,
    onActionSuccess: PropTypes.func,
};
