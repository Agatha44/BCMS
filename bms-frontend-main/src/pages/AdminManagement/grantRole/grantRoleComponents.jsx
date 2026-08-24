import { Button, DatePicker, Form, Input, Modal, Select } from 'antd';

import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import {
  BRAND,
  DATE_FORMAT,
  EMPTY_VALUE,
  NIDA_DIGIT_LENGTH,
  MODAL_STYLES,
  disablePastDates,
  formatRoleDate,
  isPastDate,
} from './grantRoleUtils.js';

export const renderRoleDateCell = (value) => {
  const formatted = formatRoleDate(value);
  return formatted ? (
    <span className="text-sm text-black">{formatted}</span>
  ) : (
    <span className="text-sm text-slate-400">{EMPTY_VALUE}</span>
  );
};

export const BrandModalHeader = ({ title, onClose }) => (
  <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
    <h2 className="m-0 text-sm font-semibold leading-none text-white">{title}</h2>
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
);

export const GrantRoleModal = ({
  open,
  onCancel,
  width,
  children,
  maskClosable = true,
  keyboard = true,
  afterOpenChange,
}) => (
  <Modal
    open={open}
    onCancel={onCancel}
    footer={null}
    width={width}
    centered
    destroyOnHidden
    title={null}
    closable={false}
    maskClosable={maskClosable}
    keyboard={keyboard}
    styles={MODAL_STYLES}
    afterOpenChange={afterOpenChange}
  >
    {children}
  </Modal>
);

export const SectionHeading = ({ children, className = 'mb-3' }) => (
  <h4
    className={`${className} border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]`}
    style={{ color: BRAND }}
  >
    {children}
  </h4>
);

export const Field = ({ label, value }) => (
  <div className="min-w-0 py-1.5">
    <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <div className="mt-0.5 text-sm font-medium text-black">
      {value != null && value !== '' ? value : <span className="text-slate-400">{EMPTY_VALUE}</span>}
    </div>
  </div>
);

export const FormLabel = ({ children, className = '' }) => (
  <span className={`text-xs font-semibold tracking-[0.01em] ${className}`.trim()} style={{ color: BRAND }}>
    {children}
  </span>
);

export const ModalFooter = ({ children }) => (
  <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
    {children}
  </div>
);

export const FormFooter = ({ children }) => (
  <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-0 pt-4">
    {children}
  </div>
);

export const LoadingState = () => <CollectionLoader size={64} compact />;

export const LoadingOverlay = () => (
  <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/90">
    <CollectionLoader size={64} compact />
  </div>
);

export const AssignRoleFields = ({
  assignRoleIds,
  onRoleChange,
  assignStartDate,
  onStartChange,
  assignEndDate,
  onEndChange,
  roleOptions,
  loadingRoles,
  disabled,
}) => (
  <div className="space-y-3">
    <div>
      <FormLabel className="mb-1 block">Roles</FormLabel>
      <Select
        mode="multiple"
        placeholder="Select one or more roles"
        showSearch
        optionFilterProp="label"
        loading={loadingRoles}
        value={assignRoleIds}
        onChange={onRoleChange}
        options={roleOptions}
        className="mt-1 w-full"
        disabled={disabled}
      />
    </div>
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <div>
        <FormLabel className="mb-1 block">Start Date</FormLabel>
        <DatePicker
          className="mt-1 w-full"
          format={DATE_FORMAT}
          value={assignStartDate}
          onChange={(date) => {
            onStartChange(date);
            if (date && assignEndDate && assignEndDate.isBefore(date, 'day')) {
              onEndChange(null);
            }
            if (assignEndDate && isPastDate(assignEndDate)) {
              onEndChange(null);
            }
          }}
          allowClear
          disabled={disabled}
          placeholder="Optional"
          disabledDate={disablePastDates}
        />
      </div>
      <div>
        <FormLabel className="mb-1 block">End Date</FormLabel>
        <DatePicker
          className="mt-1 w-full"
          format={DATE_FORMAT}
          value={assignEndDate}
          onChange={onEndChange}
          allowClear
          disabled={disabled}
          placeholder="Optional"
          disabledDate={(current) => {
            if (!current) return false;
            if (disablePastDates(current)) return true;
            if (assignStartDate && current.isBefore(assignStartDate.startOf('day'), 'day')) return true;
            return false;
          }}
        />
      </div>
    </div>
    <p className="m-0 text-[11px] text-slate-500">
      Optional assignment window. Dates cannot be in the past; end date must be on or after start date.
    </p>
  </div>
);

export const BridgeUserFormFields = ({ mode = 'create', disabled = false }) => (
  <>
    <SectionHeading>User Information</SectionHeading>
    <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
      {mode === 'create' ? (
        <Form.Item
          name="nida"
          label={<FormLabel>NIDA</FormLabel>}
          normalize={(value) =>
            value != null && value !== ''
              ? String(value).replace(/\D/g, '').slice(0, NIDA_DIGIT_LENGTH)
              : value
          }
          rules={[
            { required: true, message: 'NIDA is required' },
            { pattern: /^\d+$/, message: 'NIDA must contain numbers only' },
            { len: NIDA_DIGIT_LENGTH, message: 'Invalid NIDA number' },
          ]}
          className="sm:col-span-2"
        >
          <Input
            placeholder="Enter NIDA number"
            maxLength={NIDA_DIGIT_LENGTH}
            inputMode="numeric"
            disabled={disabled}
          />
        </Form.Item>
      ) : (
        <Form.Item name="nida" label={<FormLabel>NIDA</FormLabel>} className="sm:col-span-2">
          <Input disabled />
        </Form.Item>
      )}

      <Form.Item name="pf_number" label={<FormLabel>PF Number</FormLabel>}>
        <Input placeholder="PF12345" disabled={disabled} />
      </Form.Item>

      <Form.Item
        name="first_name"
        label={<FormLabel>First Name</FormLabel>}
        rules={[{ required: true, message: 'First name is required' }]}
      >
        <Input placeholder="John" disabled={disabled} />
      </Form.Item>

      <Form.Item name="middle_name" label={<FormLabel>Middle Name</FormLabel>}>
        <Input placeholder="Michael" disabled={disabled} />
      </Form.Item>

      <Form.Item
        name="surname"
        label={<FormLabel>Surname</FormLabel>}
        rules={[{ required: true, message: 'Surname is required' }]}
      >
        <Input placeholder="Doe" disabled={disabled} />
      </Form.Item>

      <Form.Item
        name="email"
        label={<FormLabel>Email</FormLabel>}
        rules={[
          { required: true, message: 'Email is required' },
          { type: 'email', message: 'Enter a valid email address' },
        ]}
      >
        <Input placeholder="john.doe@example.com" disabled={disabled} />
      </Form.Item>

      <Form.Item
        name="phone"
        label={<FormLabel>Phone</FormLabel>}
        rules={[{ required: true, message: 'Phone number is required' }]}
      >
        <Input placeholder="255712345678" disabled={disabled} />
      </Form.Item>
    </div>
  </>
);

export const BrandPrimaryButton = ({ children, loading, disabled, icon, htmlType, onClick }) => (
  <Button
    type="primary"
    htmlType={htmlType}
    icon={icon}
    loading={loading}
    disabled={disabled}
    onClick={onClick}
    className="btn-standard-primary"
  >
    {children}
  </Button>
);
