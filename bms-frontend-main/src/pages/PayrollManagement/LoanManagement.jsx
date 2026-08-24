import { Card, Tabs } from 'antd';
import { useMemo, useState } from 'react';
import { useSelector } from 'react-redux';
import '../../styles/common.css';
import { hasPayrollRole } from '../../common/utils/employeeUtils.jsx';
import EmployeeLoansTab from '../../components/PayrollManagement/Loans/tabs/EmployeeLoansTab.jsx';
import LoanTypesTab from '../../components/PayrollManagement/Loans/tabs/LoanTypesTab.jsx';

const LoanManagement = () => {
  const selectedRole = useSelector((state) => state.app.selectedRole);

  const canEditLoanTypes = useMemo(() => {
    const role = selectedRole ? String(selectedRole).trim() : '';
    return hasPayrollRole(role) || role.toLowerCase() === 'admin';
  }, [selectedRole]);

  const [activeTab, setActiveTab] = useState('loan-types');

  return (
    <Card>
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={[
          {
            key: 'loan-types',
            label: 'Loan Types',
            children: <LoanTypesTab canEdit={canEditLoanTypes} active={activeTab === 'loan-types'} />,
          },
          {
            key: 'employee-loans',
            label: 'Employee Loans',
            children: <EmployeeLoansTab canEdit={canEditLoanTypes} active={activeTab === 'employee-loans'} />,
          },
        ]}
      />
    </Card>
  );
};

export default LoanManagement;

