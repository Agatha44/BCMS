import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import dayjs from 'dayjs';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';
import CollectionLoader from '../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../../services/api.jsx';
import { payrollService } from '../../../services/payrollService.js';
import EmployeeBenefitFormFields from './EmployeeBenefitFormFields.jsx';

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

const numForm = (v) => {
  if (v === null || v === undefined || v === '') return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
};

const EmployeeBenefitDetailsModal = ({ open, record = null, canEdit = false, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(false);
  const [currentRecord, setCurrentRecord] = useState(null);
  const [typeOptions, setTypeOptions] = useState([]);
  const [staffList, setStaffList] = useState([]);
  const [employeesLoading, setEmployeesLoading] = useState(false);

  const loadEmployees = useCallback(async () => {
    setEmployeesLoading(true);
    try {
      const response = await apiService.getActiveBridgeEmployees({ per_page: 1000 });
      if (response.success && response.data) {
        const { items } = extractArrayFromResponse(response.data);
        setStaffList(items || []);
      } else {
        setStaffList([]);
      }
    } catch (e) {
      setStaffList([]);
      message.error(e.message || 'Failed to load employees');
    } finally {
      setEmployeesLoading(false);
    }
  }, [message]);

  const loadBenefitTypes = useCallback(async () => {
    const response = await payrollService.getActiveBenefitTypes();
    if (!response?.success) {
      setTypeOptions([]);
      return;
    }
    setTypeOptions(
      (response.data || [])
        .filter((t) => t?.benefit_type_id != null)
        .map((t) => ({
          value: t.benefit_type_id,
          label: `${t.benefit_name} (${t.benefit_code})`,
        }))
    );
  }, []);

  useEffect(() => {
    if (open) {
      loadBenefitTypes();
      loadEmployees();
    }
  }, [open, loadBenefitTypes, loadEmployees]);

  const employeesForForm = useMemo(() => {
    const list = [...staffList];
    const r = currentRecord || record;
    if (!r?.employee_national_id) return list;
    const nid = String(r.employee_national_id);
    const has = list.some((e) => String(e.national_id ?? e.nationalId ?? '') === nid);
    if (has) return list;
    return [
      ...list,
      {
        national_id: r.employee_national_id,
        pfno: r.pf_number ?? r.pfno,
        pf_number: r.pf_number,
        full_name: r.employee_name,
      },
    ];
  }, [staffList, currentRecord, record]);

  const initialValues = useMemo(() => {
    const r = currentRecord || record;
    if (!r) return null;
    return {
      employee_national_id: r.employee_national_id != null ? String(r.employee_national_id) : undefined,
      benefit_type_id: r.benefit_type_id,
      benefit_amount: numForm(r.benefit_amount),
      taxable_amount: numForm(r.taxable_amount),
      tax_free_amount: numForm(r.tax_free_amount),
      effective_start_date: toDayjsOrNull(r.effective_start_date),
      effective_end_date: toDayjsOrNull(r.effective_end_date),
      is_active: Number(r.is_active) === 1,
      notes: r.notes,
    };
  }, [record, currentRecord]);

  useEffect(() => {
    const run = async () => {
      if (!open) return;
      const employeeBenefitId = record?.employee_benefit_id;
      if (!employeeBenefitId) {
        setCurrentRecord(record || null);
        return;
      }

      setLoading(true);
      try {
        const response = await payrollService.getEmployeeBenefit(employeeBenefitId);
        if (response?.success) {
          setCurrentRecord(response.data || null);
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
  }, [open, record?.employee_benefit_id]);

  useEffect(() => {
    if (open && initialValues) {
      form.setFieldsValue(initialValues);
    }
  }, [open, initialValues, form]);

  const numOrNull = (v) => {
    if (v === undefined || v === null || v === '') return null;
    return String(v);
  };

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const employeeBenefitId = currentRecord?.employee_benefit_id ?? record?.employee_benefit_id;

      const source = currentRecord || record;
      const isActive =
        source?.is_active === 1 || source?.is_active === true || source?.is_active === '1' ? 1 : 0;

      const payload = {
        employee_national_id: values.employee_national_id,
        benefit_type_id: values.benefit_type_id,
        benefit_amount: numOrNull(values.benefit_amount),
        taxable_amount: numOrNull(values.taxable_amount),
        tax_free_amount: numOrNull(values.tax_free_amount),
        effective_start_date: toApiDate(values.effective_start_date),
        effective_end_date: toApiDate(values.effective_end_date),
        is_active: isActive,
        notes: values.notes ?? null,
      };

      const response = await payrollService.updateEmployeeBenefit(employeeBenefitId, payload);

      if (!response?.success) {
        message.error(response?.message || 'Could not save employee benefit.');
        return;
      }

      message.success(response?.message || 'Employee benefit saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save employee benefit');
    } finally {
      setSaving(false);
    }
  };

  const source = currentRecord || record;
  const headerExtra =
    source?.employee_name || source?.benefit_name
      ? `${source.employee_name ?? ''}${source.employee_name && source.benefit_name ? ' · ' : ''}${source.benefit_name ?? ''}`
      : null;

  return (
    <Modal
      title={
        <span>
          {canEdit ? 'Employee Benefit (Edit)' : 'Employee Benefit Details'}
          {headerExtra ? <span className="text-gray-500 font-normal text-sm block">{headerExtra}</span> : null}
        </span>
      }
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => {
        setCurrentRecord(null);
        setStaffList([]);
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
          <EmployeeBenefitFormFields
            disabled={!canEdit}
            benefitTypeOptions={typeOptions}
            hideActiveToggle
            employees={employeesForForm}
            employeesLoading={employeesLoading}
          />
        </Form>
      )}
    </Modal>
  );
};

EmployeeBenefitDetailsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  record: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  canEdit: PropTypes.bool,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default EmployeeBenefitDetailsModal;

