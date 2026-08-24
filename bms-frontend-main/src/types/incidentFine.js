/**
 * @typedef {Object} IncidentFine
 * @property {number} id
 * @property {string} driver_name
 * @property {string} plate_number
 * @property {string} vehicle_owner
 * @property {string} incident_date
 * @property {number} incident_nature
 * @property {string} [nature_incident]
 * @property {number} amount
 * @property {string} phone_number
 * @property {string} police_rb
 * @property {number} payment_type
 * @property {string} payer_name
 * @property {string} email
 * @property {string|null} control_num
 * @property {string|null} psp_receipt_num
 * @property {string|null} pay_ref_id
 * @property {string} t_status
 * @property {number} is_cancelled
 * @property {string} [cancel_reason]
 * @property {string} [error_code]
 * @property {string} [created_at]
 * @property {string} [updated_at]
 */

/**
 * @typedef {Object} CreateIncidentFineRequest
 * @property {string} driver_name
 * @property {string} plate_number
 * @property {string} vehicle_owner
 * @property {string} incident_date
 * @property {number} incident_nature
 * @property {number} amount
 * @property {string} phone_number
 * @property {string} police_rb
 * @property {number} payment_type
 * @property {string} payer_name
 * @property {string} email
 */

/**
 * @typedef {Object} UpdateIncidentFineRequest
 * @property {number} id
 * @property {string} driver_name
 * @property {string} plate_number
 * @property {string} vehicle_owner
 * @property {string} incident_date
 * @property {number} incident_nature
 * @property {number} amount
 * @property {string} phone_number
 * @property {string} police_rb
 * @property {number} payment_type
 * @property {string} payer_name
 * @property {string} email
 */

/**
 * @typedef {Object} IncidentCancelBillRequest
 * @property {number} id
 * @property {string} cancel_reason
 */

/**
 * @typedef {Object} IncidentGepgError
 * @property {string} error_code
 * @property {string} description
 */

/**
 * @type {{[key: number]: string}}
 */
export const INCIDENT_TYPES = {
  1: 'Vehicle Payment Evasion',
  2: 'Motorcycle Evasion',
  3: 'Infrastructure Damage',
};

/**
 * @type {{[key: number]: string}}
 */
export const PAYMENT_TYPES = {
  1: 'Driver',
  2: 'Insurance',
  3: 'Owner',
};

/**
 * @type {{[key: string]: string}}
 */
export const TRANSACTION_STATUS = {
  GF: 'Failed',
  SP: 'Successful',
  // Add more status codes as needed
};


