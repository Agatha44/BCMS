import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import dayjs from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import CollectionLoader from '../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { payrollService } from '../../../services/payrollService.js';
import LoanTypeFormFields from './LoanTypeFormFields.jsx';

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

const LoanTypeDetailsModal = ({ open, record = null, canEdit = false, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(false);
  const [currentRecord, setCurrentRecord] = useState(null);

  const initialValues = useMemo(() => {
    const r = currentRecord || record;
    if (!r) return null;
    const hasInterest = Number(r?.has_interest) === 1;
    return {
      loan_name: r?.loan_name ?? '',
      loan_code: r?.loan_code ?? '',
      has_interest: hasInterest,
      interest_percentage: hasInterest ? r?.interest_percentage ?? null : null,
      interest_calculation_method: hasInterest ? r?.interest_calculation_method ?? 'flat' : 'flat',
      minimum_loan_amount: r?.minimum_loan_amount ?? null,
      maximum_loan_amount: r?.maximum_loan_amount ?? null,
      minimum_repayment_months: r?.minimum_repayment_months ?? null,
      maximum_repayment_months: r?.maximum_repayment_months ?? null,
      priority: r?.priority ?? 0,
      contract_type: r?.contract_type != null ? Number(r.contract_type) : null,
      department_section: r?.department_section != null ? Number(r.department_section) : null,
      job_title_position: r?.job_title_position ?? null,
      start_date: toDayjsOrNull(r?.start_date),
      end_date: toDayjsOrNull(r?.end_date),
    };
  }, [record, currentRecord]);

  useEffect(() => {
    const run = async () => {
      if (!open) return;
      const loanTypeId = record?.loan_type_id ?? record?.id ?? null;
      if (!loanTypeId) {
        setCurrentRecord(record || null);
        return;
      }

      setLoading(true);
      try {
        const response = await payrollService.getLoanType(loanTypeId);
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
  }, [open, record?.loan_type_id, record?.id]);

  useEffect(() => {
    if (open && initialValues) {
      form.setFieldsValue(initialValues);
    }
  }, [open, initialValues, form]);

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const loanTypeId = currentRecord?.loan_type_id ?? currentRecord?.id ?? record?.loan_type_id ?? record?.id ?? null;
      const source = currentRecord || record;
      const isActive = source?.is_active === 1 || source?.is_active === true || source?.is_active === '1' ? 1 : 0;

      const payload = {
        loan_name: values.loan_name,
        loan_code: values.loan_code,
        has_interest: values.has_interest ? 1 : 0,
        interest_percentage:
          values.has_interest && values.interest_percentage != null && values.interest_percentage !== ''
            ? String(values.interest_percentage)
            : null,
        interest_calculation_method: values.has_interest ? values.interest_calculation_method ?? 'flat' : null,
        minimum_loan_amount:
          values.minimum_loan_amount != null && values.minimum_loan_amount !== '' ? String(values.minimum_loan_amount) : null,
        maximum_loan_amount:
          values.maximum_loan_amount != null && values.maximum_loan_amount !== '' ? String(values.maximum_loan_amount) : null,
        minimum_repayment_months: values.minimum_repayment_months ?? null,
        maximum_repayment_months: values.maximum_repayment_months ?? null,
        priority: values.priority ?? null,
        contract_type: values.contract_type ?? null,
        department_section: values.department_section ?? null,
        job_title_position: values.job_title_position ?? null,
        is_active: isActive,
        start_date: toApiDate(values.start_date),
        end_date: toApiDate(values.end_date),
      };

      const response = await payrollService.updateLoanType(loanTypeId, payload);
      if (!response?.success) {
        message.error(response?.message || 'Could not save loan type.');
        return;
      }

      message.success(response?.message || 'Loan type saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save loan type');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title={canEdit ? 'Loan Type Details (Edit)' : 'Loan Type Details'}
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
              <Button key="save" type="primary" onClick={save} loading={saving} style={{ backgroundColor: '#962E32', borderColor: '#962E32' }}>
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
          <LoanTypeFormFields disabled={!canEdit} hideActiveToggle />
        </Form>
      )}
    </Modal>
  );
};

LoanTypeDetailsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  record: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  canEdit: PropTypes.bool,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default LoanTypeDetailsModal;

