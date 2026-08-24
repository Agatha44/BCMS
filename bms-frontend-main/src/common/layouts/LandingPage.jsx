import MainHeader from '../components/MainHeader.jsx';
import PropTypes from 'prop-types';
import { useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { setSelectedModule } from '../../store/reducers/app.js';
import { apiService } from '../../services/api.jsx';
import { useState, useEffect, useCallback, useMemo } from 'react';
import LogoLoader from '../components/LogoLoader.jsx';
import { Col, Row, Tooltip } from 'antd';
import { LockOutlined, UnlockOutlined } from '@ant-design/icons';
import {
  UsersIcon,
  BuildingOfficeIcon,
  CalendarIcon,
  CurrencyDollarIcon,
  ReceiptPercentIcon,
  ExclamationTriangleIcon,
  KeyIcon,
} from '@heroicons/react/24/outline';
import '../../styles/home-dashboard.css';

const iconMap = {
  UsersIcon,
  BuildingOfficeIcon,
  CalendarIcon,
  CurrencyDollarIcon,
  ReceiptPercentIcon,
  ExclamationTriangleIcon,
  KeyIcon,
};

const ModuleCard = ({ icon, iconUrl, title, description, onCardClick, hasAccess }) => {
  const [imageError, setImageError] = useState(false);

  const renderIcon = () => {
    if (iconUrl && !imageError) {
      const isUrl =
        iconUrl.startsWith('http://') || iconUrl.startsWith('https://') || iconUrl.startsWith('/');
      if (isUrl) {
        return (
          <img src={iconUrl} alt="" onError={() => setImageError(true)} />
        );
      }
    }

    if (typeof icon === 'function') {
      const IconComponent = icon;
      return <IconComponent />;
    }

    if (typeof icon === 'string') {
      const IconComponent = iconMap[icon] || UsersIcon;
      return <IconComponent />;
    }

    return <UsersIcon />;
  };

  const cardContent = (
    <div
      className={`bms-module-card ${!hasAccess ? 'bms-module-card--locked' : ''}`}
      onClick={hasAccess ? onCardClick : undefined}
      onKeyDown={
        hasAccess
          ? (event) => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                onCardClick();
              }
            }
          : undefined
      }
      role={hasAccess ? 'button' : undefined}
      tabIndex={hasAccess ? 0 : -1}
    >
      <div
        className={`bms-module-card__lock-badge ${
          hasAccess
            ? 'bms-module-card__lock-badge--unlocked'
            : 'bms-module-card__lock-badge--locked'
        }`}
      >
        {hasAccess ? (
          <UnlockOutlined className="bms-module-card__lock-icon--unlocked" />
        ) : (
          <LockOutlined className="bms-module-card__lock-icon--locked" />
        )}
      </div>

      <div className="bms-module-card__heading">
        <div className="bms-module-card__icon-wrap">{renderIcon()}</div>
        <span className="bms-module-card__title">{title}</span>
      </div>

      <p className="bms-module-card__description">
        {description || 'No description available'}
      </p>
    </div>
  );

  if (!hasAccess) {
    return (
      <Tooltip
        title="You don't have access to this module"
        placement="top"
        color="#dc3545"
      >
        {cardContent}
      </Tooltip>
    );
  }

  return cardContent;
};

ModuleCard.propTypes = {
  icon: PropTypes.oneOfType([PropTypes.elementType, PropTypes.string]),
  iconUrl: PropTypes.string,
  title: PropTypes.string.isRequired,
  description: PropTypes.string,
  onCardClick: PropTypes.func.isRequired,
  hasAccess: PropTypes.bool.isRequired,
};

