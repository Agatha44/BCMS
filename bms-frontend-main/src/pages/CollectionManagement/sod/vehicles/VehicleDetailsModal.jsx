import {useCallback, useEffect, useRef, useState} from 'react';
import {App, Button, Checkbox, Empty, Form, Input, Modal, Select, Spin} from 'antd';
import {AlertTriangle, ImageOff, Maximize2, Pencil, Save, Upload as UploadIcon, X as XIcon} from 'lucide-react';
import {apiService} from '../../../../services/api.jsx';
import CollectionLoader from '../../components/CollectionLoader.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

const formatDate = (value) => {
    if (!value) return EMPTY_VALUE;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const isPresent = (value) =>
    !(value === null || value === undefined || value === '');

const fileToBase64DataUrl = (file) =>
    new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(reader.error || new Error('Failed to read file'));
        reader.readAsDataURL(file);
    });

const statusTone = (label) => {
    const normalized = String(label || '').toUpperCase();
    if (normalized === 'ACTIVE') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }
    return 'border-slate-200 bg-slate-100 text-slate-600';
};

const exemptionTone = (label) => {
    const normalized = String(label || '').toUpperCase();
    if (normalized === 'NOT EXEMPTED' || normalized === 'NOT-EXEMPTED') {
        return 'border-slate-200 bg-slate-100 text-slate-600';
    }
    return 'border-[#ead6d7] bg-[#fff5f5] text-[#962E32]';
};

const Section = ({title, children}) => (
    <section>
        <h4
            className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
            style={{color: BRAND}}
        >
            {title}
        </h4>
        {children}
    </section>
);

const Field = ({label, value, mono = false}) => (
    <div className="min-w-0 py-1.5">
        <span
            className="block text-xs font-semibold tracking-[0.01em]"
            style={{color: BRAND}}
        >
            {label}
        </span>
        <div
            className={`mt-0.5 truncate text-sm text-black ${
                mono ? 'font-mono tracking-wide' : 'font-medium'
            }`}
            title={isPresent(value) ? String(value) : EMPTY_VALUE}
        >
            {isPresent(value) ? (
                value
            ) : (
                <span className="text-slate-400">{EMPTY_VALUE}</span>
            )}
        </div>
    </div>
);

const FormField = ({label, name, required, rules = [], children}) => (
    <div>
        <span
            className="mb-1 block text-xs font-semibold tracking-[0.01em]"
            style={{color: BRAND}}
        >
            {label}
            {required && <span className="ml-1 text-red-500">*</span>}
        </span>
        <Form.Item
            name={name}
            rules={
                required
                    ? [{required: true, message: `${label} is required`}, ...rules]
                    : rules
            }
            className="!mb-3"
        >
            {children}
        </Form.Item>
    </div>
);

