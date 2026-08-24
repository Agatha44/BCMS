import React, { useEffect, useState } from 'react';
import { Edit } from 'lucide-react';
import { Button, Modal, Tag } from 'antd';
import BrandModalHeader from '../../sod/components/BrandModalHeader.jsx';
import CollectionLoader from '../../components/CollectionLoader.jsx';
import { apiService } from '../../../../services/api.jsx';
import {
  BRAND,
  ReadOnlyField,
  SectionTitle,
  brandPrimaryButtonProps,
} from './posTerminalModalUi.jsx';

const statusTag = (status) => {
  const value = String(status || '').toLowerCase();
  const map = {
    active: { color: 'green', label: 'Active' },
    inactive: { color: 'red', label: 'Inactive' },
    maintenance: { color: 'gold', label: 'Maintenance' },
    pending: { color: 'orange', label: 'Pending lane' },
  };
  const entry = map[value] || { color: 'default', label: status || 'Unknown' };
  return (
    <Tag color={entry.color} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
      {entry.label}
    </Tag>
  );
};

const onlineTag = (terminal) => {
  if (!terminal?.last_heartbeat) {
    return (
      <Tag color="default" className="!m-0 px-2.5 py-0.5 text-xs font-medium">
        Never connected
      </Tag>
    );
  }

  const diffMinutes = Math.floor(
    (Date.now() - new Date(terminal.last_heartbeat).getTime()) / (1000 * 60)
  );

  if (diffMinutes <= 5) {
    return (
      <Tag color="green" className="!m-0 px-2.5 py-0.5 text-xs font-medium">
        Online
      </Tag>
    );
  }

  return (
    <Tag color="red" className="!m-0 px-2.5 py-0.5 text-xs font-medium">
      Offline ({diffMinutes}m)
    </Tag>
  );
};

export default function TerminalDetailsModal({ isOpen, onClose, terminalId, onEdit }) {
  const [terminal, setTerminal] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (isOpen && terminalId) {
      fetchTerminalDetails();
    } else {
      setTerminal(null);
      setError(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, terminalId]);

  const fetchTerminalDetails = async () => {
    if (!terminalId) return;
    setLoading(true);
    setError(null);
    try {
      const response = await apiService.getPosTerminal(terminalId);
      if (response?.success && response?.data) {
        setTerminal(response.data.terminal);
      } else {
        setError(response?.message || 'Failed to fetch terminal details');
      }
    } catch (err) {
      setError('An error occurred while fetching terminal details');
      // eslint-disable-next-line no-console
      console.error('Error fetching terminal details:', err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      open={isOpen}
      onCancel={onClose}
      footer={null}
      width={800}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      className="brand-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="POS device details" onClose={onClose} />
      <div className="max-h-[min(70vh,560px)] overflow-y-auto px-6 py-5">
        {loading ? (
          <CollectionLoader size={64} compact />
        ) : error ? (
          <div className="flex flex-col items-center justify-center py-10 text-center">
            <p className="text-sm font-medium text-red-600">{error}</p>
            <Button type="primary" className="mt-4" onClick={fetchTerminalDetails} {...brandPrimaryButtonProps}>
              Retry
            </Button>
          </div>
        ) : terminal ? (
          <>
            <SectionTitle>Device information</SectionTitle>
            <div className="grid grid-cols-1 gap-x-6 md:grid-cols-2">
              <ReadOnlyField label="Device name" value={terminal.name} />
              <ReadOnlyField label="Terminal type" value={terminal.terminal_type} />
              <div className="min-w-0 py-1.5">
                <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Status
                </span>
                <div className="mt-1 flex flex-wrap gap-2">{statusTag(terminal.status)}</div>
              </div>
              <div className="min-w-0 py-1.5">
                <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Connection
                </span>
                <div className="mt-1">{onlineTag(terminal)}</div>
              </div>
              <ReadOnlyField label="Lane" value={terminal.lane_number || 'Unassigned'} />
              <ReadOnlyField label="Location" value={terminal.location} />
            </div>

            <SectionTitle>Network</SectionTitle>
            <div className="grid grid-cols-1 gap-x-6 md:grid-cols-2">
              <ReadOnlyField label="IP address" value={terminal.ip_address} mono />
              <ReadOnlyField label="MAC address" value={terminal.mac_address} mono />
              {terminal.api_key && (
                <div className="md:col-span-2">
                  <ReadOnlyField label="API key" value={terminal.api_key} mono />
                </div>
              )}
            </div>

            <SectionTitle>System</SectionTitle>
            <div className="grid grid-cols-1 gap-x-6 md:grid-cols-2">
              <ReadOnlyField label="Firmware version" value={terminal.firmware_version} />
              <ReadOnlyField
                label="Registered by"
                value={
                  terminal.registered_by?.username ||
                  terminal.registeredBy?.username ||
                  (typeof terminal.registered_by === 'number' ? `User ID: ${terminal.registered_by}` : null)
                }
              />
              <ReadOnlyField
                label="Registered at"
                value={terminal.created_at ? new Date(terminal.created_at).toLocaleString() : null}
              />
              <ReadOnlyField
                label="Last heartbeat"
                value={terminal.last_heartbeat ? new Date(terminal.last_heartbeat).toLocaleString() : null}
              />
            </div>

            {terminal.configuration && Object.keys(terminal.configuration).length > 0 && (
              <>
                <SectionTitle>Configuration</SectionTitle>
                <pre className="overflow-x-auto rounded-md border border-slate-200 bg-slate-50 p-4 text-xs font-mono text-slate-800">
                  {JSON.stringify(terminal.configuration, null, 2)}
                </pre>
              </>
            )}
          </>
        ) : (
          <div className="py-10 text-center text-sm text-slate-500">No terminal data available</div>
        )}
      </div>

      {terminal && !loading && !error && (
        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          {onEdit && (
            <Button type="primary" icon={<Edit size={14} />} onClick={() => onEdit(terminal)} {...brandPrimaryButtonProps}>
              Configure
            </Button>
          )}
          <Button onClick={onClose}>Close</Button>
        </div>
      )}
    </Modal>
  );
}
