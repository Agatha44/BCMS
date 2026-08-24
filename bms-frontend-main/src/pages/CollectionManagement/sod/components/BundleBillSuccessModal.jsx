import {App, Button, Modal} from 'antd';
import {CheckCircle2, Copy} from 'lucide-react';

import BrandModalHeader from './BrandModalHeader.jsx';

const BRAND = '#962E32';
const EMPTY_VALUE = 'N/A';

const ReadOnlyField = ({label, value, mono = false}) => (
    <div className="min-w-0">
        <span
            className="block text-xs font-semibold tracking-[0.01em]"
            style={{color: BRAND}}
        >
            {label}
        </span>
        <div className={`mt-0.5 text-sm font-medium text-black ${mono ? 'font-mono' : ''}`}>
            {value != null && value !== '' ? (
                value
            ) : (
                <span className="font-normal text-slate-400">{EMPTY_VALUE}</span>
            )}
        </div>
    </div>
);

const BundleBillSuccessModal = ({open, billSuccess, onClose}) => {
    const {message} = App.useApp();

    const copyToClipboard = async (text) => {
        if (!text) return;
        try {
            await navigator.clipboard.writeText(String(text));
            message.success('Copied');
        } catch {
            message.error('Could not copy');
        }
    };

    const amountDisplay =
        billSuccess?.bill_amount != null
            ? `TZS ${Number(billSuccess.bill_amount).toLocaleString()}`
            : null;

    return (
        <Modal
            open={open}
            onCancel={onClose}
            footer={null}
            width={520}
            centered
            destroyOnHidden
            title={null}
            closable={false}
            className="brand-modal"
            styles={{body: {padding: 0}, content: {padding: 0, overflow: 'hidden'}}}
        >
            <BrandModalHeader title={billSuccess?.title || 'Bundle Bill Created'} onClose={onClose} />

            <div className="px-6 py-5">
                <div className="mb-4 flex items-center gap-2 text-sm text-slate-700">
                    <CheckCircle2 size={18} className="shrink-0 text-green-600" />
                    <span>Bundle bill created successfully.</span>
                    {billSuccess?.message && (
                        <span className="text-slate-500">— {billSuccess.message}</span>
                    )}
                </div>

                <div className="rounded-md border border-slate-200 bg-slate-50 p-4">
                    <div className="mb-3">
                        <ReadOnlyField
                            label="Control Number"
                            value={billSuccess?.control_number}
                            mono
                        />
                        {billSuccess?.control_number && (
                            <Button
                                size="small"
                                icon={<Copy size={12} />}
                                className="mt-1"
                                onClick={() => copyToClipboard(billSuccess.control_number)}
                            >
                                Copy
                            </Button>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-4 border-t border-slate-200 pt-3">
                        <ReadOnlyField label="Amount (TZS)" value={amountDisplay} mono />
                        <ReadOnlyField label="Bill ID" value={billSuccess?.bill_id} mono />
                    </div>
                </div>

                <p className="mt-3 text-xs text-slate-500">
                    Use the control number to complete payment via bank or mobile money.
                </p>
            </div>

            <div className="flex items-center justify-end border-t border-slate-200 bg-white px-6 py-3">
                <Button onClick={onClose}>Close</Button>
            </div>
        </Modal>
    );
};

export default BundleBillSuccessModal;
