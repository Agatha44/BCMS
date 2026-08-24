import { useEffect, useState } from 'react';
import { Package } from 'lucide-react';
import AsyncSelect from 'react-select/async';
import { App, Button, Form, Modal, Select } from 'antd';

import BrandModalHeader from '../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import { apiService } from '../../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

const asyncSelectStyles = {
  control: (base, state) => ({
    ...base,
    borderColor: state.isFocused ? BRAND : '#e2e8f0',
    borderRadius: '8px',
    minHeight: '40px',
    boxShadow: state.isFocused ? '0 0 0 3px rgba(150, 46, 50, 0.12)' : 'none',
    '&:hover': { borderColor: BRAND },
  }),
  menuPortal: (base) => ({ ...base, zIndex: 9999 }),
  menu: (base) => ({ ...base, zIndex: 9999 }),
};

export default function BundleSubscriptionModal({ isOpen, onClose, onSubmit }) {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [submitting, setSubmitting] = useState(false);
  const [selectedVehicle, setSelectedVehicle] = useState(null);
  const [bundleOptions, setBundleOptions] = useState([]);
  const [loadingBundles, setLoadingBundles] = useState(false);

  const handleClose = () => {
    if (submitting) return;
    form.resetFields();
    setSelectedVehicle(null);
    onClose?.();
  };

  useEffect(() => {
    if (!isOpen) return;

    form.resetFields();
    setSelectedVehicle(null);

    const loadBundles = async () => {
      setLoadingBundles(true);
      try {
        const response = await apiService.getBundles();
        const rows = response.data;
        const options = Array.isArray(rows)
          ? rows
              .map((bundle) => {
                const value = String(bundle.id);
                const label = bundle.bundle_description;
                if (!value) return null;
                return { value, label };
              })
              .filter(Boolean)
          : [];
        setBundleOptions(options);
      } catch {
        setBundleOptions([]);
      } finally {
        setLoadingBundles(false);
      }
    };

    loadBundles();
  }, [isOpen, form]);

  const handleSubmit = async (values) => {
    if (!selectedVehicle?.plate_no) {
      message.warning('Please select a vehicle.');
      return;
    }

    setSubmitting(true);
    try {
      const payload = {
        bundle_type: values.bundle_type,
        plate_no: selectedVehicle.plate_no,
      };

      if (onSubmit) {
        await onSubmit(payload);
      } else {
        const bundleLabel =
          bundleOptions.find((option) => option.value === values.bundle_type)?.label || 'bundle';
        message.success(`Selected ${bundleLabel} for ${payload.plate_no}.`);
      }

      handleClose();
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
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="Vehicle Bundle Subscription" onClose={handleClose} />

      <Form
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        requiredMark={false}
        className="flex flex-col"
      >
        <div className="px-6 py-5">
          <div className="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
            <Form.Item
              name="bundle_type"
              label={
                <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Select Bundle
                </span>
              }
              rules={[{ required: true, message: 'Please select a bundle' }]}
            >
              <Select
                size="large"
                placeholder={loadingBundles ? 'Loading bundles...' : 'Select Bundle'}
                loading={loadingBundles}
                disabled={submitting || loadingBundles}
                options={bundleOptions}
                className="w-full"
              />
            </Form.Item>

            <div>
              <span
                className="mb-1.5 block text-xs font-semibold tracking-[0.01em]"
                style={{ color: BRAND }}
              >
                Select Vehicle
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
                      label: vehicle.plate_no || '-',
                      vehicle,
                    }));
                  } catch {
                    return [];
                  }
                }}
                value={
                  selectedVehicle
                    ? {
                        value: selectedVehicle.plate_no,
                        label: selectedVehicle.plate_no || '-',
                        vehicle: selectedVehicle,
                      }
                    : null
                }
                onChange={(option) => {
                  setSelectedVehicle(option?.vehicle || null);
                }}
                placeholder="Select vehicle as you type ..."
                isClearable
                isDisabled={submitting}
                menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                styles={asyncSelectStyles}
              />
            </div>
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            htmlType="submit"
            loading={submitting}
            icon={<Package size={14} />}
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
