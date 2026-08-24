import { Card, Tabs } from 'antd';
import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';

import RoleManagement from '../RoleManagement.jsx';
import LegacyRolesList from './LegacyRolesList.jsx';
import '../../../styles/common.css';

export const ROLE_TAB_KEYS = {
  LEGACY: 'legacy-roles',
  BMS: 'bms-roles',
};

const TAB_KEYS = Object.values(ROLE_TAB_KEYS);

const ManageRolesList = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const tabFromUrl = searchParams.get('tab');
  const [activeTab, setActiveTab] = useState(
    TAB_KEYS.includes(tabFromUrl) ? tabFromUrl : ROLE_TAB_KEYS.LEGACY
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
    <div>
      <Card className="manage-roles-container">
        <Tabs
          activeKey={activeTab}
          onChange={handleTabChange}
          items={[
            {
              key: ROLE_TAB_KEYS.LEGACY,
              label: 'Legacy Roles',
              children: <LegacyRolesList />,
            },
            {
              key: ROLE_TAB_KEYS.BMS,
              label: 'BMS Roles',
              children: <RoleManagement />,
            },
          ]}
        />
      </Card>
    </div>
  );
};

export default ManageRolesList;
