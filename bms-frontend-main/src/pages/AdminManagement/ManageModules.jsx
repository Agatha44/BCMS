import { Card, Tabs } from 'antd';
import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';

import ModuleManagement from './ModuleManagement.jsx';
import ModuleMenu from './ModuleMenu.jsx';
import RoleModuleMenu from './RoleModuleMenu.jsx';
import '../../styles/common.css';

export const MODULE_TAB_KEYS = {
  REGISTER: 'register-module',
  MENU: 'module-menu',
  ROLE_MENU: 'role-module-menu',
};

const TAB_KEYS = Object.values(MODULE_TAB_KEYS);

const ManageModules = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const tabFromUrl = searchParams.get('tab');
  const [activeTab, setActiveTab] = useState(
    TAB_KEYS.includes(tabFromUrl) ? tabFromUrl : MODULE_TAB_KEYS.REGISTER
  );

  useEffect(() => {
    if (tabFromUrl && TAB_KEYS.includes(tabFromUrl) && tabFromUrl !== activeTab) {
      setActiveTab(tabFromUrl);
    }
  }, [tabFromUrl, activeTab]);

  const handleTabChange = (key) => {
    setActiveTab(key);
    setSearchParams({ tab: key }, { replace: true });
  };

  return (
    <Card className="manage-modules-container">
      <Tabs
        activeKey={activeTab}
        onChange={handleTabChange}
        items={[
          {
            key: MODULE_TAB_KEYS.REGISTER,
            label: 'Register Module',
            children: <ModuleManagement />,
          },
          {
            key: MODULE_TAB_KEYS.MENU,
            label: 'Module Menu',
            children: <ModuleMenu />,
          },
          {
            key: MODULE_TAB_KEYS.ROLE_MENU,
            label: 'Role Module Menu',
            children: <RoleModuleMenu />,
          },
        ]}
      />
    </Card>
  );
};

export default ManageModules;
