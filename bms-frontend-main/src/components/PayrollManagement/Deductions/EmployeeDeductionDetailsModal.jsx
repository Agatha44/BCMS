import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import dayjs from 'dayjs';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';
import CollectionLoader from '../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../../services/api.jsx';
import { payrollService } from '../../../services/payrollService.js';
import EmployeeDeductionFormFields from './EmployeeDeductionFormFields.jsx';

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

const EmployeeDeductionDetailsModal = ({ open, record = null, canEdit = false, onClose, onSaved = null }) => {
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

  const loadDeductionTypes = useCallback(async () => {
    const response = await payrollService.getActiveDeductionTypes();
    if (!response?.success) {
      setTypeOptions([]);
      return;
    }
    setTypeOptions(
      (response.data || [])
        .filter((t) => t?.deduction_type_id != null)
        .map((t) => ({
          value: t.deduction_type_id,
          label: `${t.deduction_name} (${t.deduction_code})`,
        }))
    );
  }, []);

  useEffect(() => {
    if (open) {
      loadDeductionTypes();
      loadEmployees();
    }
  }, [open, loadDeductionTypes, loadEmployees]);

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
      deduction_type_id: r.deduction_type_id,
      total_deduction_amount: numForm(r.total_deduction_amount),
      employee_contribution_percentage: numForm(r.employee_contribution_percentage),
      employee_contribution_amount: numForm(r.employee_contribution_amount),
      employer_contribution_percentage: numForm(r.employer_contribution_percentage),
      employer_contribution_amount: numForm(r.employer_contribution_amount),
      is_before_tax: Number(r.is_before_tax) === 1,
      effective_start_date: toDayjsOrNull(r.effective_start_date),
      effective_end_date: toDayjsOrNull(r.effective_end_date),
      is_active: Number(r.is_active) === 1,
      notes: r.notes,
    };
  }, [record, currentRecord]);

  useEffect(() => {
    const run = async () => {
      if (!open) return;
      const employeeDeductionId = record?.employee_deduction_id;
      if (!employeeDeductionId) {
        setCurrentRecord(record || null);
        return;
      }

      setLoading(true);
      try {
        const response = await payrollService.getEmployeeDeduction(employeeDeductionId);
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
  }, [open, record?.employee_deduction_id]);

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

      const employeeDeductionId = currentRecord?.employee_deduction_id ?? record?.employee_deduction_id;

      const source = currentRecord || record;
      const isActive =
        source?.is_active === 1 || source?.is_active === true || source?.is_active === '1' ? 1 : 0;

      const payload = {
        employee_national_id: values.employee_national_id,
        deduction_type_id: values.deduction_type_id,
        total_deduction_amount: numOrNull(values.total_deduction_amount),
        employee_contribution_percentage: numOrNull(values.employee_contribution_percentage),
        employee_contribution_amount: numOrNull(values.employee_contribution_amount),
        employer_contribution_percentage: numOrNull(values.employer_contribution_percentage),
        employer_contribution_amount: numOrNull(values.employer_contribution_amount),
        is_before_tax: values.is_before_tax ? 1 : 0,
        effective_start_date: toApiDate(values.effective_start_date),
        effective_end_date: toApiDate(values.effective_end_date),
        is_active: isActive,
        notes: values.notes ?? null,
      };

      const response = await payrollService.updateEmployeeDeduction(employeeDeductionId, payload);

      if (!response?.success) {
        message.error(response?.message || 'Could not save employee deduction.');
        return;
      }

      message.success(response?.message || 'Employee deduction saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save employee deduction');
    } finally {
      setSaving(false);
    }
  };

  const source = currentRecord || record;
  const headerExtra =
    source?.employee_name || source?.deduction_name
      ? `${source.employee_name ?? ''}${source.employee_name && source.deduction_name ? ' · ' : ''}${source.deduction_name ?? ''}`
      : null;

  return (
    <Modal
      title={
        <span>
          {canEdit ? 'Employee Deduction (Edit)' : 'Employee Deduction Details'}
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
          <EmployeeDeductionFormFields
            disabled={!canEdit}
            deductionTypeOptions={typeOptions}
            hideActiveToggle
            employees={employeesForForm}
            employeesLoading={employeesLoading}
          />
        </Form>
      )}
    </Modal>
  );
};

EmployeeDeductionDetailsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  record: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  canEdit: PropTypes.bool,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default EmployeeDeductionDetailsModal;
