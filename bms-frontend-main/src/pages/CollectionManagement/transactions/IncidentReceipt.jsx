import React from 'react';

const numberToWords = (num) => {
  const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
  const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
  const teens = [
    'Ten',
    'Eleven',
    'Twelve',
    'Thirteen',
    'Fourteen',
    'Fifteen',
    'Sixteen',
    'Seventeen',
    'Eighteen',
    'Nineteen',
  ];

  if (num === 0) return 'Zero';
  if (num < 10) return ones[num];
  if (num < 20) return teens[num - 10];
  if (num < 100) return tens[Math.floor(num / 10)] + (num % 10 ? ` ${ones[num % 10]}` : '');
  if (num < 1000)
    return (
      ones[Math.floor(num / 1000)] +
      ' Hundred' +
      (num % 100 ? ` ${numberToWords(num % 100)}` : '')
    );
  if (num < 1000000)
    return (
      numberToWords(Math.floor(num / 1000)) +
      ' Thousand' +
      (num % 1000 ? ` ${numberToWords(num % 1000)}` : '')
    );
  return 'Number too large';
};

export default function IncidentReceipt({ incident, onClose }) {
  const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  };

  const formatCurrency = (amount) => {
    if (!amount) return '-';
    return new Intl.NumberFormat('en-TZ', {
      style: 'currency',
      currency: 'TZS',
      minimumFractionDigits: 2,
    }).format(amount);
  };

  const amountInWords = incident?.amount ? `${numberToWords(incident.amount)} Shillings Only` : '-';

  const handlePrint = () => {
    window.print();
  };

  if (!incident) return null;

  return (
    <div className="min-h-screen bg-white p-4 print:p-0">
      <div className="mb-4 flex justify-end print:hidden">
        {onClose && (
          <button
            type="button"
            onClick={onClose}
            className="mr-2 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400"
          >
            Close
          </button>
        )}
        <button
          type="button"
          onClick={handlePrint}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Print Receipt
        </button>
      </div>

      <div className="max-w-md mx-auto bg-white border border-gray-300 p-6 receipt-content">
        <div className="text-center mb-4">
          <h3 className="text-lg font-bold mb-2">Incident Payment Receipt</h3>
        </div>

        <table className="w-full text-xs border-collapse mb-4">
          <tbody>
            <tr>
              <td className="font-semibold py-1">Receipt Number:</td>
              <td className="py-1">{incident.receipt_number || incident.control_num || '-'}</td>
              <td className="font-semibold py-1">Receipt Date</td>
              <td className="py-1">{formatDate(incident.payment_date || incident.created_at)}</td>
            </tr>
            <tr>
              <td className="font-semibold py-1">Bank Reference:</td>
              <td className="py-1">{incident.psp_receipt_num || '-'}</td>
              <td className="font-semibold py-1">Receipt Amount</td>
              <td className="py-1">{formatCurrency(incident.amount || incident.paid_amt)}</td>
            </tr>
            <tr>
              <td className="font-semibold py-1">Amount in Words:</td>
              <td colSpan={3} className="py-1">
                {amountInWords}
              </td>
            </tr>
            <tr>
              <td className="font-semibold py-1">Receipt Description:</td>
              <td colSpan={3} className="py-1">
                Incident Fine Charges
              </td>
            </tr>
            <tr>
              <td colSpan={4} className="py-2">
                &nbsp;
              </td>
            </tr>
            <tr>
              <td className="font-semibold py-1">Payment Mode:</td>
              <td className="py-1">E-PAYMENT</td>
              <td className="font-semibold py-1">Bank Date</td>
              <td className="py-1">{formatDate(incident.trx_dt_tm)}</td>
            </tr>
            <tr>
              <td className="font-semibold py-1">Payment Type:</td>
              <td className="py-1">Fine Charge</td>
              <td colSpan={2}></td>
            </tr>
            <tr>
              <td className="font-semibold py-1">Incident Type:</td>
              <td className="py-1">{incident.nature_incident || '-'}</td>
              <td className="font-semibold py-1">Plate Number</td>
              <td className="py-1">{incident.plate_number || '-'}</td>
            </tr>
            <tr>
              <td className="font-semibold py-1">Driver Name:</td>
              <td className="py-1">{incident.driver_name || '-'}</td>
              <td className="font-semibold py-1">Payer Name</td>
              <td className="py-1">{incident.payer_name || '-'}</td>
            </tr>
            <tr>
              <td className="font-semibold py-1">Control Number:</td>
              <td className="py-1">{incident.control_num || '-'}</td>
              <td className="font-semibold py-1">Payment Reference</td>
              <td className="py-1">{incident.pay_ref_id || '-'}</td>
            </tr>
          </tbody>
        </table>

        <div className="mt-6 pt-4 border-t border-gray-300 text-center text-xs">
          <p className="mb-2">Thank you for your payment</p>
          <p className="text-gray-600">This is a system generated receipt</p>
        </div>
      </div>

      <style>{`
        @media print {
          body * {
            visibility: hidden;
          }
          .receipt-content, .receipt-content * {
            visibility: visible;
          }
          .receipt-content {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
          }
          @page {
            margin: 0.5cm;
            size: A4;
          }
        }
      `}</style>
    </div>
  );
}


