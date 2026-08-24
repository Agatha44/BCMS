import { Card, Tabs } from 'antd';
import { useMemo, useState } from 'react';
import { useSelector } from 'react-redux';
import '../../styles/common.css';
import { hasPayrollRole } from '../../common/utils/employeeUtils.jsx';
import BenefitTypesTab from '../../components/PayrollManagement/Benefits/tabs/BenefitTypesTab.jsx';
import EmployeeBenefitsTab from '../../components/PayrollManagement/Benefits/tabs/EmployeeBenefitsTab.jsx';

const Benefits = () => {
  const selectedRole = useSelector((state) => state.app.selectedRole);

  const canEditBenefits = useMemo(() => {
    const role = selectedRole ? String(selectedRole).trim() : '';
    return hasPayrollRole(role) || role.toLowerCase() === 'admin';
  }, [selectedRole]);

  const [activeTab, setActiveTab] = useState('benefit-types');

  return (
    <Card>
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={[
          {
            key: 'benefit-types',
            label: 'Benefit Types',
            children: <BenefitTypesTab canEdit={canEditBenefits} active={activeTab === 'benefit-types'} />,
          },
          {
            key: 'employee-benefits',
            label: 'Employee Benefits',
            children: <EmployeeBenefitsTab canEdit={canEditBenefits} active={activeTab === 'employee-benefits'} />,
          },
        ]}
      />
    </Card>
  );
};

export default Benefits;

