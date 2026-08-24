import { useState } from 'react';
import PropTypes from 'prop-types';
import { Row, Col, Avatar, Tag, Typography, Upload, Button } from 'antd';
import { UserOutlined, CameraOutlined, LoadingOutlined, EyeOutlined, EyeInvisibleOutlined } from '@ant-design/icons';
import { getEmployeeStatus } from '../../../common/utils/employeeUtils.jsx';

const { Text } = Typography;

const ProfileSegmentField = ({ label, value }) => (
  <div className="employee-profile-kv">
    <div className="employee-profile-kv-label">{label}</div>
    <div className="employee-profile-kv-value">{value}</div>
  </div>
);

ProfileSegmentField.propTypes = {
  label: PropTypes.node.isRequired,
  value: PropTypes.node.isRequired
};

const displayOrNA = (v) => {
  if (v === null || v === undefined || v === '') return 'N/A';
  return v;
};

const formatCurrency = (amount) => {
  if (amount === null || amount === undefined || amount === '') return 'N/A';
  const n = Number(amount);
  if (Number.isNaN(n)) return 'N/A';
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'TZS',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(n);
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  try {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  } catch {
    return dateString;
  }
};

const getInitials = (name) => {
  if (!name) return 'U';
  const parts = name.split(' ');
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
};

