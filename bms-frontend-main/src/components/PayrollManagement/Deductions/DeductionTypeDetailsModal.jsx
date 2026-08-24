import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useEffect, useMemo, useState } from 'react';
import dayjs from 'dayjs';
import CollectionLoader from '../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { payrollService } from '../../../services/payrollService.js';
import DeductionTypeFormFields from './DeductionTypeFormFields.jsx';

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

const DeductionTypeDetailsModal = ({ open, record = null, canEdit = false, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(false);
  const [currentRecord, setCurrentRecord] = useState(null);

  const initialValues = useMemo(() => {
    const r = currentRecord || record;
    if (!r) return null;
    return {
      deduction_name: r?.deduction_name ?? '',
      deduction_code: r?.deduction_code ?? '',
      calculation_type: r?.calculation_type ?? 'fixed',
      calculation_value: r?.calculation_value ?? null,
      employee_contribution_percentage: r?.employee_contribution_percentage ?? null,
      employer_contribution_percentage: r?.employer_contribution_percentage ?? null,
      is_before_tax: Number(r?.is_before_tax) === 1,
      is_mandatory: Number(r?.is_mandatory) === 1,
      start_date: toDayjsOrNull(r?.start_date),
      end_date: toDayjsOrNull(r?.end_date),
    };
  }, [record, currentRecord]);

  useEffect(() => {
    const run = async () => {
      if (!open) return;
      const deductionTypeId = record?.deduction_type_id ?? record?.id ?? null;
      if (!deductionTypeId) {
        setCurrentRecord(record || null);
        return;
      }

      setLoading(true);
      try {
        const res = await payrollService.getDeductionType(deductionTypeId);
        if (res?.success) {
          setCurrentRecord(res.data || null);
        } else {
          setCurrentRecord(record || null);
        }
      } catch (e) {
        setCurrentRecord(record || null);
      } finally {
        setLoading(false);
      }
    };

    run();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, record?.deduction_type_id, record?.id]);

  useEffect(() => {
    if (open && initialValues) {
      form.setFieldsValue(initialValues);
    }
  }, [open, initialValues, form]);

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const deductionTypeId = currentRecord?.deduction_type_id ?? currentRecord?.id ?? record?.deduction_type_id ?? record?.id ?? null;

      const source = currentRecord || record;
      const isActive =
        source?.is_active === 1 || source?.is_active === true || source?.is_active === '1' ? 1 : 0;

      const payload = {
        deduction_name: values.deduction_name,
        deduction_code: values.deduction_code,
        calculation_type: values.calculation_type,
        calculation_value:
          values.calculation_value != null && values.calculation_value !== '' ? String(values.calculation_value) : null,
        employee_contribution_percentage:
          values.employee_contribution_percentage != null && values.employee_contribution_percentage !== ''
            ? String(values.employee_contribution_percentage)
            : null,
        employer_contribution_percentage:
          values.employer_contribution_percentage != null && values.employer_contribution_percentage !== ''
            ? String(values.employer_contribution_percentage)
            : null,
        is_before_tax: values.is_before_tax ? 1 : 0,
        is_mandatory: values.is_mandatory ? 1 : 0,
        is_active: isActive,
        start_date: toApiDate(values.start_date),
        end_date: toApiDate(values.end_date),
      };

      const res = await payrollService.updateDeductionType(deductionTypeId, payload);

      if (!res?.success) {
        message.error(res?.message || 'Could not save deduction type.');
        return;
      }

      message.success(res.message || 'Deduction type saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save deduction type');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title={canEdit ? 'Deduction Type Details (Edit)' : 'Deduction Type Details'}
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
          <DeductionTypeFormFields disabled={!canEdit} hideActiveToggle />
        </Form>
      )}
    </Modal>
  );
};

DeductionTypeDetailsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  record: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  canEdit: PropTypes.bool,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default DeductionTypeDetailsModal;

