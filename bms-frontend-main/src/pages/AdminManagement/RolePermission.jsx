import React, { useState, useEffect } from 'react';
import { Modal, Form, Button, Empty, Space, Divider, List, Tag, App } from 'antd';
import { SafetyOutlined, PlusOutlined, DeleteOutlined } from '@ant-design/icons';
import PropTypes from 'prop-types';
import { apiService } from '../../services/api.jsx';
import CollectionLoader from '../CollectionManagement/components/CollectionLoader.jsx';
import '../../styles/common.css';

const RolePermission = ({ open, onClose, role }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [fetching, setFetching] = useState(false);
  const [rolePermissions, setRolePermissions] = useState([]);
  const [rolePermissionAssignments, setRolePermissionAssignments] = useState([]);
  const [availablePermissions, setAvailablePermissions] = useState([]);

  // Helper function to check if is_active is true (handles boolean, number, and string)
  const isActive = (value) => {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value === 1;
    if (typeof value === 'string') return value === '1' || value === 'true';
    return false;
  };

  // Open permission assignment modal - Fetch role's current permissions
  const openPermissionModal = async (roleData) => {
    if (!roleData || !roleData.id) {
      message.error('Role information is missing');
      return;
    }

    setFetching(true);
    setRolePermissions([]);
    setRolePermissionAssignments([]);
    setAvailablePermissions([]);

    try {
      // Fetch role's current role-permission assignments
      const roleResponse = await apiService.fetchBmsRolePermissions({ role_id: roleData.id });

      console.log('Role permissions response:', roleResponse);
      
      if (roleResponse.success && roleResponse.data) {
        console.log('Role permissions data:', roleResponse.data);
        
        // Handle different response formats
        let assignmentsRaw = roleResponse.data.assignments || roleResponse.data.role_permissions || roleResponse.data;
        
        // If it's a single object (has permission_id or role_id), wrap it in an array
        if (assignmentsRaw && !Array.isArray(assignmentsRaw)) {
          // Check if it's a single assignment object
          if (assignmentsRaw.permission_id !== undefined || assignmentsRaw.role_id !== undefined) {
            assignmentsRaw = [assignmentsRaw];
          } else {
            assignmentsRaw = [];
          }
        }
        
        // Ensure we have an array
        const assignments = Array.isArray(assignmentsRaw) ? assignmentsRaw : [];
        setRolePermissionAssignments(assignments);

        // Extract permissions from assignments
        // Extract full permission objects when available, otherwise create minimal objects with ID
        let permissions = assignments
          .map((assignment) => {
            // Prefer full permission object if available
            if (assignment.permission && assignment.permission.id) {
              return assignment.permission;
            }
            // Fallback: create permission object with available data
            const permissionObj = {
              id: assignment.permission_id,
              name: assignment.permission_name || '',
              description: '',
              is_active: assignment.is_active || 0,
            };
            return permissionObj;
          })
          .filter((p) => p && p.id);

        // Fetch all available permissions to enrich permission objects
        const permissionsResponse = await apiService.getBmsPermissions();
        if (permissionsResponse.success && permissionsResponse.data) {
          console.log('All available permissions:', permissionsResponse.data);
          const allPermissions = permissionsResponse.data.permissions || permissionsResponse.data || [];
          
          // Filter only active permissions
          const activePermissions = Array.isArray(allPermissions) 
            ? allPermissions.filter(p => isActive(p.is_active))
            : [];
          
          setAvailablePermissions(activePermissions);
          
          // Enrich permissions with full data from available permissions
          permissions = permissions.map((permission) => {
            // If permission already has a name, return as is
            if (permission.name) {
              return permission;
            }
            // Otherwise, find the full permission object from available permissions
            const fullPermission = activePermissions.find((p) => p.id === permission.id);
            return fullPermission || permission;
          });
        }
        
        setRolePermissions(permissions);
        
        console.log('Final role permissions:', permissions);
      } else {
        // No permissions assigned yet, just fetch available permissions
        const permissionsResponse = await apiService.getBmsPermissions();
        if (permissionsResponse.success && permissionsResponse.data) {
          const allPermissions = permissionsResponse.data.permissions || permissionsResponse.data || [];
          const activePermissions = Array.isArray(allPermissions) 
            ? allPermissions.filter(p => isActive(p.is_active))
            : [];
          setAvailablePermissions(activePermissions);
        }
        setRolePermissions([]);
      }
    } catch (err) {
      message.error('Failed to load role permissions');
      console.error('Error loading role permissions:', err);
      setRolePermissions([]);
      setRolePermissionAssignments([]);
      setAvailablePermissions([]);
    } finally {
      setFetching(false);
    }
  };

  // Load permissions when modal opens
  useEffect(() => {
    if (open && role) {
      openPermissionModal(role);
    } else {
      // Reset state when modal closes
      setRolePermissions([]);
      setRolePermissionAssignments([]);
      setAvailablePermissions([]);
      // Only reset form fields if modal is open (Form is mounted)
      if (open) {
        try {
          form.resetFields();
        } catch (error) {
          // Form might not be mounted yet, ignore error
          console.debug('Form not mounted yet, skipping resetFields');
        }
      }
    }
  }, [open, role, form]);

  // Set form values after fetching completes and Form is mounted
  useEffect(() => {
    if (!fetching && open && rolePermissions.length >= 0) {
      // Use setTimeout to ensure Form is fully mounted
      const timer = setTimeout(() => {
        try {
          const permissionIds = rolePermissions.map(p => p.id);
          form.setFieldsValue({ permissions: permissionIds });
        } catch (error) {
          // Form might not be mounted yet, ignore error
          console.debug('Form not mounted yet, skipping setFieldsValue');
        }
      }, 0);
      return () => clearTimeout(timer);
    }
  }, [fetching, open, rolePermissions, form]);

  // Update role permissions - Add or remove permissions
  const handleUpdatePermissions = async (permissionIds) => {
    if (!role || !role.id) {
      message.error('Role information is missing');
      return;
    }

    setLoading(true);
    try {
      // Get current assignments to determine which to add and which to remove
      const currentPermissionIds = rolePermissionAssignments.map(a => a.permission_id);
      const permissionsToAdd = permissionIds.filter(id => !currentPermissionIds.includes(id));
      const permissionsToRemove = currentPermissionIds.filter(id => !permissionIds.includes(id));

      const requests = [];

      // Remove permissions that are no longer selected
      for (const permissionId of permissionsToRemove) {
        const assignment = rolePermissionAssignments.find(a => a.permission_id === permissionId);
        if (assignment && assignment.id) {
          requests.push({
            type: 'delete',
            promise: apiService.deleteBmsRolePermission(assignment.id),
            permissionId: permissionId
          });
        }
      }

      // Add new permissions (default to active)
      for (const permissionId of permissionsToAdd) {
        requests.push({
          type: 'add',
          promise: apiService.assignBmsPermissionRole({
            role_id: role.id,
            permission_id: permissionId,
            is_active: 1, // Default to active when assigning
          }),
          permissionId: permissionId
        });
      }

      // Execute all requests
      if (requests.length > 0) {
        const results = await Promise.allSettled(requests.map(r => r.promise));
        
        const failed = results.filter((result, index) => result.status === 'rejected');
        const successful = results.filter((result, index) => result.status === 'fulfilled' && result.value.success);

        if (failed.length > 0) {
          const errorMessages = failed.map((result, index) => {
            const req = requests[index];
            return `Failed to ${req.type} permission ${req.permissionId}`;
          });
          message.error({
            content: `Some operations failed: ${errorMessages.join(', ')}`,
            duration: 5,
          });
        }

        if (successful.length > 0) {
          message.success({
            content: `Successfully updated ${successful.length} permission(s)`,
            duration: 3,
          });
        }

        // Refresh the permissions after update
        await openPermissionModal(role);
      } else {
        message.info('No changes to save');
      }
    } catch (err) {
      let errorMessage = 'An error occurred while updating role permissions';
      if (err?.message) {
        errorMessage = err.message;
      } else if (typeof err === 'string') {
        errorMessage = err;
      }
      
      message.error({
        content: errorMessage,
        duration: 5,
      });
      console.error('Error updating role permissions:', err);
    } finally {
      setLoading(false);
    }
  };

  // Add permission to role
  const addPermissionToRole = async (permission) => {
    if (!role || !role.id) {
      message.error('Role information is missing');
      return;
    }
    
    // Check if already assigned
    if (rolePermissionAssignments.find((a) => a.permission_id === permission.id)) {
      message.info({
        content: 'This permission is already assigned to this role',
        duration: 2,
      });
      return;
    }

    setLoading(true);
    try {
      const response = await apiService.assignBmsPermissionRole({
        role_id: role.id,
        permission_id: permission.id,
        is_active: 1, // Default to active
      });

      if (response.success) {
        message.success({
          content: 'Permission assigned successfully',
          duration: 2,
        });
        
        // Refresh permissions
        await openPermissionModal(role);
      } else {
        const errorMessage = response.message || response.errors || 'Failed to assign permission to role';
        let displayMessage = typeof errorMessage === 'string' ? errorMessage : 'Failed to assign permission. Please try again.';
        
        message.error({
          content: displayMessage,
          duration: 5,
        });
      }
    } catch (err) {
      let errorMessage = 'An error occurred while assigning permission';
      if (err?.message) {
        errorMessage = err.message;
      } else if (err?.data?.message) {
        errorMessage = err.data.message;
      } else if (typeof err === 'string') {
        errorMessage = err;
      }
      
      message.error({
        content: errorMessage,
        duration: 5,
      });
      console.error('Error assigning permission:', err);
    } finally {
      setLoading(false);
    }
  };

  // Remove permission from role
  const removePermissionFromRole = async (permissionId) => {
    if (!role || !role.id) {
      message.error('Role information is missing');
      return;
    }

    const assignment = rolePermissionAssignments.find(a => a.permission_id === permissionId);
    
    if (!assignment || !assignment.id) {
      message.warning('Assignment not found');
      return;
    }

    setLoading(true);
    try {
      const response = await apiService.deleteBmsRolePermission(assignment.id);
      
      if (response.success) {
        message.success({
          content: 'Permission removed successfully',
          duration: 2,
        });
        
        // Refresh permissions
        await openPermissionModal(role);
      } else {
        message.error({
          content: response.message || 'Failed to remove permission',
          duration: 3,
        });
      }
    } catch (err) {
      message.error({
        content: 'An error occurred while removing permission',
        duration: 3,
      });
      console.error('Error removing permission:', err);
    } finally {
      setLoading(false);
    }
  };

  // Handle form submit
  const handleSubmit = async (values) => {
    const selectedIds = values.permissions || [];
    await handleUpdatePermissions(selectedIds);
  };

  // Handle cancel
  const handleCancel = () => {
    form.resetFields();
    onClose();
  };

  // Get unassigned permissions
  const unassignedPermissions = availablePermissions.filter((permission) => {
    const isAssigned = rolePermissionAssignments.some(
      (assignment) => assignment.permission_id === permission.id
    );
    return !isAssigned;
  });

  return (
    <Modal
      title={
        <Space>
          <SafetyOutlined />
          <span>{role ? `Manage Permissions for ${role.role_name || role.name}` : 'Manage Permissions'}</span>
        </Space>
      }
      open={open}
      onCancel={handleCancel}
      footer={null}
      width={typeof window !== 'undefined' && window.innerWidth < 768 ? '95%' : 900}
      destroyOnHidden={true}
      className="role-permission-modal"
      style={{ top: 20 }}
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{ permissions: [] }}
        style={{ textAlign: 'left' }}
      >
        {fetching ? (
          <CollectionLoader size={64} compact />
        ) : (
          <>
          <div style={{ marginBottom: 16 }}>
            <h3 style={{ margin: 0 }}>Select Permissions</h3>
            <p style={{ margin: '8px 0 0 0', color: '#8c8c8c', fontSize: 14 }}>
              Check the permissions you want to assign to this role. Assigned permissions are automatically checked.
            </p>
          </div>

          {/* Current Role Permissions */}
          <div style={{ marginBottom: 24 }}>
            <h4 style={{ marginBottom: 12, color: '#962E32', fontWeight: 600 }}>
              Current Permissions ({rolePermissions.length})
            </h4>
            {rolePermissions.length > 0 ? (
              <List
                dataSource={rolePermissions}
                renderItem={(permission) => {
                  const assignment = rolePermissionAssignments.find(a => a.permission_id === permission.id);
                  return (
                    <List.Item
                      style={{
                        padding: '12px',
                        marginBottom: 8,
                        backgroundColor: '#fff5f5',
                        border: '1px solid #ffccc7',
                        borderRadius: 4,
                      }}
                      actions={[
                        <Button
                          key="remove"
                          type="text"
                          danger
                          icon={<DeleteOutlined />}
                          onClick={() => removePermissionFromRole(permission.id)}
                          loading={loading}
                          size="small"
                        >
                          Remove
                        </Button>
                      ]}
                    >
                      <List.Item.Meta
                        title={
                          <div>
                            <span style={{ fontWeight: 500 }}>{permission.name || `Permission ID: ${permission.id}`}</span>
                            <Tag
                              color={isActive(permission.is_active) ? 'green' : 'red'}
                              style={{ marginLeft: 8 }}
                            >
                              {isActive(permission.is_active) ? 'Active' : 'Inactive'}
                            </Tag>
                            {assignment && (
                              <Tag
                                color={isActive(assignment.is_active) ? 'blue' : 'default'}
                                style={{ marginLeft: 8 }}
                              >
                                Assignment: {isActive(assignment.is_active) ? 'Active' : 'Inactive'}
                              </Tag>
                            )}
                          </div>
                        }
                        description={
                          <div style={{ fontSize: 12, color: '#8c8c8c', marginTop: 4 }}>
                            {permission.permission && <div>Permission: {permission.permission}</div>}
                            {permission.route && <div>Route: {permission.route}</div>}
                            {permission.controller && <div>Controller: {permission.controller}</div>}
                          </div>
                        }
                      />
                    </List.Item>
                  );
                }}
              />
            ) : (
              <Empty description="No permissions assigned" style={{ padding: '20px 0' }} />
            )}
          </div>

          <Divider />

          {/* Available Permissions to Add */}
          <div style={{ marginBottom: 24 }}>
            <h4 style={{ marginBottom: 12, color: '#1890ff', fontWeight: 600 }}>
              Available Permissions ({unassignedPermissions.length})
            </h4>
            {unassignedPermissions.length > 0 ? (
              <List
                dataSource={unassignedPermissions}
                renderItem={(permission) => (
                  <List.Item
                    style={{
                      padding: '12px',
                      marginBottom: 8,
                      backgroundColor: '#f0f9ff',
                      border: '1px solid #bae6fd',
                      borderRadius: 4,
                    }}
                    actions={[
                      <Button
                        key="add"
                        type="text"
                        icon={<PlusOutlined />}
                        onClick={() => addPermissionToRole(permission)}
                        loading={loading}
                        size="small"
                      >
                        Add
                      </Button>
                    ]}
                  >
                    <List.Item.Meta
                      title={
                        <div>
                          <span style={{ fontWeight: 500 }}>{permission.name || `Permission ID: ${permission.id}`}</span>
                          <Tag
                            color={isActive(permission.is_active) ? 'green' : 'red'}
                            style={{ marginLeft: 8 }}
                          >
                            {isActive(permission.is_active) ? 'Active' : 'Inactive'}
                          </Tag>
                        </div>
                      }
                      description={
                        <div style={{ fontSize: 12, color: '#8c8c8c', marginTop: 4 }}>
                          {permission.permission && <div>Permission: {permission.permission}</div>}
                          {permission.route && <div>Route: {permission.route}</div>}
                          {permission.controller && <div>Controller: {permission.controller}</div>}
                        </div>
                      }
                    />
                  </List.Item>
                )}
              />
            ) : (
              <Empty description="All available permissions are already assigned" style={{ padding: '20px 0' }} />
            )}
          </div>

          <Divider />

          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
            <Button onClick={handleCancel} disabled={loading}>
              Cancel
            </Button>
            <Button
              type="primary"
              htmlType="submit"
              loading={loading}
              className="btn-standard-primary"
            >
              Save Permissions
            </Button>
          </div>
          </>
        )}
      </Form>
    </Modal>
  );
};

RolePermission.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  role: PropTypes.object,
};

export default RolePermission;

