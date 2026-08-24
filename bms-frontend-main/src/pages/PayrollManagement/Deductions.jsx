import { Card, Tabs } from 'antd';
import { useMemo, useState } from 'react';
import { useSelector } from 'react-redux';
import '../../styles/common.css';
import { hasPayrollRole } from '../../common/utils/employeeUtils.jsx';
import DeductionTypesTab from '../../components/PayrollManagement/Deductions/tabs/DeductionTypesTab.jsx';
import EmployeeDeductionsTab from '../../components/PayrollManagement/Deductions/tabs/EmployeeDeductionsTab.jsx';

const Deductions = () => {
  const selectedRole = useSelector((state) => state.app.selectedRole);

  const canEditDeductionTypes = useMemo(() => {
    const role = selectedRole ? String(selectedRole).trim() : '';
    return (
      hasPayrollRole(role) ||
      role.toLowerCase() === 'admin'
    );
  }, [selectedRole]);

  const [activeTab, setActiveTab] = useState('deduction-types');

  return (
    <Card>
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={[
          {
            key: 'deduction-types',
            label: 'Deduction Types',
            children: <DeductionTypesTab canEdit={canEditDeductionTypes} active={activeTab === 'deduction-types'} />,
          },
          {
            key: 'employee-deductions',
            label: 'Employee Deductions',
            children: <EmployeeDeductionsTab canEdit={canEditDeductionTypes} active={activeTab === 'employee-deductions'} />,
          },
        ]}
      />
    </Card>
  );
};

export default Deductions;

