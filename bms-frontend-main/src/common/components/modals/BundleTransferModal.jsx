import {useEffect, useMemo, useState} from 'react';
import {Save} from 'lucide-react';
import AsyncSelect from 'react-select/async';
import {Button, Form, Input, Modal} from 'antd';
import Swal from 'sweetalert2';

import BrandModalHeader from '../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import {apiService} from '../../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const formatDate = (value) => {
    if (!value) return null;
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

const ReadOnlyField = ({label, value}) => (
    <div className="min-w-0 py-1.5">
        <span
            className="block text-xs font-semibold tracking-[0.01em]"
            style={{color: BRAND}}
        >
            {label}
        </span>
        <div className="mt-0.5 text-sm font-medium text-black">
            {value != null && value !== '' && value !== '-' ? (
                value
            ) : (
                <span className="text-slate-400">{EMPTY_VALUE}</span>
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

const asyncSelectStyles = {
    control: (base, state) => ({
        ...base,
        borderColor: state.isFocused ? BRAND : '#e2e8f0',
        borderRadius: '8px',
        minHeight: '40px',
        boxShadow: state.isFocused ? '0 0 0 3px rgba(150, 46, 50, 0.12)' : 'none',
        '&:hover': {borderColor: BRAND},
    }),
    menuPortal: (base) => ({...base, zIndex: 9999}),
    menu: (base) => ({...base, zIndex: 9999}),
};

export default function BundleTransferModal({isOpen, onClose, onSubmit, onSuccess, subscription}) {
    const [form] = Form.useForm();
    const [selectedVehicle, setSelectedVehicle] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const viewModel = useMemo(() => {
        const row = subscription || {};
        return {
            customer: row.customer || row.customer_name || row.customer_fullname || null,
            accountNo: row.account_no || null,
            plateNo: row.plate_no || null,
            bundleName:
                row.bundle ||
                row.bundle_name ||
                row.bundle_type ||
                row.bundle_description ||
                null,
            startDate: formatDate(row.start_date || row.start_time),
            expireDate: formatDate(row.expire_date || row.end_date || row.expire_time),
        };
    }, [subscription]);

    useEffect(() => {
        if (!isOpen) return;
        form.resetFields();
        setSelectedVehicle(null);
    }, [isOpen, subscription, form]);

    const handleClose = () => {
        if (submitting) return;
        form.resetFields();
        setSelectedVehicle(null);
        onClose?.();
    };

    const handleSubmit = async (values) => {
        if (!selectedVehicle?.value) {
            await Swal.fire({
                icon: 'warning',
                title: 'Vehicle required',
                text: 'Please select a new vehicle.',
            });
            return;
        }

        const payload = {
            id: subscription?.id,
            plate_no: subscription?.plate_no,
            new_plate_no: selectedVehicle.value,
            reason: values.reason?.trim(),
        };

        setSubmitting(true);
        try {
            if (onSubmit) {
                await onSubmit({...payload, subscription});
            } else {
                const response = await apiService.transferBundleSubscription(payload);
                if (!response?.success) {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: response?.message || 'Could not transfer bundle subscription.',
                    });
                    return;
                }
                await Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: response?.message || 'Bundle subscription transferred successfully.',
                    timer: 1600,
                    showConfirmButton: false,
                });
            }
            onSuccess?.();
            handleClose();
        } catch (error) {
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error?.message || 'An error occurred while transferring the bundle subscription.',
            });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Modal
            open={isOpen}
            onCancel={handleClose}
            footer={null}
            width={640}
            centered
            destroyOnHidden
            title={null}
            closable={false}
            maskClosable={!submitting}
            keyboard={!submitting}
            className="brand-modal"
            styles={{body: {padding: 0}, content: {padding: 0, overflow: 'hidden'}}}
        >
            <BrandModalHeader title="Edit Bundle Subscription" onClose={handleClose} />

            <Form
                form={form}
                layout="vertical"
                onFinish={handleSubmit}
                requiredMark={false}
                className="flex flex-col"
            >
                <div className="max-h-[70vh] overflow-y-auto px-6 py-5">
                    <SectionHeading>Current Subscription</SectionHeading>
                    <div className="mb-5 grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
                        <ReadOnlyField label="Customer" value={viewModel.customer} />
                        <ReadOnlyField label="Account No" value={viewModel.accountNo} />
                        <ReadOnlyField label="Plate Number" value={viewModel.plateNo} />
                        <ReadOnlyField label="Bundle" value={viewModel.bundleName} />
                        <ReadOnlyField label="Start Date" value={viewModel.startDate} />
                        <ReadOnlyField label="Expire Date" value={viewModel.expireDate} />
                    </div>

                    <SectionHeading>Transfer Details</SectionHeading>
                    <div className="space-y-4">
                        <div>
                            <span
                                className="mb-1.5 block text-xs font-semibold tracking-[0.01em]"
                                style={{color: BRAND}}
                            >
                                Select New Vehicle
                            </span>
                            <AsyncSelect
                                cacheOptions
                                defaultOptions={false}
                                loadOptions={async (inputValue) => {
                                    const search = (inputValue || '').trim();
                                    if (search.length < 2) return [];
                                    try {
                                        const response = await apiService.searchVehicles({
                                            search,
                                            page: 1,
                                            per_page: 20,
                                        });
                                        const items = response.data?.vehicles || response.data || [];
                                        if (!Array.isArray(items)) return [];
                                        return items.map((vehicle) => ({
                                            value: vehicle.plate_no,
                                            label: vehicle.plate_no || EMPTY_VALUE,
                                            vehicle,
                                        }));
                                    } catch {
                                        return [];
                                    }
                                }}
                                value={selectedVehicle}
                                onChange={(option) => setSelectedVehicle(option || null)}
                                placeholder="Type at least 2 characters to search..."
                                isClearable
                                isDisabled={submitting}
                                menuPortalTarget={
                                    typeof document !== 'undefined' ? document.body : null
                                }
                                styles={asyncSelectStyles}
                            />
                        </div>

                        <Form.Item
                            name="reason"
                            label={
                                <span className="text-xs font-semibold tracking-[0.01em]" style={{color: BRAND}}>
                                    Reason for Change
                                </span>
                            }
                            rules={[
                                {required: true, message: 'Please provide a reason for this change'},
                                {whitespace: true, message: 'Please provide a reason for this change'},
                            ]}
                        >
                            <Input.TextArea
                                rows={3}
                                placeholder="Describe why this subscription is being transferred"
                                disabled={submitting}
                                className="!rounded-lg"
                            />
                        </Form.Item>
                    </div>
                </div>

                <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
                    <Button
                        type="primary"
                        htmlType="submit"
                        loading={submitting}
                        icon={<Save size={14} />}
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
                        Submit
                    </Button>
                    <Button onClick={handleClose} disabled={submitting}>
                        Close
                    </Button>
                </div>
            </Form>
        </Modal>
    );
}
