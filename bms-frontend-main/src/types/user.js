/**
 * @typedef {Object} UserRole
 * @property {number} id
 * @property {string} name
 */

/**
 * @typedef {Object} User
 * @property {number} id
 * @property {string} first_name
 * @property {string} [middle_name]
 * @property {string} surname
 * @property {string} full_name
 * @property {string} username
 * @property {string} email
 * @property {string} phone
 * @property {number} status
 * @property {string} status_text
 * @property {UserRole[]} roles
 * @property {string} created_at
 * @property {string} updated_at
 */

/**
 * @typedef {Object} Role
 * @property {number} id
 * @property {string} name
 * @property {string} [description]
 * @property {number} is_active
 */

/**
 * @typedef {Object} CreateUserRequest
 * @property {string} first_name
 * @property {string} [middle_name]
 * @property {string} surname
 * @property {string} email
 * @property {string} phone
 * @property {string} [nida]
 */

/**
 * @typedef {Object} UpdateUserStatusRequest
 * @property {number} status
 */

/**
 * @typedef {Object} UpdateUserRolesRequest
 * @property {number[]} role_ids
 */

/**
 * @typedef {Object} UsersPagination
 * @property {number} current_page
 * @property {number} last_page
 * @property {number} per_page
 * @property {number} total
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {Object} UsersResponse
 * @property {User[]} users
 * @property {UsersPagination} pagination
 */

/**
 * @typedef {Object} UserFilters
 * @property {string} [search]
 * @property {number} [status]
 * @property {string} [role]
 * @property {string} [sort_by]
 * @property {'asc'|'desc'} [sort_order]
 * @property {number} [per_page]
 * @property {number} [page]
 */

export {};


