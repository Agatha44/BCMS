/**
 * @typedef {Object} UpdateDetail
 * @property {string} field_name
 * @property {string} old_value
 * @property {string} new_value
 */

/**
 * @typedef {Object} RegistrationBodyType
 * @property {number} id
 * @property {string} name
 * @property {string} [description]
 * @property {number} is_active
 */

/**
 * @typedef {Object} RegistrationUserSummary
 * @property {number} id
 * @property {string} name
 * @property {string} email
 */

/**
 * @typedef {Object} RegistrationRequest
 * @property {number} id
 * @property {'registration'|'exemption'|'update'} request_type
 * @property {string} plate_number
 * @property {number} [body_type_id]
 * @property {string} [body_type_name]
 * @property {string} owner_name
 * @property {string} owner_phone
 * @property {string} [owner_email]
 * @property {string} [nida_number]
 * @property {string} [exemption_reason]
 * @property {UpdateDetail[]} [update_details]
 * @property {string} [registration_card_path]
 * @property {'pending'|'approved'|'rejected'} status
 * @property {number} submitted_by
 * @property {string} submitted_at
 * @property {number} [reviewed_by]
 * @property {string} [reviewed_at]
 * @property {string} [comments]
 * @property {string} created_at
 * @property {string} updated_at
 * @property {RegistrationBodyType} [body_type]
 * @property {RegistrationUserSummary} [submitted_by_user]
 * @property {RegistrationUserSummary} [reviewed_by_user]
 * @property {string} [status_text]
 * @property {string} [request_type_text]
 * @property {string} [formatted_submitted_at]
 * @property {string} [formatted_reviewed_at]
 */

/**
 * @typedef {Object} CreateRegistrationRequest
 * @property {'registration'|'exemption'|'update'} request_type
 * @property {string} plate_number
 * @property {number} [body_type_id]
 * @property {string} [body_type_name]
 * @property {string} owner_name
 * @property {string} owner_phone
 * @property {string} [owner_email]
 * @property {string} [nida_number]
 * @property {string} [exemption_reason]
 * @property {UpdateDetail[]} [update_details]
 */

/**
 * @typedef {Object} UpdateRegistrationRequest
 * @property {string} [plate_number]
 * @property {number} [body_type_id]
 * @property {string} [body_type_name]
 * @property {string} [owner_name]
 * @property {string} [owner_phone]
 * @property {string} [owner_email]
 * @property {string} [nida_number]
 * @property {string} [exemption_reason]
 * @property {UpdateDetail[]} [update_details]
 */

/**
 * @typedef {Object} ReviewRegistrationRequest
 * @property {'approved'|'rejected'} status
 * @property {string} [comments]
 */

/**
 * @typedef {Object} RegistrationFilters
 * @property {string} [search]
 * @property {'registration'|'exemption'|'update'} [request_type]
 * @property {'pending'|'approved'|'rejected'} [status]
 * @property {string} [sort_by]
 * @property {'asc'|'desc'} [sort_order]
 * @property {number} [per_page]
 * @property {number} [page]
 */

/**
 * @typedef {Object} RegistrationStatisticsByType
 * @property {number} registration
 * @property {number} exemption
 * @property {number} update
 */

/**
 * @typedef {Object} RegistrationStatistics
 * @property {number} total
 * @property {number} pending
 * @property {number} approved
 * @property {number} rejected
 * @property {RegistrationStatisticsByType} by_type
 */

/**
 * @typedef {Object} RegistrationPagination
 * @property {number} current_page
 * @property {number} last_page
 * @property {number} per_page
 * @property {number} total
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {Object} RegistrationRequestsResponseData
 * @property {RegistrationRequest[]} requests
 * @property {RegistrationPagination} pagination
 */

/**
 * @typedef {Object} RegistrationRequestsResponse
 * @property {boolean} success
 * @property {string} message
 * @property {RegistrationRequestsResponseData} data
 */

/**
 * @typedef {Object} RegistrationRequestResponse
 * @property {boolean} success
 * @property {string} message
 * @property {RegistrationRequest} data
 */

/**
 * @typedef {Object} RegistrationStatisticsResponse
 * @property {boolean} success
 * @property {string} message
 * @property {RegistrationStatistics} data
 */

export {};


