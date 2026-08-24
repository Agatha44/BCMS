import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useEffect, useMemo, useState } from 'react';
import dayjs from 'dayjs';
import CollectionLoader from '../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { payrollService } from '../../../services/payrollService.js';
import BenefitTypeFormFields from './BenefitTypeFormFields.jsx';
import { apiService } from '../../../services/api.jsx';

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

const toNumberOrOriginal = (v) => {
  if (v === null || v === undefined || v === '') return v;
  const n = Number(v);
  return Number.isFinite(n) ? n : v;
};

const BenefitTypeDetailsModal = ({ open, record = null, canEdit = false, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(false);
  const [currentRecord, setCurrentRecord] = useState(null);
  const [employmentTypes, setEmploymentTypes] = useState([]);
  const [employmentTypesLoading, setEmploymentTypesLoading] = useState(false);
  const [departments, setDepartments] = useState([]);
  const [departmentsLoading, setDepartmentsLoading] = useState(false);

  const initialValues = useMemo(() => {
    const r = currentRecord || record;
    if (!r) return null;
    return {
      benefit_name: r?.benefit_name ?? '',
      benefit_code: r?.benefit_code ?? '',
      calculation_type: r?.calculation_type ?? 'fixed',
      calculation_value: r?.calculation_value ?? null,
      // Normalize IDs to numbers so Select shows labels reliably
      contract_type: toNumberOrOriginal(r?.contract_type ?? null),
      department_section: toNumberOrOriginal(r?.department_section ?? null),
      job_title_position: r?.job_title_position ?? null,
      start_date: toDayjsOrNull(r?.start_date),
      end_date: toDayjsOrNull(r?.end_date),
    };
  }, [record, currentRecord]);

  useEffect(() => {
    const run = async () => {
      if (!open) return;
      const benefitTypeId = record?.benefit_type_id ?? record?.id ?? null;
      if (!benefitTypeId) {
        setCurrentRecord(record || null);
        return;
      }

      setLoading(true);
      try {
        const response = await payrollService.getBenefitType(benefitTypeId);
        if (response?.success) {
          setCurrentRecord(response.data || null);
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
  }, [open, record?.benefit_type_id, record?.id]);

  useEffect(() => {
    if (open && initialValues) {
      form.setFieldsValue(initialValues);
    }
  }, [open, initialValues, form]);

  useEffect(() => {
    if (!open) return;
    const fetchEmploymentTypes = async () => {
      setEmploymentTypesLoading(true);
      try {
        const response = await apiService.getActiveBridgeEmploymentTypes();
        if (response?.success && Array.isArray(response.data)) {
          setEmploymentTypes(response.data);
        } else if (response?.success && response?.data && Array.isArray(response.data?.items)) {
          setEmploymentTypes(response.data.items);
        } else {
          setEmploymentTypes([]);
        }
      } catch (e) {
        setEmploymentTypes([]);
      } finally {
        setEmploymentTypesLoading(false);
      }
    };
    fetchEmploymentTypes();
  }, [open]);

  useEffect(() => {
    if (!open) return;
    const fetchDepartments = async () => {
      setDepartmentsLoading(true);
      try {
        const response = await apiService.getActiveBmsDepartments();
        if (response?.success && Array.isArray(response.data)) {
          setDepartments(response.data);
        } else {
          setDepartments([]);
        }
      } catch (e) {
        setDepartments([]);
      } finally {
        setDepartmentsLoading(false);
      }
    };
    fetchDepartments();
  }, [open]);

  // If record has department name stored, map it to department_id for the dropdown
  useEffect(() => {
    if (!open) return;
    if (!departments || departments.length === 0) return;
    const current = form.getFieldValue('department_section');
    if (current == null || current === '') return;
    if (typeof current === 'number') return;
    if (/^\d+$/.test(String(current))) return;

    const match = departments.find(
      (d) =>
        String(d?.department_name ?? d?.name ?? '').toLowerCase().trim() === String(current).toLowerCase().trim()
    );
    if (match && (match.department_id ?? match.id) != null) {
      form.setFieldValue('department_section', Number(match.department_id ?? match.id));
    }
  }, [open, departments, form]);

  // If record has contract type name stored, map it to emptype_id for the dropdown
  useEffect(() => {
    if (!open) return;
    if (!employmentTypes || employmentTypes.length === 0) return;
    const current = form.getFieldValue('contract_type');
    if (current == null || current === '') return;
    if (typeof current === 'number') return;
    if (/^\d+$/.test(String(current))) {
      // Normalize numeric string to number for Select matching
      form.setFieldValue('contract_type', Number(current));
      return;
    }

    const match = employmentTypes.find(
      (t) => String(t?.emptype_name ?? '').toLowerCase().trim() === String(current).toLowerCase().trim()
    );
    if (match && (match.emptype_id ?? match.id) != null) {
      form.setFieldValue('contract_type', Number(match.emptype_id ?? match.id));
    }
  }, [open, employmentTypes, form]);

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const benefitTypeId =
        currentRecord?.benefit_type_id ??
        currentRecord?.id ??
        record?.benefit_type_id ??
        record?.id ??
        null;

      const source = currentRecord || record;
      const isActive =
        source?.is_active === 1 || source?.is_active === true || source?.is_active === '1' ? 1 : 0;
      const isTaxable =
        source?.is_taxable === 1 || source?.is_taxable === true || source?.is_taxable === '1' ? 1 : 0;

      const payload = {
        benefit_name: values.benefit_name,
        benefit_code: values.benefit_code,
        calculation_type: values.calculation_type,
        calculation_value:
          values.calculation_value != null && values.calculation_value !== '' ? String(values.calculation_value) : null,
        contract_type: values.contract_type ?? null, // emptype_id
        department_section: values.department_section ?? null,
        job_title_position: values.job_title_position ?? null,
        is_taxable: isTaxable, // not editable in this modal
        is_active: isActive, // status change handled elsewhere
        start_date: toApiDate(values.start_date),
        end_date: toApiDate(values.end_date),
      };

      const res = await payrollService.updateBenefitType(benefitTypeId, payload);

      if (!res?.success) {
        message.error(res?.message || 'Could not save benefit type.');
        return;
      }

      message.success(res.message || 'Benefit type saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save benefit type');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title={canEdit ? 'Benefit Type Details (Edit)' : 'Benefit Type Details'}
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
          <BenefitTypeFormFields
            disabled={!canEdit}
            hideActiveToggle
            employmentTypes={employmentTypes}
            employmentTypesLoading={employmentTypesLoading}
            departments={departments}
            departmentsLoading={departmentsLoading}
          />
        </Form>
      )}
    </Modal>
  );
};

BenefitTypeDetailsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  record: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  canEdit: PropTypes.bool,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default BenefitTypeDetailsModal;

