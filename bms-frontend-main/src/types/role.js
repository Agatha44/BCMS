/**
 * @typedef {Object} Role
 * @property {number} id
 * @property {string} name
 * @property {string} description
 * @property {number} is_active
 * @property {string} [status_text]
 * @property {number} [created_by]
 * @property {string} [created_at]
 * @property {number} [updated_by]
 * @property {string} [updated_at]
 */

/**
 * @typedef {Object} Permission
 * @property {number} id
 * @property {string} name
 * @property {string} description
 * @property {number} is_active
 * @property {string} [status_text]
 * @property {number} [created_by]
 * @property {string} [created_at]
 * @property {number} [updated_by]
 * @property {string} [updated_at]
 */

/**
 * @typedef {Object} CreateRoleRequest
 * @property {string} name
 * @property {string} description
 * @property {number} [is_active]
 */

/**
 * @typedef {Object} UpdateRoleRequest
 * @property {number} id
 * @property {string} name
 * @property {string} description
 * @property {number} [is_active]
 */

/**
 * @typedef {Object} RoleFilters
 * @property {string} [search]
 * @property {number} [status]
 * @property {string} [sort_by]
 * @property {'asc'|'desc'} [sort_order]
 * @property {number} [per_page]
 * @property {number} [page]
 */

/**
 * @typedef {Object} PermissionFilters
 * @property {string} [search]
 * @property {number} [status]
 * @property {string} [sort_by]
 * @property {'asc'|'desc'} [sort_order]
 * @property {number} [per_page]
 * @property {number} [page]
 */

/**
 * @typedef {Object} RolePagination
 * @property {number} current_page
 * @property {number} last_page
 * @property {number} per_page
 * @property {number} total
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {Object} RolesResponse
 * @property {Role[]} roles
 * @property {RolePagination} pagination
 */

/**
 * @typedef {Object} PermissionsResponse
 * @property {Permission[]} permissions
 * @property {RolePagination} pagination
 */

/**
 * @typedef {Object} AssignPermissionsRequest
 * @property {number[]} permission_ids
 */

export {};


