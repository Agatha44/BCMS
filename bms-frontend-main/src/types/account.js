/**
 * @typedef {Object} Account
 * @property {number} id
 * @property {string} account_no
 * @property {string} [nida]
 * @property {string} [control_no]
 * @property {number} account_balance
 * @property {number} amount_received
 * @property {string} first_name
 * @property {string} [middle_name]
 * @property {string} surname
 * @property {string} phone
 * @property {string} email
 * @property {string} [password_hash]
 * @property {string} [otp_cdate]
 * @property {string} [one_time_password]
 * @property {string} [last_login]
 * @property {string} status
 * @property {number} [created_by]
 * @property {string} [created_at]
 * @property {string} [updated_at]
 * @property {number} [updated_by]
 * @property {number} [otp_status]
 * @property {string} [nfc_card]
 * @property {string} [card_reference]
 * @property {string} [status_text]
 */

/**
 * @typedef {Object} CreateAccountRequest
 * @property {string} [nida]
 * @property {string} first_name
 * @property {string} [middle_name]
 * @property {string} surname
 * @property {string} email
 * @property {string} phone
 * @property {number} created_by
 */

/**
 * @typedef {Object} UpdateAccountRequest
 * @property {string} [nida]
 * @property {string} first_name
 * @property {string} [middle_name]
 * @property {string} surname
 * @property {string} email
 * @property {string} phone
 * @property {number} updated_by
 */

/**
 * @typedef {Object} AccountFilters
 * @property {string} [search]
 * @property {number} [status]
 * @property {string} [sort_by]
 * @property {'asc'|'desc'} [sort_order]
 * @property {number} [per_page]
 * @property {number} [page]
 */

/**
 * @typedef {Object} AccountsPagination
 * @property {number} current_page
 * @property {number} last_page
 * @property {number} per_page
 * @property {number} total
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {Object} AccountsResponse
 * @property {Account[]} accounts
 * @property {AccountsPagination} pagination
 */

export {};


