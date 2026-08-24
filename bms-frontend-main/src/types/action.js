/**
 * @typedef {Object} Action
 * @property {number} id
 * @property {number} [parent_id]
 * @property {string} title
 * @property {string} controller_id
 * @property {string} [action_id]
 * @property {string} [route]
 * @property {string} menu_icon
 * @property {number} on_menu
 * @property {number} order_no
 * @property {number} is_active
 * @property {string} [status_text]
 * @property {string} [full_route]
 * @property {string} [created_at]
 * @property {string} [updated_at]
 */

/**
 * @typedef {Object} CreateActionRequest
 * @property {number} [parent_id]
 * @property {string} title
 * @property {string} controller_id
 * @property {string} [action_id]
 * @property {string} [route]
 * @property {string} [menu_icon]
 * @property {number} [on_menu]
 * @property {number} [order_no]
 * @property {number} [is_active]
 */

/**
 * @typedef {Object} UpdateActionRequest
 * @property {number} id
 * @property {number} [parent_id]
 * @property {string} title
 * @property {string} controller_id
 * @property {string} [action_id]
 * @property {string} [route]
 * @property {string} [menu_icon]
 * @property {number} [on_menu]
 * @property {number} [order_no]
 * @property {number} [is_active]
 */

/**
 * @typedef {Object} ActionFilters
 * @property {string} [search]
 * @property {number} [status]
 * @property {number} [on_menu]
 * @property {string} [sort_by]
 * @property {'asc'|'desc'} [sort_order]
 * @property {number} [per_page]
 * @property {number} [page]
 */

/**
 * @typedef {Object} ActionPagination
 * @property {number} current_page
 * @property {number} last_page
 * @property {number} per_page
 * @property {number} total
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {Object} ActionsResponse
 * @property {Action[]} actions
 * @property {ActionPagination} pagination
 */

/**
 * @typedef {Object} AssignActionsRequest
 * @property {number[]} action_ids
 */

export {};


