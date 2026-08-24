import { useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { 
  Card, 
  App, 
  Typography,
  Modal,
  Tabs
} from 'antd';
import { apiService } from '../../../services/api.jsx';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';
import { CONFIG } from '../../../config';
import { getAuthToken } from '../../../modules/auth/authSession.js';
import CollectionLoader from '../../CollectionManagement/components/CollectionLoader.jsx';
import EmployeeDetailsTab from './EmployeeDetailsTab.jsx';
import EmployeeDocumentsTab from './employeeDocumentsTab.jsx';
import './EmployeeProfile.css';

const { Text } = Typography;

const resolveNationalId = (obj) =>
  obj?.national_id || obj?.nida;

/** Normalize bridge GET-by-id payload to a single employee row. */
const parseBridgeEmployeeRecord = (payload) => {
  if (!payload || typeof payload !== 'object') return null;
  const { items } = extractArrayFromResponse(payload);
  if (items.length > 0) return items[0];
  if (payload.employee && typeof payload.employee === 'object') return payload.employee;
  if (Array.isArray(payload.employees) && payload.employees.length > 0) return payload.employees[0];
  if (resolveNationalId(payload)) return payload;
  return null;
};

const EmployeeProfile = () => {
  const { message } = App.useApp();
  const currentUser = useSelector((state) => state.auth.user);
  const [loading, setLoading] = useState(false);
  const [employeeData, setEmployeeData] = useState(null);
  const [userRoles, setUserRoles] = useState([]);
  const [uploadingPhoto, setUploadingPhoto] = useState(false);
  const [photoUrl, setPhotoUrl] = useState(null);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewImage, setPreviewImage] = useState('');
  const [profileTab, setProfileTab] = useState('details');

  useEffect(() => {
    if (!currentUser) return;
    fetchEmployeeProfile();
  }, [currentUser]);

  const fetchEmployeeProfile = async () => {
    if (!currentUser) {
      return;
    }

    setLoading(true);
    try {
      
      const nationalId = resolveNationalId(currentUser);

      console.log('nationalId', nationalId);

      let employee = null;

      if (nationalId) {
        const response = await apiService.getBridgeEmployeeById(nationalId);
        if (response.success && response.data) {
          employee = parseBridgeEmployeeRecord(response.data);
        }
      }

      if (employee) {
        setEmployeeData(employee);
        if (employee.photo_url || employee.pic || employee.profile_picture) {
          setPhotoUrl(employee.photo_url || employee.pic || employee.profile_picture);
        }
        if (employee.national_id) {
          fetchEmployeeRoles(employee.national_id);
        }
      } else {
        message.warning('Employee profile not found. Please contact your administrator.');
        setEmployeeData(null);
      }
    } catch (error) {
      console.error('Error fetching employee profile:', error);
      message.error('An error occurred while fetching employee profile');
      setEmployeeData(null);
    } finally {
      setLoading(false);
    }
  };

  const fetchEmployeeRoles = async (nationalId) => {
    try {
      const response = await apiService.getBridgeEmployeeRoles(nationalId);
      if (response.success && response.data) {
        const roles = Array.isArray(response.data) 
          ? response.data 
          : (response.data.roles || []);
        
        const roleNames = roles.map(role => {
          if (typeof role === 'string') {
            return role;
          } else if (role?.role_name) {
            return role.role_name;
          } else if (role?.name) {
            return role.name;
          } else if (role?.role) {
            return typeof role.role === 'string' ? role.role : (role.role?.role_name || role.role?.name);
          }
          return null;
        }).filter(Boolean);
        
        setUserRoles(roleNames);
      }
    } catch (error) {
      console.error('Error fetching employee roles:', error);
    }
  };

  const getBase64 = (file) => {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.readAsDataURL(file);
      reader.onload = () => resolve(reader.result);
      reader.onerror = error => reject(error);
    });
  };

  const updateEmployeePhoto = async (photoBase64) => {
    try {
      const updateData = {
        photo_url: photoBase64,
        pic: photoBase64
      };
      
      const response = await apiService.updateBridgeEmployee(employeeData.national_id, updateData);
      
      if (response.success) {
        setEmployeeData({ ...employeeData, ...updateData });
        message.success('Photo updated successfully!');
      } else {
        setEmployeeData({ ...employeeData, ...updateData });
        message.success('Photo updated locally!');
      }
    } catch (error) {
      console.error('Error updating employee photo:', error);
      setEmployeeData({ ...employeeData, photo_url: photoBase64, pic: photoBase64 });
      message.success('Photo updated locally!');
    }
  };

  const handlePhotoUpload = async (file) => {
    const isJpgOrPng = file.type === 'image/jpeg' || file.type === 'image/png' || file.type === 'image/jpg';
    if (!isJpgOrPng) {
      message.error('You can only upload JPG/PNG files!');
      return false;
    }
    const isLt2M = file.size / 1024 / 1024 < 2;
    if (!isLt2M) {
      message.error('Image must be smaller than 2MB!');
      return false;
    }

    setUploadingPhoto(true);
    try {
      const base64 = await getBase64(file);
      setPhotoUrl(base64);

      const formData = new FormData();
      formData.append('photo', file);
      formData.append('national_id', employeeData.national_id);

      try {
        const token = getAuthToken();
        const baseURL = CONFIG.API_CONFIG.BASE_URL;
        const response = await fetch(`${baseURL}/api/bridge-employees/${employeeData.national_id}/photo`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`
          },
          body: formData
        });

        if (response.ok) {
          const result = await response.json();
          if (result.success || result.data) {
            const photoUrlFromServer = result.data?.photo_url || result.data?.photo || result.photo_url;
            if (photoUrlFromServer) {
              setPhotoUrl(photoUrlFromServer);
              setEmployeeData({ ...employeeData, photo_url: photoUrlFromServer, pic: photoUrlFromServer });
            }
            message.success('Photo uploaded successfully!');
          } else {
            setEmployeeData({ ...employeeData, photo_url: base64, pic: base64 });
            message.success('Photo updated locally!');
          }
        } else {
          await updateEmployeePhoto(base64);
        }
      } catch (uploadError) {
        console.warn('Photo upload endpoint not available, updating employee record:', uploadError);
        await updateEmployeePhoto(base64);
      }
    } catch (error) {
      console.error('Error uploading photo:', error);
      message.error('Failed to upload photo');
    } finally {
      setUploadingPhoto(false);
    }
    return false;
  };

  const handleAvatarClick = () => {
    if (photoUrl) {
      setPreviewImage(photoUrl);
      setPreviewOpen(true);
    }
  };

  const uploadProps = {
    name: 'photo',
    listType: 'picture-circle',
    showUploadList: false,
    beforeUpload: handlePhotoUpload,
    accept: 'image/*',
    maxCount: 1
  };

  if (loading) {
    return (
      <div className="employee-profile-loading">
        <CollectionLoader />
      </div>
    );
  }

  if (!employeeData) {
    return (
      <div className="employee-profile-empty">
        <Card>
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <Text type="secondary">Employee profile not found</Text>
          </div>
        </Card>
      </div>
    );
  }

  const roleDisplay =
    employeeData.role_name ||
    employeeData.role?.name ||
    (userRoles.length > 0 ? userRoles.join(', ') : null);

  const profileTabItems = [
    {
      key: 'details',
      label: 'Details',
      children: (
        <EmployeeDetailsTab
          employeeData={employeeData}
          photoUrl={photoUrl}
          uploadingPhoto={uploadingPhoto}
          uploadProps={uploadProps}
          onAvatarClick={handleAvatarClick}
          roleDisplay={roleDisplay}
        />
      )
    },
    {
      key: 'documents',
      label: 'Documents',
      children: <EmployeeDocumentsTab employeeData={employeeData} />
    }
  ];

  return (
    <div className="employee-profile-container">
      <Card className="employee-profile-shell-card" styles={{ body: { padding: '12px' } }}>
        <Tabs
          activeKey={profileTab}
          onChange={setProfileTab}
          items={profileTabItems}
          size="large"
          className="employee-profile-tabs employee-profile-tabs-top"
        />
      </Card>

      <Modal
        open={previewOpen}
        title="Photo Preview"
        footer={null}
        onCancel={() => setPreviewOpen(false)}
        centered
      >
        <img alt="preview" style={{ width: '100%' }} src={previewImage} />
      </Modal>
    </div>
  );
};

export default EmployeeProfile;
