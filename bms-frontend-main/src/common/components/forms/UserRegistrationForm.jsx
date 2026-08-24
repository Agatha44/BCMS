import { useState, useEffect } from 'react';
import { Button, Col, Form, Input, Row, Select, DatePicker, Checkbox, App, Steps, Upload } from 'antd';
import PropTypes from 'prop-types';
import dayjs from 'dayjs';
import { UploadOutlined, QuestionCircleOutlined } from '@ant-design/icons';
import { apiService } from '../../../services/api.jsx';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';

const { Option } = Select;
const { Step } = Steps;

const UserRegistrationForm = ({ onSubmit, initialValues }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [currentStep, setCurrentStep] = useState(0);
  const [roles, setRoles] = useState([]);
  const [rolesLoading, setRolesLoading] = useState(false);
  const [educationalLevels, setEducationalLevels] = useState([]);
  const [loadingEducationalLevels, setLoadingEducationalLevels] = useState(false);
  const [regions, setRegions] = useState([]);
  const [regionsLoading, setRegionsLoading] = useState(false);
  const [districts, setDistricts] = useState([]);
  const [districtsLoading, setDistrictsLoading] = useState(false);
  const [selectedRegionId, setSelectedRegionId] = useState(null);
  const [banks, setBanks] = useState([]);
  const [banksLoading, setBanksLoading] = useState(false);
  const [employmentTypes, setEmploymentTypes] = useState([]);
  const [employmentTypesLoading, setEmploymentTypesLoading] = useState(false);
  const [schemes, setSchemes] = useState([]);
  const [schemesLoading, setSchemesLoading] = useState(false);
  const [offices, setOffices] = useState([]);
  const [officesLoading, setOfficesLoading] = useState(false);
  const [selectedOffice, setSelectedOffice] = useState(null);
  const [departments, setDepartments] = useState([]);
  const [departmentsLoading, setDepartmentsLoading] = useState(false);

  const normalizeBoolean = (value) =>
    value === true || value === 1 || value === '1' || value === 'true' || value === 'TRUE';

  // Fetch active roles from API
  useEffect(() => {
    const fetchActiveRoles = async () => {
      setRolesLoading(true);
      try {
        const response = await apiService.getActiveBmsRoles();
        if (response.success && response.data) {
          const rolesData = Array.isArray(response.data) ? response.data : [];
          setRoles(rolesData);
        } else {
          message.error(response.message || 'Failed to fetch roles');
          setRoles([]);
        }
      } catch (error) {
        console.error('Error fetching active roles:', error);
        message.error('An error occurred while fetching roles');
        setRoles([]);
      } finally {
        setRolesLoading(false);
      }
    };

    fetchActiveRoles();
  }, []);

  // Fetch active educational levels from API
  useEffect(() => {
    const fetchEducationalLevels = async () => {
      setLoadingEducationalLevels(true);
      try {
        const response = await apiService.getActiveEducationalLevels();
        if (response.success && response.data) {
          setEducationalLevels(Array.isArray(response.data) ? response.data : []);
        }
      } catch (error) {
        console.error('Error fetching educational levels:', error);
      } finally {
        setLoadingEducationalLevels(false);
      }
    };

    fetchEducationalLevels();
  }, []);

  // Fetch active regions from API
  useEffect(() => {
    const fetchActiveRegions = async () => {
      setRegionsLoading(true);
      try {
        const response = await apiService.getActiveRegions();
        if (response.success && response.data) {
          const { items: regionsData } = extractArrayFromResponse(response.data);
          // Filter to only include active regions
          const filteredRegions = (regionsData || []).filter(
            region => region.is_active === 1 || region.is_active === true || region.is_active === '1'
          );
          setRegions(filteredRegions);
        }
      } catch (error) {
        console.error('Error fetching active regions:', error);
        message.error('An error occurred while fetching regions');
      } finally {
        setRegionsLoading(false);
      }
    };

    fetchActiveRegions();
  }, []);

  // Fetch districts when region is selected
  useEffect(() => {
    const fetchDistrictsByRegion = async () => {
      if (!selectedRegionId) {
        setDistricts([]);
        form.setFieldValue('district_id', undefined);
        return;
      }

      setDistrictsLoading(true);
      try {
        const response = await apiService.getDistrictsByRegion(selectedRegionId);
        if (response.success && response.data) {
          const { items: districtsData } = extractArrayFromResponse(response.data);
          // Filter to only include active districts
          const filteredDistricts = (districtsData || []).filter(
            district => district.is_active === 1 || district.is_active === true || district.is_active === '1'
          );
          setDistricts(filteredDistricts);
        }
      } catch (error) {
        console.error('Error fetching districts by region:', error.message || error);
        message.error('An error occurred while fetching districts');
        setDistricts([]);
      } finally {
        setDistrictsLoading(false);
      }
    };

    fetchDistrictsByRegion();
  }, [selectedRegionId]);

  // Fetch active banks from API
  useEffect(() => {
    const fetchActiveBanks = async () => {
      setBanksLoading(true);
      try {
        const response = await apiService.getActiveBanks();
        if (response.success && response.data) {

          console.log(response.success && response.data);
          // Check if response.data is already an array
          let banksData = [];
          if (Array.isArray(response.data)) {
            banksData = response.data;
          } else {
            // Try to extract using utility function (in case it's paginated)
            const extracted = extractArrayFromResponse(response.data);
            banksData = extracted.items || [];
          }
          setBanks(banksData);
        } else {
          setBanks([]);
        }
      } catch (error) {
        console.error('Error fetching active banks:', error);
        message.error('An error occurred while fetching banks');
        setBanks([]);
      } finally {
        setBanksLoading(false);
      }
    };

    fetchActiveBanks();
  }, []);

  // Fetch active employment types from API
  useEffect(() => {
    const fetchActiveEmploymentTypes = async () => {
      setEmploymentTypesLoading(true);
      try {
        const response = await apiService.getActiveBridgeEmploymentTypes();

        if (response.success && response.data) {
          // Check if response.data is already an array
          let employmentTypesData = [];
          if (Array.isArray(response.data)) {
            employmentTypesData = response.data;
          } else {
            // Try to extract using utility function (in case it's paginated)
            const extracted = extractArrayFromResponse(response.data);
            employmentTypesData = extracted.items || [];
          }
          setEmploymentTypes(employmentTypesData);
        } else {
          setEmploymentTypes([]);
        }
      } catch (error) {
        console.error('Error fetching employment types:', error);
        message.error('An error occurred while fetching employment types');
        setEmploymentTypes([]);
      } finally {
        setEmploymentTypesLoading(false);
      }
    };

    fetchActiveEmploymentTypes();
  }, []);

  // Fetch active schemes from API
  useEffect(() => {
    const fetchActiveSchemes = async () => {
      setSchemesLoading(true);
      try {
        const response = await apiService.getActiveSchemes();
        if (response.success && response.data) {
          const schemesData = Array.isArray(response.data) ? response.data : [];
          setSchemes(schemesData);
        } else {
          setSchemes([]);
        }
      } catch (error) {
        console.error('Error fetching active schemes:', error);
        message.error('An error occurred while fetching schemes');
        setSchemes([]);
      } finally {
        setSchemesLoading(false);
      }
    };

    fetchActiveSchemes();
  }, []);

  // Fetch active offices from API
  useEffect(() => {
    const fetchActiveOffices = async () => {
      setOfficesLoading(true);
      try {
        // Ask for a large page size so the dropdown can show all offices
        const response = await apiService.getBridgeOffices({ page: 1, per_page: 1000 });
        if (response.success && response.data) {
          // Support multiple API shapes:
          // - Array directly
          // - { offices: [], pagination: {} }
          // - Paginated wrappers
          let officesData = [];
          if (Array.isArray(response.data)) {
            officesData = response.data;
          } else if (Array.isArray(response.data?.offices)) {
            officesData = response.data.offices;
          } else {
            const extracted = extractArrayFromResponse(response.data);
            officesData = extracted.items || [];
          }

          // Normalize boolean-ish fields to real booleans for consistent UI logic
          const normalizedOffices = (officesData || []).map((office) => ({
            ...office,
            is_active: normalizeBoolean(office?.is_active),
            auto_generate_pf: normalizeBoolean(office?.auto_generate_pf),
          }));

          setOffices(normalizedOffices);
          
          // If we have initialValues with officeid, set selected office
          if (initialValues && (initialValues.officeid || initialValues.office_id)) {
            const officeId = Number(initialValues.officeid || initialValues.office_id);
            const office = normalizedOffices.find(off => Number(off.id) === officeId);
            if (office) {
              setSelectedOffice(office);
            }
          }
        } else {
          setOffices([]);
        }
      } catch (error) {
        console.error('Error fetching active offices:', error);
        message.error('An error occurred while fetching offices');
        setOffices([]);
      } finally {
        setOfficesLoading(false);
      }
    };

    fetchActiveOffices();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Fetch active departments from API
  useEffect(() => {
    const fetchActiveDepartments = async () => {
      setDepartmentsLoading(true);
      try {
        const response = await apiService.getActiveBmsDepartments();
        if (response.success && Array.isArray(response.data)) {

          console.log(response.data);
          
          // Filter and normalize department data
          const validDepartments = response.data
            .filter(dept => dept && (dept.department_id != null || dept.id != null) && (dept.department_id !== undefined || dept.id !== undefined))
            .filter((dept, index, self) => {
              const deptId = dept.department_id || dept.id;
              return index === self.findIndex(d => (d.department_id || d.id) === deptId);
            })
            .map(dept => ({
              ...dept,
              id: dept.department_id || dept.id,
              department_name: dept.department_name || dept.name
            }));
          setDepartments(validDepartments);
        } else {
          setDepartments([]);
        }
      } catch (error) {
        console.error('Error fetching active departments:', error);
        message.error('An error occurred while fetching departments');
        setDepartments([]);
      } finally {
        setDepartmentsLoading(false);
      }
    };

    fetchActiveDepartments();
  }, []);

  useEffect(() => {
    if (initialValues && educationalLevels.length > 0) {
      // Find educational level by ID or name
      let educationalLevelId = initialValues.educational_level_id || initialValues.educationalLevel;
      const educationalLevelName = initialValues.educational_level_name || initialValues.educationalLevel;
      
      if (!educationalLevelId && educationalLevelName) {
        const foundLevel = educationalLevels.find(
          level => level.level_name === educationalLevelName || level.name === educationalLevelName
        );
        if (foundLevel) {
          educationalLevelId = foundLevel.id;
        }
      }

      // Get region_id from initialValues if available
      const regionId = initialValues.region_id || initialValues.domicile_id || 
        (initialValues.region?.id) || (initialValues.domicile?.id);
      
      // Set region_id if available, which will trigger district fetch
      if (regionId) {
        setSelectedRegionId(regionId);
      }

      // Set selected office if officeid is available
      const officeId = initialValues.officeid || initialValues.office_id;
      if (officeId && offices.length > 0) {
        const office = offices.find(off => Number(off.id) === Number(officeId));
        if (office) {
          setSelectedOffice(office);
        }
      }

      form.setFieldsValue({
        // Step 1: Employee Details
        national_id: initialValues.national_id || initialValues.nida_number,
        first_name: initialValues.first_name || initialValues.firstName,
        middle_name: initialValues.middle_name || initialValues.middleName,
        surname: initialValues.surname || initialValues.sname,
        gender: initialValues.gender,
        title: initialValues.title,
        birth_date: initialValues.birth_date || initialValues.dob ? dayjs(initialValues.birth_date || initialValues.dob) : null,
        maritalstatus: initialValues.maritalstatus || initialValues.marital_status || initialValues.maritalStatus,
        region_id: regionId,
        domicile: initialValues.domicile,
        nationality: initialValues.nationality,
        passport_no: initialValues.passport_no,
        mobile: initialValues.mobile || initialValues.phone_number,
        email: initialValues.email,
        district_id: initialValues.district_id,
        ext_no: initialValues.ext_no,
        ssn: initialValues.ssn,
        // Step 2: Medical Info
        health_insurance_id: initialValues.health_insurance_id,
        // Step 3: Employment Contract
        officeid: initialValues.officeid || initialValues.office_id,
        employment_place: initialValues.employment_place,
        scheme_id: initialValues.scheme_id,
        pfno: initialValues.pfno || initialValues.pf_number,
        emptype_id: initialValues.emptype_id,
        bank_id: initialValues.bank_id,
        account_no: initialValues.account_no,
        basicsalary: initialValues.basicsalary,
        employee_status: initialValues.employee_status,
        tin: initialValues.tin,
        department_id: initialValues.department_id,
        // Step 4: Academic Details
        educational_level_id: educationalLevelId,
        is_first_time: initialValues.is_first_time,
        account_status: initialValues.account_status,
        status: initialValues.is_active === 'Active' || initialValues.is_active === true || initialValues.is_active === 'active'
      });
    } else if (!initialValues) {
      form.resetFields();
      setSelectedOffice(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialValues, educationalLevels, regions, offices]);

  const handleSubmit = async (values) => {
    setLoading(true);
    try {
      // Get all form values from all steps (including fields not currently visible)
      const allValues = form.getFieldsValue(true);
      
      // Use allValues instead of values to ensure we get all fields from all steps
      const formValues = allValues || values;
      
      // Transform form data to match bridge employee registration API format
      // Helper function to extract file from fileList
      const getFileFromList = (fileList) => {
        if (!fileList || fileList.length === 0) return null;
        return fileList[0]?.originFileObj || fileList[0];
      };

      // Controller expects lowercase field names for bridge_employee table
      const formData = {
        // Bridge Employee Required Fields
        fname: formValues.first_name,
        sname: formValues.surname,
        national_id: formValues.national_id,
        
        // Bridge Employee Optional Fields
        mname: formValues.middle_name,
        gender: formValues.gender,
        title: formValues.title,
        dob: formValues.birth_date?.format ? formValues.birth_date.format('YYYY-MM-DD') : formValues.birth_date,
        maritalstatus: formValues.maritalstatus,
        domicile: formValues.region_id ? parseInt(formValues.region_id) : formValues.region_id,
        officeid: formValues.officeid ? parseInt(formValues.officeid) : formValues.officeid,
        employment_place: formValues.employment_place,
        // For auto-generate offices, pfno should be null (will be generated on approval)
        // For manual-entry offices, pfno is required and provided by user
        pfno: (() => {
          if (!formValues.officeid) return null;
          const office = offices.find(off => off.id === formValues.officeid);
          if (office && office.auto_generate_pf) {
            return null; // Auto-generate offices don't need PF at registration
          }
          return formValues.pfno || null;
        })(),
        nationality: formValues.nationality,
        passport_no: formValues.passport_no,
        mobile: formValues.mobile,
        email: formValues.email,
        scheme_id: formValues.scheme_id ? parseInt(formValues.scheme_id) : formValues.scheme_id,
        emptype_id: formValues.emptype_id ? parseInt(formValues.emptype_id) : formValues.emptype_id,
        bank_id: formValues.bank_id ? parseInt(formValues.bank_id) : formValues.bank_id,
        account_no: formValues.account_no,
        employee_status: formValues.employee_status,
        district_id: formValues.district_id ? parseInt(formValues.district_id) : formValues.district_id,
        ext_number: formValues.ext_no,
        ssn: formValues.ssn,
        basicsalary: formValues.basicsalary ? parseFloat(formValues.basicsalary) : formValues.basicsalary,
        tin: formValues.tin,
        department_id: formValues.department_id ? parseInt(formValues.department_id) : formValues.department_id,
        health_insurance_id: formValues.health_insurance_id ? parseInt(formValues.health_insurance_id) : formValues.health_insurance_id,
        educational_level_id: formValues.educational_level_id !== undefined && formValues.educational_level_id !== null ? parseInt(formValues.educational_level_id) : formValues.educational_level_id,
        
        // Educational Certificates (PDF files)
        primary_education_certificate: getFileFromList(formValues.primary_education_certificate),
        secondary_education_certificate: getFileFromList(formValues.secondary_education_certificate),
        advanced_education_certificate: getFileFromList(formValues.advanced_education_certificate),
        diploma_certificate: getFileFromList(formValues.diploma_certificate),
        certificate: getFileFromList(formValues.certificate),
        bachelor_degree_certificate: getFileFromList(formValues.bachelor_degree_certificate),
        masters_certificate: getFileFromList(formValues.masters_certificate),
        phd_certificate: getFileFromList(formValues.phd_certificate),
        
        // Auth User Fields (if provided, will create auth_user record)
        // Controller checks for first_name, surname, phone to determine if creating auth_user
        first_name: formValues.first_name,
        surname: formValues.surname,
        phone: formValues.mobile,
        nida: formValues.national_id,
        middle_name: formValues.middle_name,
      };
      
      // Remove null/undefined/empty values to clean up the payload
      // But keep required fields even if they might be null (they should be validated by form rules)
      // Also keep file objects (certificates) even if they might be null
      const requiredFields = ['educational_level_id', 'fname', 'sname', 'national_id'];
      const certificateFields = [
        'primary_education_certificate',
        'secondary_education_certificate',
        'advanced_education_certificate',
        'diploma_certificate',
        'certificate',
        'bachelor_degree_certificate',
        'masters_certificate',
        'phd_certificate'
      ];
      Object.keys(formData).forEach(key => {
        // Only remove if it's null/undefined/empty AND not a required field AND not a certificate field
        // Important: Keep fields that have valid values (even if they're in importantOptionalFields)
        // Keep file objects (certificates) - they will be handled by FormData in the parent component
        if (!requiredFields.includes(key) && !certificateFields.includes(key) && 
            (formData[key] === null || formData[key] === undefined || formData[key] === '' || 
            (typeof formData[key] === 'number' && isNaN(formData[key])))) {
          delete formData[key];
        }
      });
      
      if (onSubmit) {
        const result = await onSubmit(formData);
        // If onSubmit returns an error response, handle validation errors
        if (result && !result.success && result.data) {
          const validationErrors = result.data;
          // Map API field names to form field names
          const fieldMapping = {
            email: 'email',
            mobile: 'mobile',
            phone: 'mobile',
            national_id: 'national_id',
            nida: 'national_id',
            first_name: 'first_name',
            surname: 'surname',
            sname: 'surname',
            fname: 'first_name',
            mname: 'middle_name',
            middle_name: 'middle_name',
          };
          
          // Set form field errors
          const fieldsToSet = Object.entries(validationErrors).map(([field, errors]) => {
            const formFieldName = fieldMapping[field] || field;
            const errorMessages = Array.isArray(errors) ? errors : [errors];
            return {
              name: formFieldName,
              errors: errorMessages
            };
          });
          
          if (fieldsToSet.length > 0) {
            form.setFields(fieldsToSet);
            // Scroll to first step if email error (email is in step 0)
            if (validationErrors.email || validationErrors.mobile || validationErrors.phone) {
              setCurrentStep(0);
            }
          }
          
          // Don't show generic error message here, let parent handle it
          throw new Error('Validation failed');
        }
        
        // Show success message when submission is successful
        if (result && result.success) {
          if (!initialValues) {
            // New registration
            message.success({
              content: 'Employee registration submitted successfully! The request has been sent for approval. PF number will be generated after approval.',
              duration: 5,
            });
          } else {
            // Update
            message.success({
              content: 'Employee update submitted successfully! The request has been sent for approval.',
              duration: 5,
            });
          }
        }
      }
    } catch (error) {
      console.error('Form submission error:', error);
      // Only show generic error if it's not a validation error (which is handled above)
      if (!error.message || error.message !== 'Validation failed') {
        message.error('Failed to submit registration request. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  const next = () => {
    // Define fields for each step
    const stepFields = [
      // Step 0: Employee Details
      ['national_id', 'first_name', 'middle_name', 'surname', 'gender', 'birth_date', 'maritalstatus', 'region_id', 'district_id', 'nationality', 'mobile', 'email'],
      // Step 1: Medical Info
      [],
      // Step 2: Employment Contract
      // Note: pfno validation is handled dynamically based on office selection
      ['officeid', 'scheme_id', 'emptype_id', 'department_id', 'bank_id', 'account_no', 'basicsalary', 'tin'],
      // Step 3: Academic Details
      ['educational_level_id', 'account_status'],
    ];

    const fieldsToValidate = stepFields[currentStep] || [];
    
    if (fieldsToValidate.length > 0) {
      form.validateFields(fieldsToValidate).then(() => {
        // Additional validation: Check PF number if office requires it
        if (currentStep === 2) { // Employment Contract step
          const officeId = form.getFieldValue('officeid');
          if (officeId) {
            const office = offices.find(off => off.id === officeId);
            if (office && !office.auto_generate_pf) {
              // Office requires manual PF entry, validate it
              return form.validateFields(['pfno']).then(() => {
                setCurrentStep(currentStep + 1);
              }).catch(() => {
                // Validation error will be shown on the field
              });
            }
          }
        }
        setCurrentStep(currentStep + 1);
      }).catch((err) => {
        console.log('Validation failed:', err);
      });
    } else {
      setCurrentStep(currentStep + 1);
    }
  };

  const prev = () => {
    setCurrentStep(currentStep - 1);
  };


  const steps = [
    {
      title: 'Employee Details',
      content: (
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={24} md={18} lg={18}>
            <Form.Item
              name="national_id"
              label="National ID (NIDA)"
              normalize={(value) => value ? value.replace(/\D/g, '') : value}
              rules={[
                {
                  required: true,
                  message: 'Please enter National ID',
                },
                {
                  pattern: /^\d+$/,
                  message: 'National ID must contain only numbers',
                },
                {
                  len: 20,
                  message: 'Enter valid National ID',
                },
              ]}
            >
              <Input 
                placeholder="Enter National ID" 
                maxLength={20}
              />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={6} lg={6}>
            <Form.Item
              name="title"
              label="Title"
            >
              <Select placeholder="Select Title" style={{ textAlign: 'left' }}>
                <Option key="Mr" value="Mr">Mr</Option>
                <Option key="Mrs" value="Mrs">Mrs</Option>
                <Option key="Ms" value="Ms">Ms</Option>
                <Option key="Dr" value="Dr">Dr</Option>
                <Option key="Prof" value="Prof">Prof</Option>
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="first_name"
              label="First Name"
              rules={[
                {
                  required: true,
                  message: 'Please enter first name',
                },
              ]}
            >
              <Input placeholder="Enter first name" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="middle_name"
              label="Middle Name"
              rules={[
                {
                  required: true,
                  message: 'Please enter middle name',
                },
              ]}
            >
              <Input placeholder="Enter middle name" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="surname"
              label="Surname"
              rules={[
                {
                  required: true,
                  message: 'Please enter surname',
                },
              ]}
            >
              <Input placeholder="Enter surname" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="gender"
              label="Gender"
              rules={[
                {
                  required: true,
                  message: 'Please select gender',
                },
              ]}
            >
              <Select placeholder="Select Gender" style={{ textAlign: 'left' }}>
                <Option key="Male" value="Male">Male</Option>
                <Option key="Female" value="Female">Female</Option>
                <Option key="Other" value="Other">Other</Option>
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="birth_date"
              label="Date of Birth"
              rules={[
                {
                  required: true,
                  message: 'Please select date of birth',
                },
              ]}
            >
              <DatePicker
                style={{ width: '100%', textAlign: 'left' }}
                placeholder="Select date of birth"
                format="YYYY-MM-DD"
              />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="maritalstatus"
              label="Marital Status"
              rules={[
                {
                  required: true,
                  message: 'Please select marital status',
                },
              ]}
            >
              <Select placeholder="Select Marital Status" style={{ textAlign: 'left' }}>
                <Option key="Single" value="Single">Single</Option>
                <Option key="Married" value="Married">Married</Option>
                <Option key="Divorced" value="Divorced">Divorced</Option>
                <Option key="Widowed" value="Widowed">Widowed</Option>
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={12} lg={12}>
            <Form.Item
              name="region_id"
              label="Domicile"
              rules={[
                {
                  required: true,
                  message: 'Please select domicile',
                },
              ]}
            >
              <Select 
                placeholder="Select domicile" 
                style={{ textAlign: 'left' }}
                loading={regionsLoading}
                showSearch
                filterOption={(input, option) =>
                  (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
                }
                onChange={(value) => {
                  setSelectedRegionId(value);
                  form.setFieldValue('district_id', undefined);
                }}
              >
                {regions.filter(region => region && region.id).map((region) => (
                  <Option key={`region-${region.id}`} value={region.id}>
                    {region.name}
                  </Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={12} lg={12}>
            <Form.Item
              name="district_id"
              label="District"
              rules={[
                {
                  required: true,
                  message: 'Please select district',
                },
              ]}
            >
              <Select 
                placeholder="Select district" 
                style={{ textAlign: 'left' }}
                loading={districtsLoading}
                disabled={!selectedRegionId}
                showSearch
                filterOption={(input, option) =>
                  (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
                }
              >
                {districts.filter(district => district && district.id).map((district) => (
                  <Option key={`district-${district.id}`} value={district.id}>
                    {district.name}
                  </Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="nationality"
              label="Nationality"
              rules={[
                {
                  required: true,
                  message: 'Please select nationality',
                },
              ]}
            >
              <Select placeholder="Select nationality" style={{ textAlign: 'left' }} showSearch filterOption={(input, option) =>
                (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
              }>
                <Option key="Tanzanian" value="Tanzanian">Tanzanian</Option>
                <Option key="Kenyan" value="Kenyan">Kenyan</Option>
                <Option key="Ugandan" value="Ugandan">Ugandan</Option>
                <Option key="Rwandan" value="Rwandan">Rwandan</Option>
                <Option key="Burundian" value="Burundian">Burundian</Option>
                <Option key="Ethiopian" value="Ethiopian">Ethiopian</Option>
                <Option key="Somali" value="Somali">Somali</Option>
                <Option key="Sudanese" value="Sudanese">Sudanese</Option>
                <Option key="South Sudanese" value="South Sudanese">South Sudanese</Option>
                <Option key="Zambian" value="Zambian">Zambian</Option>
                <Option key="Malawian" value="Malawian">Malawian</Option>
                <Option key="Mozambican" value="Mozambican">Mozambican</Option>
                <Option key="Zimbabwean" value="Zimbabwean">Zimbabwean</Option>
                <Option key="South African" value="South African">South African</Option>
                <Option key="Botswanan" value="Botswanan">Botswanan</Option>
                <Option key="Namibian" value="Namibian">Namibian</Option>
                <Option key="Angolan" value="Angolan">Angolan</Option>
                <Option key="Congolese" value="Congolese">Congolese</Option>
                <Option key="Ghanaian" value="Ghanaian">Ghanaian</Option>
                <Option key="Nigerian" value="Nigerian">Nigerian</Option>
                <Option key="Cameroonian" value="Cameroonian">Cameroonian</Option>
                <Option key="Ivorian" value="Ivorian">Ivorian</Option>
                <Option key="Senegalese" value="Senegalese">Senegalese</Option>
                <Option key="Egyptian" value="Egyptian">Egyptian</Option>
                <Option key="Moroccan" value="Moroccan">Moroccan</Option>
                <Option key="Algerian" value="Algerian">Algerian</Option>
                <Option key="Tunisian" value="Tunisian">Tunisian</Option>
                <Option key="Libyan" value="Libyan">Libyan</Option>
                <Option key="Mauritian" value="Mauritian">Mauritian</Option>
                <Option key="Seychellois" value="Seychellois">Seychellois</Option>
                <Option key="British" value="British">British</Option>
                <Option key="American" value="American">American</Option>
                <Option key="Canadian" value="Canadian">Canadian</Option>
                <Option key="Australian" value="Australian">Australian</Option>
                <Option key="New Zealander" value="New Zealander">New Zealander</Option>
                <Option key="Indian" value="Indian">Indian</Option>
                <Option key="Pakistani" value="Pakistani">Pakistani</Option>
                <Option key="Bangladeshi" value="Bangladeshi">Bangladeshi</Option>
                <Option key="Chinese" value="Chinese">Chinese</Option>
                <Option key="Japanese" value="Japanese">Japanese</Option>
                <Option key="Korean" value="Korean">Korean</Option>
                <Option key="Filipino" value="Filipino">Filipino</Option>
                <Option key="Indonesian" value="Indonesian">Indonesian</Option>
                <Option key="Malaysian" value="Malaysian">Malaysian</Option>
                <Option key="Singaporean" value="Singaporean">Singaporean</Option>
                <Option key="Thai" value="Thai">Thai</Option>
                <Option key="Vietnamese" value="Vietnamese">Vietnamese</Option>
                <Option key="Lebanese" value="Lebanese">Lebanese</Option>
                <Option key="Syrian" value="Syrian">Syrian</Option>
                <Option key="Jordanian" value="Jordanian">Jordanian</Option>
                <Option key="Saudi Arabian" value="Saudi Arabian">Saudi Arabian</Option>
                <Option key="Emirati" value="Emirati">Emirati</Option>
                <Option key="Kuwaiti" value="Kuwaiti">Kuwaiti</Option>
                <Option key="Qatari" value="Qatari">Qatari</Option>
                <Option key="Omani" value="Omani">Omani</Option>
                <Option key="Bahraini" value="Bahraini">Bahraini</Option>
                <Option key="Yemeni" value="Yemeni">Yemeni</Option>
                <Option key="Iraqi" value="Iraqi">Iraqi</Option>
                <Option key="Iranian" value="Iranian">Iranian</Option>
                <Option key="Turkish" value="Turkish">Turkish</Option>
                <Option key="Israeli" value="Israeli">Israeli</Option>
                <Option key="Palestinian" value="Palestinian">Palestinian</Option>
                <Option key="Afghan" value="Afghan">Afghan</Option>
                <Option key="Nepalese" value="Nepalese">Nepalese</Option>
                <Option key="Sri Lankan" value="Sri Lankan">Sri Lankan</Option>
                <Option key="Myanmar" value="Myanmar">Myanmar</Option>
                <Option key="Cambodian" value="Cambodian">Cambodian</Option>
                <Option key="Laotian" value="Laotian">Laotian</Option>
                <Option key="Mongolian" value="Mongolian">Mongolian</Option>
                <Option key="Kazakhstani" value="Kazakhstani">Kazakhstani</Option>
                <Option key="Uzbekistani" value="Uzbekistani">Uzbekistani</Option>
                <Option key="Russian" value="Russian">Russian</Option>
                <Option key="Ukrainian" value="Ukrainian">Ukrainian</Option>
                <Option key="Polish" value="Polish">Polish</Option>
                <Option key="German" value="German">German</Option>
                <Option key="French" value="French">French</Option>
                <Option key="Italian" value="Italian">Italian</Option>
                <Option key="Spanish" value="Spanish">Spanish</Option>
                <Option key="Portuguese" value="Portuguese">Portuguese</Option>
                <Option key="Dutch" value="Dutch">Dutch</Option>
                <Option key="Belgian" value="Belgian">Belgian</Option>
                <Option key="Swiss" value="Swiss">Swiss</Option>
                <Option key="Austrian" value="Austrian">Austrian</Option>
                <Option key="Swedish" value="Swedish">Swedish</Option>
                <Option key="Norwegian" value="Norwegian">Norwegian</Option>
                <Option key="Danish" value="Danish">Danish</Option>
                <Option key="Finnish" value="Finnish">Finnish</Option>
                <Option key="Greek" value="Greek">Greek</Option>
                <Option key="Romanian" value="Romanian">Romanian</Option>
                <Option key="Bulgarian" value="Bulgarian">Bulgarian</Option>
                <Option key="Croatian" value="Croatian">Croatian</Option>
                <Option key="Serbian" value="Serbian">Serbian</Option>
                <Option key="Czech" value="Czech">Czech</Option>
                <Option key="Slovak" value="Slovak">Slovak</Option>
                <Option key="Hungarian" value="Hungarian">Hungarian</Option>
                <Option key="Brazilian" value="Brazilian">Brazilian</Option>
                <Option key="Argentine" value="Argentine">Argentine</Option>
                <Option key="Chilean" value="Chilean">Chilean</Option>
                <Option key="Colombian" value="Colombian">Colombian</Option>
                <Option key="Peruvian" value="Peruvian">Peruvian</Option>
                <Option key="Venezuelan" value="Venezuelan">Venezuelan</Option>
                <Option key="Ecuadorian" value="Ecuadorian">Ecuadorian</Option>
                <Option key="Bolivian" value="Bolivian">Bolivian</Option>
                <Option key="Paraguayan" value="Paraguayan">Paraguayan</Option>
                <Option key="Uruguayan" value="Uruguayan">Uruguayan</Option>
                <Option key="Mexican" value="Mexican">Mexican</Option>
                <Option key="Cuban" value="Cuban">Cuban</Option>
                <Option key="Jamaican" value="Jamaican">Jamaican</Option>
                <Option key="Trinidadian" value="Trinidadian">Trinidadian</Option>
                <Option key="Barbadian" value="Barbadian">Barbadian</Option>
                <Option key="Bahamian" value="Bahamian">Bahamian</Option>
                <Option key="Haitian" value="Haitian">Haitian</Option>
                <Option key="Dominican" value="Dominican">Dominican</Option>
                <Option key="Costa Rican" value="Costa Rican">Costa Rican</Option>
                <Option key="Panamanian" value="Panamanian">Panamanian</Option>
                <Option key="Nicaraguan" value="Nicaraguan">Nicaraguan</Option>
                <Option key="Honduran" value="Honduran">Honduran</Option>
                <Option key="Guatemalan" value="Guatemalan">Guatemalan</Option>
                <Option key="Belizean" value="Belizean">Belizean</Option>
                <Option key="Salvadoran" value="Salvadoran">Salvadoran</Option>
                <Option key="Fijian" value="Fijian">Fijian</Option>
                <Option key="Papua New Guinean" value="Papua New Guinean">Papua New Guinean</Option>
                <Option key="Other" value="Other">Other</Option>
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="passport_no"
              label="Passport Number"
            >
              <Input placeholder="Enter passport number" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="mobile"
              label="Mobile"
              rules={[
                {
                  required: true,
                  message: 'Please enter mobile number',
                },
                {
                  pattern: /^[+]?[(]?[0-9]{3}[)]?[-\s.]?[0-9]{3}[-\s.]?[0-9]{4,6}$/,
                  message: 'Please enter a valid mobile number',
                },
              ]}
            >
              <Input placeholder="Enter mobile number" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="email"
              label="Email"
              rules={[
                {
                  required: true,
                  message: 'Please enter email',
                },
                {
                  type: 'email',
                  message: 'Please enter a valid email',
                },
              ]}
            >
              <Input placeholder="Enter email address" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="ext_no"
              label="Extension Number"
            >
              <Input placeholder="Enter extension number" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="ssn"
              label="SSN"
            >
              <Input placeholder="Enter SSN" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={12} lg={12}>
            <Form.Item
              name="pic"
              label="Picture"
              valuePropName="fileList"
              getValueFromEvent={(e) => {
                if (Array.isArray(e)) {
                  return e;
                }
                return e?.fileList;
              }}
            >
              <Upload
                listType="picture-card"
                maxCount={1}
                beforeUpload={() => false}
              >
                <div>Upload Picture</div>
              </Upload>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={12} lg={12}>
            <Form.Item
              name="sig"
              label="Signature"
              valuePropName="fileList"
              getValueFromEvent={(e) => {
                if (Array.isArray(e)) {
                  return e;
                }
                return e?.fileList;
              }}
            >
              <Upload
                listType="picture-card"
                maxCount={1}
                beforeUpload={() => false}
              >
                <div>Upload Signature</div>
              </Upload>
            </Form.Item>
          </Col>
        </Row>
      ),
    },
    {
      title: 'Medical Info',
      content: (
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={24} md={12} lg={12}>
            <Form.Item
              name="health_insurance_id"
              label="Health Insurance ID"
            >
              <Input placeholder="Enter health insurance ID" />
            </Form.Item>
          </Col>
        </Row>
      ),
    },
    {
      title: 'Employment Contract',
      content: (
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="officeid"
              label="Office"
              rules={[
                {
                  required: true,
                  message: 'Please select office',
                },
              ]}
            >
              <Select 
                placeholder="Select office" 
                style={{ textAlign: 'left' }}
                loading={officesLoading}
                showSearch
                filterOption={(input, option) =>
                  (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
                }
                onChange={(value) => {
                  const office = offices.find(off => Number(off.id) === Number(value));
                  setSelectedOffice(office);
                  // Clear PF number if office changes and auto-generates
                  if (office && office.auto_generate_pf) {
                    form.setFieldValue('pfno', null);
                    // Clear any validation errors
                    form.setFields([{ name: 'pfno', errors: [] }]);
                  }
                  // Update employment_place for backward compatibility
                  if (office) {
                    form.setFieldValue('employment_place', office.office_name);
                  }
                }}
              >
                {offices
                  .filter((office) => office && office.id && office.is_active === true)
                  .map((office) => (
                  <Option key={`office-${office.id}`} value={office.id}>
                    {office.office_name}
                  </Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
          {/* PF Number Field - Only show when office is selected and does NOT auto-generate */}
          {selectedOffice && !selectedOffice.auto_generate_pf && (
            <Col xs={24} sm={24} md={8} lg={8}>
              <Form.Item
                name="pfno"
                label="PF Number"
                rules={[
                  {
                    required: true,
                    message: `PF Number is required for ${selectedOffice.office_name}. This office does not auto-generate PF numbers.`,
                  },
                ]}
              >
                <Input 
                  placeholder={`Enter PF number for ${selectedOffice.office_name}`}
                />
              </Form.Item>
            </Col>
          )}
          {/* Message for auto-generate offices */}
          {selectedOffice && selectedOffice.auto_generate_pf && (
            <Col xs={24} sm={24} md={8} lg={8}>
              <Form.Item label="PF Number">
                <div style={{ 
                  display: 'flex', 
                  alignItems: 'center', 
                  gap: '8px',
                  padding: '4px 11px',
                  border: '1px solid #d9d9d9',
                  borderRadius: '6px',
                  backgroundColor: '#fafafa',
                  minHeight: '32px',
                  fontSize: '14px',
                  color: '#595959'
                }}>
                  <QuestionCircleOutlined style={{ color: '#1890ff', fontSize: '16px' }} />
                  <span>PF Number will be generated once approved</span>
                </div>
              </Form.Item>
            </Col>
          )}
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="scheme_id"
              label="Scheme"
              rules={[
                {
                  required: true,
                  message: 'Please select scheme',
                },
              ]}
            >
              <Select 
                placeholder="Select scheme" 
                style={{ textAlign: 'left' }}
                loading={schemesLoading}
                showSearch
                filterOption={(input, option) =>
                  (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
                }
              >
                {schemes.filter(scheme => scheme && scheme.id).map((scheme) => (
                  <Option key={`scheme-${scheme.id}`} value={scheme.id}>
                    {scheme.scheme_name || scheme.name}
                  </Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="emptype_id"
              label="Employment Type"
              rules={[
                {
                  required: true,
                  message: 'Please select employment type',
                },
              ]}
            >
              <Select 
                placeholder="Select employment type" 
                style={{ textAlign: 'left' }}
                loading={employmentTypesLoading}
                showSearch
                filterOption={(input, option) =>
                  (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
                }
              >
                {employmentTypes.filter(employmentType => employmentType && (employmentType.id || employmentType.emptype_id)).map((employmentType) => {
                  const empTypeId = employmentType.id || employmentType.emptype_id;
                  return (
                    <Option key={`emptype-${empTypeId}`} value={empTypeId}>
                      {employmentType.emptype_name}
                    </Option>
                  );
                })}
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="department_id"
              label="Department"
              rules={[
                {
                  required: true,
                  message: 'Please select department',
                },
              ]}
            >
              <Select 
                placeholder="Select department" 
                style={{ textAlign: 'left' }}
                loading={departmentsLoading}
                showSearch
                filterOption={(input, option) =>
                  (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
                }
              >
                {departments.filter(department => department && department.id).map((department) => (
                  <Option key={`dept-${department.id}`} value={department.id}>
                    {department.department_name || department.name}
                  </Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="bank_id"
              label="Bank"
              rules={[
                {
                  required: true,
                  message: 'Please select bank',
                },
              ]}
            >
              <Select 
                placeholder="Select bank" 
                style={{ textAlign: 'left' }}
                loading={banksLoading}
                showSearch
                filterOption={(input, option) =>
                  (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
                }
              >
                {banks.filter(bank => bank && (bank.id || bank.bank_id)).map((bank) => {
                  const bankId = bank.id || bank.bank_id;
                  return (
                    <Option key={`bank-${bankId}`} value={bankId}>
                      {bank.bank_name}
                    </Option>
                  );
                })}
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="account_no"
              label="Account Number"
              rules={[
                {
                  required: true,
                  message: 'Please enter account number',
                },
              ]}
            >
              <Input placeholder="Enter account number" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="basicsalary"
              label="Basic Salary"
              rules={[
                {
                  required: true,
                  message: 'Please enter basic salary',
                },
              ]}
            >
              <Input type="number" placeholder="Enter basic salary" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="tin"
              label="TIN"
              rules={[
                {
                  required: true,
                  message: 'Please enter TIN',
                },
              ]}
            >
              <Input placeholder="Enter TIN" />
            </Form.Item>
          </Col>
        </Row>
      ),
    },
    {
      title: 'Employee Academic Details',
      content: (
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="educational_level_id"
              label="Educational Level"
              rules={[
                {
                  required: true,
                  message: 'Please select educational level',
                },
              ]}
            >
              <Select 
                placeholder="Select educational level"
                loading={loadingEducationalLevels}
                showSearch
                filterOption={(input, option) =>
                  (option?.children ?? '').toLowerCase().includes(input.toLowerCase())
                }
                style={{ textAlign: 'left' }}
              >
                {educationalLevels.filter(level => level && level.id).map(level => (
                  <Option key={`edulevel-${level.id}`} value={level.id}>
                    {level.level_name || level.name}
                  </Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={24} lg={24}>
            <div style={{ marginBottom: 12, fontWeight: 500, fontSize: '14px' }}>Educational Certificates (PDF only)</div>
          </Col>
          {[
            { name: 'primary_education_certificate', label: 'Primary Education' },
            { name: 'secondary_education_certificate', label: 'Secondary Education' },
            { name: 'advanced_education_certificate', label: 'Advanced Education' },
            { name: 'diploma_certificate', label: 'Diploma' },
            { name: 'certificate', label: 'Certificate' },
            { name: 'bachelor_degree_certificate', label: 'Bachelor Degree' },
            { name: 'masters_certificate', label: 'Masters' },
            { name: 'phd_certificate', label: 'PhD' },
          ].map((cert) => (
            <Col xs={24} sm={12} md={8} lg={6} key={cert.name}>
              <Form.Item
                name={cert.name}
                label={cert.label}
                valuePropName="fileList"
                getValueFromEvent={(e) => {
                  if (Array.isArray(e)) return e;
                  return e?.fileList;
                }}
              >
                <Upload
                  accept=".pdf"
                  beforeUpload={(file) => {
                    const isPDF = file.type === 'application/pdf';
                    if (!isPDF) {
                      message.error('Only PDF files are allowed!');
                    }
                    return false;
                  }}
                  maxCount={1}
                  listType="text"
                >
                  <Button icon={<UploadOutlined />} size="small" style={{ width: '100%' }}>
                    Upload PDF
                  </Button>
                </Upload>
              </Form.Item>
            </Col>
          ))}
          <Col xs={24} sm={24} md={8} lg={8}>
            <Form.Item
              name="account_status"
              label="Account Status"
              rules={[
                {
                  required: true,
                  message: 'Please select account status',
                },
              ]}
            >
              <Select placeholder="Select Account Status" style={{ textAlign: 'left' }}>
                <Option key="Active" value="Active">Active</Option>
                <Option key="Inactive" value="Inactive">Inactive</Option>
                <Option key="Suspended" value="Suspended">Suspended</Option>
              </Select>
            </Form.Item>
          </Col>
        </Row>
      ),
    },
  ];

  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={handleSubmit}
      initialValues={{ status: true, is_first_time: false, ...initialValues }}
      autoComplete="off"
      style={{ textAlign: 'left' }}
      onKeyDown={(e) => {
        // Prevent full form submit with Enter on intermediate steps.
        // Instead, treat Enter as "Next" until the last step.
        if (e.key === 'Enter') {
          const isTextArea = e.target && e.target.tagName === 'TEXTAREA';
          if (isTextArea) return;

          if (currentStep < steps.length - 1) {
            e.preventDefault();
            next();
          }
        }
      }}
    >
      <Steps current={currentStep} style={{ marginBottom: 24 }}>
        {steps.map((step, index) => (
          <Step 
            key={index} 
            title={<span style={{ fontSize: '12px', fontWeight: 'normal' }}>{step.title}</span>}
          />
        ))}
      </Steps>

      <div style={{ minHeight: '400px' }}>
        {steps[currentStep].content}
      </div>

      <Row gutter={16} style={{ marginTop: 24 }}>
        <Col span={24}>
          <Form.Item style={{ marginBottom: 0 }}>
            <div style={{ width: '100%', display: 'flex', justifyContent: 'space-between' }}>
              <Button
                disabled={currentStep === 0}
                onClick={prev}
              >
                Previous
              </Button>
              <div>
                {currentStep < steps.length - 1 && (
                  <Button
                    type="primary"
                    onClick={next}
                    style={{
                      backgroundColor: '#962E32',
                      borderColor: '#962E32',
                    }}
                  >
                    Next
                  </Button>
                )}
                {currentStep === steps.length - 1 && (
                  <Button
                    type="primary"
                    htmlType="submit"
                    loading={loading}
                    style={{
                      backgroundColor: '#962E32',
                      borderColor: '#962E32',
                    }}
                  >
                    {initialValues ? 'Update' : 'Submit'}
                  </Button>
                )}
              </div>
            </div>
          </Form.Item>
        </Col>
      </Row>
    </Form>
  );
};

UserRegistrationForm.propTypes = {
  onSubmit: PropTypes.func,
  onCancel: PropTypes.func,
  initialValues: PropTypes.object,
};

export default UserRegistrationForm;

