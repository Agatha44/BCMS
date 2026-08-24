import { Card, Tabs } from 'antd';
import { useMemo, useState } from 'react';
import { useSelector } from 'react-redux';
import '../../styles/common.css';
import { hasPayrollRole } from '../../common/utils/employeeUtils.jsx';
import ArrearsReasonsTab from '../../components/PayrollManagement/Arrears/tabs/ArrearsReasonsTab.jsx';
import EmployeeArrearsTab from '../../components/PayrollManagement/Arrears/tabs/EmployeeArrearsTab.jsx';

const Arrears = () => {
  const selectedRole = useSelector((state) => state.app.selectedRole);

  const canEditArrears = useMemo(() => {
    const role = selectedRole ? String(selectedRole).trim() : '';
    return hasPayrollRole(role) || role.toLowerCase() === 'admin';
  }, [selectedRole]);

  const [activeTab, setActiveTab] = useState('arrears-reasons');

  return (
    <Card>
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={[
          {
            key: 'arrears-reasons',
            label: 'Arrears Reasons',
            children: <ArrearsReasonsTab canEdit={canEditArrears} active={activeTab === 'arrears-reasons'} />,
          },
          {
            key: 'employee-arrears',
            label: 'Employee Arrears',
            children: <EmployeeArrearsTab canEdit={canEditArrears} active={activeTab === 'employee-arrears'} />,
          },
        ]}
      />
    </Card>
  );
};

export default Arrears;

