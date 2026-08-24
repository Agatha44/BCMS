import { useCallback, useState } from 'react';
import { payrollService } from '../../../services/payrollService.js';
import { applyLoanTypeDefaults, recalcLoanFormAmounts } from './employeeLoanUtils.js';

export const useEmployeeLoanForm = (
  form,
  { preserveTotalRepaid = false, applyDefaultsOnTypeChange = false } = {}
) => {
  const [selectedLoanType, setSelectedLoanType] = useState(null);

  const loadLoanType = useCallback(async (loanTypeId) => {
    if (!loanTypeId) {
      setSelectedLoanType(null);
      return null;
    }

    try {
      const response = await payrollService.getLoanType(loanTypeId);
      if (response?.success && response.data) {
        setSelectedLoanType(response.data);
        return response.data;
      }
      setSelectedLoanType(null);
      return null;
    } catch {
      setSelectedLoanType(null);
      return null;
    }
  }, []);

  const onLoanTypeChange = useCallback(
    async (loanTypeId) => {
      const loanType = await loadLoanType(loanTypeId);
      if (!loanType) return;

      if (applyDefaultsOnTypeChange) {
        form.setFieldsValue(applyLoanTypeDefaults(loanType));
      }

      recalcLoanFormAmounts(form, loanType, { preserveTotalRepaid });
    },
    [form, loadLoanType, preserveTotalRepaid, applyDefaultsOnTypeChange]
  );

  const onRecalculate = useCallback(() => {
    recalcLoanFormAmounts(form, selectedLoanType, { preserveTotalRepaid });
  }, [form, selectedLoanType, preserveTotalRepaid]);

  const resetLoanType = useCallback(() => {
    setSelectedLoanType(null);
  }, []);

  return {
    loadLoanType,
    onLoanTypeChange,
    onRecalculate,
    resetLoanType,
  };
};