const VehicleDetailsModal = ({open, vehicleId, onClose, onUpdated}) => {
    const {message} = App.useApp();
    const [form] = Form.useForm();
    const fileInputRef = useRef(null);

    const [mode, setMode] = useState('view');
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [vehicle, setVehicle] = useState(null);
    const [bodyTypes, setBodyTypes] = useState([]);
    const [bodyTypesLoading, setBodyTypesLoading] = useState(false);
    const [imageFile, setImageFile] = useState(null);
    const [imagePreview, setImagePreview] = useState(null);
    const [clearImage, setClearImage] = useState(false);
    const [zoomOpen, setZoomOpen] = useState(false);
    const [zoomSrc, setZoomSrc] = useState(null);

    const loadVehicle = useCallback(async () => {
        if (!vehicleId) return null;
        setLoading(true);
        setError(null);
        try {
            const response = await apiService.getCollectionVehicleById(vehicleId);
            if (response?.success) {
                setVehicle(response.data || null);
                return response.data || null;
            }
            setVehicle(null);
            setError(response?.message || 'Failed to load vehicle details');
            return null;
        } catch (err) {
            setVehicle(null);
            setError(err?.message || 'Failed to load vehicle details');
            return null;
        } finally {
            setLoading(false);
        }
    }, [vehicleId]);

    useEffect(() => {
        if (!open || !vehicleId) return;
        setMode('view');
        loadVehicle();
    }, [open, vehicleId, loadVehicle]);

    const loadBodyTypes = async () => {
        if (bodyTypes.length > 0) return;
        setBodyTypesLoading(true);
        try {
            const response = await apiService.getBodyTypes();
            if (response?.success && Array.isArray(response.data?.body_types)) {
                setBodyTypes(response.data.body_types);
            } else if (response?.success && Array.isArray(response.data)) {
                setBodyTypes(response.data);
            } else {
                setBodyTypes([]);
            }
        } catch (e) {
            setBodyTypes([]);
        } finally {
            setBodyTypesLoading(false);
        }
    };

    const resetImageState = () => {
        setImageFile(null);
        setImagePreview(null);
        setClearImage(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handleEnterEdit = () => {
        if (!vehicle) return;
        loadBodyTypes();
        form.setFieldsValue({
            plate_no: vehicle.plate_no?.trim() || '',
            body_type_id: vehicle.body_type?.id ?? null,
            card_number: vehicle.card_number ?? '',
            rfid_tag_no: vehicle.rfid_tag_no ?? '0',
        });
        resetImageState();
        setMode('edit');
    };

    const handleCancelEdit = () => {
        form.resetFields();
        resetImageState();
        setMode('view');
    };

    const handleFileChange = (event) => {
        const file = event.target.files?.[0];
        if (!file) return;
        if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
            message.error('Only JPEG, PNG, GIF or WEBP images are allowed');
            event.target.value = '';
            return;
        }
        if (file.size > MAX_IMAGE_BYTES) {
            message.error('Image must be 5 MB or smaller');
            event.target.value = '';
            return;
        }
        setImageFile(file);
        setClearImage(false);
        const reader = new FileReader();
        reader.onload = (e) => setImagePreview(e.target?.result || null);
        reader.readAsDataURL(file);
    };

    const handleClearSelectedImage = () => {
        setImageFile(null);
        setImagePreview(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handleToggleClearImage = (e) => {
        const checked = e.target.checked;
        setClearImage(checked);
        if (checked) handleClearSelectedImage();
    };

    const handleSave = async () => {
        try {
            const values = await form.validateFields();
            const payload = {
                vehicle_id: vehicle?.id ?? Number(vehicleId),
                plate_no: String(values.plate_no || '').trim(),
                body_type_id: Number(values.body_type_id),
                rfid_tag_no: String(values.rfid_tag_no ?? '0').trim() || '0',
            };

            const cardNumber = String(values.card_number ?? '').trim();
            if (cardNumber) payload.card_number = cardNumber;

            if (isPresent(vehicle?.account_no)) payload.account_no = vehicle.account_no;
            if (isPresent(vehicle?.account_vehicle_id)) {
                payload.account_vehicle_id = vehicle.account_vehicle_id;
            }

            setSaving(true);

            if (clearImage) {
                payload.clear_vehicle_image = true;
            } else if (imageFile) {
                // Use base64 + JSON (recommended path: works even when the PHP
                // multipart upload temp dir is broken on the API host).
                try {
                    payload.vehicle_image_base64 = await fileToBase64DataUrl(imageFile);
                } catch (readErr) {
                    setSaving(false);
                    message.error(readErr?.message || 'Failed to read image');
                    return;
                }
            }
            const response = await apiService.updateCollectionVehicle(
                vehicleId,
                payload
            );
            if (response?.success) {
                message.success(response?.message || 'Vehicle updated successfully');
                await loadVehicle();
                resetImageState();
                setMode('view');
                if (typeof onUpdated === 'function') onUpdated();
            } else {
                message.error(response?.message || 'Failed to update vehicle');
            }
        } catch (e) {
            if (e?.errorFields) return;
            message.error(e?.message || 'Failed to update vehicle');
        } finally {
            setSaving(false);
        }
    };

    const imageSrc = (() => {
        const image = vehicle?.image;
        if (!image?.base64) return null;
        const type = image.content_type || 'image/png';
        return image.base64.startsWith('data:')
            ? image.base64
            : `data:${type};base64,${image.base64}`;
    })();

    const isExempt =
        vehicle?.exemption === 1 ||
        vehicle?.exemption === '1' ||
        vehicle?.exemption === true;

    const headerTitle = mode === 'edit' ? 'Update Vehicle' : 'Vehicle Details';

    const openZoom = (src) => {
        if (!src) return;
        setZoomSrc(src);
        setZoomOpen(true);
    };

    const closeZoom = () => {
        setZoomOpen(false);
        setZoomSrc(null);
    };

    const ZoomableImage = ({src, alt, className = '', containerClassName = ''}) => (
        <div className={`group relative h-full w-full ${containerClassName}`}>
            <img
                src={src}
                alt={alt}
                className={`max-h-full max-w-full object-contain object-center ${className}`}
            />
            <button
                type="button"
                aria-label="Zoom image"
                onClick={() => openZoom(src)}
                className="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition duration-200 group-hover:bg-black/30 group-hover:opacity-100 focus:opacity-100 focus:outline-none"
            >
                <span className="flex h-11 w-11 items-center justify-center rounded-full bg-black/55 text-white shadow-lg ring-1 ring-white/20 backdrop-blur-md transition-transform duration-200 hover:scale-110">
                    <Maximize2 size={18} />
                </span>
            </button>
        </div>
    );

    const renderImageBlock = () => (
        <div className="flex h-44 w-full items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50">
            {imageSrc ? (
                <ZoomableImage
                    src={imageSrc}
                    alt={`Vehicle ${vehicle?.plate_no?.trim() || EMPTY_VALUE}`}
                />
            ) : (
                <div className="flex flex-col items-center gap-1.5 text-slate-400">
                    <ImageOff size={24} />
                    <span className="text-[11px] font-medium uppercase tracking-wide">
                        No image
                    </span>
                </div>
            )}
        </div>
    );

    return (
        <>
        <Modal
            open={open}
            onCancel={mode === 'edit' ? undefined : onClose}
            footer={null}
            width={860}
            centered
            destroyOnHidden
            title={null}
            closable={false}
            maskClosable={mode !== 'edit'}
            keyboard={mode !== 'edit'}
            className="brand-modal vehicle-details-modal"
            styles={{body: {padding: 0}, content: {padding: 0, overflow: 'hidden'}}}
        >
            <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
                <h2 className="m-0 text-sm font-semibold leading-none text-white">
                    {headerTitle}
                </h2>
                <button
                    type="button"
                    aria-label="Close"
                    onClick={onClose}
                    className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="h-4 w-4"
                    >
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            {loading && !vehicle ? (
                <div className="flex min-h-[440px] items-center justify-center">
                    <CollectionLoader size={64} compact />
                </div>
            ) : error && !vehicle ? (
                <div className="flex min-h-[440px] flex-col items-center justify-center gap-2 text-center">
                    <AlertTriangle size={28} className="text-amber-500" />
                    <p className="text-sm font-medium text-slate-700">
                        Could not load vehicle
                    </p>
                    <p className="max-w-xs text-xs text-slate-500">{error}</p>
                </div>
            ) : !vehicle ? (
                <div className="flex min-h-[440px] items-center justify-center">
                    <Empty description="No vehicle data" />
                </div>
            ) : mode === 'view' ? (
                <div className="grid gap-6 px-6 py-5 lg:grid-cols-[240px_1fr]">
                    <div className="flex flex-col gap-3">
                        {renderImageBlock()}

                        <div className="rounded-md border border-slate-200 bg-white px-3 py-2.5">
                            <p
                                className="text-xs font-semibold tracking-[0.01em]"
                                style={{color: BRAND}}
                            >
                                Plate Number
                            </p>
                            <p className="mt-0.5 font-mono text-lg font-semibold text-black">
                                {vehicle.plate_no?.trim() || EMPTY_VALUE}
                            </p>
                        </div>

                        <div>
                            <span
                                className="block text-xs font-semibold tracking-[0.01em]"
                                style={{color: BRAND}}
                            >
                                Vehicle Status
                            </span>
                            <div className="mt-1.5">
                                <span
                                    className={`inline-flex w-fit items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${statusTone(vehicle.status_label)}`}
                                >
                                    <span className="h-1.5 w-1.5 rounded-full bg-current" />
                                    {vehicle.status_label ||
                                        (vehicle.status ? 'ACTIVE' : 'INACTIVE')}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-5">
                        <Section title="Vehicle Information">
                            <div className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                                <Field
                                    label="Body Type"
                                    value={vehicle.body_type?.name}
                                />
                                <Field
                                    label="Body Description"
                                    value={vehicle.body_type?.description}
                                />
                                <Field
                                    label="Registration Date"
                                    value={formatDate(vehicle.registration_date)}
                                />
                                <Field
                                    label="Last Updated"
                                    value={
                                        vehicle.updated_at
                                            ? formatDate(vehicle.updated_at)
                                            : null
                                    }
                                />
                            </div>
                        </Section>

                        <Section title="Account & Identifiers">
                            <div className="grid gap-x-6 gap-y-1 sm:grid-cols-3">
                                <Field
                                    label="Account No."
                                    value={vehicle.account_no}
                                    mono
                                />
                                <Field
                                    label="Card Number"
                                    value={vehicle.card_number}
                                    mono
                                />
                                <Field
                                    label="RFID Tag"
                                    value={
                                        vehicle.rfid_tag_no &&
                                        vehicle.rfid_tag_no !== '0'
                                            ? vehicle.rfid_tag_no
                                            : null
                                    }
                                    mono
                                />
                            </div>
                        </Section>

                        <Section title="Exemption">
                            <div className="grid gap-x-6 gap-y-3 sm:grid-cols-[180px_1fr]">
                                <div className="min-w-0 py-1.5">
                                    <span
                                        className="block text-xs font-semibold tracking-[0.01em]"
                                        style={{color: BRAND}}
                                    >
                                        Status
                                    </span>
                                    <div className="mt-1.5">
                                        <span
                                            className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${exemptionTone(vehicle.exemption_label)}`}
                                        >
                                            <span className="h-1.5 w-1.5 rounded-full bg-current" />
                                            {vehicle.exemption_label ||
                                                (isExempt ? 'EXEMPTED' : 'NOT EXEMPTED')}
                                        </span>
                                    </div>
                                </div>
                                <Field
                                    label="Reason"
                                    value={vehicle.exempt_reason}
                                />
                            </div>
                        </Section>
                    </div>
                </div>
            ) : (
                <Spin spinning={saving} tip="Saving...">
                    <Form form={form} layout="vertical" requiredMark={false}>
                        <div className="grid gap-6 px-6 py-5 lg:grid-cols-[240px_1fr]">
                            <div className="flex flex-col gap-3">
                                <div
                                    className={`flex h-44 w-full items-center justify-center overflow-hidden rounded-md border bg-slate-50 ${
                                        clearImage ? 'border-dashed border-red-300 bg-red-50' : 'border-slate-200'
                                    }`}
                                >
                                    {clearImage ? (
                                        <div className="flex flex-col items-center gap-1.5 text-red-500">
                                            <ImageOff size={24} />
                                            <span className="text-[11px] font-medium uppercase tracking-wide">
                                                Will be cleared
                                            </span>
                                        </div>
                                    ) : imagePreview ? (
                                        <ZoomableImage
                                            src={imagePreview}
                                            alt="Selected vehicle"
                                        />
                                    ) : imageSrc ? (
                                        <ZoomableImage
                                            src={imageSrc}
                                            alt={`Vehicle ${vehicle?.plate_no?.trim() || EMPTY_VALUE}`}
                                        />
                                    ) : (
                                        <div className="flex flex-col items-center gap-1.5 text-slate-400">
                                            <ImageOff size={24} />
                                            <span className="text-[11px] font-medium uppercase tracking-wide">
                                                No image
                                            </span>
                                        </div>
                                    )}
                                </div>

                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept={ACCEPTED_IMAGE_TYPES.join(',')}
                                    onChange={handleFileChange}
                                    tabIndex={-1}
                                    aria-hidden="true"
                                    style={{display: 'none'}}
                                />

                                <div className="flex flex-col gap-1.5">
                                    <Button
                                        icon={<UploadIcon size={14} />}
                                        onClick={() => fileInputRef.current?.click()}
                                        disabled={clearImage || saving}
                                        block
                                    >
                                        {imageFile ? 'Replace Image' : 'Choose Image'}
                                    </Button>
                                    {imageFile && (
                                        <div className="flex items-center justify-between gap-2 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-600">
                                            <span className="truncate" title={imageFile.name}>
                                                {imageFile.name}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={handleClearSelectedImage}
                                                aria-label="Remove selected file"
                                                className="flex h-5 w-5 items-center justify-center rounded text-slate-500 transition hover:bg-slate-200"
                                            >
                                                <XIcon size={12} />
                                            </button>
                                        </div>
                                    )}
                                    <Checkbox
                                        checked={clearImage}
                                        onChange={handleToggleClearImage}
                                        disabled={saving}
                                    >
                                        <span className="text-xs text-slate-600">Clear current image</span>
                                    </Checkbox>
                                    <p className="text-[10.5px] text-slate-400">
                                        JPEG, PNG, GIF, WEBP · max 5 MB
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-1">
                                <Section title="Editable Fields">
                                    <div className="grid gap-x-6 sm:grid-cols-2">
                                        <FormField label="Plate Number" name="plate_no" required>
                                            <Input
                                                placeholder="e.g. T123ABC"
                                                className="font-mono"
                                            />
                                        </FormField>
                                        <FormField label="Body Type" name="body_type_id" required>
                                            <Select
                                                showSearch
                                                placeholder="Select body type"
                                                loading={bodyTypesLoading}
                                                optionFilterProp="label"
                                                options={bodyTypes.map((bt) => ({
                                                    value: bt.id,
                                                    label: bt.name,
                                                }))}
                                            />
                                        </FormField>
                                        <FormField label="Card Number" name="card_number">
                                            <Input
                                                placeholder="Card number"
                                                className="font-mono"
                                            />
                                        </FormField>
                                        <FormField label="RFID Tag" name="rfid_tag_no">
                                            <Input
                                                placeholder="0"
                                                className="font-mono"
                                            />
                                        </FormField>
                                    </div>
                                </Section>

                                <Section title="Read-only">
                                    <div className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                                        <Field
                                            label="Account No."
                                            value={vehicle.account_no}
                                            mono
                                        />
                                        <Field
                                            label="Registration Date"
                                            value={formatDate(vehicle.registration_date)}
                                        />
                                        <Field
                                            label="Status"
                                            value={
                                                vehicle.status_label ||
                                                (vehicle.status ? 'ACTIVE' : 'INACTIVE')
                                            }
                                        />
                                    </div>
                                </Section>
                            </div>
                        </div>
                    </Form>
                </Spin>
            )}

            <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
                {mode === 'view' ? (
                    <>
                        <Button
                            type="primary"
                            icon={<Pencil size={14} />}
                            onClick={handleEnterEdit}
                            disabled={!vehicle || loading}
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
                            Update
                        </Button>
                        <Button onClick={onClose}>Close</Button>
                    </>
                ) : (
                    <>
                        <Button
                            type="primary"
                            icon={<Save size={14} />}
                            onClick={handleSave}
                            loading={saving}
                            disabled={saving}
                            style={{backgroundColor: BRAND, borderColor: BRAND}}
                            onMouseEnter={(e) => {
                                if (saving) return;
                                e.currentTarget.style.backgroundColor = BRAND_DARK;
                                e.currentTarget.style.borderColor = BRAND_DARK;
                            }}
                            onMouseLeave={(e) => {
                                if (saving) return;
                                e.currentTarget.style.backgroundColor = BRAND;
                                e.currentTarget.style.borderColor = BRAND;
                            }}
                        >
                            Save
                        </Button>
                        <Button onClick={handleCancelEdit} disabled={saving}>
                            Cancel
                        </Button>
                    </>
                )}
            </div>
        </Modal>

        <Modal
            open={zoomOpen}
            onCancel={closeZoom}
            footer={null}
            closable={false}
            centered
            destroyOnHidden
            width="auto"
            maskClosable
            className="vehicle-image-zoom-modal"
            styles={{
                body: {padding: 0, background: 'transparent'},
                content: {
                    padding: 0,
                    background: 'transparent',
                    boxShadow: 'none',
                },
                mask: {
                    background: 'rgba(0, 0, 0, 0.82)',
                    backdropFilter: 'blur(6px)',
                    WebkitBackdropFilter: 'blur(6px)',
                },
            }}
        >
            {zoomSrc && (
                <div className="relative">
                    <button
                        type="button"
                        aria-label="Close"
                        onClick={closeZoom}
                        className="absolute -top-3 -right-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white text-slate-800 shadow-xl ring-1 ring-black/10 transition hover:scale-105 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-white/60"
                    >
                        <XIcon size={18} />
                    </button>
                    <img
                        src={zoomSrc}
                        alt="Vehicle full view"
                        className="block max-h-[85vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                    />
                </div>
            )}
        </Modal>
        </>
    );
};

export default VehicleDetailsModal;
