import {useEffect, useState} from 'react';
import {AlertCircle, Copy, Package} from 'lucide-react';
import AsyncSelect from 'react-select/async';
import {Button, Form, Modal, Radio, Select} from 'antd';
import Swal from 'sweetalert2';

import {apiService} from '../../../../services/api.jsx';
import BrandModalHeader from './BrandModalHeader.jsx';
import CollectionLoader from '../../components/CollectionLoader.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const normalizeEligibleBundle = (bundle = {}) => ({
    ...bundle,
    amount:
        bundle.amount ??
        bundle.bundle_amount ??
        bundle.price ??
        bundle.bill_amount ??
        null,
    currency: bundle.currency || 'TZS',
});

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

const PlateAsyncSelect = ({value, onChange, disabled}) => (
    <AsyncSelect
        cacheOptions
        defaultOptions={false}
        isClearable
        isDisabled={disabled}
        placeholder="Search plate number..."
        menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
        styles={asyncSelectStyles}
        value={value ? {value, label: String(value).toUpperCase()} : null}
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
                return items.map((vehicle) => {
                    const plate = String(vehicle.plate_no || '').trim();
                    return {
                        value: plate,
                        label: plate.toUpperCase() || '—',
                    };
                });
            } catch {
                return [];
            }
        }}
        onChange={(option) => onChange(option?.value || undefined)}
    />
);

const extractControlNumber = (data = {}) =>
    data.control_number ||
    data.gepg_control_number ||
    data.api_control_number ||
    data.contr_num ||
    null;

/**
 * Request Bundle modal — same flow as Account Management account details.
 */
