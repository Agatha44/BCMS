import React, { useEffect, useState } from 'react';
import { MapPin, Plus } from 'lucide-react';
import { Button, Modal } from 'antd';

import BrandModalHeader from '../../sod/components/BrandModalHeader.jsx';
import { apiService } from '../../../../services/api.jsx';
import { CONFIG } from '../../../../config/index.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const INPUT_CLASS =
  'w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm text-black focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/20';

const FormLabel = ({ children, required = false }) => (
  <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
    {children}
    {required && <span className="text-red-500"> *</span>}
  </label>
);

export default function TerminalRegistrationForm({ isOpen, onClose, onSuccess }) {
  const [formData, setFormData] = useState({
    name: '',
    lane_id: '',
    mac_address: '',
    ip_address: '',
    terminal_type: 'POS',
    location: '',
    firmware_version: '',
    configuration: {},
  });
  const [lanes, setLanes] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingLanes, setLoadingLanes] = useState(false);
  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (isOpen) {
      fetchLanes();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen]);

  const fetchLanes = async () => {
    setLoadingLanes(true);
    try {
      const response = await apiService.getLanesList({ per_page: 100 });
      if (response?.success && response?.data) {
        const lanesData = response.data.lanes || response.data || [];
        const activeLanes = lanesData.filter((lane) => lane.status === true || lane.status === 1 || lane.status === '1');
        setLanes(activeLanes);
      } else {
        setLanes([]);
      }
    } catch {
      setLanes([]);
    } finally {
      setLoadingLanes(false);
    }
  };

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors((prev) => ({ ...prev, [name]: '' }));

    if (name === 'lane_id' && value) {
      const selectedLane = lanes.find((lane) => String(lane.id) === value);
      if (selectedLane && !formData.name) {
        setFormData((prev) => ({ ...prev, name: `POS Terminal Lane ${selectedLane.lane_no}` }));
      }
    }
  };

  const validateForm = () => {
    const newErrors = {};
    if (!formData.name.trim()) newErrors.name = 'Terminal name is required';
    if (!formData.lane_id) newErrors.lane_id = 'Please select a lane';

    if (!formData.mac_address.trim()) {
      newErrors.mac_address = 'MAC address is required';
    } else if (!/^([0-9A-F]{2}[:-]){5}([0-9A-F]{2})$/i.test(formData.mac_address)) {
      newErrors.mac_address = 'Invalid MAC address format (e.g., AA:BB:CC:DD:EE:FF)';
    }

    if (!formData.ip_address.trim()) {
      newErrors.ip_address = 'IP address is required';
    } else if (
      !/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(
        formData.ip_address
      )
    ) {
      newErrors.ip_address = 'Invalid IP address format';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const formatMacAddress = (value) => {
    const cleaned = value.replace(/[^0-9A-Fa-f]/g, '');
    const formatted = cleaned.match(/.{1,2}/g)?.join(':').toUpperCase() || '';
    return formatted.substring(0, 17);
  };

  const handleMacAddressChange = (e) => {
    const formatted = formatMacAddress(e.target.value);
    setFormData((prev) => ({ ...prev, mac_address: formatted }));
    if (errors.mac_address) setErrors((prev) => ({ ...prev, mac_address: '' }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validateForm()) return;

    setLoading(true);
    try {
      const response = await apiService.request('/api/pos-terminals', {
        method: 'POST',
        body: JSON.stringify({
          ...formData,
          lane_number: lanes.find((l) => String(l.id) === formData.lane_id)?.lane_no || '',
          registered_by: 1,
          configuration: formData.configuration || {},
        }),
      });

      if (response?.success) {
        // eslint-disable-next-line no-alert
        alert(
          `Terminal registered successfully!\n\nAPI Key: ${response.data?.api_key}\n\nPlease save this API key securely.`
        );
        setFormData({
          name: '',
          lane_id: '',
          mac_address: '',
          ip_address: '',
          terminal_type: 'POS',
          location: '',
          firmware_version: '',
          configuration: {},
        });
        onSuccess?.();
        onClose?.();
      } else if (response?.data && typeof response.data === 'object') {
        setErrors(response.data);
      } else {
        // eslint-disable-next-line no-alert
        alert(response?.message || 'Failed to register terminal');
      }
    } catch {
      // eslint-disable-next-line no-alert
      alert('Failed to register terminal. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      open={isOpen}
      onCancel={() => !loading && onClose?.()}
      footer={null}
      width={720}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      maskClosable={!loading}
      keyboard={!loading}
      className="brand-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="Register POS" onClose={() => !loading && onClose?.()} />
      <div className="max-h-[min(70vh,560px)] overflow-y-auto px-6 py-5">
        <h4
          className="mb-4 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
          style={{ color: BRAND }}
        >
          Terminal details
        </h4>
        <form id="register-pos-form" onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <FormLabel required>Terminal Name</FormLabel>
              <input
                type="text"
                name="name"
                value={formData.name}
                onChange={handleInputChange}
                className={`${INPUT_CLASS} ${errors.name ? 'border-red-500' : ''}`}
              />
              {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
            </div>
            <div>
              <FormLabel>Terminal Type</FormLabel>
              <select
                name="terminal_type"
                value={formData.terminal_type}
                onChange={handleInputChange}
                className={INPUT_CLASS}
              >
                <option value="POS">POS Terminal</option>
                <option value="Kiosk">Self-Service Kiosk</option>
                <option value="Mobile">Mobile Terminal</option>
              </select>
            </div>
          </div>

          <div>
            <FormLabel required>Assigned Lane</FormLabel>
            <select
              name="lane_id"
              value={formData.lane_id}
              onChange={handleInputChange}
              disabled={loadingLanes}
              className={`${INPUT_CLASS} ${errors.lane_id ? 'border-red-500' : ''} ${loadingLanes ? 'cursor-not-allowed opacity-50' : ''}`}
            >
              <option value="">{loadingLanes ? 'Loading lanes...' : 'Select a lane'}</option>
              {lanes.map((lane) => (
                <option key={lane.id} value={lane.id}>
                  Lane {lane.lane_no}
                </option>
              ))}
            </select>
            {errors.lane_id && <p className="mt-1 text-xs text-red-500">{errors.lane_id}</p>}
            {formData.lane_id && (
              <div className="mt-2 rounded-lg border border-slate-200 bg-[#fff5f5] p-3">
                <div className="flex items-center gap-2 text-sm" style={{ color: BRAND }}>
                  <MapPin size={14} />
                  <span className="font-medium">Selected lane information</span>
                </div>
              </div>
            )}
          </div>

          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <FormLabel required>MAC Address</FormLabel>
              <input
                type="text"
                name="mac_address"
                value={formData.mac_address}
                onChange={handleMacAddressChange}
                placeholder="AA:BB:CC:DD:EE:FF"
                maxLength={17}
                className={`font-mono ${INPUT_CLASS} ${errors.mac_address ? 'border-red-500' : ''}`}
              />
              {errors.mac_address && <p className="mt-1 text-xs text-red-500">{errors.mac_address}</p>}
            </div>
            <div>
              <FormLabel required>IP Address</FormLabel>
              <input
                type="text"
                name="ip_address"
                value={formData.ip_address}
                onChange={handleInputChange}
                placeholder="192.168.1.100"
                className={`font-mono ${INPUT_CLASS} ${errors.ip_address ? 'border-red-500' : ''}`}
              />
              {errors.ip_address && <p className="mt-1 text-xs text-red-500">{errors.ip_address}</p>}
            </div>
          </div>

          <div>
            <FormLabel>Physical Location</FormLabel>
            <input type="text" name="location" value={formData.location} onChange={handleInputChange} className={INPUT_CLASS} />
          </div>

          <div>
            <FormLabel>Firmware Version</FormLabel>
            <input
              type="text"
              name="firmware_version"
              value={formData.firmware_version}
              onChange={handleInputChange}
              className={INPUT_CLASS}
            />
          </div>
        </form>
        <div className="hidden">{CONFIG?.API_CONFIG?.BASE_URL}</div>
      </div>
      <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
        <Button
          type="primary"
          htmlType="submit"
          form="register-pos-form"
          loading={loading}
          icon={<Plus size={14} />}
          disabled={loading}
          style={{ backgroundColor: BRAND, borderColor: BRAND }}
          onMouseEnter={(e) => {
            if (!loading) {
              e.currentTarget.style.backgroundColor = BRAND_DARK;
              e.currentTarget.style.borderColor = BRAND_DARK;
            }
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.backgroundColor = BRAND;
            e.currentTarget.style.borderColor = BRAND;
          }}
        >
          Register
        </Button>
        <Button onClick={onClose} disabled={loading}>
          Close
        </Button>
      </div>
    </Modal>
  );
}
