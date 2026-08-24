/**
 * @typedef {Object} OverloadFine
 * @property {number} id
 * @property {string} vehicle_num
 * @property {string} first_name
 * @property {string} [middle_name]
 * @property {string} surname
 * @property {string} pyr_cell_num
 * @property {string} [pyr_email]
 * @property {string} [tin_number]
 * @property {string} [bill_desc]
 * @property {number} bill_amount
 * @property {string} [bill_exp_dt]
 * @property {string|null} contr_num
 * @property {number} [bill_gen_by]
 * @property {string} [bill_gen_at]
 * @property {number} [bill_cancel_by]
 * @property {string} [bill_cancel_date]
 * @property {number} is_cancelled
 * @property {string|null} [trx_id]
 * @property {string|null} [trx_dt_tm]
 * @property {string} usd_pay_chn
 * @property {string|null} psp_receipt_num
 * @property {string|null} [psp_name]
 * @property {string|null} [ctr_acc_num]
 * @property {string} [bill_status]
 * @property {string} [cancel_reason]
 * @property {string} [ticket_num]
 * @property {string} [t_status]
 * @property {string} [error_code]
 * @property {string|null} [pay_ref_id]
 * @property {string|null} [receipt_number]
 * @property {string} [created_at]
 * @property {string} [updated_at]
 */

/**
 * @typedef {Object} CreateOverloadFineRequest
 * @property {string} first_name
 * @property {string} [middle_name]
 * @property {string} surname
 * @property {string} pyr_cell_num
 * @property {string} [pyr_email]
 * @property {string} [tin_number]
 * @property {number} bill_amount
 * @property {string} [ticket_num]
 * @property {string} vehicle_num
 * @property {string} [bill_desc]
 */

/**
 * @typedef {Object} UpdateOverloadFineRequest
 * @property {number} id
 * @property {string} first_name
 * @property {string} [middle_name]
 * @property {string} surname
 * @property {string} pyr_cell_num
 * @property {string} [pyr_email]
 * @property {string} [tin_number]
 * @property {number} bill_amount
 * @property {string} [ticket_num]
 * @property {string} vehicle_num
 * @property {string} [bill_desc]
 */

/**
 * @typedef {Object} OverloadCancelBillRequest
 * @property {number} id
 * @property {string} cancel_reason
 */

/**
 * @typedef {Object} OverloadGepgError
 * @property {string} error_code
 * @property {string} description
 */

export {};