const LandingPage = () => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const currentUser = useSelector((state) => state.auth.user);
  const isAuthenticated = useSelector((state) => state.auth.authenticated);
  const [modules, setModules] = useState([]);
  const [modulesLoading, setModulesLoading] = useState(false);
  const [userModules, setUserModules] = useState([]);
  const [userModulesLoading, setUserModulesLoading] = useState(false);

  useEffect(() => {
    dispatch(setSelectedModule(null));
  }, [dispatch]);

  const fetchActiveModules = useCallback(async () => {
    setModulesLoading(true);
    try {
      const response = await apiService.getActiveBmsModules();
      setModules(response.success && response.data ? response.data : []);
    } catch (error) {
      setModules([]);
    } finally {
      setModulesLoading(false);
    }
  }, []);

  const fetchUserModules = useCallback(async () => {
    if (!isAuthenticated || !currentUser) {
      setUserModules([]);
      return;
    }

    setUserModulesLoading(true);
    try {
      const response = await apiService.getUserModules();

      if (response.success && response.data?.modules) {
        const accessibleModuleIds = response.data.modules
          .map((module) => {
            const moduleIdentifier = module.module_identifier || module.module_id || module.moduleId;
            const moduleId = module.id;
            const ids = [];

            if (moduleIdentifier) {
              ids.push(String(moduleIdentifier).toLowerCase().trim().replace(/[_\s]+/g, '-'));
            }
            if (moduleId) {
              ids.push(String(moduleId).toLowerCase().trim());
            }
            return ids;
          })
          .flat()
          .filter(Boolean);

        setUserModules(accessibleModuleIds);
      } else {
        setUserModules([]);
      }
    } catch (error) {
      setUserModules([]);
    } finally {
      setUserModulesLoading(false);
    }
  }, [isAuthenticated, currentUser]);

  useEffect(() => {
    fetchActiveModules();
    fetchUserModules();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (isAuthenticated && currentUser) {
      fetchUserModules();
    }
  }, [isAuthenticated, currentUser, fetchUserModules]);

  const normalizeModuleId = useCallback((moduleId) => {
    if (!moduleId) return '';
    return String(moduleId).toLowerCase().trim().replace(/[_\s]+/g, '-');
  }, []);

  const hasModuleAccess = useCallback(
    (moduleId) => {
      if (!isAuthenticated || !currentUser || userModulesLoading) {
        return false;
      }

      const normalizedModuleId = normalizeModuleId(moduleId);
      return userModules.some(
        (accessibleModuleId) => normalizeModuleId(accessibleModuleId) === normalizedModuleId,
      );
    },
    [isAuthenticated, currentUser, userModulesLoading, userModules, normalizeModuleId],
  );

  const managementCards = useMemo(() => {
    const cards = modules.map((module) => {
      const moduleIdentifier = module.module_identifier || module.module_id;
      const moduleId = module.id;
      const normalizedModuleId = normalizeModuleId(moduleIdentifier || moduleId?.toString() || '');

      const apiModuleIds = [
        ...(moduleIdentifier ? [normalizeModuleId(moduleIdentifier)] : []),
        ...(moduleId ? [normalizeModuleId(moduleId)] : []),
      ];

      const iconStr = module.icon ? String(module.icon) : '';
      const iconUrl =
        iconStr.startsWith('http://') || iconStr.startsWith('https://') || iconStr.startsWith('/')
          ? iconStr
          : null;

      const hasAccess =
        apiModuleIds.some((apiId) => hasModuleAccess(apiId)) || hasModuleAccess(normalizedModuleId);

      return {
        id: module.id || module.module_id,
        icon: iconUrl ? null : module.icon,
        iconUrl: iconUrl || module.icon_url,
        title: module.title || module.module_name || 'Untitled Module',
        description: module.description || module.module_description || 'No description available',
        moduleId: normalizedModuleId,
        apiModuleIds,
        hasAccess,
      };
    });

    return cards.sort((a, b) => {
      if (a.hasAccess !== b.hasAccess) {
        return a.hasAccess ? -1 : 1;
      }
      return a.title.localeCompare(b.title);
    });
  }, [modules, normalizeModuleId, hasModuleAccess]);

  const accessibleCount = useMemo(
    () => managementCards.filter((card) => card.hasAccess).length,
    [managementCards],
  );

  const getUserGreetingName = () => {
    if (!currentUser) return 'User';
    const firstName = currentUser.first_name || currentUser.firstName || currentUser.firstname || '';
    const lastName = currentUser.surname || currentUser.last_name || currentUser.lastName || currentUser.lastname || '';
    const fullName = [firstName, lastName].filter(Boolean).join(' ');
    return fullName || currentUser.username || 'User';
  };

  const handleCardClick = useCallback(
    (moduleId) => {
      if (!hasModuleAccess(moduleId)) return;

      dispatch(setSelectedModule(moduleId));
      const routeModuleId = moduleId === 'account-management' ? 'admin-management' : moduleId;
      navigate(`/${routeModuleId}/dashboard`);
    },
    [hasModuleAccess, dispatch, navigate],
  );

  if (modulesLoading || userModulesLoading) {
    return (
      <div className="flex min-h-screen flex-col">
        <MainHeader />
        <div className="bms-home-dashboard__loader">
          <LogoLoader size={80} showText={false} fullScreen={false} vibrationIntensity="normal" />
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen flex-col">
      <MainHeader />

      <div className="bms-home-dashboard">
        <div className="bms-home-dashboard__header">
          <div>
            <div className="bms-home-dashboard__title">
              Welcome back, {getUserGreetingName()}
            </div>
            <div className="bms-home-dashboard__subtitle">
              Select a module to get started
            </div>
          </div>
          <div className="bms-home-dashboard__badge">
            {accessibleCount} of {managementCards.length} modules accessible
          </div>
        </div>

        {managementCards.length === 0 ? (
          <div className="bms-home-dashboard__empty">
            <p className="text-base font-semibold" style={{ color: 'var(--bms-text)' }}>
              No modules available
            </p>
            <p className="mt-1 text-sm">Contact your administrator if you believe this is an error.</p>
          </div>
        ) : (
          <Row gutter={[24, 24]} className="bms-home-dashboard__grid">
            {managementCards.map((card) => (
              <Col xs={24} sm={12} md={8} lg={8} key={card.id} className="bms-home-dashboard__grid-col">
                <ModuleCard
                  icon={card.icon}
                  iconUrl={card.iconUrl}
                  title={card.title}
                  description={card.description}
                  onCardClick={() => handleCardClick(card.moduleId)}
                  hasAccess={card.hasAccess}
                />
              </Col>
            ))}
          </Row>
        )}
      </div>
    </div>
  );
};

export default LandingPage;
