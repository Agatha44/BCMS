import {useEffect, useRef, useState} from 'react';
import {App, Button, Form, Input, Modal, Select, Spin} from 'antd';
import {ImageOff, Maximize2, Upload as UploadIcon, X as XIcon} from 'lucide-react';
import {apiService} from '../../../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

const fileToBase64DataUrl = (file) =>
    new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(reader.error || new Error('Failed to read file'));
        reader.readAsDataURL(file);
    });

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

const VehicleCreateModal = ({open, onClose, onCreated}) => {
    const {message} = App.useApp();
    const [form] = Form.useForm();
    const fileInputRef = useRef(null);

    const [saving, setSaving] = useState(false);
    const [bodyTypes, setBodyTypes] = useState([]);
    const [bodyTypesLoading, setBodyTypesLoading] = useState(false);
    const [imageFile, setImageFile] = useState(null);
    const [imagePreview, setImagePreview] = useState(null);
    const [zoomOpen, setZoomOpen] = useState(false);

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

    useEffect(() => {
        if (!open) return;
        form.resetFields();
        form.setFieldsValue({rfid_tag_no: '0'});
        setImageFile(null);
        setImagePreview(null);
        loadBodyTypes();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

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
        const reader = new FileReader();
        reader.onload = (e) => setImagePreview(e.target?.result || null);
        reader.readAsDataURL(file);
    };

    const handleClearSelectedImage = () => {
        setImageFile(null);
        setImagePreview(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handleSave = async () => {
        try {
            const values = await form.validateFields();
            const payload = {
                plate_no: String(values.plate_no || '').trim(),
                body_type_id: Number(values.body_type_id),
            };

            const accountNo = String(values.account_no ?? '').trim();
            if (accountNo) payload.account_no = accountNo;

            const cardNumber = String(values.card_number ?? '').trim();
            if (cardNumber) payload.card_number = cardNumber;

            const rfid = String(values.rfid_tag_no ?? '0').trim();
            payload.rfid_tag_no = rfid || '0';

            setSaving(true);

            if (imageFile) {
                try {
                    payload.vehicle_image_base64 = await fileToBase64DataUrl(imageFile);
                } catch (readErr) {
                    setSaving(false);
                    message.error(readErr?.message || 'Failed to read image');
                    return;
                }
            }

            const response = await apiService.createCollectionVehicle(payload);
            if (response?.success) {
                message.success(response?.message || 'Vehicle created successfully');
                if (typeof onCreated === 'function') {
                    onCreated(response?.data || null);
                }
                handleCloseInternal();
            } else {
                message.error(response?.message || 'Failed to create vehicle');
            }
        } catch (e) {
            if (e?.errorFields) return;
            message.error(e?.message || 'Failed to create vehicle');
        } finally {
            setSaving(false);
        }
    };

    const handleCloseInternal = () => {
        form.resetFields();
        setImageFile(null);
        setImagePreview(null);
        onClose?.();
    };

    return (
        <>
            <Modal
                open={open}
                onCancel={handleCloseInternal}
                footer={null}
                width={860}
                centered
                destroyOnHidden
                title={null}
                closable={false}
                maskClosable={false}
                keyboard={false}
                className="brand-modal vehicle-create-modal"
                styles={{body: {padding: 0}, content: {padding: 0, overflow: 'hidden'}}}
            >
                <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
                    <h2 className="m-0 text-sm font-semibold leading-none text-white">
                        Add Vehicle
                    </h2>
                    <button
                        type="button"
                        aria-label="Close"
                        onClick={handleCloseInternal}
                        className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
                    >
                        <XIcon size={16} />
                    </button>
                </div>

                <Spin spinning={saving} tip="Saving...">
                    <Form form={form} layout="vertical" requiredMark={false}>
                        <div className="grid gap-6 px-6 py-5 lg:grid-cols-[240px_1fr]">
                            <div className="flex flex-col gap-3">
                                <div className="flex h-44 w-full items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50">
                                    {imagePreview ? (
                                        <div className="group relative h-full w-full">
                                            <img
                                                src={imagePreview}
                                                alt="Selected vehicle"
                                                className="max-h-full max-w-full object-contain object-center"
                                            />
                                            <button
                                                type="button"
                                                aria-label="Zoom image"
                                                onClick={() => setZoomOpen(true)}
                                                className="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition duration-200 group-hover:bg-black/30 group-hover:opacity-100 focus:opacity-100 focus:outline-none"
                                            >
                                                <span className="flex h-11 w-11 items-center justify-center rounded-full bg-black/55 text-white shadow-lg ring-1 ring-white/20 backdrop-blur-md transition-transform duration-200 hover:scale-110">
                                                    <Maximize2 size={18} />
                                                </span>
                                            </button>
                                        </div>
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
                                        disabled={saving}
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
                                    <p className="text-[10.5px] text-slate-400">
                                        JPEG, PNG, GIF, WEBP · max 5 MB · optional
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-1">
                                <Section title="Vehicle Details">
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
                                                placeholder="Card number (optional)"
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

                                <Section title="Account Link (Optional)">
                                    <div className="grid gap-x-6 sm:grid-cols-2">
                                        <FormField label="Account No." name="account_no">
                                            <Input
                                                placeholder="e.g. ACC001234"
                                                className="font-mono"
                                            />
                                        </FormField>
                                    </div>
                                    <p className="text-[11px] text-slate-500">
                                        If provided, the account must already exist on the server. Leave blank to create the vehicle without an account link.
                                    </p>
                                </Section>
                            </div>
                        </div>
                    </Form>
                </Spin>

                <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
                    <Button
                        type="primary"
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
                        Add
                    </Button>
                    <Button onClick={handleCloseInternal} disabled={saving}>
                        Close
                    </Button>
                </div>
            </Modal>

            <Modal
                open={zoomOpen}
                onCancel={() => setZoomOpen(false)}
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
                {imagePreview && (
                    <div className="relative">
                        <button
                            type="button"
                            aria-label="Close"
                            onClick={() => setZoomOpen(false)}
                            className="absolute -top-3 -right-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white text-slate-800 shadow-xl ring-1 ring-black/10 transition hover:scale-105 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-white/60"
                        >
                            <XIcon size={18} />
                        </button>
                        <img
                            src={imagePreview}
                            alt="Vehicle full view"
                            className="block max-h-[85vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                        />
                    </div>
                )}
            </Modal>
        </>
    );
};

export default VehicleCreateModal;
