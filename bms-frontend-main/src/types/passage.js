/**
 * @typedef {Object} Passage
 * @property {number} id
 * @property {string} plate_no
 * @property {string} [lane_no]
 * @property {number} amount
 * @property {string} [passage_time]
 * @property {string} [receipt_number]
 * @property {string} status
 * @property {string} created_at
 * @property {string} updated_at
 * @property {string} [bundle_name]
 * @property {string} [start_date]
 * @property {string} [end_date]
 * @property {string} [contract_number]
 * @property {string} [bundle_status]
 * @property {string} [payment_method]
 * @property {number} [charge]
 * @property {string} [pass_date]
 * @property {string} [payment_receipt]
 * @property {number} [payment_amount]
 * @property {'toll'|'bundle'} [passage_type]
 */

/**
 * @typedef {Object} PassagesPagination
 * @property {number} current_page
 * @property {number} last_page
 * @property {number} per_page
 * @property {number} total
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {Object} PassagesResponse
 * @property {Passage[]} passages
 * @property {PassagesPagination} pagination
 */

/**
 * @typedef {Object} PassageFilters
 * @property {number} [per_page]
 * @property {number} [page]
 */

export {};


