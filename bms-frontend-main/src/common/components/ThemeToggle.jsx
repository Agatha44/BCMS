import { Moon, Sun } from 'lucide-react';
import { useTheme } from '../context/ThemeContext.jsx';

const ThemeToggle = ({ variant = 'burgundy' }) => {
  const { theme, toggleTheme } = useTheme();
  const isDark = theme === 'dark';

  const variantClasses = {
    burgundy:
      'text-[var(--bms-on-brand-muted)] hover:text-[var(--bms-on-brand)] hover:bg-black/20 focus-visible:ring-[var(--bms-on-brand)]/40',
    yellow:
      'text-gray-800 hover:text-gray-900 hover:bg-black/10 focus-visible:ring-gray-700/30',
  };

  return (
    <button
      type="button"
      onClick={toggleTheme}
      aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
      title={isDark ? 'Light mode' : 'Dark mode'}
      className={`flex h-9 w-9 items-center justify-center rounded-full transition focus:outline-none focus-visible:ring-2 ${variantClasses[variant] ?? variantClasses.burgundy}`}
    >
      {isDark ? (
        <Sun className="h-[18px] w-[18px]" strokeWidth={2} />
      ) : (
        <Moon className="h-[18px] w-[18px]" strokeWidth={2} />
      )}
    </button>
  );
};

export default ThemeToggle;
