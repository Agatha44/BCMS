import React, { useEffect, useState } from 'react';
import { Save } from 'lucide-react';
import { Button, Modal } from 'antd';
import BrandModalHeader from '../../sod/components/BrandModalHeader.jsx';
import { apiService } from '../../../../services/api.jsx';
import {
  BRAND,
  FormLabel,
  INPUT_CLASS,
  ReadOnlyField,
  SectionTitle,
  brandPrimaryButtonProps,
} from './posTerminalModalUi.jsx';

export default function TerminalEditModal({ isOpen, onClose, onSuccess, terminal }) {
  const [formData, setFormData] = useState({
    name: '',
    lane_id: '',
    location: '',
    terminal_type: 'POS',
    status: 'pending',
  });
  const [lanes, setLanes] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingLanes, setLoadingLanes] = useState(false);
  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (!isOpen || !terminal) return;

    setFormData({
      name: terminal.name || '',
      lane_id: terminal.lane_id ? String(terminal.lane_id) : '',
      location: terminal.location || '',
      terminal_type: terminal.terminal_type || 'POS',
      status: terminal.status || 'pending',
    });
    setErrors({});
    fetchLanes();
  }, [isOpen, terminal]);

  const fetchLanes = async () => {
    setLoadingLanes(true);
    try {
      const response = await apiService.getLanesList({ per_page: 100 });
      if (response?.success && response?.data) {
        const lanesData = response.data.lanes || response.data || [];
        const activeLanes = lanesData.filter(
          (lane) => lane.status === true || lane.status === 1 || lane.status === '1'
        );
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
  };

  const validateForm = () => {
    const newErrors = {};
    if (!formData.name.trim()) newErrors.name = 'Device name is required';
    if (!formData.lane_id) newErrors.lane_id = 'Select a lane to activate this device';
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!terminal?.id || !validateForm()) return;

    setLoading(true);
    try {
      const response = await apiService.updatePosTerminal(terminal.id, {
        name: formData.name.trim(),
        lane_id: Number(formData.lane_id),
        location: formData.location.trim() || undefined,
        terminal_type: formData.terminal_type,
        status: formData.lane_id ? 'active' : formData.status,
      });

      if (response?.success) {
        onSuccess?.();
        onClose?.();
      } else if (response?.data && typeof response.data === 'object') {
        setErrors(response.data);
      } else {
        // eslint-disable-next-line no-alert
        alert(response?.message || 'Failed to update terminal');
      }
    } catch {
      // eslint-disable-next-line no-alert
      alert('Failed to update terminal. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      open={isOpen && !!terminal}
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
      <BrandModalHeader title="Configure POS device" onClose={() => !loading && onClose?.()} />
      <div className="max-h-[min(70vh,560px)] overflow-y-auto px-6 py-5">
        <SectionTitle>Assignment</SectionTitle>
        <form id="edit-pos-form" onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <FormLabel required>Device name</FormLabel>
              <input
                name="name"
                value={formData.name}
                onChange={handleInputChange}
                className={`${INPUT_CLASS} ${errors.name ? 'border-red-500' : ''}`}
                placeholder="POS Lane A2"
              />
              {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
            </div>
            <div>
              <FormLabel>Terminal type</FormLabel>
              <select
                name="terminal_type"
                value={formData.terminal_type}
                onChange={handleInputChange}
                className={INPUT_CLASS}
              >
                <option value="POS">POS</option>
                <option value="Kiosk">Kiosk</option>
                <option value="Mobile">Mobile</option>
              </select>
            </div>
          </div>

          <div>
            <FormLabel required>Assigned lane</FormLabel>
            <select
              name="lane_id"
              value={formData.lane_id}
              onChange={handleInputChange}
              disabled={loadingLanes}
              className={`${INPUT_CLASS} ${errors.lane_id ? 'border-red-500' : ''} ${loadingLanes ? 'cursor-not-allowed opacity-50' : ''}`}
            >
              <option value="">{loadingLanes ? 'Loading lanes...' : 'Select lane'}</option>
              {lanes.map((lane) => (
                <option key={lane.id} value={lane.id}>
                  Lane {lane.lane_no}
                </option>
              ))}
            </select>
            {errors.lane_id && <p className="mt-1 text-xs text-red-500">{errors.lane_id}</p>}
          </div>

          <div>
            <FormLabel>Location</FormLabel>
            <input
              name="location"
              value={formData.location}
              onChange={handleInputChange}
              className={INPUT_CLASS}
              placeholder="Lane booth"
            />
          </div>

          <SectionTitle>Device reference</SectionTitle>
          <div className="grid grid-cols-1 gap-x-6 md:grid-cols-2">
            <ReadOnlyField label="MAC address" value={terminal?.mac_address} mono />
            <ReadOnlyField label="IP address" value={terminal?.ip_address} mono />
            <ReadOnlyField label="Current status" value={terminal?.status} />
            <ReadOnlyField
              label="Last seen"
              value={terminal?.last_heartbeat ? new Date(terminal.last_heartbeat).toLocaleString() : null}
            />
          </div>
        </form>
      </div>
      <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
        <Button
          type="primary"
          htmlType="submit"
          form="edit-pos-form"
          loading={loading}
          icon={<Save size={14} />}
          disabled={loading}
          {...brandPrimaryButtonProps}
        >
          Save
        </Button>
        <Button onClick={onClose} disabled={loading}>
          Close
        </Button>
      </div>
    </Modal>
  );
}
