/**
 * @typedef {Object} PaymentMethod
 * @property {number} id
 * @property {string} name
 */

/**
 * @typedef {Object} Lane
 * @property {number} id
 * @property {string} lane_no
 * @property {string} camera_ip
 * @property {string} reader_ip
 * @property {boolean} status
 * @property {number} [created_by]
 * @property {string} [created_at]
 * @property {number} [updated_by]
 * @property {string} [updated_at]
 * @property {string} [com_port]
 * @property {number|PaymentMethod} payment_method
 * @property {number} [reader_port]
 * @property {string} [mac_address]
 * @property {string} [gate_ip]
 * @property {string} [status_text]
 * @property {string} [payment_method_text]
 * @property {PaymentMethod} [paymentMethod]
 */

/**
 * @typedef {Object} CreateLaneRequest
 * @property {string} lane_no
 * @property {string} camera_ip
 * @property {string} reader_ip
 * @property {string} [com_port]
 * @property {number} payment_method
 * @property {number} [reader_port]
 * @property {string} [mac_address]
 * @property {string} [gate_ip]
 * @property {boolean} [status]
 */

/**
 * @typedef {Object} UpdateLaneRequest
 * @property {number} id
 * @property {string} lane_no
 * @property {string} camera_ip
 * @property {string} reader_ip
 * @property {string} [com_port]
 * @property {number} payment_method
 * @property {number} [reader_port]
 * @property {string} [mac_address]
 * @property {string} [gate_ip]
 * @property {boolean} [status]
 */

/**
 * @typedef {Object} LaneFilters
 * @property {string} [search]
 * @property {boolean} [status]
 * @property {number} [payment_method]
 * @property {string} [sort_by]
 * @property {'asc'|'desc'} [sort_order]
 * @property {number} [per_page]
 * @property {number} [page]
 */

/**
 * @typedef {Object} LanesPagination
 * @property {number} current_page
 * @property {number} last_page
 * @property {number} per_page
 * @property {number} total
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {Object} LanesResponse
 * @property {Lane[]} lanes
 * @property {LanesPagination} pagination
 */

/**
 * @typedef {Object} ManualOpenGateRequest
 * @property {number} lane_id
 * @property {number} user_id
 * @property {string} reason
 */

export {};


