import './index.css';
import AppRoutes from './routes/index.jsx';
import { ConfigProvider, App as AntdApp, theme as antdTheme } from 'antd';
import { BrowserRouter } from 'react-router-dom';
import AuthInitializer from './common/components/AuthInitializer.jsx';
import SessionTimeoutProvider from './common/components/SessionTimeoutProvider.jsx';
import { ThemeProvider, useTheme } from './common/context/ThemeContext.jsx';
import './App.css';

const lightTokens = {
  colorPrimaryHover: '#962E32',
  colorPrimaryActive: '#962E32',
  colorPrimary: '#962E32',
  colorInfo: '#962E32',
  colorInfoHover: '#7A2326',
  colorInfoActive: '#7A2326',
  colorLink: '#962E32',
  colorLinkHover: '#7A2326',
  colorLinkActive: '#7A2326',
};

const lightComponents = {
  Spin: {
    dotSizeLG: 30,
  },
  Button: {
    contentFontSizeLG: 15,
    defaultHoverBorderColor: '#962E32',
    defaultHoverColor: '#962E32',
    colorPrimaryHover: '#962E31',
    textHoverBg: '#962E32',
    defaultActiveBg: 'rgba(150,46,50,0.2)',
  },
  Input: {
    activeBorderColor: '#962E32',
    controlOutline: 'none',
    hoverBorderColor: '#962E32',
  },
  DatePicker: {
    activeBorderColor: '#962E32',
    hoverBorderColor: '#962E32',
    activeShadow: '0 0 0 3px rgba(150, 46, 50, 0.12)',
    cellActiveWithRangeBg: 'rgba(150, 46, 50, 0.12)',
    cellHoverWithRangeBg: 'rgba(150, 46, 50, 0.08)',
    cellHoverBg: 'rgba(150, 46, 50, 0.08)',
  },
  Divider: {
    colorSplit: '#dad1d1',
    lineWidth: 2,
  },
};

const darkTokens = {
  ...lightTokens,
  colorBgContainer: '#111827',
  colorBgElevated: '#1f2937',
  colorBgLayout: '#0b1120',
  colorBgSpotlight: '#1f2937',
  colorBorder: '#374151',
  colorBorderSecondary: '#1f2937',
  colorText: '#e5e7eb',
  colorTextSecondary: '#9ca3af',
  colorTextTertiary: '#6b7280',
  colorFillAlter: '#1a2332',
  colorFillSecondary: '#374151',
  colorFillContent: '#1f2937',
  colorBgMask: 'rgba(0, 0, 0, 0.65)',
};

const darkComponents = {
  ...lightComponents,
  Divider: { colorSplit: '#374151', lineWidth: 2 },
  Table: {
    headerBg: '#962E32',
    headerColor: '#f3f4f6',
    rowHoverBg: 'rgba(150, 46, 50, 0.15)',
    borderColor: '#374151',
  },
  Modal: {
    contentBg: '#1f2937',
    headerBg: '#1f2937',
    footerBg: '#1f2937',
    titleColor: '#e5e7eb',
    colorIcon: '#9ca3af',
    colorIconHover: '#e5e7eb',
  },
  Drawer: {
    colorBgElevated: '#1f2937',
    colorBgMask: 'rgba(0, 0, 0, 0.65)',
    colorIcon: '#9ca3af',
    colorIconHover: '#e5e7eb',
  },
};

function ThemedApp() {
  const { theme } = useTheme();
  const isDark = theme === 'dark';

  return (
    <ConfigProvider
      theme={{
        algorithm: isDark ? antdTheme.darkAlgorithm : antdTheme.defaultAlgorithm,
        token: isDark ? darkTokens : lightTokens,
        components: isDark ? darkComponents : lightComponents,
      }}
    >
      <AntdApp>
        <AuthInitializer>
          <BrowserRouter>
            <SessionTimeoutProvider>
              <AppRoutes />
            </SessionTimeoutProvider>
          </BrowserRouter>
        </AuthInitializer>
      </AntdApp>
    </ConfigProvider>
  );
}

function App() {
  return (
    <ThemeProvider>
      <ThemedApp />
    </ThemeProvider>
  );
}

export default App;
