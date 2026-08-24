import { useState } from 'react';
import { Form, Input, Button, Card, Typography, App } from 'antd';
import {
  UserOutlined,
  ArrowLeftOutlined,
  LockOutlined,
  SafetyOutlined,
  EyeInvisibleOutlined,
  EyeTwoTone,
} from '@ant-design/icons';
import { useNavigate, Link } from 'react-router-dom';
import { apiService } from '../../services/api.jsx';
import logo from '../../assets/images/logo.png';
import './ForgotPasswordPage.css';

const { Title, Text } = Typography;

const ForgotPasswordPage = () => {
  const { message } = App.useApp();
  const [usernameForm] = Form.useForm();
  const [resetForm] = Form.useForm();
  const navigate = useNavigate();
  const [loading, setLoadingState] = useState(false);
  const [step, setStep] = useState('username');
  const [username, setUsername] = useState('');

  const requestOtp = async (values) => {
    setLoadingState(true);

    try {
      const response = await apiService.forgotPassword({
        username: values.username,
      });

      if (response.success) {
        setUsername(values.username);
        setStep('reset');
        message.success('A verification code has been sent to your registered phone number.');
      } else {
        message.error(response.message || 'Failed to send verification code. Please try again.');
      }
    } catch (error) {
      message.error(error.message || 'An error occurred. Please try again.');
    } finally {
      setLoadingState(false);
    }
  };

  const resetPassword = async (values) => {
    setLoadingState(true);

    try {
      const response = await apiService.resetPassword({
        username,
        otp: values.otp,
        new_password: values.newPassword,
        confirm_password: values.confirmPassword,
      });

      if (response.success) {
        message.success('Password reset successfully! You can now sign in with your new password.');
        navigate('/login', { replace: true });
      } else {
        message.error(response.message || 'Failed to reset password. Please try again.');
      }
    } catch (error) {
      message.error(error.message || 'An error occurred. Please try again.');
    } finally {
      setLoadingState(false);
    }
  };

  const resendOtp = async () => {
    setLoadingState(true);

    try {
      const response = await apiService.forgotPassword({ username });

      if (response.success) {
        message.success('A new verification code has been sent.');
      } else {
        message.error(response.message || 'Failed to resend verification code. Please try again.');
      }
    } catch (error) {
      message.error(error.message || 'An error occurred. Please try again.');
    } finally {
      setLoadingState(false);
    }
  };

  const onFinishFailed = () => {
    message.warning('Please fill in all required fields correctly.');
  };

  const renderUsernameStep = () => (
    <>
      <div className="forgot-password-card-header">
        <div className="forgot-password-logo-container">
          <img src={logo} alt="BMS Logo" className="forgot-password-logo" />
        </div>
        <Title level={2} className="forgot-password-title">
          Forgot Password
        </Title>
        <Text type="secondary" className="forgot-password-subtitle">
          Enter your username to receive a verification code via SMS
        </Text>
      </div>

      <Form
        form={usernameForm}
        name="forgotPasswordUsername"
        onFinish={requestOtp}
        onFinishFailed={onFinishFailed}
        autoComplete="off"
        layout="vertical"
        size="small"
        className="forgot-password-form"
      >
        <Form.Item
          label="Username"
          name="username"
          rules={[
            { required: true, message: 'Please enter your username!' },
            { min: 3, message: 'Username must be at least 3 characters!' },
          ]}
        >
          <Input
            prefix={<UserOutlined className="forgot-password-input-icon" />}
            placeholder="Enter your username"
            autoFocus
          />
        </Form.Item>

        <Form.Item className="forgot-password-form-actions">
          <Button
            type="primary"
            htmlType="submit"
            loading={loading}
            block
            className="forgot-password-button"
          >
            {loading ? 'Sending...' : 'Send Verification Code'}
          </Button>
        </Form.Item>
      </Form>
    </>
  );

  const renderResetStep = () => (
    <>
      <div className="forgot-password-card-header">
        <div className="forgot-password-logo-container">
          <img src={logo} alt="BMS Logo" className="forgot-password-logo" />
        </div>
        <Title level={2} className="forgot-password-title">
          Reset Password
        </Title>
        <Text type="secondary" className="forgot-password-subtitle">
          Enter the code sent to your phone and choose a new password for{' '}
          <strong>{username}</strong>
        </Text>
      </div>

      <Form
        form={resetForm}
        name="forgotPasswordReset"
        onFinish={resetPassword}
        onFinishFailed={onFinishFailed}
        autoComplete="off"
        layout="vertical"
        size="small"
        className="forgot-password-form"
      >
        <Form.Item
          label="Verification Code"
          name="otp"
          rules={[
            { required: true, message: 'Please enter the verification code!' },
            { len: 6, message: 'Verification code must be 6 digits!' },
            { pattern: /^\d{6}$/, message: 'Verification code must contain only digits!' },
          ]}
        >
          <Input
            prefix={<SafetyOutlined className="forgot-password-input-icon" />}
            placeholder="Enter 6-digit code"
            maxLength={6}
            autoFocus
          />
        </Form.Item>

        <Form.Item
          label="New Password"
          name="newPassword"
          rules={[
            { required: true, message: 'Please enter a new password!' },
            { min: 6, message: 'Password must be at least 6 characters!' },
          ]}
        >
          <Input.Password
            prefix={<LockOutlined className="forgot-password-input-icon" />}
            placeholder="Enter new password"
            iconRender={(visible) => (visible ? <EyeTwoTone /> : <EyeInvisibleOutlined />)}
          />
        </Form.Item>

        <Form.Item
          label="Confirm Password"
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
            prefix={<LockOutlined className="forgot-password-input-icon" />}
            placeholder="Confirm new password"
            iconRender={(visible) => (visible ? <EyeTwoTone /> : <EyeInvisibleOutlined />)}
          />
        </Form.Item>

        <Form.Item className="forgot-password-form-actions">
          <Button
            type="primary"
            htmlType="submit"
            loading={loading}
            block
            className="forgot-password-button"
          >
            {loading ? 'Resetting...' : 'Reset Password'}
          </Button>
        </Form.Item>
      </Form>

      <div className="forgot-password-resend">
        <Text type="secondary" className="forgot-password-resend-text">
          Didn&apos;t receive the code?{' '}
        </Text>
        <button
          type="button"
          className="forgot-password-resend-link"
          onClick={resendOtp}
          disabled={loading}
        >
          Resend code
        </button>
      </div>

      <div className="forgot-password-change-user">
        <button
          type="button"
          className="forgot-password-back-link"
          onClick={() => {
            setStep('username');
            setUsername('');
            resetForm.resetFields();
          }}
        >
          <ArrowLeftOutlined /> Use a different username
        </button>
      </div>
    </>
  );

  return (
    <div className="forgot-password-container">
      <div className="forgot-password-background">
        <div className="forgot-password-background-overlay" />
      </div>

      <div className="forgot-password-content">
        <Card className="forgot-password-card" variant="borderless">
          {step === 'username' ? renderUsernameStep() : renderResetStep()}

          <div className="forgot-password-footer">
            <Link to="/login" className="forgot-password-back-link">
              <ArrowLeftOutlined /> Back to Login
            </Link>
          </div>
        </Card>
      </div>
    </div>
  );
};

export default ForgotPasswordPage;
