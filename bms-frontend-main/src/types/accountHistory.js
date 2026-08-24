/**
 * @typedef {Object} BalanceHistoryPosTerminal
 * @property {number} id
 * @property {string} name
 * @property {string} mac_address
 * @property {string} ip_address
 */

/**
 * @typedef {Object} BalanceHistoryMetadata
 * @property {BalanceHistoryPosTerminal} [pos_terminal]
 * @property {any} [pos_request]
 * @property {string} [timestamp]
 * @property {string} [ip_address]
 */

/**
 * @typedef {Object} BalanceHistoryTerminal
 * @property {number} id
 * @property {string} name
 * @property {string} lane_number
 * @property {string} mac_address
 * @property {string} ip_address
 */

/**
 * @typedef {Object} BalanceHistoryProcessedBy
 * @property {number} id
 * @property {string} username
 */

/**
 * @typedef {Object} BalanceHistory
 * @property {number} id
 * @property {number} account_id
 * @property {string} card_reference
 * @property {'deduction'|'top_up'|'adjustment'|'refund'} transaction_type
 * @property {number} previous_balance
 * @property {number} transaction_amount
 * @property {number} new_balance
 * @property {string} [lane_number]
 * @property {number} [terminal_id]
 * @property {string} reference_number
 * @property {string} description
 * @property {BalanceHistoryMetadata} [metadata]
 * @property {number} [processed_by]
 * @property {string} created_at
 * @property {string} updated_at
 * @property {BalanceHistoryTerminal} [terminal]
 * @property {BalanceHistoryProcessedBy} [processedBy]
 */

/**
 * @typedef {Object} CardHistoryMetadata
 * @property {string} [ip_address]
 * @property {string} [user_agent]
 * @property {string} [timestamp]
 */

/**
 * @typedef {Object} CardHistoryPerformedBy
 * @property {number} id
 * @property {string} username
 */

/**
 * @typedef {Object} CardHistoryAccount
 * @property {number} id
 * @property {string} account_no
 * @property {string} first_name
 * @property {string} surname
 * @property {string} full_name
 */

/**
 * @typedef {Object} CardHistory
 * @property {number} id
 * @property {number} account_id
 * @property {'registered'|'updated'|'linked'|'unlinked'} action
 * @property {string} [card_reference]
 * @property {string} [old_card_reference]
 * @property {string} [new_card_reference]
 * @property {string} [reason]
 * @property {CardHistoryMetadata} [metadata]
 * @property {CardHistoryPerformedBy} [performed_by]
 * @property {string} created_at
 * @property {string} updated_at
 * @property {CardHistoryAccount} [account]
 */

/**
 * @typedef {Object} AccountHistoryFilters
 * @property {string} [account_no]
 * @property {string} [transaction_type]
 * @property {string} [action_type]
 * @property {string} [start_date]
 * @property {string} [end_date]
 * @property {string} [lane_number]
 * @property {number} [terminal_id]
 * @property {number} [per_page]
 * @property {number} [page]
 */

/**
 * @typedef {Object} AccountHistoryPagination
 * @property {number} current_page
 * @property {number} last_page
 * @property {number} per_page
 * @property {number} total
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {Object} BalanceHistoryResponse
 * @property {BalanceHistory[]} balance_history
 * @property {AccountHistoryPagination} pagination
 */

/**
 * @typedef {Object} CardHistoryResponse
 * @property {CardHistory[]} card_history
 * @property {AccountHistoryPagination} pagination
 */

/**
 * @typedef {Object} AccountHistoryStats
 * @property {number} total_transactions
 * @property {number} total_deductions
 * @property {number} total_topups
 * @property {number} total_amount_deducted
 * @property {number} total_amount_topped_up
 * @property {BalanceHistory} [last_transaction]
 * @property {number} card_changes
 */

export {};


