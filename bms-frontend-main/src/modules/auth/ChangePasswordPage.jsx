import { useState } from 'react';
import { Form, Input, Button, Card, Typography, App } from 'antd';
import { LockOutlined, EyeInvisibleOutlined, EyeTwoTone } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { useDispatch } from 'react-redux';
import { setUser } from '../../store/reducers/auth';
import { apiService } from '../../services/api.jsx';
import { getStoredUser, setMustChangePasswordFlag, setStoredUser } from './authSession.js';
import logo from '../../assets/images/logo.png';
import './LoginPage.css';

const { Title } = Typography;

const ChangePasswordPage = () => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const [loading, setLoadingState] = useState(false);

  const onFinish = async (values) => {
    setLoadingState(true);

    try {
      const user = getStoredUser() || {};
      const response = await apiService.updatePassword({
        username: user.username || '',
        current_password: values.currentPassword,
        new_pass: values.newPassword,
      });

      if (response.success) {
        message.success('Password changed successfully!');
        const updatedUser = { ...user, must_change_password: false };
        setMustChangePasswordFlag(false);
        setStoredUser(updatedUser);
        dispatch(setUser(updatedUser));

        setTimeout(() => {
          navigate('/', { replace: true });
        }, 500);
      } else {
        message.error(response.message || 'Failed to change password. Please try again.');
      }
    } catch (error) {
      const errorMessage = error.message || 'An error occurred. Please try again.';
      message.error(errorMessage);
    } finally {
      setLoadingState(false);
    }
  };

  const onFinishFailed = () => {
    message.warning('Please fill in all required fields correctly.');
  };

  return (
    <div className="login-container">
      <div className="login-content">
        <Card className="login-card" variant="borderless">
          <div className="login-card-header">
            <img src={logo} alt="BMS Logo" className="login-logo" />
            <Title level={2} className="login-title">
              Change Password
            </Title>
            <p className="login-subtitle">
              Enter your current password and choose a new password to continue.
            </p>
          </div>

          <Form
            form={form}
            name="changePassword"
            onFinish={onFinish}
            onFinishFailed={onFinishFailed}
            autoComplete="off"
            layout="vertical"
            size="small"
            className="login-form"
          >
            <Form.Item
              name="currentPassword"
              rules={[
                { required: true, message: 'Please enter your current password!' },
                { min: 5, message: 'Current password must be at least 5 characters!' },
              ]}
            >
              <Input.Password
                prefix={<LockOutlined className="login-input-icon" />}
                placeholder="Current password"
                autoFocus
                iconRender={(visible) => (visible ? <EyeTwoTone /> : <EyeInvisibleOutlined />)}
              />
            </Form.Item>

            <Form.Item
              name="newPassword"
              rules={[
                { required: true, message: 'Please enter a new password!' },
                { min: 6, message: 'Password must be at least 6 characters!' },
              ]}
            >
              <Input.Password
                prefix={<LockOutlined className="login-input-icon" />}
                placeholder="New password"
                iconRender={(visible) => (visible ? <EyeTwoTone /> : <EyeInvisibleOutlined />)}
              />
            </Form.Item>

            <Form.Item
              name="confirmPassword"
              dependencies={['newPassword']}
              rules={[
                { required: true, message: 'Please confirm your new password!' },
                ({ getFieldValue }) => ({
                  validator(_, value) {
                    if (!value || getFieldValue('newPassword') === value) {
                      return Promise.resolve();
                    }
                    return Promise.reject(new Error('The two passwords do not match!'));
                  },
                }),
              ]}
            >
              <Input.Password
                prefix={<LockOutlined className="login-input-icon" />}
                placeholder="Confirm new password"
                iconRender={(visible) => (visible ? <EyeTwoTone /> : <EyeInvisibleOutlined />)}
              />
            </Form.Item>

            <Form.Item className="login-form-actions">
              <Button
                type="primary"
                htmlType="submit"
                loading={loading}
                block
                className="login-button"
              >
                {loading ? 'Changing Password...' : 'Change Password'}
              </Button>
            </Form.Item>
          </Form>

          <div className="login-card-footer">
            <div>Nyerere Bidge</div>
            <div>National Social Security Fund</div>
          </div>
        </Card>
      </div>
    </div>
  );
};

export default ChangePasswordPage;
