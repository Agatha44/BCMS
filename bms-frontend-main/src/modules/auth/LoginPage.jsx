import { useState, useEffect } from 'react';
import { Form, Input, Button, Card, Typography, App } from 'antd';
import { UserOutlined, LockOutlined, EyeInvisibleOutlined, EyeTwoTone } from '@ant-design/icons';
import { useNavigate, Link } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { setUser, setAuthenticated, setLoading, setError } from '../../store/reducers/auth';
import { resetAppState } from '../../store/reducers/app';
import { apiService } from '../../services/api.jsx';
import logo from '../../assets/images/logo.png';
import { userMustChangePassword } from './authUtils.js';
import {
  getAuthToken,
  getMustChangePasswordFlag,
  setMustChangePasswordFlag,
  setStoredUser
} from './authSession.js';
import './LoginPage.css';

const { Title } = Typography;

const LoginPage = () => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const { authenticated } = useSelector((state) => state.auth);
  const [loading, setLoadingState] = useState(false);

  useEffect(() => {
    const token = getAuthToken();
    if (authenticated || token) {
      navigate(getMustChangePasswordFlag() ? '/change-password' : '/', { replace: true });
    }
  }, [authenticated, navigate]);

  const onFinish = async (values) => {
    setLoadingState(true);
    dispatch(setLoading(true));
    dispatch(setError(null));

    try {
      const response = await apiService.login({
        username: values.username,
        password: values.password,
      });

      if (response.success) {
        dispatch(resetAppState());

        const userData = response.data?.user || { username: values.username };
        const mustChangePassword = userMustChangePassword(userData);

        dispatch(setUser(userData));
        dispatch(setAuthenticated(true));
        dispatch(setLoading(false));

        setStoredUser(userData);
        setMustChangePasswordFlag(mustChangePassword);

        message.success(
          mustChangePassword
            ? 'Please change your password to continue.'
            : 'Login successful! Redirecting...'
        );

        setTimeout(() => {
          navigate(mustChangePassword ? '/change-password' : '/');
        }, 500);
      } else {
        dispatch(setError(response.message || 'Login failed'));
        dispatch(setLoading(false));
        message.error(response.message || 'Invalid credentials. Please try again.');
      }
    } catch (error) {
      const errorMessage = error.message || 'An error occurred during login. Please try again.';
      dispatch(setError(errorMessage));
      dispatch(setLoading(false));
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
              Bridge Management System
            </Title>
          </div>

          <Form
            form={form}
            name="login"
            onFinish={onFinish}
            onFinishFailed={onFinishFailed}
            autoComplete="off"
            layout="vertical"
            size="small"
            className="login-form"
          >
            <Form.Item
              name="username"
              rules={[
                { required: true, message: 'Please enter your username!' },
                { min: 3, message: 'Username must be at least 3 characters!' },
              ]}
            >
              <Input
                prefix={<UserOutlined className="login-input-icon" />}
                placeholder="Username"
                autoFocus
              />
            </Form.Item>

            <Form.Item
              name="password"
              rules={[
                { required: true, message: 'Please enter your password!' },
                { min: 5, message: 'Password must be at least 5 characters!' },
              ]}
            >
              <Input.Password
                prefix={<LockOutlined className="login-input-icon" />}
                placeholder="Password"
                iconRender={(visible) => (visible ? <EyeTwoTone /> : <EyeInvisibleOutlined />)}
              />
            </Form.Item>

            <div className="login-forgot-password-link">
              <Link to="/forgot-password" className="forgot-password-link">
                Forgot password?
              </Link>
            </div>

            <Form.Item className="login-form-actions">
              <Button
                type="primary"
                htmlType="submit"
                loading={loading}
                block
                className="login-button"
              >
                {loading ? 'Signing in...' : 'Sign In'}
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

export default LoginPage;