const RequestBundleModal = ({
    open,
    onClose,
    initialPlateNo,
    plateOptions = [],
    accountNo,
    onSuccess,
    title = 'Request Bundle',
    submitLabel = 'Request Bundle',
    showAccountSummary = true,
}) => {
    const [creatingBundle, setCreatingBundle] = useState(false);
    const [bundleForm] = Form.useForm();
    const bundlePlateNo = Form.useWatch('plate_no', bundleForm);
    const bundleTierId = Form.useWatch('bundle_id', bundleForm);
    const [eligibleInfo, setEligibleInfo] = useState(null);
    const [eligibleInfoPlate, setEligibleInfoPlate] = useState(null);
    const [loadingEligible, setLoadingEligible] = useState(false);
    const [eligibleError, setEligibleError] = useState(null);

    useEffect(() => {
        if (!open) return;
        bundleForm.resetFields();
        bundleForm.setFieldsValue({
            plate_no: initialPlateNo || undefined,
            bundle_id: 3,
        });
        setEligibleInfo(null);
        setEligibleInfoPlate(null);
        setEligibleError(null);
    }, [open, initialPlateNo, bundleForm]);

    useEffect(() => {
        if (!open || !bundlePlateNo) {
            if (!bundlePlateNo) {
                setEligibleInfo(null);
                setEligibleInfoPlate(null);
                setEligibleError(null);
            }
            return;
        }
        if (eligibleInfoPlate === bundlePlateNo) return;

        let cancelled = false;
        setLoadingEligible(true);
        setEligibleError(null);
        setEligibleInfo(null);

        apiService
            .getEligibleBundlesByPlate(bundlePlateNo)
            .then((res) => {
                if (cancelled) return;
                if (res?.success && res?.data) {
                    const normalizedData = {
                        ...res.data,
                        bundles: Array.isArray(res.data?.bundles)
                            ? res.data.bundles.map(normalizeEligibleBundle)
                            : [],
                    };
                    setEligibleInfo(normalizedData);
                    setEligibleInfoPlate(bundlePlateNo);
                    const bundles = normalizedData.bundles;
                    const currentId = bundleForm.getFieldValue('bundle_id');
                    const currentEligible = bundles.find(
                        (b) => Number(b.bundle_id) === Number(currentId) && b.eligible
                    );
                    if (!currentEligible) {
                        const firstEligible = bundles.find((b) => b.eligible);
                        if (firstEligible) {
                            bundleForm.setFieldsValue({bundle_id: firstEligible.bundle_id});
                        }
                    }
                } else {
                    setEligibleError(res?.message || 'Could not load eligible bundles');
                }
            })
            .catch((err) => {
                if (cancelled) return;
                setEligibleError(err?.message || 'Failed to load eligible bundles');
            })
            .finally(() => {
                if (!cancelled) setLoadingEligible(false);
            });

        return () => {
            cancelled = true;
        };
    }, [open, bundlePlateNo, eligibleInfoPlate, bundleForm]);

    const copyToClipboard = async (text) => {
        if (!text) return;
        try {
            await navigator.clipboard.writeText(String(text));
        } catch {
            // ignore
        }
    };

    const handleSubmitBundle = async (values) => {
        setCreatingBundle(true);
        try {
            const res = await apiService.requestBundleBill({
                plate_no: values.plate_no,
                bundle_id: Number(values.bundle_id),
                source: 'portal',
            });
            if (res?.success) {
                const data = res.data || {};
                onSuccess?.({
                    type: 'bundle',
                    title: 'Bundle bill created',
                    control_number: extractControlNumber(data),
                    bill_amount: data.bill_amount,
                    bill_id: data.bill_id,
                    message: res.message,
                    plate_no: values.plate_no,
                });
                onClose();
                bundleForm.resetFields();
            } else {
                await Swal.fire({
                    icon: 'error',
                    title: 'Could not create bundle bill',
                    text: res?.message || 'Request failed',
                });
            }
        } catch (err) {
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err?.message || 'Unexpected error',
            });
        } finally {
            setCreatingBundle(false);
        }
    };

    const bundles = Array.isArray(eligibleInfo?.bundles) ? eligibleInfo.bundles : [];
    const findBundle = (id) => bundles.find((b) => Number(b.bundle_id) === Number(id));
    const selectedBundle = findBundle(bundleTierId);
    const bodyTypeName = eligibleInfo?.body_type?.name || null;
    const hasOutstanding = !!eligibleInfo?.has_outstanding_bundle_bill;
    const outstanding = eligibleInfo?.outstanding_bill || null;
    const displayAccountNo =
        accountNo || eligibleInfo?.account?.account_no || eligibleInfo?.account_no || null;
    const fmt = (n, currency = 'TZS') => `${currency} ${Number(n || 0).toLocaleString()}`;
    const submitDisabled =
        !bundlePlateNo ||
        loadingEligible ||
        !!eligibleError ||
        hasOutstanding ||
        !selectedBundle ||
        !selectedBundle.eligible;

    const selectOptions =
        plateOptions.length > 0
            ? plateOptions
            : initialPlateNo
              ? [{value: initialPlateNo, label: initialPlateNo}]
              : [];

    const usePlateSearch = selectOptions.length === 0;

    return (
        <Modal
            open={open}
            onCancel={() => !creatingBundle && onClose()}
            footer={null}
            width={520}
            centered
            destroyOnHidden
            title={null}
            closable={false}
            maskClosable={!creatingBundle}
            keyboard={!creatingBundle}
            className="brand-modal"
            styles={{body: {padding: 0}, content: {padding: 0, overflow: 'hidden'}}}
        >
            <BrandModalHeader title={title} onClose={() => !creatingBundle && onClose()} />
            <div className="px-6 py-5">
                {showAccountSummary && (
                <div className="mb-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <div
                                className="text-[11px] font-semibold uppercase tracking-[0.06em]"
                                style={{color: BRAND}}
                            >
                                Account No
                            </div>
                            <div className="font-mono text-sm text-black">
                                {displayAccountNo || EMPTY_VALUE}
                            </div>
                        </div>
                        <div>
                            <div
                                className="text-[11px] font-semibold uppercase tracking-[0.06em]"
                                style={{color: BRAND}}
                            >
                                Body Type
                            </div>
                            <div className="flex items-center gap-2 truncate text-sm text-black">
                                {loadingEligible ? (
                                    <span className="text-xs text-slate-500">Loading…</span>
                                ) : (
                                    bodyTypeName || EMPTY_VALUE
                                )}
                            </div>
                        </div>
                    </div>
                </div>
                )}

                {hasOutstanding && (
                    <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3">
                        <div className="flex items-start gap-2">
                            <AlertCircle size={16} className="mt-0.5 shrink-0 text-amber-600" />
                            <div className="flex-1">
                                <div className="text-sm font-semibold text-amber-900">
                                    Outstanding bundle bill
                                </div>
                                <div className="mt-0.5 text-xs text-amber-800">
                                    This vehicle already has an unpaid bundle bill. Pay or wait for it
                                    to expire before requesting a new one.
                                </div>
                                {outstanding?.control_number && (
                                    <div className="mt-2 flex items-center gap-2">
                                        <span className="text-[11px] font-semibold uppercase tracking-[0.06em] text-amber-900">
                                            Control No
                                        </span>
                                        <span className="font-mono text-sm font-bold text-amber-900">
                                            {outstanding.control_number}
                                        </span>
                                        <Button
                                            size="small"
                                            icon={<Copy size={12} />}
                                            onClick={() => copyToClipboard(outstanding.control_number)}
                                        >
                                            Copy
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {eligibleError && !loadingEligible && (
                    <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {eligibleError}
                    </div>
                )}

                <Form
                    form={bundleForm}
                    layout="vertical"
                    onFinish={handleSubmitBundle}
                    requiredMark={false}
                    onValuesChange={(changed) => {
                        if (Object.prototype.hasOwnProperty.call(changed, 'plate_no')) {
                            setEligibleInfo(null);
                            setEligibleInfoPlate(null);
                            setEligibleError(null);
                        }
                    }}
                >
                    <Form.Item
                        name="plate_no"
                        label={
                            <span className="text-sm font-medium" style={{color: BRAND}}>
                                Plate Number
                            </span>
                        }
                        rules={[{required: true, message: 'Plate number is required'}]}
                    >
                        {usePlateSearch ? (
                            <PlateAsyncSelect disabled={creatingBundle} />
                        ) : (
                            <Select
                                size="large"
                                placeholder="Select a vehicle"
                                options={selectOptions}
                                showSearch
                                optionFilterProp="label"
                            />
                        )}
                    </Form.Item>

                    {!bundlePlateNo ? (
                        <div className="mb-4 rounded-md border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            Select a plate number to load bundle options and amounts.
                        </div>
                    ) : loadingEligible ? (
                        <div className="mb-4">
                            <CollectionLoader size={64} compact />
                        </div>
                    ) : (
                        <>
                            <Form.Item
                                name="bundle_id"
                                label={
                                    <span className="text-sm font-medium" style={{color: BRAND}}>
                                        Bundle Type
                                    </span>
                                }
                                rules={[{required: true, message: 'Bundle type is required'}]}
                                initialValue={3}
                            >
                                <Radio.Group
                                    className="w-full"
                                    buttonStyle="solid"
                                    disabled={!bundles.length}
                                >
                                    <div className="grid w-full grid-cols-3 gap-2">
                                        {(bundles.length
                                            ? bundles
                                            : [
                                                  {
                                                      bundle_id: 1,
                                                      bundle_name: 'Daily',
                                                      amount: null,
                                                      eligible: false,
                                                  },
                                                  {
                                                      bundle_id: 2,
                                                      bundle_name: 'Weekly',
                                                      amount: null,
                                                      eligible: false,
                                                  },
                                                  {
                                                      bundle_id: 3,
                                                      bundle_name: 'Monthly',
                                                      amount: null,
                                                      eligible: false,
                                                  },
                                              ]
                                        ).map((b) => (
                                            <Radio.Button
                                                key={b.bundle_id}
                                                value={b.bundle_id}
                                                disabled={bundles.length > 0 && !b.eligible}
                                                style={{
                                                    textAlign: 'center',
                                                    width: '100%',
                                                    height: 'auto',
                                                    padding: '6px 0',
                                                    lineHeight: 1.2,
                                                }}
                                                title={
                                                    !b.eligible && b.ineligible_reason
                                                        ? b.ineligible_reason
                                                        : undefined
                                                }
                                            >
                                                <div className="text-sm font-medium">
                                                    {b.bundle_name?.replace(/\s*Bundle\s*$/i, '') ||
                                                        b.bundle_name ||
                                                        '—'}
                                                </div>
                                                <div className="font-mono text-[11px] opacity-90">
                                                    {b.amount != null
                                                        ? fmt(b.amount, b.currency || 'TZS')
                                                        : '—'}
                                                </div>
                                            </Radio.Button>
                                        ))}
                                    </div>
                                </Radio.Group>
                            </Form.Item>

                            <div
                                className="mb-4 rounded-md border px-4 py-3"
                                style={{
                                    borderColor:
                                        selectedBundle?.eligible && selectedBundle?.amount != null
                                            ? BRAND
                                            : '#E2E8F0',
                                    backgroundColor:
                                        selectedBundle?.eligible && selectedBundle?.amount != null
                                            ? '#FBF1F2'
                                            : '#F8FAFC',
                                }}
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <div
                                            className="text-[11px] font-semibold uppercase tracking-[0.06em]"
                                            style={{color: BRAND}}
                                        >
                                            {selectedBundle?.bundle_name || 'Bundle Amount'}
                                        </div>
                                        <div className="mt-0.5 text-xs text-slate-500">
                                            {!bundlePlateNo
                                                ? 'Select a vehicle to see the bundle amount.'
                                                : eligibleError
                                                  ? 'Could not retrieve bundle eligibility.'
                                                  : !selectedBundle
                                                    ? 'No bundle option available for this vehicle.'
                                                    : !selectedBundle.eligible
                                                      ? selectedBundle.ineligible_reason ||
                                                        'This bundle is not eligible for this vehicle.'
                                                      : selectedBundle.bundle_description ||
                                                        'Final amount is confirmed by the server after submission.'}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div
                                            className="font-mono text-lg font-bold"
                                            style={{
                                                color:
                                                    selectedBundle?.eligible &&
                                                    selectedBundle?.amount != null
                                                        ? BRAND
                                                        : '#94A3B8',
                                            }}
                                        >
                                            {selectedBundle?.amount != null
                                                ? fmt(
                                                      selectedBundle.amount,
                                                      selectedBundle.currency || 'TZS'
                                                  )
                                                : '—'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </>
                    )}

                    <div className="flex items-center justify-end gap-2 pt-1">
                        <Button onClick={onClose} disabled={creatingBundle}>
                            Close
                        </Button>
                        <Button
                            type="primary"
                            htmlType="submit"
                            loading={creatingBundle}
                            disabled={submitDisabled}
                            icon={<Package size={14} />}
                            style={
                                submitDisabled
                                    ? undefined
                                    : {backgroundColor: BRAND, borderColor: BRAND}
                            }
                            onMouseEnter={(e) => {
                                if (!submitDisabled) {
                                    e.currentTarget.style.backgroundColor = BRAND_DARK;
                                    e.currentTarget.style.borderColor = BRAND_DARK;
                                }
                            }}
                            onMouseLeave={(e) => {
                                if (!submitDisabled) {
                                    e.currentTarget.style.backgroundColor = BRAND;
                                    e.currentTarget.style.borderColor = BRAND;
                                }
                            }}
                        >
                            {submitLabel}
                        </Button>
                    </div>
                </Form>
            </div>
        </Modal>
    );
};

export default RequestBundleModal;
