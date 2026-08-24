import {useEffect, useMemo, useState} from 'react';
import {useLocation, useNavigate} from 'react-router-dom';
import {Tabs} from 'antd';
import {Wrench} from 'lucide-react';

const BRAND = '#962E32';
const BRAND_SOFT = '#fff5f5';

/**
 * Shared placeholder shown when a tab has no content yet. Tabs that need real
 * implementation can pass `content` instead.
 */
const TabPlaceholder = ({tab}) => (
    <div className="rounded-3xl border border-dashed border-[#ead6d7] bg-[#fffafa] px-5 py-4">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-3">
                <div
                    className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
                    style={{backgroundColor: BRAND_SOFT, color: BRAND}}
                >
                    {tab.icon || <Wrench size={22} />}
                </div>
                <div>
                    <h3 className="text-base font-semibold text-slate-800">{tab.label}</h3>
                    <p className="mt-1 text-sm text-slate-500">
                        {tab.description ||
                            'This section is ready for business content and API integration.'}
                    </p>
                </div>
            </div>
            <span
                className="inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-wide"
                style={{backgroundColor: BRAND_SOFT, color: BRAND}}
            >
                <span className="h-1.5 w-1.5 rounded-full" style={{backgroundColor: BRAND}} />
                Awaiting content
            </span>
        </div>
    </div>
);

/**
 * Reusable shell for SoD parent pages. Renders tabs wired to `?tab=` for
 * deep-linking. Page title comes from breadcrumb navigation only.
 *
 * @param {Object} props
 * @param {Array<{key: string, label: string, icon?: React.ReactNode, description?: string, content?: React.ReactNode}>} props.tabs
 *        Tab definitions. Provide `content` to render real UI; otherwise a
 *        placeholder is shown.
 */
const SoDTabbedPage = ({tabs}) => {
    const location = useLocation();
    const navigate = useNavigate();

    const validKeys = useMemo(() => new Set(tabs.map((t) => t.key)), [tabs]);

    const getTabFromSearch = () => {
        const params = new URLSearchParams(location.search);
        const t = params.get('tab');
        return t && validKeys.has(t) ? t : tabs[0]?.key;
    };

    const [activeKey, setActiveKey] = useState(getTabFromSearch);

    useEffect(() => {
        const next = getTabFromSearch();
        if (next && next !== activeKey) {
            setActiveKey(next);
        }
    }, [location.search]);

    const handleChange = (key) => {
        setActiveKey(key);
        const params = new URLSearchParams(location.search);
        params.set('tab', key);
        navigate(
            {pathname: location.pathname, search: `?${params.toString()}`},
            {replace: true}
        );
    };

    const items = tabs.map((tab) => ({
        key: tab.key,
        label: (
            <span className="inline-flex items-center gap-2">
                {tab.icon ? (
                    <span className="flex items-center text-current">{tab.icon}</span>
                ) : null}
                <span>{tab.label}</span>
            </span>
        ),
        children: tab.content ?? <TabPlaceholder tab={tab} />,
    }));

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <Tabs
                activeKey={activeKey}
                onChange={handleChange}
                items={items}
                tabBarStyle={{marginBottom: 12}}
            />
        </div>
    );
};

export default SoDTabbedPage;
