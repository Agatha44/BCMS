import {useCallback, useMemo, useRef, useState} from 'react';
import {App, Button, Modal, Spin} from 'antd';
import {Download, Printer} from 'lucide-react';
import {useSelector} from 'react-redux';

import nssfLogo from '../../../../assets/images/nssf-order-logo.png';
import tanzaniaEmblem from '../../../../assets/images/tanzania-emblem.png';
import BrandModalHeader from '../../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import {buildBundleBillOrderForm} from './bundleBillOrderFormUtils.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const CELL_BORDER = '1px solid #cbd5e1';

const sectionHeaderStyle = {
    background: BRAND,
    color: '#fff',
    fontWeight: 700,
    fontSize: 13,
    textAlign: 'left',
    padding: '8px 12px',
    border: `1px solid ${BRAND}`,
};

const labelCellStyle = {
    width: '42%',
    fontWeight: 700,
    fontSize: 12,
    padding: '8px 12px',
    border: CELL_BORDER,
    verticalAlign: 'top',
    background: '#fff',
};

const valueCellStyle = {
    fontSize: 12,
    padding: '8px 12px',
    border: CELL_BORDER,
    verticalAlign: 'top',
    background: '#fff',
};

const display = (value) => {
    if (value == null || String(value).trim() === '') {
        return <span style={{color: '#94a3b8'}}>—</span>;
    }
    return value;
};

const DetailTable = ({title, rows}) => (
    <table
        className="order-form-detail-table"
        style={{width: '100%', borderCollapse: 'collapse', marginBottom: 16}}
    >
        <thead>
        <tr>
            <th colSpan={2} style={sectionHeaderStyle}>
                {title}
            </th>
        </tr>
        </thead>
        <tbody>
        {rows.map(({label, value, boldValue = false}) => (
            <tr key={label}>
                <td style={labelCellStyle}>{label}</td>
                <td style={{...valueCellStyle, fontWeight: boldValue ? 700 : 400}}>{display(value)}</td>
            </tr>
        ))}
        </tbody>
    </table>
);

const OrderFormDocument = ({form, documentRef}) => (
    <div
        ref={documentRef}
        className="order-form-document"
        style={{
            fontFamily: 'Arial, Helvetica, sans-serif',
            color: '#000',
            background: '#fff',
            padding: '28px 40px 24px',
            maxWidth: 720,
            margin: '0 auto',
            lineHeight: 1.4,
        }}
    >
        <div
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                justifyContent: 'space-between',
                marginBottom: 20,
                background: 'transparent',
            }}
        >
            <img
                src={nssfLogo}
                alt="NSSF"
                className="order-form-logo"
                style={{
                    height: 72,
                    width: 'auto',
                    maxWidth: 120,
                    objectFit: 'contain',
                    background: 'transparent',
                    display: 'block',
                }}
            />
            <img
                src={tanzaniaEmblem}
                alt="Coat of Arms"
                className="order-form-logo"
                style={{
                    height: 72,
                    width: 'auto',
                    maxWidth: 100,
                    objectFit: 'contain',
                    background: 'transparent',
                    display: 'block',
                }}
            />
        </div>

        <DetailTable
            title="1. Remitter Details:"
            rows={[
                {label: 'Payer Name:', value: form.remitter.payerName},
                {label: 'Phone Number:', value: form.remitter.phoneNumber},
            ]}
        />

        <DetailTable
            title="2. Beneficiary Details"
            rows={[
                {label: 'Account Name:', value: form.beneficiary.accountName},
                {label: 'Currency:', value: form.beneficiary.currency},
            ]}
        />

        <DetailTable
            title="3. Bill Details"
            rows={[
                {label: 'CONTROL NUMBER:', value: form.bill.controlNumber, boldValue: true},
                {label: 'Billed To:', value: form.bill.billedTo},
                {label: 'Billed Amount:', value: form.bill.billedAmount},
                {label: 'Being payment for:', value: form.bill.beingPaymentFor},
                {label: 'Bill Due Date:', value: form.bill.billDueDate},
                {label: 'Printed By:', value: form.meta.printedBy},
                {label: 'Printed on:', value: form.meta.printedOn, boldValue: true},
            ]}
        />

        <table
            className="order-form-detail-table"
            style={{width: '100%', borderCollapse: 'collapse', marginBottom: 0}}
        >
            <thead>
            <tr>
                <th colSpan={2} style={sectionHeaderStyle}>
                    Note to Commercial Banks:
                </th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td colSpan={2} style={{...valueCellStyle, fontSize: 12, lineHeight: 1.5}}>
                    1. Field &ldquo;Control Number&rdquo; with value:{' '}
                    <strong>{form.bill.controlNumber || '—'}</strong> Must be captured correctly.
                </td>
            </tr>
            </tbody>
        </table>
    </div>
);

