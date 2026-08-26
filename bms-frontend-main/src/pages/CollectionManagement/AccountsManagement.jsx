import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useSelector } from 'react-redux';
import { AlertCircle, Edit, Eye, ToggleLeft, ToggleRight, Save, Receipt, Package, Phone, Mail, Hash, Plus, Copy, CheckCircle2, Link2, Unlink, ImageOff, ShieldCheck } from 'lucide-react';
import { Modal, Button, Tabs, Select, Tag, Empty, Form, Input, InputNumber, Radio, Spin, AutoComplete, message as antMessage } from 'antd';
import Swal from 'sweetalert2';

import DataTable from '../../common/data/DataTable.jsx';
import CollectionLoader from './components/CollectionLoader.jsx';
import TopUpBillDetailsModal from '../../common/components/transactions/prepayments/TopUpBillDetailsModal.jsx';
import BundleBillDetailsModal from '../../common/components/transactions/bundles/BundleBillDetailsModal.jsx';
import { formatMoney } from '../../common/utils/numberFormat.js';
import { apiService } from '../../services/api.jsx';

const EMPTY_CREATE_FORM = {
  nida: '',
  first_name: '',
  middle_name: '',
  surname: '',
  email: '',
  phone: '',
};

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const isPresent = (v) => !(v === null || v === undefined || v === '');

const cleanName = (str) => {
  if (str === null || str === undefined) return '';
  return String(str).replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
};

const buildFullName = (account) => {
  if (!account) return '';
  return [account.first_name, account.middle_name, account.surname]
    .map((part) => cleanName(part))
    .filter(Boolean)
    .join(' ');
};

const formatShortDate = (value) => {
  if (!value) return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return null;
  return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const fireSwalAboveModals = (options) =>
  Swal.fire({
    ...options,
    didOpen: (popup) => {
      if (typeof options.didOpen === 'function') options.didOpen(popup);
      const container = popup?.closest?.('.swal2-container');
      if (container) container.style.zIndex = '3000';
    },
  });

const formatStaffVehicleApiError = (res) => {
  if (!res || res.success) return '';
  const parts = [res.message].filter(Boolean);
  const errs = res.validationErrors;
  if (errs && typeof errs === 'object') {
    Object.entries(errs).forEach(([k, v]) => {
      parts.push(`${k}: ${Array.isArray(v) ? v.join(', ') : String(v)}`);
    });
  }
  const d = res.responseData?.data ?? res.responseData;
  if (d && typeof d === 'object') {
    if (d.existing_account_id != null) parts.push(`Existing account ID: ${d.existing_account_id}`);
    if (d.current_account_id != null) parts.push(`Current account ID: ${d.current_account_id}`);
    if (d.requested_account_id != null) parts.push(`Requested account ID: ${d.requested_account_id}`);
  }
  return parts.join('\n');
};

/** Resolve image URL for vehicle detail payloads (lookup vs collection vs search row). */
const resolveVehiclePreviewImageSrc = (detail, searchRow) => {
  if (!detail && !searchRow) return null;
  const src = detail || {};
  const img = src.image;
  if (typeof img === 'string' && img.trim()) return img.trim();
  if (img && typeof img === 'object' && img.base64) {
    const type = img.content_type || 'image/png';
    const b = String(img.base64);
    return b.startsWith('data:') ? b : `data:${type};base64,${b}`;
  }
  const url = src.image_url || searchRow?.image_url;
  if (url && String(url).trim()) return String(url).trim();
  return null;
};

const BrandModalHeader = ({ title, onClose }) => (
  <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
    <h2 className="m-0 text-sm font-semibold leading-none text-white">{title}</h2>
    <button
      type="button"
      aria-label="Close"
      onClick={onClose}
      className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
      </svg>
    </button>
  </div>
);

const Section = ({ title, children }) => (
  <section className="mb-4">
    <h4 className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]" style={{ color: BRAND }}>
      {title}
    </h4>
    {children}
  </section>
);

const Field = ({ label, value, mono = false }) => (
  <div className="min-w-0 py-1.5">
    <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <div className={`mt-0.5 text-sm text-black ${mono ? 'font-mono tracking-wide' : 'font-medium'}`} title={isPresent(value) ? String(value) : EMPTY_VALUE}>
      {isPresent(value) ? value : <span className="text-slate-400">{EMPTY_VALUE}</span>}
    </div>
  </div>
);

