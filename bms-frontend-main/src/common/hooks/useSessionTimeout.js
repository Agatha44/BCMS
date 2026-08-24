import { useCallback, useEffect, useRef, useState } from 'react';

/** Total idle time before forced logout (10 minutes). */
export const SESSION_IDLE_MS = 15 * 60 * 1000;

/** Show "still there?" prompt this long before logout (2 minutes). */
export const SESSION_WARNING_MS = 2 * 60 * 1000;

const ACTIVITY_EVENTS = ['mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
const THROTTLE_MS = 1000;

/**
 * Tracks user inactivity and drives warning / logout timers.
 * @param {boolean} enabled
 * @param {() => void} onIdleLogout - Called when idle limit is reached
 */
export function useSessionTimeout(enabled, onIdleLogout) {
  const [showWarning, setShowWarning] = useState(false);
  const [secondsLeft, setSecondsLeft] = useState(Math.ceil(SESSION_WARNING_MS / 1000));

  const lastActivityRef = useRef(Date.now());
  const throttleRef = useRef(0);
  const warningTimeoutRef = useRef(null);
  const logoutTimeoutRef = useRef(null);
  const countdownIntervalRef = useRef(null);
  const onIdleLogoutRef = useRef(onIdleLogout);
  const showWarningRef = useRef(false);

  onIdleLogoutRef.current = onIdleLogout;
  showWarningRef.current = showWarning;

  const clearTimers = useCallback(() => {
    if (warningTimeoutRef.current) {
      clearTimeout(warningTimeoutRef.current);
      warningTimeoutRef.current = null;
    }
    if (logoutTimeoutRef.current) {
      clearTimeout(logoutTimeoutRef.current);
      logoutTimeoutRef.current = null;
    }
    if (countdownIntervalRef.current) {
      clearInterval(countdownIntervalRef.current);
      countdownIntervalRef.current = null;
    }
  }, []);

  const triggerLogout = useCallback(() => {
    clearTimers();
    setShowWarning(false);
    onIdleLogoutRef.current?.();
  }, [clearTimers]);

  const startCountdown = useCallback((remainingMs) => {
    if (countdownIntervalRef.current) {
      clearInterval(countdownIntervalRef.current);
    }

    const endAt = Date.now() + remainingMs;
    setSecondsLeft(Math.max(1, Math.ceil(remainingMs / 1000)));

    countdownIntervalRef.current = setInterval(() => {
      const left = Math.max(0, endAt - Date.now());
      setSecondsLeft(Math.ceil(left / 1000));
    }, 1000);
  }, []);

  const openWarning = useCallback(
    (remainingMs) => {
      setShowWarning(true);
      if (logoutTimeoutRef.current) {
        clearTimeout(logoutTimeoutRef.current);
      }
      logoutTimeoutRef.current = setTimeout(triggerLogout, remainingMs);
      startCountdown(remainingMs);
    },
    [startCountdown, triggerLogout]
  );

  const scheduleTimers = useCallback(() => {
    clearTimers();
    setShowWarning(false);

    const idleFor = Date.now() - lastActivityRef.current;
    const msUntilWarning = SESSION_IDLE_MS - SESSION_WARNING_MS - idleFor;
    const msUntilLogout = SESSION_IDLE_MS - idleFor;

    if (msUntilLogout <= 0) {
      triggerLogout();
      return;
    }

    if (msUntilWarning <= 0) {
      openWarning(msUntilLogout);
      return;
    }

    warningTimeoutRef.current = setTimeout(() => {
      const remaining = SESSION_IDLE_MS - (Date.now() - lastActivityRef.current);
      if (remaining <= 0) {
        triggerLogout();
      } else {
        openWarning(remaining);
      }
    }, msUntilWarning);
  }, [clearTimers, openWarning, triggerLogout]);

  const stayLoggedIn = useCallback(() => {
    lastActivityRef.current = Date.now();
    throttleRef.current = Date.now();
    scheduleTimers();
  }, [scheduleTimers]);

  const registerActivity = useCallback(() => {
    if (!enabled || showWarningRef.current) return;

    const now = Date.now();
    if (now - throttleRef.current < THROTTLE_MS) return;
    throttleRef.current = now;
    lastActivityRef.current = now;
    scheduleTimers();
  }, [enabled, scheduleTimers]);

  useEffect(() => {
    if (!enabled) {
      clearTimers();
      setShowWarning(false);
      return undefined;
    }

    lastActivityRef.current = Date.now();
    scheduleTimers();

    const onVisibility = () => {
      if (document.visibilityState !== 'visible') return;

      const idleFor = Date.now() - lastActivityRef.current;
      if (idleFor >= SESSION_IDLE_MS) {
        triggerLogout();
        return;
      }
      if (idleFor >= SESSION_IDLE_MS - SESSION_WARNING_MS) {
        clearTimers();
        openWarning(SESSION_IDLE_MS - idleFor);
      } else if (!showWarningRef.current) {
        scheduleTimers();
      }
    };

    ACTIVITY_EVENTS.forEach((event) => {
      window.addEventListener(event, registerActivity, { passive: true });
    });
    document.addEventListener('visibilitychange', onVisibility);

    return () => {
      clearTimers();
      ACTIVITY_EVENTS.forEach((event) => {
        window.removeEventListener(event, registerActivity);
      });
      document.removeEventListener('visibilitychange', onVisibility);
    };
  }, [enabled, clearTimers, registerActivity, scheduleTimers, openWarning, triggerLogout]);

  return {
    showWarning,
    secondsLeft,
    stayLoggedIn,
  };
}
