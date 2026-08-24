/**
 * @typedef {Object} Vehicle
 * @property {number} id
 * @property {string} plate_no
 * @property {number} body_type_id
 * @property {string} body_type
 * @property {string} [account_no]
 * @property {number} exempted
 * @property {string} [exempt_reason]
 * @property {number} status
 * @property {string} created_at
 * @property {string} updated_at
 */

/**
 * @typedef {Object} VehicleAccount
 * @property {number} id
 * @property {string} account_no
 * @property {string} first_name
 * @property {string} [middle_name]
 * @property {string} surname
 * @property {string} phone
 * @property {string} email
 * @property {number} account_balance
 * @property {number} amount_received
 * @property {string} status
 * @property {string} created_at
 * @property {string} updated_at
 */

/**
 * @typedef {Object} VehicleResponse
 * @property {Vehicle} vehicle
 * @property {VehicleAccount} [account]
 * @property {number} [price]
 * @property {string} [image] base64 encoded image
 */

/**
 * @typedef {Object} VehicleSearchRequest
 * @property {string} plate_no
 * @property {string} source
 */

export {};