const printStyles = `
  @page { size: A4; margin: 14mm; }
  body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; }
  .order-form-logo {
    max-height: 72px;
    background: transparent !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .order-form-detail-table th {
    background: ${BRAND} !important;
    color: #fff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .order-form-detail-table td,
  .order-form-detail-table th {
    border: 1px solid #cbd5e1 !important;
  }
`;

const buildPdfFilename = (form, bill) => {
    const control = form?.bill?.controlNumber ?? bill?.control_number ?? bill?.id ?? 'order-form';
    const safe = String(control).replace(/[^\w.-]+/g, '_');
    return `payment-order-form-${safe}.pdf`;
};

export default function BundleBillOrderFormModal({
    open,
    bill,
    orderFormPayload,
    loading = false,
    onClose,
}) {
    const {message} = App.useApp();
    const documentRef = useRef(null);
    const [downloadingPdf, setDownloadingPdf] = useState(false);
    const user = useSelector((state) => state.auth?.user);

    const printedBy = useMemo(() => {
        if (!user) return '';
        return (
            user.full_name ||
            user.name ||
            [user.first_name, user.surname].filter(Boolean).join(' ') ||
            user.username ||
            ''
        );
    }, [user]);

    const form = useMemo(
        () => (bill ? buildBundleBillOrderForm(bill, orderFormPayload, printedBy) : null),
        [bill, orderFormPayload, printedBy],
    );

    const handlePrint = useCallback(() => {
        if (!documentRef.current || !form) return;
        const html = documentRef.current.outerHTML;
        const win = window.open('', '_blank', 'noopener,noreferrer');
        if (!win) return;
        win.document.write(
            `<!DOCTYPE html><html><head><title>Payment Order Form</title><style>${printStyles}</style></head><body>${html}</body></html>`,
        );
        win.document.close();
        win.focus();
        win.onload = () => {
            win.print();
        };
    }, [form]);

    const handleDownloadPdf = useCallback(async () => {
        const el = documentRef.current;
        if (!el || !form) return;

        setDownloadingPdf(true);
        try {
            const [{default: html2canvas}, {default: jsPDF}] = await Promise.all([
                import('html2canvas'),
                import('jspdf'),
            ]);

            const canvas = await html2canvas(el, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
            });

            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const margin = 10;
            const contentWidth = pageWidth - margin * 2;
            const imgHeight = (canvas.height * contentWidth) / canvas.width;
            const printableHeight = pageHeight - margin * 2;

            let heightLeft = imgHeight;
            let position = margin;

            pdf.addImage(imgData, 'PNG', margin, position, contentWidth, imgHeight);
            heightLeft -= printableHeight;

            while (heightLeft > 0) {
                pdf.addPage();
                position = margin - (imgHeight - heightLeft);
                pdf.addImage(imgData, 'PNG', margin, position, contentWidth, imgHeight);
                heightLeft -= printableHeight;
            }

            pdf.save(buildPdfFilename(form, bill));
            message.success('PDF downloaded');
        } catch {
            message.error('Could not generate PDF. Try Print instead.');
        } finally {
            setDownloadingPdf(false);
        }
    }, [bill, form, message]);

    const footerBusy = loading || downloadingPdf;

    return (
        <Modal
            open={open && !!bill}
            onCancel={onClose}
            footer={null}
            width={800}
            centered
            destroyOnHidden
            title={null}
            closable={false}
            className="brand-modal"
            styles={{body: {padding: 0}, content: {padding: 0, overflow: 'hidden'}}}
        >
            <BrandModalHeader title="Payment Order Form" onClose={onClose} />

            <div className="relative max-h-[78vh] overflow-y-auto bg-slate-100 py-5">
                {loading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/70">
                        <Spin tip="Loading order form…" />
                    </div>
                )}
                {form && <OrderFormDocument form={form} documentRef={documentRef} />}
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
                <Button
                    icon={<Download size={14} />}
                    onClick={handleDownloadPdf}
                    disabled={footerBusy || !form}
                    loading={downloadingPdf}
                    style={{borderColor: BRAND, color: BRAND}}
                    className="hover:!bg-[#fff5f5]"
                >
                    Download PDF
                </Button>
                <Button
                    type="primary"
                    icon={<Printer size={14} />}
                    onClick={handlePrint}
                    disabled={footerBusy || !form}
                    style={{backgroundColor: BRAND, borderColor: BRAND}}
                    onMouseEnter={(e) => {
                        if (!e.currentTarget.disabled) {
                            e.currentTarget.style.backgroundColor = BRAND_DARK;
                            e.currentTarget.style.borderColor = BRAND_DARK;
                        }
                    }}
                    onMouseLeave={(e) => {
                        if (!e.currentTarget.disabled) {
                            e.currentTarget.style.backgroundColor = BRAND;
                            e.currentTarget.style.borderColor = BRAND;
                        }
                    }}
                >
                    Print
                </Button>
                <Button onClick={onClose} disabled={downloadingPdf}>
                    Close
                </Button>
            </div>
        </Modal>
    );
}
