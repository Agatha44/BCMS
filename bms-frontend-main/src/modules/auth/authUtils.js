/** True when login/user payload requires a mandatory password change. */
export const userMustChangePassword = (user) => Boolean(user?.must_change_password);
