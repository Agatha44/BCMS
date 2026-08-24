import {useMemo, useState} from 'react';
import {Tabs} from 'antd';

/**
 * Nested tabs inside a SoD parent tab (e.g. bundle bills / subscriptions).
 * Does not add the outer page padding/card — parent SoDTabbedPage provides that.
 */
const InlineSoDTabs = ({tabs, defaultActiveKey}) => {
    const validKeys = useMemo(() => new Set(tabs.map((t) => t.key)), [tabs]);
    const initialKey =
        defaultActiveKey && validKeys.has(defaultActiveKey) ? defaultActiveKey : tabs[0]?.key;

    const [activeKey, setActiveKey] = useState(initialKey);

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
        children: tab.content,
    }));

    return (
        <Tabs
            activeKey={activeKey}
            onChange={setActiveKey}
            items={items}
            tabBarStyle={{marginBottom: 12}}
        />
    );
};

export default InlineSoDTabs;