export default function AccountsManagement() {
  const currentUser = useSelector((state) => state.auth.user);
  const staffUserId = currentUser?.id ?? currentUser?.user_id ?? 1;

  const [accounts, setAccounts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isInitialLoad, setIsInitialLoad] = useState(true);

  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  });

  const [filters, setFilters] = useState({
    search: '',
    status: undefined, // 1 | 0
    sort_by: 'id',
    sort_order: 'asc',
    per_page: 15,
    page: 1,
  });

  const [showViewModal, setShowViewModal] = useState(false);
  const [viewAccount, setViewAccount] = useState(null);
  const [loadingViewId, setLoadingViewId] = useState(null);
  const [loadingView, setLoadingView] = useState(false);

  const [showCreateModal, setShowCreateModal] = useState(false);
  const [createForm, setCreateForm] = useState(EMPTY_CREATE_FORM);
  const [creating, setCreating] = useState(false);
  const [showOtpModal, setShowOtpModal] = useState(false);
  const [otpCode, setOtpCode] = useState('');
  const [otpMaskedPhone, setOtpMaskedPhone] = useState('');
  const [sendingOtp, setSendingOtp] = useState(false);
  const [verifyingOtp, setVerifyingOtp] = useState(false);
  const [otpResendIn, setOtpResendIn] = useState(0);

  const [showEditModal, setShowEditModal] = useState(false);
  const [editForm, setEditForm] = useState({
    id: 0,
    nida: '',
    first_name: '',
    middle_name: '',
    surname: '',
    email: '',
    phone: '',
    created_by: 1,
  });
  const [loadingEditId, setLoadingEditId] = useState(null);
  const [updating, setUpdating] = useState(false);
  const [loadingStatusId, setLoadingStatusId] = useState(null);

  // View modal tabs + data
  const [activeTab, setActiveTab] = useState('vehicles');

  const [viewVehicles, setViewVehicles] = useState([]);
  const [loadingVehicles, setLoadingVehicles] = useState(false);
  const [vehiclePagination, setVehiclePagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0,
  });
  const [vehicleFilters, setVehicleFilters] = useState({ search: '', per_page: 10, page: 1 });

  // Associate / disassociate vehicle (staff: associate-vehicle / disassociate-vehicle)
  const [showAssociateVehicleModal, setShowAssociateVehicleModal] = useState(false);
  const [associateSearch, setAssociateSearch] = useState('');
  const [unassociatedOptions, setUnassociatedOptions] = useState([]);
  const [searchUnassociatedLoading, setSearchUnassociatedLoading] = useState(false);
  const [associateSelectedVehicle, setAssociateSelectedVehicle] = useState(null);
  const [associateVehicleDetail, setAssociateVehicleDetail] = useState(null);
  const [associateVehicleDetailLoading, setAssociateVehicleDetailLoading] = useState(false);
  const [associateVehicleDetailError, setAssociateVehicleDetailError] = useState(null);
  const [associateSubmitting, setAssociateSubmitting] = useState(false);
  const [unlinkingPlate, setUnlinkingPlate] = useState(null);
  const associateSearchDebounceRef = useRef(null);

  // Shared selector for per-vehicle tabs (Passages, Bundles)
  const [selectedPlateNo, setSelectedPlateNo] = useState(null);

  const [passages, setPassages] = useState([]);
  const [loadingPassages, setLoadingPassages] = useState(false);
  const [passagesError, setPassagesError] = useState(null);

  const [prepayments, setPrepayments] = useState([]);
  const [loadingPrepayments, setLoadingPrepayments] = useState(false);
  const [prepaymentPagination, setPrepaymentPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0,
  });

  const [bundles, setBundles] = useState([]);
  const [loadingBundles, setLoadingBundles] = useState(false);
  const [bundlesError, setBundlesError] = useState(null);
  const [bundlesPagination, setBundlesPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0,
  });

  // Billing request modals (top-up + bundle)
  const [showTopupModal, setShowTopupModal] = useState(false);
  const [creatingTopup, setCreatingTopup] = useState(false);
  const [topupForm] = Form.useForm();

  const [showBundleModal, setShowBundleModal] = useState(false);
  const [creatingBundle, setCreatingBundle] = useState(false);
  const [bundleForm] = Form.useForm();
  const bundlePlateNo = Form.useWatch('plate_no', bundleForm);
  const bundleTierId = Form.useWatch('bundle_id', bundleForm);
  const [eligibleInfo, setEligibleInfo] = useState(null);
  const [eligibleInfoPlate, setEligibleInfoPlate] = useState(null);
  const [loadingEligible, setLoadingEligible] = useState(false);
  const [eligibleError, setEligibleError] = useState(null);

  // Shared success modal showing the control number after a bill is created
  const [billSuccess, setBillSuccess] = useState(null);

  const [selectedPrepaymentBill, setSelectedPrepaymentBill] = useState(null);
  const [selectedBundleBill, setSelectedBundleBill] = useState(null);

  const fetchAccounts = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiService.getAccountsList(filters);
      if (response.success && response.data?.accounts && response.data?.pagination) {
        setAccounts(response.data.accounts);
        setPagination(response.data.pagination);
      } else {
        setAccounts([]);
        setPagination({
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 0,
          from: 0,
          to: 0,
        });
        setError(response.message || 'Unexpected data format received from server');
      }
    } catch (err) {
      setAccounts([]);
      setError('An error occurred while fetching accounts');
      // eslint-disable-next-line no-console
      console.error('Error fetching accounts:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAccounts();
    setIsInitialLoad(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!isInitialLoad) fetchAccounts();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.page, isInitialLoad]);

  useEffect(() => {
    if (!isInitialLoad && (filters.status !== undefined || filters.sort_by !== 'id' || filters.per_page !== 15)) {
      fetchAccounts();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.per_page, filters.status, filters.sort_by, filters.sort_order, isInitialLoad]);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (!isInitialLoad && filters.search !== undefined) fetchAccounts();
    }, 500);
    return () => clearTimeout(timeoutId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.search, isInitialLoad]);

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value, page: key === 'page' ? value : 1 }));
  };

  useEffect(() => {
    if (!selectedPlateNo && viewVehicles.length > 0) {
      const firstPlate = viewVehicles[0]?.plate_no || null;
      if (firstPlate) setSelectedPlateNo(firstPlate);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [viewVehicles]);

  const handleSearch = (searchTerm) => handleFilterChange('search', searchTerm);

  const openCreateModal = () => {
    setCreateForm(EMPTY_CREATE_FORM);
    setOtpCode('');
    setOtpMaskedPhone('');
    setShowOtpModal(false);
    setShowCreateModal(true);
  };

  const startOtpResendTimer = () => setOtpResendIn(60);

  useEffect(() => {
    if (otpResendIn <= 0) return undefined;
    const timer = setTimeout(() => setOtpResendIn((s) => s - 1), 1000);
    return () => clearTimeout(timer);
  }, [otpResendIn]);

  const requestCreateAccountOtp = async () => {
    const response = await apiService.sendAccountCreationOtp({
      ...createForm,
      created_by: staffUserId,
    });
    if (!response.success) {
      throw Object.assign(new Error(formatStaffVehicleApiError(response) || response.message || 'Failed to send OTP'), {
        response,
      });
    }
    const data = response.data || {};
    setOtpMaskedPhone(data.phone || createForm.phone);
    setOtpCode('');
    startOtpResendTimer();
    setShowOtpModal(true);
  };

  const handleCreateAccount = async (e) => {
    e.preventDefault();
    setSendingOtp(true);
    setCreating(true);
    try {
      await requestCreateAccountOtp();
    } catch (err) {
      await Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: formatStaffVehicleApiError(err) || err?.message || 'Failed to send OTP',
      });
      // eslint-disable-next-line no-console
      console.error('Error sending account OTP:', err);
    } finally {
      setSendingOtp(false);
      setCreating(false);
    }
  };

  const handleResendOtp = async () => {
    if (otpResendIn > 0 || sendingOtp) return;
    setSendingOtp(true);
    try {
      await requestCreateAccountOtp();
    } catch (err) {
      await fireSwalAboveModals({
        icon: 'error',
        title: 'Error!',
        text: formatStaffVehicleApiError(err) || err?.message || 'Failed to resend OTP',
      });
    } finally {
      setSendingOtp(false);
    }
  };

  const handleVerifyOtpAndCreate = async () => {
    if (!otpCode || otpCode.length !== 6) {
      await fireSwalAboveModals({
        icon: 'warning',
        title: 'Enter OTP',
        text: 'Please enter the 6-digit code sent to the phone number.',
      });
      return;
    }

    setVerifyingOtp(true);
    setCreating(true);
    try {
      const response = await apiService.createAccount({
        ...createForm,
        created_by: staffUserId,
        otp: otpCode,
      });
      if (response.success) {
        const accountData = response.data?.account || response.data || {};
        const accountNo = response.data?.account_no || accountData.account_no || '';
        setShowOtpModal(false);
        setShowCreateModal(false);
        setCreateForm(EMPTY_CREATE_FORM);
        setOtpCode('');
        await Swal.fire({
          icon: 'success',
          title: 'Account created',
          html: accountNo
            ? `Account <strong>${accountNo}</strong> was created successfully.`
            : 'Account created successfully.',
          timer: 3000,
          showConfirmButton: true,
        });
        fetchAccounts();
      } else {
        await fireSwalAboveModals({
          icon: 'error',
          title: 'Error!',
          text: formatStaffVehicleApiError(response) || response.message || 'Failed to create account',
        });
      }
    } catch (err) {
      await fireSwalAboveModals({
        icon: 'error',
        title: 'Error!',
        text: formatStaffVehicleApiError(err) || err?.message || 'An error occurred while creating account',
      });
      // eslint-disable-next-line no-console
      console.error('Error creating account:', err);
    } finally {
      setVerifyingOtp(false);
      setCreating(false);
    }
  };

  const openViewModal = async (account) => {
    setLoadingViewId(account.id);
    setLoadingView(true);
    try {
      const accountResponse = await apiService.getAccountById(account.id);
      if (accountResponse.success && accountResponse.data) {
        const accountData = accountResponse.data.account || accountResponse.data;
        setViewAccount(accountData);

        setActiveTab('vehicles');
        const initialVehicleFilters = { search: '', per_page: 10, page: 1 };
        setVehicleFilters(initialVehicleFilters);
        setSelectedPlateNo(null);
        setPassages([]);
        setBundles([]);
        setPrepayments([]);

        await fetchVehicles(account.id, initialVehicleFilters);
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to load account details' });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while loading account details' });
      // eslint-disable-next-line no-console
      console.error('Error loading account details:', err);
    } finally {
      setLoadingView(false);
      setLoadingViewId(null);
      setShowViewModal(true);
    }
  };

  const openEditModal = async (account) => {
    setLoadingEditId(account.id);
    try {
      const response = await apiService.getAccountById(account.id);
      if (response.success && response.data) {
        const accountData = response.data.account || response.data;
        setEditForm({
          id: accountData.id,
          nida: accountData.nida || '',
          first_name: accountData.first_name || '',
          middle_name: accountData.middle_name || '',
          surname: accountData.surname || '',
          email: accountData.email || '',
          phone: accountData.phone || '',
          created_by: 1,
        });
        setShowEditModal(true);
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to load account details' });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while loading account details' });
      // eslint-disable-next-line no-console
      console.error('Error loading account details:', err);
    } finally {
      setLoadingEditId(null);
    }
  };

  const handleEditAccount = async (e) => {
    e.preventDefault();
    setUpdating(true);
    try {
      const response = await apiService.updateAccount(editForm.id, editForm);
      if (response.success) {
        await Swal.fire({ icon: 'success', title: 'Success!', text: 'Account updated successfully', timer: 2000, showConfirmButton: false });
        setShowEditModal(false);
        setEditForm({
          id: 0,
          nida: '',
          first_name: '',
          middle_name: '',
          surname: '',
          email: '',
          phone: '',
          created_by: 1,
        });
        fetchAccounts();

        if (viewAccount?.id === editForm.id) {
          const accountResponse = await apiService.getAccountById(editForm.id);
          if (accountResponse.success && accountResponse.data) {
            setViewAccount(accountResponse.data.account || accountResponse.data);
          }
        }
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Failed to update account' });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while updating account' });
      // eslint-disable-next-line no-console
      console.error('Error updating account:', err);
    } finally {
      setUpdating(false);
    }
  };

  const toggleAccountStatus = async (accountId, currentStatus) => {
    setLoadingStatusId(accountId);
    try {
      const isActive = Number(currentStatus) === 1;
      const action = isActive ? 'deactivate' : 'activate';

      const result = await Swal.fire({
        title: 'Are you sure?',
        text: `Do you want to ${action} this account?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: `Yes, ${action} account!`,
        cancelButtonText: 'Cancel',
      });
      if (!result.isConfirmed) return;

      const response = isActive ? await apiService.deactivateAccount(accountId) : await apiService.activateAccount(accountId);
      if (response.success) {
        await Swal.fire({ icon: 'success', title: 'Success!', text: `Account ${action}d successfully`, timer: 2000, showConfirmButton: false });

        setAccounts((prev) =>
          prev.map((a) => (a.id === accountId ? { ...a, status: isActive ? '0' : '1', status_text: isActive ? 'Inactive' : 'Active' } : a))
        );
        if (viewAccount?.id === accountId) {
          setViewAccount((prev) => (prev ? { ...prev, status: isActive ? '0' : '1', status_text: isActive ? 'Inactive' : 'Active' } : prev));
        }
      } else {
        await Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Failed to update account status' });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while updating account status' });
      // eslint-disable-next-line no-console
      console.error('Error updating account status:', err);
    } finally {
      setLoadingStatusId(null);
    }
  };

  const fetchVehicles = async (accountId, vf = vehicleFilters) => {
    setLoadingVehicles(true);
    try {
      const response = await apiService.getVehiclesByAccountId(accountId, vf);
      if (response.success && response.data) {
        setViewVehicles(response.data.vehicles || []);
        setVehiclePagination(
          response.data.pagination || {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
            from: 0,
            to: 0,
          }
        );
      } else {
        setViewVehicles([]);
      }
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Error loading vehicles:', err);
      setViewVehicles([]);
    } finally {
      setLoadingVehicles(false);
    }
  };

  const openAssociateVehicleModal = () => {
    setAssociateSearch('');
    setUnassociatedOptions([]);
    setAssociateSelectedVehicle(null);
    setAssociateVehicleDetail(null);
    setAssociateVehicleDetailError(null);
    setAssociateVehicleDetailLoading(false);
    setShowAssociateVehicleModal(true);
  };

  const runUnassociatedSearch = useCallback(async (raw) => {
    const q = String(raw || '').trim().slice(0, 50);
    if (!q) {
      setUnassociatedOptions([]);
      return;
    }
    setSearchUnassociatedLoading(true);
    try {
      const res = await apiService.searchUnassociatedVehicles(q);
      if (res?.success && res.data?.vehicles) {
        setUnassociatedOptions(Array.isArray(res.data.vehicles) ? res.data.vehicles : []);
      } else {
        setUnassociatedOptions([]);
      }
    } catch {
      setUnassociatedOptions([]);
    } finally {
      setSearchUnassociatedLoading(false);
    }
  }, []);

  const onAssociateSearchChange = (value) => {
    const v = String(value ?? '');
    setAssociateSearch(v);
    if (associateSelectedVehicle && String(associateSelectedVehicle.plate_number || '').trim() !== v.trim()) {
      setAssociateSelectedVehicle(null);
    }
    if (associateSearchDebounceRef.current) clearTimeout(associateSearchDebounceRef.current);
    associateSearchDebounceRef.current = setTimeout(() => {
      runUnassociatedSearch(v);
    }, 350);
    if (!v.trim()) {
      setUnassociatedOptions([]);
    }
  };

  useEffect(() => {
    if (!associateSelectedVehicle) {
      setAssociateVehicleDetail(null);
      setAssociateVehicleDetailError(null);
      setAssociateVehicleDetailLoading(false);
      return;
    }
    const plate = String(associateSelectedVehicle.plate_number || '').trim();
    if (!plate) {
      setAssociateVehicleDetail(null);
      setAssociateVehicleDetailError(null);
      return;
    }
    let cancelled = false;
    const run = async () => {
      setAssociateVehicleDetailLoading(true);
      setAssociateVehicleDetailError(null);
      setAssociateVehicleDetail(null);
      try {
        let detail = null;
        let lastMsg = '';
        const vid = associateSelectedVehicle.id;
        if (vid != null && vid !== '') {
          const res = await apiService.getVehicleById(vid);
          if (cancelled) return;
          if (res?.success && res.data) detail = res.data;
          else lastMsg = res?.message || '';
        }
        if (!detail) {
          const res2 = await apiService.lookupVehicleByPlate(plate);
          if (cancelled) return;
          if (res2?.success && res2.data) detail = res2.data;
          else lastMsg = res2?.message || lastMsg || 'Could not load vehicle details';
        }
        if (cancelled) return;
        if (detail) setAssociateVehicleDetail(detail);
        else setAssociateVehicleDetailError(lastMsg || 'Could not load vehicle details');
      } catch (err) {
        if (!cancelled) {
          setAssociateVehicleDetailError(err?.message || 'Could not load vehicle details');
        }
      } finally {
        if (!cancelled) setAssociateVehicleDetailLoading(false);
      }
    };
    run();
    return () => {
      cancelled = true;
    };
  }, [associateSelectedVehicle]);

  const handleAssociateVehicleSubmit = async () => {
    const accountNo = viewAccount?.account_no ? String(viewAccount.account_no).trim() : '';
    if (!accountNo) {
      antMessage.warning('Account number is missing');
      return;
    }
    const plateNum =
      associateSelectedVehicle?.plate_number != null
        ? String(associateSelectedVehicle.plate_number).trim()
        : '';
    if (!plateNum) {
      antMessage.warning('Pick a vehicle from the search results');
      return;
    }
    const body = { plate_num: plateNum, account_no: accountNo };

    setAssociateSubmitting(true);
    try {
      const res = await apiService.associateVehicleWithAccount(body);
      if (res?.success) {
        antMessage.success(res.message || 'Vehicle associated successfully');
        setShowAssociateVehicleModal(false);
        await fetchVehicles(viewAccount.id, vehicleFilters);
        const plate = plateNum;
        setSelectedPlateNo((prev) => prev || plate);
      } else {
        await Swal.fire({
          icon: 'error',
          title: 'Could not associate vehicle',
          text: formatStaffVehicleApiError(res) || res?.message || 'Request failed',
        });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error', text: err?.message || 'Unexpected error' });
    } finally {
      setAssociateSubmitting(false);
    }
  };

  const handleDisassociateVehicle = async (plateRaw) => {
    const plateNum = String(plateRaw || '').trim();
    if (!plateNum || !viewAccount?.account_no) return;
    const result = await Swal.fire({
      title: 'Disassociate vehicle?',
      html: `Remove plate <strong>${plateNum}</strong> from account <strong>${viewAccount.account_no}</strong>?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: BRAND,
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Yes, disassociate',
      cancelButtonText: 'Cancel',
    });
    if (!result.isConfirmed) return;

    setUnlinkingPlate(plateNum);
    try {
      const res = await apiService.disassociateStaffVehicle({
        plate_num: plateNum,
        account_no: String(viewAccount.account_no).trim(),
      });
      if (res?.success) {
        antMessage.success(res.message || 'Vehicle disassociated');
        await fetchVehicles(viewAccount.id, vehicleFilters);
        if (selectedPlateNo === plateNum) {
          setSelectedPlateNo(null);
        }
      } else {
        await Swal.fire({
          icon: 'error',
          title: 'Could not disassociate',
          text: formatStaffVehicleApiError(res) || res?.message || 'Request failed',
        });
      }
    } catch (err) {
      await Swal.fire({ icon: 'error', title: 'Error', text: err?.message || 'Unexpected error' });
    } finally {
      setUnlinkingPlate(null);
    }
  };

  const fetchPassages = async (plateNo) => {
    if (!plateNo) {
      setPassages([]);
      return;
    }
    setLoadingPassages(true);
    setPassagesError(null);
    try {
      const response = await apiService.getNormalPassages(plateNo);
      if (response?.success && response.data) {
        const list = response.data.passages || response.data.transactions || response.data.data || [];
        setPassages(Array.isArray(list) ? list : []);
      } else {
        setPassages([]);
        if (response?.message) setPassagesError(response.message);
      }
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Error fetching passages:', err);
      setPassages([]);
      setPassagesError('Could not load passages');
    } finally {
      setLoadingPassages(false);
    }
  };

  const fetchPrepayments = async (accountNo, page = 1, perPage = 10) => {
    if (!accountNo) {
      setPrepayments([]);
      return;
    }
    setLoadingPrepayments(true);
    try {
      const response = await apiService.getAccountTopUps({
        account_no: accountNo,
        page,
        per_page: perPage,
      });
      if (response?.success && response.data) {
        const list = response.data.top_ups || response.data.topUps || [];
        setPrepayments(Array.isArray(list) ? list : []);
        setPrepaymentPagination(
          response.data.pagination || {
              current_page: 1,
              last_page: 1,
            per_page: perPage,
              total: 0,
              from: 0,
              to: 0,
            }
          );
        } else {
        setPrepayments([]);
      }
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Error fetching prepayments:', err);
      setPrepayments([]);
    } finally {
      setLoadingPrepayments(false);
    }
  };

  const fetchBundles = async (plateNo) => {
    if (!plateNo) {
      setBundles([]);
      return;
    }
    setLoadingBundles(true);
    setBundlesError(null);
    try {
      const response = await apiService.getBundleSubscriptions(plateNo);
      if (response?.success && response.data) {
        const list =
          response.data.passages ||
          response.data.subscriptions ||
          response.data.bundles ||
          response.data.data ||
          [];
        setBundles(Array.isArray(list) ? list : []);
        setBundlesPagination(
          response.data.pagination || {
              current_page: 1,
              last_page: 1,
              per_page: 10,
            total: Array.isArray(list) ? list.length : 0,
              from: 0,
              to: 0,
            }
          );
        } else {
        setBundles([]);
        if (response?.message) setBundlesError(response.message);
      }
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Error fetching bundles:', err);
      setBundles([]);
      setBundlesError('Could not load bundles');
    } finally {
      setLoadingBundles(false);
    }
  };

  const extractControlNumber = (data = {}) =>
    data.control_number ||
    data.gepg_control_number ||
    data.api_control_number ||
    data.contr_num ||
    null;

  const openTopupModal = () => {
    if (!viewAccount?.account_no) {
      antMessage.warning('Account number not available');
      return;
    }
    topupForm.resetFields();
    topupForm.setFieldsValue({ bill_amount: undefined, tin: '' });
    setShowTopupModal(true);
  };

  const handleSubmitTopup = async (values) => {
    if (!viewAccount?.account_no) return;
    setCreatingTopup(true);
    try {
      const res = await apiService.requestTopUpBill({
        bill_amount: Number(values.bill_amount),
        account_no: viewAccount.account_no,
        tin: values.tin || undefined,
      });
      if (res?.success) {
        const data = res.data || {};
        setBillSuccess({
          type: 'topup',
          title: 'Top-up bill created',
          control_number: extractControlNumber(data),
          bill_amount: data.bill_amount || values.bill_amount,
          bill_id: data.bill_id,
          message: res.message,
        });
        setShowTopupModal(false);
        topupForm.resetFields();
        fetchPrepayments(viewAccount.account_no, 1, prepaymentPagination.per_page || 10);
      } else {
        const outstanding = res?.data?.outstanding_bill;
        await Swal.fire({
          icon: 'error',
          title: 'Could not create top-up',
          html: outstanding?.control_number
            ? `${res?.message || 'Outstanding bill exists.'}<br/><br/><b>Control Number:</b> ${outstanding.control_number}`
            : res?.message || 'Request failed',
        });
      }
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Top-up request error:', err);
      await Swal.fire({ icon: 'error', title: 'Error', text: err?.message || 'Unexpected error' });
    } finally {
      setCreatingTopup(false);
    }
  };

  const openBundleModal = () => {
    bundleForm.resetFields();
    bundleForm.setFieldsValue({ plate_no: selectedPlateNo || undefined, bundle_id: 3 });
    setEligibleInfo(null);
    setEligibleInfoPlate(null);
    setEligibleError(null);
    setShowBundleModal(true);
  };

  // Fetch eligible bundle types whenever the plate in the bundle form changes
  useEffect(() => {
    if (!showBundleModal || !bundlePlateNo) {
      return;
    }
    if (eligibleInfoPlate === bundlePlateNo) return;
    let cancelled = false;
    setLoadingEligible(true);
    setEligibleError(null);
    setEligibleInfo(null);
    apiService
      .getEligibleBundlesByPlate(bundlePlateNo)
      .then((res) => {
        if (cancelled) return;
        if (res?.success && res?.data) {
          setEligibleInfo(res.data);
          setEligibleInfoPlate(bundlePlateNo);
          const bundles = Array.isArray(res.data?.bundles) ? res.data.bundles : [];
          const currentId = bundleForm.getFieldValue('bundle_id');
          const currentEligible = bundles.find((b) => Number(b.bundle_id) === Number(currentId) && b.eligible);
          if (!currentEligible) {
            const firstEligible = bundles.find((b) => b.eligible);
            if (firstEligible) bundleForm.setFieldsValue({ bundle_id: firstEligible.bundle_id });
          }
        } else {
          setEligibleError(res?.message || 'Could not load eligible bundles');
        }
      })
      .catch((err) => {
        if (cancelled) return;
        setEligibleError(err?.message || 'Failed to load eligible bundles');
      })
      .finally(() => {
        if (!cancelled) setLoadingEligible(false);
      });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [showBundleModal, bundlePlateNo]);

  const handleSubmitBundle = async (values) => {
    setCreatingBundle(true);
    try {
      const res = await apiService.requestBundleBill({
        plate_no: values.plate_no,
        bundle_id: Number(values.bundle_id),
        source: 'portal',
      });
      if (res?.success) {
        const data = res.data || {};
        setBillSuccess({
          type: 'bundle',
          title: 'Bundle bill created',
          control_number: extractControlNumber(data),
          bill_amount: data.bill_amount,
          bill_id: data.bill_id,
          message: res.message,
        });
        setShowBundleModal(false);
        bundleForm.resetFields();
        if (values.plate_no === selectedPlateNo) fetchBundles(values.plate_no);
      } else {
        await Swal.fire({
          icon: 'error',
          title: 'Could not create bundle bill',
          text: res?.message || 'Request failed',
        });
      }
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('Bundle request error:', err);
      await Swal.fire({ icon: 'error', title: 'Error', text: err?.message || 'Unexpected error' });
    } finally {
      setCreatingBundle(false);
    }
  };

  const copyToClipboard = async (text) => {
    if (!text) return;
    try {
      await navigator.clipboard.writeText(String(text));
      antMessage.success('Copied');
    } catch {
      antMessage.error('Could not copy');
    }
  };

  const columns = useMemo(
    () => [
      {
        title: 'S/N',
        key: 'serial',
        width: 80,
        align: 'center',
        render: (_, __, idx) => (
          <span className="text-sm font-medium text-gray-600">
            {(pagination.current_page - 1) * (filters.per_page || 15) + idx + 1}
          </span>
        ),
      },
      {
        title: 'Account Number',
        dataIndex: 'account_no',
        key: 'account_no',
        searchable: true,
        render: (v) => <span className="font-mono text-sm">{v || EMPTY_VALUE}</span>,
      },
      {
        title: 'Account Name',
        key: 'full_name',
        render: (_, account) => (
          <span className="text-sm">{buildFullName(account) || EMPTY_VALUE}</span>
        ),
      },
      {
        title: 'Phone Number',
        dataIndex: 'phone',
        key: 'phone',
        searchable: true,
        render: (v) => <span className="text-sm">{v || EMPTY_VALUE}</span>,
      },
      {
        title: 'Balance',
        dataIndex: 'account_balance',
        key: 'account_balance',
        align: 'right',
        money: false,
        render: (v) => <span className="text-sm">{formatMoney(v)}</span>,
      },
      {
        title: 'Status',
        dataIndex: 'status',
        key: 'status',
        align: 'center',
        render: (_, account) => {
          const isActive = account.status === '1' || account.status === 1 || account.status === true;
          const label = account.status_text || (isActive ? 'Active' : 'Inactive');
          return (
            <Tag color={isActive ? 'green' : 'red'} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {label}
            </Tag>
          );
        },
      },
      {
        title: 'Action',
        key: 'actions',
        width: 120,
        align: 'center',
        render: (_, account) => (
          <button
            type="button"
            onClick={() => openViewModal(account)}
            disabled={loadingViewId === account.id}
            className="inline-flex items-center space-x-1 rounded-lg px-3 py-1.5 text-sm text-white disabled:cursor-not-allowed disabled:opacity-50"
            style={{ backgroundColor: BRAND }}
            onMouseEnter={(e) => {
              if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
            }}
            title="View Account Details"
          >
            <Eye size={16} className="text-white" />
            <span>View</span>
          </button>
        ),
      },
    ],
    [filters.per_page, loadingViewId, pagination]
  );

  const paginationConfig = useMemo(
    () => ({
      current: pagination.current_page,
      pageSize: pagination.per_page,
      total: pagination.total,
      showTotal: () => null,
      showQuickJumper: false,
      showSizeChanger: true,
      pageSizeOptions: ['10', '15', '25', '50', '100'],
      onChange: (page, pageSize) => {
        if (pageSize !== filters.per_page) handleFilterChange('per_page', pageSize);
        handleFilterChange('page', page);
      },
    }),
    [filters.per_page, pagination, filters]
  );

  const formatDateTime = (value) => {
    if (!value) return EMPTY_VALUE;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('en-GB', {
      day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
  };

  const plateOptions = useMemo(
    () => (viewVehicles || []).map((v) => ({ value: v.plate_no, label: v.plate_no })),
    [viewVehicles]
  );

  const onPlateSelectorChange = (plate) => {
    setSelectedPlateNo(plate);
    if (activeTab === 'passages') fetchPassages(plate);
    else if (activeTab === 'bundles') fetchBundles(plate);
  };

  const EmptyTabState = ({ message }) => (
    <div className="flex min-h-[240px] items-center justify-center">
      <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={<span className="text-sm text-slate-500">{message}</span>} />
    </div>
  );

  const PlateSelectorBar = () => {
    if (!viewVehicles?.length) return null;
  return (
      <div className="mb-4 flex flex-wrap items-center gap-3 rounded-md border border-slate-200 bg-slate-50 px-4 py-2.5">
        <span className="text-xs font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>Vehicle</span>
        <Select
          value={selectedPlateNo || undefined}
          onChange={onPlateSelectorChange}
          options={plateOptions}
          placeholder="Select plate number"
          size="middle"
          style={{ minWidth: 220 }}
        />
        </div>
    );
  };

  const renderVehiclesTab = () => {
    const columns = [
      { title: '#', key: 'serial', width: 60, align: 'center',
        render: (_, __, idx) => (
          <span className="text-sm font-medium text-slate-600">
            {(vehiclePagination.current_page - 1) * vehiclePagination.per_page + idx + 1}
          </span>
        ),
      },
      { title: 'Plate Number', dataIndex: 'plate_no', key: 'plate_no',
        render: (v) => <span className="font-mono text-sm font-semibold text-black">{v || EMPTY_VALUE}</span>,
      },
      { title: 'Body Type', key: 'body_type',
        render: (_, v) => <span className="text-sm text-black">{v.body_type?.name || v.body_type_name || EMPTY_VALUE}</span>,
      },
      { title: 'Status', key: 'status', align: 'center',
        render: (_, v) => (
          <Tag color={v.status ? 'green' : 'red'} className="!m-0">
            {v.status ? 'Active' : 'Inactive'}
          </Tag>
        ),
      },
      { title: 'Exempted', key: 'exempted', align: 'center',
        render: (_, v) => (
          <Tag color={v.exempted ? 'gold' : 'default'} className="!m-0">
            {v.exempted ? 'Yes' : 'No'}
          </Tag>
        ),
      },
      {
        title: 'Actions',
        key: 'vehicle_assoc',
        width: 150,
        align: 'center',
        render: (_, v) => {
          const plate = String(v.plate_no || '').replace(/\s+/g, ' ').trim();
          return (
            <Button
              type="link"
              size="small"
              danger
              icon={<Unlink size={14} className="inline" />}
              loading={unlinkingPlate === plate}
              disabled={(!!unlinkingPlate && unlinkingPlate !== plate) || !viewAccount?.account_no}
              onClick={() => handleDisassociateVehicle(plate)}
              className="!px-1"
            >
              Disassociate
            </Button>
          );
        },
      },
    ];
    const pag = {
      current: vehiclePagination.current_page,
      pageSize: vehiclePagination.per_page,
      total: vehiclePagination.total,
      showTotal: () => null,
      showQuickJumper: false,
      onChange: (page, pageSize) => {
        const nf = { ...vehicleFilters, page, per_page: pageSize };
        setVehicleFilters(nf);
        fetchVehicles(viewAccount.id, nf);
      },
    };
    return (
      <div className="space-y-3">
        <div className="flex flex-wrap items-center justify-end gap-2">
          <Button
            type="primary"
            icon={<Link2 size={14} />}
            onClick={openAssociateVehicleModal}
            disabled={!viewAccount?.account_no}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => {
              if (!viewAccount?.account_no) return;
              e.currentTarget.style.backgroundColor = BRAND_DARK;
              e.currentTarget.style.borderColor = BRAND_DARK;
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
              e.currentTarget.style.borderColor = BRAND;
            }}
          >
            Associate vehicle
          </Button>
        </div>
        <div className="bg-white rounded-md border border-slate-200">
          <DataTable
            columns={columns}
            data={viewVehicles}
            loading={loadingVehicles}
            pagination={pag}
            showSearch={false}
            showRefresh={false}
          />
        </div>
      </div>
    );
  };

  const renderPassagesTab = () => {
    const columns = [
      { title: '#', key: 'serial', width: 60, align: 'center',
        render: (_, __, idx) => <span className="text-sm font-medium text-slate-600">{idx + 1}</span>,
      },
      { title: 'Passage Time', key: 'passage_time',
        render: (_, r) => (
          <span className="text-sm text-black">
            {formatDateTime(r.passage_time || r.created_at)}
          </span>
        ),
      },
      { title: 'Lane', key: 'lane_no', align: 'center',
        render: (_, r) => (
          <span className="font-mono text-sm font-medium text-black">
            {r.lane_no || EMPTY_VALUE}
          </span>
        ),
      },
      { title: 'Amount (TZS)', key: 'amount', align: 'right',
        render: (_, r) => (
          <span className="block text-right font-mono text-sm text-black">
            {Number(r.amount || 0).toLocaleString()}
          </span>
        ),
      },
      { title: 'Receipt Number', key: 'receipt_number',
        render: (_, r) => (
          <span className="font-mono text-sm text-black">
            {r.receipt_number || EMPTY_VALUE}
          </span>
        ),
      }
    ];
    return (
      <>
        <PlateSelectorBar />
        {!selectedPlateNo ? (
          <EmptyTabState message="Select a vehicle to view its passages." />
        ) : passagesError ? (
          <div className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{passagesError}</div>
        ) : (
          <div className="bg-white rounded-md border border-slate-200">
            <DataTable
              columns={columns}
              data={passages}
              loading={loadingPassages}
              pagination={{
                pageSize: 5,
                defaultPageSize: 5,
                pageSizeOptions: ['5', '10', '20', '50'],
                showSizeChanger: true,
                showTotal: () => null,
                showQuickJumper: false,
              }}
              showSearch={false}
              showRefresh={false}
            />
          </div>
        )}
      </>
    );
  };

  const renderPrepaymentsTab = () => {
    const formatTopupDate = (value) => {
      if (!value) return EMPTY_VALUE;
      const d = new Date(value);
      if (Number.isNaN(d.getTime())) return value;
      return d.toLocaleString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
      }).replace(',', '');
    };
    const statusLabel = (s) => {
      const v = String(s || '').trim();
      if (!v) return EMPTY_VALUE;
      return v.charAt(0).toUpperCase() + v.slice(1).toLowerCase();
    };
    const columns = [
      { title: '#', key: 'serial', width: 60, align: 'center',
        render: (_, __, idx) => (
          <span className="text-sm font-medium text-slate-600">
            {(prepaymentPagination.current_page - 1) * prepaymentPagination.per_page + idx + 1}
          </span>
        ),
      },
      { title: 'Topup Date', key: 'trx_dt_tm',
        render: (_, r) => (
          <span className="text-sm text-black">
            {formatTopupDate(r.trx_dt_tm || r.payment_date || r.created_at)}
          </span>
        ),
      },
      { title: 'Control No', key: 'control_number',
        render: (_, r) => (
          <span className="font-mono text-sm text-black">
            {r.gepg_control_number || r.api_control_number || r.contr_num || EMPTY_VALUE}
          </span>
        ),
      },
      { title: 'Topup Amount', key: 'amount', align: 'right',
        render: (_, r) => (
          <span className="block text-right font-mono text-sm text-black">
            TZS {Number(r.bill_amount || r.bill_amount || r.bill_amount || 0).toLocaleString()}
          </span>
        ),
      },
      { title: 'Topup Status', key: 'bill_status', align: 'right',
        render: (_, r) => {
          const status = String(r.bill_status || '').toUpperCase();
          const color =
            status === 'PAID' ? 'green'
              : status === 'UNPAID' ? 'gold'
              : status === 'EXPIRED' ? 'red'
              : status === 'CANCELLED' ? 'red'
              : 'default';
          return (
            <Tag color={color} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {statusLabel(r.bill_status)}
            </Tag>
          );
        },
      },
      {
        title: 'Actions',
        key: 'actions',
        width: 120,
        align: 'center',
        render: (_, r) => (
          <Button
            type="link"
            size="small"
            icon={<Eye size={14} className="inline" />}
            onClick={() => setSelectedPrepaymentBill(r)}
            className="!px-1"
          >
            View
          </Button>
        ),
      },
    ];
    const pag = {
      current: prepaymentPagination.current_page,
      pageSize: prepaymentPagination.per_page,
      total: prepaymentPagination.total,
      showSizeChanger: true,
      pageSizeOptions: ['10', '20', '50', '100'],
      showTotal: () => null,
      showQuickJumper: false,
      onChange: (page, pageSize) => {
        if (!viewAccount?.account_no) return;
        fetchPrepayments(viewAccount.account_no, page, pageSize);
      },
    };
    return (
      <>
        <div className="mb-3 flex items-center justify-end">
          <Button
            type="primary"
            icon={<Plus size={14} />}
            onClick={openTopupModal}
            disabled={!viewAccount?.account_no}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = BRAND_DARK; e.currentTarget.style.borderColor = BRAND_DARK; }}
            onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = BRAND; e.currentTarget.style.borderColor = BRAND; }}
          >
            Request Top-up
          </Button>
        </div>
        <div className="bg-white rounded-md border border-slate-200">
          <DataTable
            columns={columns}
            data={prepayments}
            loading={loadingPrepayments}
            pagination={pag}
            showSearch={false}
            showRefresh={false}
          />
        </div>
      </>
    );
  };

  const mapBundleRowForDetails = (row) => {
    const status = String(row.status || '').toUpperCase();
    const isCancelled = status === 'CANCELLED' || Number(row.is_cancelled) === 1;
    const isPaid = status === 'PAID' || !!row.trx_dt_tm || !!row.psp_receipt_num;
    const amount = row.bill_amount ?? row.amount;

    return {
      ...row,
      id: row.id ?? row.bill_id,
      account_no: row.account_no ?? viewAccount?.account_no,
      customer_name: row.customer_name ?? buildFullName(viewAccount),
      plate_no: row.plate_no ?? selectedPlateNo,
      control_number: row.control_number ?? row.contract_number,
      bill_amount: amount,
      bill_description: row.bill_description ?? row.bundle_name,
      bill_status: row.bill_status ?? row.status,
      bill_status_display: row.status,
      bill_generated_at: row.bill_generated_at ?? row.start_date,
      bill_expiry_at: row.bill_expiry_at ?? row.end_date,
      amount_display: formatMoney(amount),
      can_cancel: row.can_cancel ?? (!isCancelled && !isPaid),
      can_repost: row.can_repost ?? (!isCancelled && !isPaid),
      can_print: row.can_print ?? isPaid,
    };
  };

  const renderBundlesTab = () => {
    const formatBundleDate = (value) => {
      if (!value) return EMPTY_VALUE;
      const d = new Date(value);
      if (Number.isNaN(d.getTime())) return value;
      return d.toLocaleString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
      }).replace(',', '');
    };
    const statusLabel = (s) => {
      const v = String(s || '').trim();
      if (!v) return EMPTY_VALUE;
      return v.charAt(0).toUpperCase() + v.slice(1).toLowerCase();
    };
    const columns = [
      { title: '#', key: 'serial', width: 60, align: 'center',
        render: (_, __, idx) => (
          <span className="text-sm font-medium text-slate-600">
            {(bundlesPagination.current_page - 1) * (bundlesPagination.per_page || 10) + idx + 1}
          </span>
        ),
      },
      { title: 'Bundle Name', key: 'bundle_name',
        render: (_, r) => (
          <span className="text-sm font-medium text-black">{r.bundle_name || EMPTY_VALUE}</span>
        ),
      },
      { title: 'Start Date', key: 'start_date',
        render: (_, r) => (
          <span className="text-sm text-black">{formatBundleDate(r.start_date)}</span>
        ),
      },
      { title: 'End Date', key: 'end_date',
        render: (_, r) => (
          <span className="text-sm text-black">{formatBundleDate(r.end_date)}</span>
        ),
      },
      { title: 'Contract Number', key: 'contract_number',
        render: (_, r) => (
          <span className="font-mono text-sm text-black">{r.contract_number || EMPTY_VALUE}</span>
        ),
      },
      { title: 'Amount (TZS)', key: 'amount', align: 'right',
        render: (_, r) => (
          <span className="block text-right font-mono text-sm text-black">
            TZS {Number(r.amount || 0).toLocaleString()}
          </span>
        ),
      },
      { title: 'Payment Status', key: 'status', align: 'center',
        render: (_, r) => {
          const status = String(r.status || '').toUpperCase();
          const color =
            status === 'PAID' || status === 'ACTIVE' ? 'green'
              : status === 'UNPAID' || status === 'PENDING' ? 'gold'
              : status === 'EXPIRED' || status === 'CANCELLED' ? 'red'
              : 'default';
          return (
            <Tag color={color} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {statusLabel(r.status)}
            </Tag>
          );
        },
      },
      { title: 'Bundle Status', key: 'bundle_status', align: 'center',
        render: (_, r) => {
          const isActive = Number(r.bundle_status) === 1;
          return (
            <Tag color={isActive ? 'green' : 'red'} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
              {isActive ? 'Active' : 'Inactive'}
            </Tag>
          );
        },
      },
      {
        title: 'Actions',
        key: 'actions',
        width: 120,
        align: 'center',
        render: (_, r) => (
          <Button
            type="link"
            size="small"
            icon={<Eye size={14} className="inline" />}
            onClick={() => setSelectedBundleBill(mapBundleRowForDetails(r))}
            className="!px-1"
          >
            View
          </Button>
        ),
      },
    ];
    return (
      <>
        <div className="mb-3 flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            <PlateSelectorBar />
          </div>
          <Button
            type="primary"
            icon={<Plus size={14} />}
            onClick={openBundleModal}
            disabled={!selectedPlateNo}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = BRAND_DARK; e.currentTarget.style.borderColor = BRAND_DARK; }}
            onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = BRAND; e.currentTarget.style.borderColor = BRAND; }}
          >
            Request Bundle
          </Button>
        </div>
        {!selectedPlateNo ? (
          <EmptyTabState message="Select a vehicle to view its bundles." />
        ) : bundlesError ? (
          <div className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{bundlesError}</div>
        ) : (
          <div className="bg-white rounded-md border border-slate-200">
            <DataTable
              columns={columns}
              data={bundles}
              loading={loadingBundles}
              pagination={{
                pageSize: 5,
                defaultPageSize: 5,
                pageSizeOptions: ['5', '10', '20', '50'],
                showSizeChanger: true,
                showTotal: () => null,
                showQuickJumper: false,
              }}
              showSearch={false}
              showRefresh={false}
            />
          </div>
      )}
      </>
    );
  };

  return (
    <div className="space-y-4">
      {error && (
        <div className="rounded-lg border border-red-200 bg-red-50 p-4">
          <div className="flex">
            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-red-400" />
            <div className="ml-3">
              <h3 className="text-sm font-medium text-red-800">Error</h3>
              <p className="mt-1 text-sm text-red-700">{error}</p>
            </div>
          </div>
        </div>
      )}

      <div className="relative">
        {loading ? (
          <div
            className="pointer-events-none absolute inset-x-0 z-10 flex justify-center"
            style={{ top: '4.75rem' }}
          >
            <CollectionLoader />
          </div>
        ) : null}
        <DataTable
          columns={columns}
          data={accounts || []}
          loading={false}
          pagination={paginationConfig}
          onSearchChange={handleSearch}
          serverSideSearch={true}
          showSearch={true}
          showRefresh={false}
          searchPlaceholder="Search accounts by name, phone, email, account number..."
          rightAction={
            <button
              type="button"
              className="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
              style={{ backgroundColor: BRAND }}
              onMouseEnter={(e) => {
                if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = BRAND_DARK;
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = BRAND;
              }}
              onClick={openCreateModal}
              disabled={showCreateModal || creating}
            >
              Create
            </button>
          }
        />
      </div>

      <Modal
        open={showCreateModal}
        onCancel={() => !creating && !showOtpModal && setShowCreateModal(false)}
        footer={null}
        width={680}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!creating && !showOtpModal}
        keyboard={!creating && !showOtpModal}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Create Account" onClose={() => !creating && !showOtpModal && setShowCreateModal(false)} />

        <form id="create-account-form" onSubmit={handleCreateAccount} className="px-6 py-5">
          <Section title="Personal">
            <div className="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>NIDA</label>
                <input
                  type="text"
                  value={createForm.nida}
                  onChange={(e) => setCreateForm((prev) => ({ ...prev, nida: e.target.value }))}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-black transition-colors focus:border-[#962E32] focus:ring-1 focus:ring-[#962E32]"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  First Name <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  value={createForm.first_name}
                  onChange={(e) => setCreateForm((prev) => ({ ...prev, first_name: e.target.value }))}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-black transition-colors focus:border-[#962E32] focus:ring-1 focus:ring-[#962E32]"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>Middle Name</label>
                <input
                  type="text"
                  value={createForm.middle_name}
                  onChange={(e) => setCreateForm((prev) => ({ ...prev, middle_name: e.target.value }))}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-black transition-colors focus:border-[#962E32] focus:ring-1 focus:ring-[#962E32]"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Surname <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  value={createForm.surname}
                  onChange={(e) => setCreateForm((prev) => ({ ...prev, surname: e.target.value }))}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-black transition-colors focus:border-[#962E32] focus:ring-1 focus:ring-[#962E32]"
                />
              </div>
            </div>
          </Section>

          <Section title="Contact">
            <div className="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Email <span className="text-red-500">*</span>
                </label>
                <input
                  type="email"
                  required
                  value={createForm.email}
                  onChange={(e) => setCreateForm((prev) => ({ ...prev, email: e.target.value }))}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-black transition-colors focus:border-[#962E32] focus:ring-1 focus:ring-[#962E32]"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Phone <span className="text-red-500">*</span>
                </label>
                <input
                  type="tel"
                  required
                  value={createForm.phone}
                  onChange={(e) => setCreateForm((prev) => ({ ...prev, phone: e.target.value }))}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-black transition-colors focus:border-[#962E32] focus:ring-1 focus:ring-[#962E32]"
                />
              </div>
            </div>
          </Section>
        </form>

        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
            htmlType="submit"
            form="create-account-form"
            loading={sendingOtp}
            disabled={sendingOtp || showOtpModal}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = BRAND_DARK; e.currentTarget.style.borderColor = BRAND_DARK; }}
            onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = BRAND; e.currentTarget.style.borderColor = BRAND; }}
          >
            Create
          </Button>
          <Button onClick={() => setShowCreateModal(false)} disabled={sendingOtp || showOtpModal}>Close</Button>
        </div>
      </Modal>

      <Modal
        open={showOtpModal}
        onCancel={() => !verifyingOtp && !sendingOtp && setShowOtpModal(false)}
        footer={null}
        width={440}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!verifyingOtp && !sendingOtp}
        keyboard={!verifyingOtp && !sendingOtp}
        zIndex={1100}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader
          title="Verify One-Time Password"
          onClose={() => !verifyingOtp && !sendingOtp && setShowOtpModal(false)}
        />

        <div className="px-6 py-5">
          <p className="text-sm text-black">
            OTP is sent to{' '}
            <span className="font-semibold">{otpMaskedPhone || createForm.phone}</span>.
            {createForm.email ? (
              <>
                {' '}If SMS does not arrive, check{' '}
                <span className="font-semibold">{createForm.email}</span>.
              </>
            ) : null}
          </p>
         
          <div className="mt-5 flex justify-center">
            <Input.OTP
              length={6}
              value={otpCode}
              onChange={setOtpCode}
              disabled={verifyingOtp}
              autoFocus
            />
          </div>
          <div className="mt-4 text-center">
            {otpResendIn > 0 ? (
              <span className="text-xs text-slate-500">Resend code in {otpResendIn}s</span>
            ) : (
              <button
                type="button"
                onClick={handleResendOtp}
                disabled={sendingOtp}
                className="text-xs font-semibold hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                style={{ color: BRAND }}
              >
                {sendingOtp ? 'Sending…' : 'Resend OTP'}
              </button>
            )}
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
          <Button
            type="primary"
          
            loading={verifyingOtp}
            disabled={verifyingOtp || sendingOtp || otpCode.length !== 6}
            onClick={handleVerifyOtpAndCreate}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = BRAND_DARK; e.currentTarget.style.borderColor = BRAND_DARK; }}
            onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = BRAND; e.currentTarget.style.borderColor = BRAND; }}
          >
            Verify 
          </Button>
          <Button onClick={() => setShowOtpModal(false)} disabled={verifyingOtp || sendingOtp}>
            Close
          </Button>
        </div>
      </Modal>

      <Modal
        open={showEditModal}
        onCancel={() => !updating && setShowEditModal(false)}
        footer={null}
        width={680}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!updating}
        keyboard={!updating}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title={loadingEditId ? 'Loading Account…' : 'Edit Account'} onClose={() => !updating && setShowEditModal(false)} />

              {loadingEditId ? (
          <CollectionLoader size={64} compact />
              ) : (
          <form id="edit-account-form" onSubmit={handleEditAccount} className="px-6 py-5">
            <Section title="Account Holder">
              <div className="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
                    <div>
                  <label className="block text-xs font-semibold tracking-[0.01em] mb-1.5" style={{ color: BRAND }}>NIDA</label>
                      <input
                        type="text"
                        value={editForm.nida}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, nida: e.target.value }))}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm text-black focus:ring-1 focus:ring-[#962E32] focus:border-[#962E32] transition-colors"
                      />
                    </div>
                    <div>
                  <label className="block text-xs font-semibold tracking-[0.01em] mb-1.5" style={{ color: BRAND }}>First Name <span className="text-red-500">*</span></label>
                      <input
                        type="text"
                        required
                        value={editForm.first_name}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, first_name: e.target.value }))}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm text-black focus:ring-1 focus:ring-[#962E32] focus:border-[#962E32] transition-colors"
                      />
                    </div>
                    <div>
                  <label className="block text-xs font-semibold tracking-[0.01em] mb-1.5" style={{ color: BRAND }}>Middle Name</label>
                      <input
                        type="text"
                        value={editForm.middle_name}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, middle_name: e.target.value }))}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm text-black focus:ring-1 focus:ring-[#962E32] focus:border-[#962E32] transition-colors"
                      />
                    </div>
                    <div>
                  <label className="block text-xs font-semibold tracking-[0.01em] mb-1.5" style={{ color: BRAND }}>Surname <span className="text-red-500">*</span></label>
                      <input
                        type="text"
                        required
                        value={editForm.surname}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, surname: e.target.value }))}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm text-black focus:ring-1 focus:ring-[#962E32] focus:border-[#962E32] transition-colors"
                      />
                    </div>
                  </div>
            </Section>

            <Section title="Contact">
              <div className="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
                    <div>
                  <label className="block text-xs font-semibold tracking-[0.01em] mb-1.5" style={{ color: BRAND }}>Email <span className="text-red-500">*</span></label>
                      <input
                        type="email"
                        required
                        value={editForm.email}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, email: e.target.value }))}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm text-black focus:ring-1 focus:ring-[#962E32] focus:border-[#962E32] transition-colors"
                      />
                    </div>
                    <div>
                  <label className="block text-xs font-semibold tracking-[0.01em] mb-1.5" style={{ color: BRAND }}>Phone <span className="text-red-500">*</span></label>
                      <input
                        type="tel"
                        required
                        value={editForm.phone}
                        onChange={(e) => setEditForm((prev) => ({ ...prev, phone: e.target.value }))}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm text-black focus:ring-1 focus:ring-[#962E32] focus:border-[#962E32] transition-colors"
                      />
                    </div>
                  </div>
            </Section>
                </form>
              )}

            {!loadingEditId && (
          <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
            <Button
              type="primary"
              htmlType="submit"
                  form="edit-account-form"
              icon={<Save size={14} />}
              loading={updating}
                  disabled={updating}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
              onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = BRAND_DARK; e.currentTarget.style.borderColor = BRAND_DARK; }}
              onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = BRAND; e.currentTarget.style.borderColor = BRAND; }}
            >
              Save
            </Button>
            <Button onClick={() => setShowEditModal(false)} disabled={updating}>Close</Button>
              </div>
            )}
      </Modal>

      <Modal
        open={showViewModal && !!viewAccount}
        onCancel={() => setShowViewModal(false)}
        footer={null}
        width={1100}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable
        className="brand-modal"
        styles={{ body: { padding: 0, maxHeight: '85vh', overflowY: 'auto' }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Account Details" onClose={() => setShowViewModal(false)} />

            {loadingView ? (
          <CollectionLoader size={64} compact />
        ) : viewAccount ? (
          <div className="px-6 py-5">
            {(() => {
              const fullName = buildFullName(viewAccount) || EMPTY_VALUE;
              const isActive =
                Number(viewAccount.status) === 1 ||
                viewAccount.status === '1' ||
                viewAccount.status === true;

              return (
                <div className="mb-6 overflow-hidden rounded-md border border-slate-200 bg-white">
                  <div className="grid grid-cols-1 divide-y divide-slate-200 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <div className="min-w-0 px-4 py-3">
                      <p className="m-0 text-[11px] font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>
                        Account Name
                      </p>
                      <p className="m-0 mt-1 truncate text-sm font-semibold text-black">
                        {fullName}
                      </p>
                    </div>
                    <div className="min-w-0 px-4 py-3">
                      <p className="m-0 text-[11px] font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>
                        Account Number
                      </p>
                      <p className="m-0 mt-1 truncate font-mono text-sm font-medium text-black">
                        {viewAccount.account_no || EMPTY_VALUE}
                      </p>
                    </div>
                    <div className="min-w-0 px-4 py-3">
                      <p className="m-0 text-[11px] font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>
                        Account Status
                      </p>
                      <div className="mt-1">
                        <Tag color={isActive ? 'green' : 'red'} className="!m-0 px-2.5 py-0.5 text-xs font-medium">
                          {isActive ? 'Active' : 'Inactive'}
                        </Tag>
                    </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 divide-y divide-slate-200 border-t border-slate-200 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <div className="min-w-0 px-4 py-3">
                      <p className="m-0 text-[11px] font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>
                        Account Phone
                      </p>
                      <p className="m-0 mt-1 truncate text-sm font-medium text-black">
                        {viewAccount.phone || EMPTY_VALUE}
                      </p>
                </div>
                    <div className="min-w-0 px-4 py-3">
                      <p className="m-0 text-[11px] font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>
                        Account Email
                      </p>
                      <p className="m-0 mt-1 truncate text-sm font-medium text-black" title={viewAccount.email || ''}>
                        {viewAccount.email || EMPTY_VALUE}
                      </p>
                      </div>
                    <div className="min-w-0 px-4 py-3">
                      <p className="m-0 text-[11px] font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>
                        Account Balance
                      </p>
                      <p className="m-0 mt-1 font-mono text-sm font-medium text-black">
                        {formatMoney(viewAccount.account_balance || 0)}
                      </p>
                      </div>
                    </div>
                    </div>
              );
            })()}

            <Tabs
              activeKey={activeTab}
              onChange={(key) => {
                setActiveTab(key);
                if (!viewAccount) return;
                if (key === 'vehicles' && viewVehicles.length === 0) {
                  fetchVehicles(viewAccount.id, vehicleFilters);
                } else if (key === 'passages') {
                  const plate = selectedPlateNo || viewVehicles[0]?.plate_no || null;
                  if (plate && !selectedPlateNo) setSelectedPlateNo(plate);
                  if (plate) fetchPassages(plate);
                } else if (key === 'prepayments') {
                  if (viewAccount.account_no) fetchPrepayments(viewAccount.account_no, 1, prepaymentPagination.per_page || 10);
                } else if (key === 'bundles') {
                  const plate = selectedPlateNo || viewVehicles[0]?.plate_no || null;
                  if (plate && !selectedPlateNo) setSelectedPlateNo(plate);
                  if (plate) fetchBundles(plate);
                }
              }}
              tabBarStyle={{ marginBottom: 16, borderBottom: '1px solid #e2e8f0' }}
              items={[
                {
                  key: 'vehicles',
                  label: (
                    <span className="inline-flex items-center gap-1.5 text-sm font-medium">
                      Vehicles
                      {viewVehicles.length > 0 && (
                        <Tag color="#962E32" className="!ml-1 !mr-0">{viewVehicles.length}</Tag>
                      )}
                              </span>
                  ),
                  children: renderVehiclesTab(),
                },
                {
                  key: 'passages',
                  label: (
                    <span className="text-sm font-medium">Passages</span>
                  ),
                  children: renderPassagesTab(),
                },
                {
                  key: 'prepayments',
                  label: (
                    <span className="text-sm font-medium">Prepayments</span>
                  ),
                  children: renderPrepaymentsTab(),
                },
                {
                  key: 'bundles',
                  label: (
                    <span className="text-sm font-medium">Bundles</span>
                  ),
                  children: renderBundlesTab(),
                },
              ]}
            />
                            </div>
        ) : null}

        {viewAccount && !loadingView && (
          <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
            <Button
              type="primary"
              icon={Number(viewAccount.status) === 1 ? <ToggleLeft size={14} /> : <ToggleRight size={14} />}
                onClick={() => toggleAccountStatus(viewAccount.id, viewAccount.status)}
              loading={loadingStatusId === viewAccount?.id}
                disabled={loadingStatusId === viewAccount?.id}
              style={
                Number(viewAccount.status) === 1
                  ? { backgroundColor: BRAND, borderColor: BRAND }
                  : { backgroundColor: '#16a34a', borderColor: '#16a34a' }
              }
            >
              {Number(viewAccount.status) === 1 ? 'Deactivate' : 'Activate'}
            </Button>
            <Button
              type="primary"
              icon={<Edit size={14} />}
              onClick={() => openEditModal(viewAccount)}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
              onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = BRAND_DARK; e.currentTarget.style.borderColor = BRAND_DARK; }}
              onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = BRAND; e.currentTarget.style.borderColor = BRAND; }}
            >
              Edit
            </Button>
            <Button onClick={() => setShowViewModal(false)}>Close</Button>
        </div>
      )}
      </Modal>

      {/* Associate vehicle (staff: plate_num + account_no) */}
      <Modal
        open={showAssociateVehicleModal}
        onCancel={() => !associateSubmitting && setShowAssociateVehicleModal(false)}
        footer={null}
        width={520}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!associateSubmitting}
        keyboard={!associateSubmitting}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader
          title="Associate vehicle"
          onClose={() => !associateSubmitting && setShowAssociateVehicleModal(false)}
        />
        <div className="px-6 py-6">
          <div className="mb-5 border-b border-slate-100 pb-4">
            <div className="text-[11px] font-semibold uppercase tracking-[0.08em]" style={{ color: BRAND }}>
              Account
            </div>
            <div className="mt-1 font-mono text-base font-medium text-black">{viewAccount?.account_no || EMPTY_VALUE}</div>
          </div>

          <label className="mb-2 block text-sm font-medium" style={{ color: BRAND }}>
            Search unassociated vehicles
          </label>
          <AutoComplete
            value={associateSearch}
            filterOption={false}
            size="large"
            options={unassociatedOptions.map((veh) => ({
              value: veh.plate_number,
              label: (
                <div className="flex items-center justify-between gap-3 py-1">
                  <span className="font-mono text-sm font-semibold text-black">{veh.plate_number}</span>
                  <span className="truncate text-xs text-slate-500">{veh.body_type || ''}</span>
                </div>
              ),
            }))}
            onSearch={onAssociateSearchChange}
            onSelect={(val) => {
              const found = unassociatedOptions.find(
                (x) => String(x.plate_number || '').trim().toUpperCase() === String(val || '').trim().toUpperCase()
              );
              setAssociateSelectedVehicle(found || null);
              setAssociateSearch(String(val || ''));
            }}
            onChange={(val) => {
              const s = String(val ?? '');
              setAssociateSearch(s);
              if (!s.trim()) {
                setAssociateSelectedVehicle(null);
                setUnassociatedOptions([]);
                setAssociateVehicleDetail(null);
                setAssociateVehicleDetailError(null);
              }
            }}
            notFoundContent={searchUnassociatedLoading ? <div className="py-3 text-center"><Spin size="small" /></div> : undefined}
            placeholder="Search by plate…"
            allowClear
            className="w-full [&_.ant-select-selector]:rounded-md"
            style={{ width: '100%' }}
          />

          {associateSelectedVehicle && (
            <div className="mt-5">
              <div className="mb-2 text-[11px] font-semibold uppercase tracking-[0.1em]" style={{ color: BRAND }}>
                Verify before associating
              </div>
              {associateVehicleDetailLoading ? (
                <CollectionLoader size={64} compact />
              ) : associateVehicleDetailError ? (
                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                  {associateVehicleDetailError}
                </div>
              ) : associateVehicleDetail ? (
                (() => {
                  const d = associateVehicleDetail;
                  const row = associateSelectedVehicle;
                  const imgSrc = resolveVehiclePreviewImageSrc(d, row);
                  const vid = d.vehicleID ?? d.vehicle_id ?? d.id ?? row.id;
                  const plateShow = (d.plate_no || d.plate_number || row.plate_number || '').trim() || EMPTY_VALUE;
                  const bodyShow =
                    d.body_type?.name ||
                    d.name ||
                    d.body_type_name ||
                    (typeof row.body_type === 'string' ? row.body_type : row.body_type?.name) ||
                    EMPTY_VALUE;
                  const statusShow =
                    d.status_label != null && d.status_label !== ''
                      ? d.status_label
                      : d.status != null && d.status !== ''
                        ? String(d.status)
                        : EMPTY_VALUE;
                  const exemptShow =
                    isPresent(d.exemption_label) ? d.exemption_label
                      : isPresent(d.exempted) ? String(d.exempted)
                      : EMPTY_VALUE;
                  const regRaw = d.registration_date || d.created_at;
                  const regShow = regRaw ? formatDateTime(regRaw) : EMPTY_VALUE;
                  const PreviewField = ({ label, value }) => (
                    <div className="min-w-0">
                      <div className="text-[10px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>
                        {label}
                      </div>
                      <div className="mt-0.5 truncate text-sm text-black" title={String(value)}>
                        {isPresent(value) ? value : <span className="text-slate-400">{EMPTY_VALUE}</span>}
                      </div>
                    </div>
                  );
                  return (
                    <div className="overflow-hidden rounded-lg border border-slate-200">
                      <div className="flex min-h-[160px] items-center justify-center border-b border-slate-200 bg-slate-100 px-4 py-4">
                        {imgSrc ? (
                          <img
                            src={imgSrc}
                            alt=""
                            className="max-h-44 w-auto max-w-full rounded object-contain shadow-sm"
                          />
                        ) : (
                          <div className="flex flex-col items-center gap-2 py-6 text-slate-400">
                            <ImageOff className="h-10 w-10" strokeWidth={1.25} />
                            <span className="text-xs">No image</span>
                          </div>
                        )}
                      </div>
                      <div className="grid grid-cols-2 gap-x-4 gap-y-3 bg-white p-4 sm:grid-cols-3">
                        <PreviewField label="Vehicle ID" value={vid != null ? String(vid) : ''} />
                        <PreviewField label="Plate number" value={plateShow} />
                        <PreviewField label="Body type" value={bodyShow} />
                        <PreviewField label="Status" value={statusShow} />
                        <PreviewField label="Exemption" value={exemptShow} />
                        <PreviewField label="Registration" value={regShow} />
                      </div>
                    </div>
                  );
                })()
              ) : null}
            </div>
          )}

          <div className="mt-6 flex items-center justify-end gap-2">
            <Button onClick={() => setShowAssociateVehicleModal(false)} disabled={associateSubmitting}>
              Close
            </Button>
            <Button
              type="primary"
              icon={<Link2 size={14} />}
              loading={associateSubmitting}
              disabled={
                !associateSelectedVehicle ||
                associateVehicleDetailLoading ||
                !!associateVehicleDetailError ||
                !associateVehicleDetail
              }
              onClick={handleAssociateVehicleSubmit}
              style={{ backgroundColor: BRAND, borderColor: BRAND }}
            >
              Associate
            </Button>
          </div>
        </div>
      </Modal>

      {/* Request Top-up modal */}
      <Modal
        open={showTopupModal}
        onCancel={() => !creatingTopup && setShowTopupModal(false)}
        footer={null}
        width={520}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!creatingTopup}
        keyboard={!creatingTopup}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Request Top-up" onClose={() => !creatingTopup && setShowTopupModal(false)} />
        <div className="px-6 py-5">
          <div className="mb-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Account No</div>
                <div className="font-mono text-sm text-black">{viewAccount?.account_no || EMPTY_VALUE}</div>
              </div>
              <div>
                <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Account Name</div>
                <div className="text-sm text-black truncate">{buildFullName(viewAccount) || EMPTY_VALUE}</div>
              </div>
            </div>
          </div>
          <Form form={topupForm} layout="vertical" onFinish={handleSubmitTopup} requiredMark={false}>
            <Form.Item
              name="bill_amount"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Amount (TZS)</span>}
              rules={[
                { required: true, message: 'Amount is required' },
                { type: 'number', min: 1, message: 'Amount must be at least 1' },
              ]}
            >
              <InputNumber
                size="large"
                min={1}
                step={1000}
                style={{ width: '100%' }}
                placeholder="Enter amount to top up"
                formatter={(value) => `${value ?? ''}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                parser={(value) => (value || '').replace(/[^\d.]/g, '')}
              />
            </Form.Item>
            <Form.Item
              name="tin"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>TIN (Optional)</span>}
            >
              <Input size="large" placeholder="Tax Identification Number (optional)" />
            </Form.Item>
            <div className="flex items-center justify-end gap-2 pt-2">
              <Button onClick={() => setShowTopupModal(false)} disabled={creatingTopup}>Close</Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={creatingTopup}
                icon={<Receipt size={14} />}
                style={{ backgroundColor: BRAND, borderColor: BRAND }}
              >
                Request Top-up
              </Button>
            </div>
          </Form>
        </div>
      </Modal>

      {/* Request Bundle modal */}
      <Modal
        open={showBundleModal}
        onCancel={() => !creatingBundle && setShowBundleModal(false)}
        footer={null}
        width={520}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!creatingBundle}
        keyboard={!creatingBundle}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Request Bundle" onClose={() => !creatingBundle && setShowBundleModal(false)} />
        <div className="px-6 py-5">
          {(() => {
            const bundles = Array.isArray(eligibleInfo?.bundles) ? eligibleInfo.bundles : [];
            const findBundle = (id) => bundles.find((b) => Number(b.bundle_id) === Number(id));
            const selectedBundle = findBundle(bundleTierId);
            const bodyTypeName = eligibleInfo?.body_type?.name || null;
            const hasOutstanding = !!eligibleInfo?.has_outstanding_bundle_bill;
            const outstanding = eligibleInfo?.outstanding_bill || null;
            const fmt = (n, currency = 'TZS') => `${currency} ${Number(n || 0).toLocaleString()}`;
            const submitDisabled =
              !bundlePlateNo ||
              loadingEligible ||
              !!eligibleError ||
              hasOutstanding ||
              !selectedBundle ||
              !selectedBundle.eligible;
            return (
              <>
                <div className="mb-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Account No</div>
                      <div className="font-mono text-sm text-black">{viewAccount?.account_no || EMPTY_VALUE}</div>
                    </div>
                    <div>
                      <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Body Type</div>
                      <div className="text-sm text-black truncate flex items-center gap-2">
                        {loadingEligible ? (
                          <span className="text-slate-500 text-xs">Loading…</span>
                        ) : (
                          bodyTypeName || EMPTY_VALUE
                        )}
                      </div>
                    </div>
                  </div>
                </div>

                {hasOutstanding && (
                  <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3">
                    <div className="flex items-start gap-2">
                      <AlertCircle size={16} className="mt-0.5 shrink-0 text-amber-600" />
                      <div className="flex-1">
                        <div className="text-sm font-semibold text-amber-900">Outstanding bundle bill</div>
                        <div className="mt-0.5 text-xs text-amber-800">
                          This vehicle already has an unpaid bundle bill. Pay or wait for it to expire before requesting a new one.
                        </div>
                        {outstanding?.control_number && (
                          <div className="mt-2 flex items-center gap-2">
                            <span className="text-[11px] font-semibold uppercase tracking-[0.06em] text-amber-900">Control No</span>
                            <span className="font-mono text-sm font-bold text-amber-900">{outstanding.control_number}</span>
                            <Button size="small" icon={<Copy size={12} />} onClick={() => copyToClipboard(outstanding.control_number)}>Copy</Button>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                )}

                {eligibleError && !loadingEligible && (
                  <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {eligibleError}
                  </div>
                )}

                <Form form={bundleForm} layout="vertical" onFinish={handleSubmitBundle} requiredMark={false}>
                  <Form.Item
                    name="plate_no"
                    label={<span className="text-sm font-medium" style={{ color: BRAND }}>Plate Number</span>}
                    rules={[{ required: true, message: 'Plate number is required' }]}
                  >
                    <Select
                      size="large"
                      placeholder="Select a vehicle"
                      options={(viewVehicles || []).map((v) => ({ value: v.plate_no, label: v.plate_no }))}
                      showSearch
                      optionFilterProp="label"
                    />
                  </Form.Item>
                  {loadingEligible ? (
                    <div className="mb-4">
                      <CollectionLoader size={64} compact />
                    </div>
                  ) : (
                    <>
                      <Form.Item
                        name="bundle_id"
                        label={<span className="text-sm font-medium" style={{ color: BRAND }}>Bundle Type</span>}
                        rules={[{ required: true, message: 'Bundle type is required' }]}
                        initialValue={3}
                      >
                        <Radio.Group className="w-full" buttonStyle="solid" disabled={!bundles.length}>
                          <div className="grid grid-cols-3 gap-2 w-full">
                            {(bundles.length
                              ? bundles
                              : [
                                  { bundle_id: 1, bundle_name: 'Daily', amount: null, eligible: false },
                                  { bundle_id: 2, bundle_name: 'Weekly', amount: null, eligible: false },
                                  { bundle_id: 3, bundle_name: 'Monthly', amount: null, eligible: false },
                                ]
                            ).map((b) => (
                              <Radio.Button
                                key={b.bundle_id}
                                value={b.bundle_id}
                                disabled={bundles.length > 0 && !b.eligible}
                                style={{ textAlign: 'center', width: '100%', height: 'auto', padding: '6px 0', lineHeight: 1.2 }}
                                title={!b.eligible && b.ineligible_reason ? b.ineligible_reason : undefined}
                              >
                                <div className="text-sm font-medium">
                                  {b.bundle_name?.replace(/\s*Bundle\s*$/i, '') || b.bundle_name || '—'}
                                </div>
                                <div className="text-[11px] opacity-90 font-mono">
                                  {b.amount != null ? fmt(b.amount, b.currency || 'TZS') : '—'}
                                </div>
                              </Radio.Button>
                            ))}
                          </div>
                        </Radio.Group>
                      </Form.Item>

                      <div
                        className="mb-4 rounded-md border px-4 py-3"
                        style={{
                          borderColor: selectedBundle?.eligible && selectedBundle?.amount != null ? BRAND : '#E2E8F0',
                          backgroundColor: selectedBundle?.eligible && selectedBundle?.amount != null ? '#FBF1F2' : '#F8FAFC',
                        }}
                      >
                        <div className="flex items-center justify-between gap-3">
                          <div>
                            <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>
                              {selectedBundle?.bundle_name || 'Bundle Amount'}
                            </div>
                            <div className="mt-0.5 text-xs text-slate-500">
                              {!bundlePlateNo
                                ? 'Select a vehicle to see the bundle amount.'
                                : eligibleError
                                ? 'Could not retrieve bundle eligibility.'
                                : !selectedBundle
                                ? 'No bundle option available for this vehicle.'
                                : !selectedBundle.eligible
                                ? selectedBundle.ineligible_reason || 'This bundle is not eligible for this vehicle.'
                                : selectedBundle.bundle_description || 'Final amount is confirmed by the server after submission.'}
                            </div>
                          </div>
                          <div className="text-right">
                            <div
                              className="font-mono text-lg font-bold"
                              style={{ color: selectedBundle?.eligible && selectedBundle?.amount != null ? BRAND : '#94A3B8' }}
                            >
                              {selectedBundle?.amount != null ? fmt(selectedBundle.amount, selectedBundle.currency || 'TZS') : '—'}
                            </div>
                          </div>
                        </div>
                      </div>
                    </>
                  )}

                  <div className="flex items-center justify-end gap-2 pt-1">
                    <Button onClick={() => setShowBundleModal(false)} disabled={creatingBundle}>Close</Button>
                    <Button
                      type="primary"
                      htmlType="submit"
                      loading={creatingBundle}
                      disabled={submitDisabled}
                      icon={<Package size={14} />}
                      style={
                        submitDisabled
                          ? undefined
                          : { backgroundColor: BRAND, borderColor: BRAND }
                      }
                    >
                      Request Bundle
                    </Button>
                  </div>
                </Form>
              </>
            );
          })()}
        </div>
      </Modal>

      <TopUpBillDetailsModal
        isOpen={!!selectedPrepaymentBill}
        bill={selectedPrepaymentBill}
        onClose={() => setSelectedPrepaymentBill(null)}
        onActionSuccess={() => {
          if (viewAccount?.account_no) {
            fetchPrepayments(
              viewAccount.account_no,
              prepaymentPagination.current_page || 1,
              prepaymentPagination.per_page || 10
            );
          }
        }}
      />

      <BundleBillDetailsModal
        isOpen={!!selectedBundleBill}
        bill={selectedBundleBill}
        onClose={() => setSelectedBundleBill(null)}
        onActionSuccess={() => {
          if (selectedPlateNo) {
            fetchBundles(selectedPlateNo);
          }
        }}
      />

      {/* Bill created success modal */}
      <Modal
        open={!!billSuccess}
        onCancel={() => setBillSuccess(null)}
        footer={null}
        width={520}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title={billSuccess?.title || 'Bill Created'} onClose={() => setBillSuccess(null)} />
        <div className="px-6 py-6">
          <div className="flex flex-col items-center text-center mb-5">
            <div
              className="flex h-14 w-14 items-center justify-center rounded-full"
              style={{ backgroundColor: '#ECFDF5', color: '#16A34A' }}
            >
              <CheckCircle2 size={28} />
            </div>
            <h3 className="mt-3 text-base font-semibold text-slate-900">
              {billSuccess?.type === 'topup' ? 'Top-up bill created successfully' : 'Bundle bill created successfully'}
            </h3>
            {billSuccess?.message && (
              <p className="mt-1 text-xs text-slate-500 max-w-sm">{billSuccess.message}</p>
            )}
          </div>

          <div className="rounded-md border border-slate-200 bg-slate-50 p-4 space-y-3">
            <div>
              <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Control Number</div>
              <div className="mt-1 flex items-center gap-2">
                <span className="font-mono text-lg font-bold text-black tracking-wider">
                  {billSuccess?.control_number || EMPTY_VALUE}
                </span>
                {billSuccess?.control_number && (
                  <Button
                    size="small"
                    icon={<Copy size={12} />}
                    onClick={() => copyToClipboard(billSuccess.control_number)}
                  >
                    Copy
                  </Button>
                )}
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3 pt-1 border-t border-slate-200">
              <div>
                <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Amount (TZS)</div>
                <div className="mt-0.5 font-mono text-sm text-black">
                  {billSuccess?.bill_amount != null ? `TZS ${Number(billSuccess.bill_amount).toLocaleString()}` : EMPTY_VALUE}
                </div>
              </div>
              <div>
                <div className="text-[11px] font-semibold uppercase tracking-[0.06em]" style={{ color: BRAND }}>Bill ID</div>
                <div className="mt-0.5 font-mono text-sm text-black">
                  {billSuccess?.bill_id || EMPTY_VALUE}
                </div>
              </div>
            </div>
          </div>

          <p className="mt-4 text-xs text-slate-500 text-center">
            Use the control number to complete payment via bank or mobile money.
          </p>

          <div className="flex items-center justify-end gap-2 pt-5">
            <Button onClick={() => setBillSuccess(null)}>Close</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}