const EmployeeDetailsTab = ({
  employeeData,
  photoUrl = null,
  uploadingPhoto = false,
  uploadProps,
  onAvatarClick,
  roleDisplay = null
}) => {
  const [showSalary, setShowSalary] = useState(false);

  const getStatusTag = () => {
    const status = getEmployeeStatus(employeeData);
    return (
      <Tag color={status.color} icon={status.icon} style={{ fontSize: '14px', padding: '4px 12px' }}>
        {status.text}
      </Tag>
    );
  };

  const bankBranch =
    employeeData.bank_branch ||
    employeeData.branch_name ||
    employeeData.branch ||
    null;

  const deductionsTotal =
    employeeData.deductions ??
    employeeData.total_deductions ??
    employeeData.deduction_total ??
    null;

  const basicSalary = employeeData.basic_salary ?? employeeData.basicsalary;
  const salaryFieldPresent = (v) => v !== null && v !== undefined && v !== '';
  const hasSalaryFigures =
    salaryFieldPresent(basicSalary) ||
    salaryFieldPresent(employeeData.gross_salary) ||
    salaryFieldPresent(employeeData.net_salary) ||
    salaryFieldPresent(employeeData.allowances) ||
    salaryFieldPresent(deductionsTotal);

  const educationalLevelDisplay = employeeData.educational_level
    ? typeof employeeData.educational_level === 'string'
      ? employeeData.educational_level
      : employeeData.educational_level?.level_name || employeeData.educational_level?.name || 'N/A'
    : null;

  const maskValue = (renderedValue) => (showSalary ? renderedValue : '******');

  const dobRaw = employeeData.date_of_birth || employeeData.dob || employeeData.birth_date;
  let ageDisplay = 'N/A';
  if (dobRaw) {
    try {
      const birth = new Date(dobRaw);
      if (!Number.isNaN(birth.getTime())) {
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
          age -= 1;
        }
        ageDisplay = age >= 0 ? String(age) : 'N/A';
      }
    } catch {
      ageDisplay = 'N/A';
    }
  }

  const genderDisplay = employeeData.gender || employeeData.sex || null;

  const fullNameDisplay =
    employeeData.title && employeeData.full_name
      ? `${employeeData.title} ${employeeData.full_name}`
      : employeeData.full_name || null;

  return (
    <div className="employee-profile-details-main">
      <section className="employee-profile-section employee-profile-member-section">
        <Row gutter={[20, 14]} align="top">
          <Col xs={24} sm={6} md={5} lg={4}>
            <div className="employee-profile-member-photo">
              <div className="employee-profile-member-avatar-wrap">
                <Avatar
                  shape="square"
                  size={72}
                  icon={<UserOutlined />}
                  className="employee-profile-member-avatar"
                  src={photoUrl}
                  onClick={onAvatarClick}
                  style={{ cursor: photoUrl ? 'pointer' : 'default' }}
                >
                  {!photoUrl && getInitials(employeeData.full_name)}
                </Avatar>
                <Upload {...uploadProps} className="employee-profile-member-avatar-upload">
                  <div className="employee-profile-member-avatar-overlay">
                    {uploadingPhoto ? (
                      <LoadingOutlined style={{ fontSize: 16, color: '#fff' }} />
                    ) : (
                      <CameraOutlined style={{ fontSize: 16, color: '#fff' }} />
                    )}
                  </div>
                </Upload>
              </div>
            </div>
          </Col>
          <Col xs={24} sm={18} md={19} lg={20}>
            <Row gutter={[16, 12]}>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField label="Employee ID" value={displayOrNA(employeeData.pfno)} />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField label="National ID" value={displayOrNA(employeeData.national_id)} />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField label="Age" value={ageDisplay} />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField label="Employee name" value={displayOrNA(fullNameDisplay)} />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField label="Gender" value={displayOrNA(genderDisplay)} />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField
                  label="Phone"
                  value={displayOrNA(employeeData.mobile || employeeData.phone_number)}
                />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField label="Email" value={displayOrNA(employeeData.email)} />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField
                  label="Employment date"
                  value={formatDate(employeeData.hire_date || employeeData.date_of_employment)}
                />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField label="Role" value={displayOrNA(roleDisplay)} />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField label="Username" value={displayOrNA(employeeData.username)} />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField label="Domicile" value={displayOrNA(employeeData.domicile)} />
              </Col>
              <Col xs={24} sm={12} md={8} lg={6}>
                <ProfileSegmentField label="Status" value={getStatusTag() || 'N/A'} />
              </Col>
            </Row>
          </Col>
        </Row>
      </section>

      <section className="employee-profile-section">
        <h3 className="employee-profile-section-title">Bank</h3>
        <div className="employee-profile-section-rule" aria-hidden />
        <Row gutter={[16, 12]}>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField label="Bank name" value={displayOrNA(employeeData.bank_name)} />
          </Col>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField label="Account number" value={displayOrNA(employeeData.account_no)} />
          </Col>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField label="Branch" value={displayOrNA(bankBranch)} />
          </Col>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField
              label="Account status"
              value={
                employeeData.account_status ? (
                  <Tag color={employeeData.account_status === 'Active' ? 'green' : 'default'}>
                    {employeeData.account_status}
                  </Tag>
                ) : (
                  'N/A'
                )
              }
            />
          </Col>
        </Row>
      </section>

      <section className="employee-profile-section">
        <div className="employee-profile-section-title-row">
          <h3 className="employee-profile-section-title">Salary information</h3>
          {hasSalaryFigures ? (
            <Button
              type="text"
              aria-label={showSalary ? 'Hide salary amounts' : 'Show salary amounts'}
              onClick={() => setShowSalary((v) => !v)}
              className="employee-profile-salary-eye-btn"
              icon={showSalary ? <EyeInvisibleOutlined /> : <EyeOutlined />}
            />
          ) : null}
        </div>
        <div className="employee-profile-section-rule" aria-hidden />
        {!hasSalaryFigures ? (
          <Text type="secondary" className="employee-profile-section-empty">
            No salary information available.
          </Text>
        ) : (
          <Row gutter={[16, 12]}>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField
              label="Basic salary"
              value={maskValue(salaryFieldPresent(basicSalary) ? formatCurrency(basicSalary) : 'N/A')}
            />
          </Col>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField
              label="Allowances"
              value={maskValue(
                employeeData.allowances != null && employeeData.allowances !== ''
                  ? formatCurrency(employeeData.allowances)
                  : 'N/A'
              )}
            />
          </Col>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField
              label="Deductions"
              value={maskValue(
                deductionsTotal != null && deductionsTotal !== ''
                  ? formatCurrency(deductionsTotal)
                  : 'N/A'
              )}
            />
          </Col>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField
              label="Gross pay"
              value={maskValue(
                employeeData.gross_salary != null && employeeData.gross_salary !== ''
                  ? formatCurrency(employeeData.gross_salary)
                  : 'N/A'
              )}
            />
          </Col>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField
              label="Net pay"
              value={maskValue(
                employeeData.net_salary != null && employeeData.net_salary !== ''
                  ? formatCurrency(employeeData.net_salary)
                  : 'N/A'
              )}
            />
          </Col>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField
              label="Currency"
              value={maskValue(displayOrNA(employeeData.salary_currency))}
            />
          </Col>
          <Col xs={24} sm={12} md={8} lg={6}>
            <ProfileSegmentField
              label="Payment date"
              value={maskValue(formatDate(employeeData.salary_payment_date))}
            />
          </Col>
          </Row>
        )}
      </section>

      <section className="employee-profile-section employee-profile-section-last">
        <h3 className="employee-profile-section-title">Additional info</h3>
        <div className="employee-profile-section-rule" aria-hidden />
        <Row gutter={[16, 12]}>
        <Col xs={24} sm={12} md={8} lg={6}>
          <ProfileSegmentField label="Username" value={displayOrNA(employeeData.username)} />
        </Col>
        <Col xs={24} sm={12} md={8} lg={6}>
          <ProfileSegmentField
            label="Employment place"
            value={displayOrNA(employeeData.employment_place)}
          />
        </Col>
        <Col xs={24} sm={12} md={8} lg={6}>
          <ProfileSegmentField label="Scheme" value={displayOrNA(employeeData.scheme_name)} />
        </Col>
        <Col xs={24} sm={12} md={8} lg={6}>
          <ProfileSegmentField label="Employment type" value={displayOrNA(employeeData.emptype_name)} />
        </Col>
        <Col xs={24} sm={12} md={8} lg={6}>
          <ProfileSegmentField label="Emergency contact" value={displayOrNA(employeeData.emergency_contact)} />
        </Col>
        <Col xs={24} sm={12} md={8} lg={6}>
          <ProfileSegmentField label="Emergency phone" value={displayOrNA(employeeData.emergency_phone)} />
        </Col>
        <Col xs={24} sm={12} md={8} lg={6}>
          <ProfileSegmentField
            label="Educational level"
            value={displayOrNA(educationalLevelDisplay)}
          />
        </Col>
        <Col xs={24} sm={12} md={8} lg={6}>
          <ProfileSegmentField label="TIN" value={displayOrNA(employeeData.tin)} />
        </Col>
        <Col xs={24} sm={12} md={8} lg={6}>
          <ProfileSegmentField
            label="Health insurance ID"
            value={displayOrNA(employeeData.health_insurance_id)}
          />
        </Col>
        <Col xs={24} sm={12} md={8} lg={6}>
          <ProfileSegmentField label="SSN" value={displayOrNA(employeeData.ssn)} />
        </Col>
        </Row>
      </section>
    </div>
  );
};

EmployeeDetailsTab.propTypes = {
  employeeData: PropTypes.object.isRequired,
  photoUrl: PropTypes.string,
  uploadingPhoto: PropTypes.bool,
  uploadProps: PropTypes.object.isRequired,
  onAvatarClick: PropTypes.func.isRequired,
  roleDisplay: PropTypes.string
};

export default EmployeeDetailsTab;
