import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useEffect, useMemo, useState } from 'react';
import dayjs from 'dayjs';
import CollectionLoader from '../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { payrollService } from '../../../services/payrollService.js';
import ArrearsReasonFormFields from './ArrearsReasonFormFields.jsx';

const toApiDate = (d) => {
  if (!d) return null;
  if (typeof d.format === 'function') return d.format('YYYY-MM-DD');
  return null;
};

const toDayjsOrNull = (v) => {
  if (!v) return null;
  const d = dayjs(v);
  return d.isValid() ? d : null;
};

const ArrearsReasonDetailsModal = ({ open, record = null, canEdit = false, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(false);
  const [currentRecord, setCurrentRecord] = useState(null);

  const initialValues = useMemo(() => {
    const r = currentRecord || record;
    if (!r) return null;
    return {
      reason_name: r?.reason_name ?? '',
      reason_code: r?.reason_code ?? '',
      is_taxable: Number(r?.is_taxable) === 1 ? 1 : 0,
      start_date: toDayjsOrNull(r?.start_date),
      end_date: toDayjsOrNull(r?.end_date),
    };
  }, [record, currentRecord]);

  useEffect(() => {
    const run = async () => {
      if (!open) return;
      const arrearsReasonId = record?.arrears_reason_id ?? record?.id ?? null;
      if (!arrearsReasonId) {
        setCurrentRecord(record || null);
        return;
      }

      setLoading(true);
      try {
        const res = await payrollService.getArrearsReason(arrearsReasonId);
        if (res?.success) {
          setCurrentRecord(res.data || null);
        } else {
          setCurrentRecord(record || null);
        }
      } catch {
        setCurrentRecord(record || null);
      } finally {
        setLoading(false);
      }
    };

    run();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, record?.arrears_reason_id, record?.id]);

  useEffect(() => {
    if (open && initialValues) {
      form.setFieldsValue(initialValues);
    }
  }, [open, initialValues, form]);

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const arrearsReasonId =
        currentRecord?.arrears_reason_id ?? currentRecord?.id ?? record?.arrears_reason_id ?? record?.id ?? null;

      const source = currentRecord || record;
      const isActive =
        source?.is_active === 1 || source?.is_active === true || source?.is_active === '1' ? 1 : 0;

      const payload = {
        reason_name: values.reason_name,
        reason_code: values.reason_code,
        is_taxable: Number(values.is_taxable) === 1 ? 1 : 0,
        is_active: isActive,
        start_date: toApiDate(values.start_date),
        end_date: toApiDate(values.end_date),
      };

      const res = await payrollService.updateArrearsReason(arrearsReasonId, payload);
      if (!res?.success) {
        message.error(res?.message || 'Could not save arrears reason.');
        return;
      }

      message.success(res?.message || 'Arrears reason saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save arrears reason');
    } finally {
      setSaving(false);
    }
  };

  const source = currentRecord || record;
  const headerExtra = source?.reason_name ? `${source.reason_name}${source.reason_code ? ` (${source.reason_code})` : ''}` : null;

  return (
    <Modal
      title={
        <span>
          {canEdit ? 'Arrears Reason (Edit)' : 'Arrears Reason Details'}
          {headerExtra ? <span className="text-gray-500 font-normal text-sm block">{headerExtra}</span> : null}
        </span>
      }
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => {
        setCurrentRecord(null);
        form.resetFields();
      }}
      width={1200}
      styles={{ body: { maxHeight: 'none', overflow: 'visible', paddingTop: 16 } }}
      footer={
        canEdit
          ? [
              <Button key="close" onClick={onClose} disabled={saving}>
                Close
              </Button>,
              <Button key="save" type="primary" onClick={save} loading={saving}>
                Update
              </Button>,
            ]
          : [
              <Button key="close" onClick={onClose}>
                Close
              </Button>,
            ]
      }
    >
      {loading ? (
        <CollectionLoader size={64} compact />
      ) : (
        <Form form={form} layout="vertical">
          <ArrearsReasonFormFields disabled={!canEdit} hideActiveToggle />
        </Form>
      )}
    </Modal>
  );
};

ArrearsReasonDetailsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  record: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  canEdit: PropTypes.bool,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default ArrearsReasonDetailsModal;

