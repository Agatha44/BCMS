/**
 * @typedef {Object} PriceBodyType
 * @property {number} id
 * @property {string} name
 * @property {string} [description]
 * @property {number} is_active
 * @property {number} [created_by]
 * @property {number} [updated_by]
 * @property {string} [created_at]
 * @property {string} [updated_at]
 * @property {string} [status_text]
 */

/**
 * @typedef {Object} PriceList
 * @property {number} id
 * @property {number} body_type_id
 * @property {number} amount
 * @property {number} daily_bundle_amount
 * @property {number} weekly_bundle_amount
 * @property {number} monthly_bundle_amount
 * @property {boolean} status
 * @property {number} [created_by]
 * @property {string} [created_at]
 * @property {number} [updated_by]
 * @property {string} [updated_at]
 * @property {string} [status_text]
 * @property {PriceBodyType} [body_type]
 */

/**
 * @typedef {Object} CreatePriceRequest
 * @property {number} body_type_id
 * @property {number} amount
 * @property {number} daily_bundle_amount
 * @property {number} weekly_bundle_amount
 * @property {number} monthly_bundle_amount
 * @property {boolean} [status]
 */

/**
 * @typedef {Object} UpdatePriceRequest
 * @property {number} id
 * @property {number} body_type_id
 * @property {number} amount
 * @property {number} daily_bundle_amount
 * @property {number} weekly_bundle_amount
 * @property {number} monthly_bundle_amount
 * @property {boolean} [status]
 */

/**
 * @typedef {Object} PriceFilters
 * @property {string} [search]
 * @property {boolean} [status]
 * @property {number} [body_type_id]
 * @property {string} [sort_by]
 * @property {'asc'|'desc'} [sort_order]
 * @property {number} [per_page]
 * @property {number} [page]
 */

/**
 * @typedef {Object} PricePagination
 * @property {number} current_page
 * @property {number} last_page
 * @property {number} per_page
 * @property {number} total
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {Object} PricesResponse
 * @property {PriceList[]} prices
 * @property {PricePagination} pagination
 */

/**
 * @typedef {Object} BodyTypesResponse
 * @property {PriceBodyType[]} body_types
 * @property {PricePagination} pagination
 */

export {};


