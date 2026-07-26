import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import AppShell from '../components/AppShell.jsx';
import { LoadingState } from '../components/common/ApiState.jsx';
import { useAuth } from '../state/AuthContext.jsx';
import LandingPage from '../pages/LandingPage.jsx';
import LoginPage from '../pages/LoginPage.jsx';
import PrivacyPolicyPage from '../pages/PrivacyPolicyPage.jsx';
import TermsPage from '../pages/TermsPage.jsx';
import ForgotPasswordPage from '../pages/auth/ForgotPasswordPage.jsx';
import ResetPasswordPage from '../pages/auth/ResetPasswordPage.jsx';
import UnauthorizedPage from '../pages/errors/UnauthorizedPage.jsx';
import ForbiddenPage from '../pages/errors/ForbiddenPage.jsx';
import SessionExpiredPage from '../pages/errors/SessionExpiredPage.jsx';
import NotFoundPage from '../pages/errors/NotFoundPage.jsx';

const DashboardPage = lazy(() => import('../pages/DashboardPage.jsx'));
const ClustersPage = lazy(() => import('../pages/ClustersPage.jsx'));
const ClusterDetailPage = lazy(() => import('../pages/ClusterDetailPage.jsx'));
const ClusterMapPage = lazy(() => import('../pages/cluster-map/ClusterMapPage.jsx'));
const ResidentsPage = lazy(() => import('../pages/ResidentsPage.jsx'));
const ResidentDetailPage = lazy(() => import('../pages/ResidentDetailPage.jsx'));
const UnitsPage = lazy(() => import('../pages/UnitsPage.jsx'));
const BillingsPage = lazy(() => import('../pages/BillingsPage.jsx'));
const PaymentsPage = lazy(() => import('../pages/PaymentsPage.jsx'));
const InstallmentsPage = lazy(() => import('../pages/InstallmentsPage.jsx'));
const ReversalsPage = lazy(() => import('../pages/ReversalsPage.jsx'));
const ReceivablesPage = lazy(() => import('../pages/ReceivablesPage.jsx'));
const ReportsPage = lazy(() => import('../pages/ReportsPage.jsx'));
const DocumentsPage = lazy(() => import('../pages/DocumentsPage.jsx'));
const UsersPage = lazy(() => import('../pages/UsersPage.jsx'));
const UserDetailPage = lazy(() => import('../pages/UserDetailPage.jsx'));
const AuditLogsPage = lazy(() => import('../pages/AuditLogsPage.jsx'));
const NotificationsPage = lazy(() => import('../pages/NotificationsPage.jsx'));
const ProfilePage = lazy(() => import('../pages/auth/ProfilePage.jsx'));
const ChangePasswordPage = lazy(() => import('../pages/auth/ChangePasswordPage.jsx'));
const AdminPaymentGatewaySettingsPage = lazy(() => import('../pages/AdminPaymentGatewaySettingsPage.jsx'));
const ResidentPortalPage = lazy(() => import('../pages/resident/ResidentPortalPage.jsx'));
const ManualBookPage = lazy(() => import('../pages/ManualBookPage.jsx'));
const AdminManualBookPage = lazy(() => import('../pages/AdminManualBookPage.jsx'));
const AdminHelpSettingsPage = lazy(() => import('../pages/AdminHelpSettingsPage.jsx'));
const CmsHeroSlidesPage = lazy(() => import('../pages/cms/CmsHeroSlidesPage.jsx'));
const CmsServicesPage = lazy(() => import('../pages/cms/CmsServicesPage.jsx'));
const CmsFeaturesPage = lazy(() => import('../pages/cms/CmsFeaturesPage.jsx'));
const CmsStatisticsPage = lazy(() => import('../pages/cms/CmsStatisticsPage.jsx'));
const CmsEventsPage = lazy(() => import('../pages/cms/CmsEventsPage.jsx'));
const CmsArticlesPage = lazy(() => import('../pages/cms/CmsArticlesPage.jsx'));
const CmsTestimonialsPage = lazy(() => import('../pages/cms/CmsTestimonialsPage.jsx'));
const CmsPartnersPage = lazy(() => import('../pages/cms/CmsPartnersPage.jsx'));
const CmsGalleryAlbumsPage = lazy(() => import('../pages/cms/CmsGalleryAlbumsPage.jsx'));
const CmsFaqsPage = lazy(() => import('../pages/cms/CmsFaqsPage.jsx'));
const CmsCategoriesPage = lazy(() => import('../pages/cms/CmsCategoriesPage.jsx'));
const CmsNavMenuItemsPage = lazy(() => import('../pages/cms/CmsNavMenuItemsPage.jsx'));
const CmsSocialLinksPage = lazy(() => import('../pages/cms/CmsSocialLinksPage.jsx'));
const CmsMediaLibraryPage = lazy(() => import('../pages/cms/CmsMediaLibraryPage.jsx'));
const CmsHeaderSettingsPage = lazy(() => import('../pages/cms/CmsHeaderSettingsPage.jsx'));
const CmsAboutSettingsPage = lazy(() => import('../pages/cms/CmsAboutSettingsPage.jsx'));
const CmsContactSettingsPage = lazy(() => import('../pages/cms/CmsContactSettingsPage.jsx'));
const CmsFooterSettingsPage = lazy(() => import('../pages/cms/CmsFooterSettingsPage.jsx'));
const CmsSeoSettingsPage = lazy(() => import('../pages/cms/CmsSeoSettingsPage.jsx'));
const CmsGeneralSettingsPage = lazy(() => import('../pages/cms/CmsGeneralSettingsPage.jsx'));
const CollectorsPage = lazy(() => import('../pages/CollectorsPage.jsx'));
const CollectorDetailPage = lazy(() => import('../pages/CollectorDetailPage.jsx'));
const CollectorAssignmentsPage = lazy(() => import('../pages/CollectorAssignmentsPage.jsx'));
const CollectorTargetsPage = lazy(() => import('../pages/CollectorTargetsPage.jsx'));
const CollectorPerformancePage = lazy(() => import('../pages/CollectorPerformancePage.jsx'));
const CollectorLiveMapPage = lazy(() => import('../pages/CollectorLiveMapPage.jsx'));
const CollectionLettersPage = lazy(() => import('../pages/CollectionLettersPage.jsx'));
const CollectorRouteMapPage = lazy(() => import('../pages/collector/CollectorRouteMapPage.jsx'));
const CollectorRemindersPage = lazy(() => import('../pages/collector/CollectorRemindersPage.jsx'));

function Protected({ children, permissions = [], roles = [] }) {
  const { token, canAny, hasRole } = useAuth();
  const location = useLocation();

  if (!token) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  if (permissions.length && !canAny(permissions)) {
    return <Navigate to="/403" replace />;
  }

  if (roles.length && !roles.some((role) => hasRole(role))) {
    return <Navigate to="/403" replace />;
  }

  return children;
}

function LazyPage({ children }) {
  return <Suspense fallback={<LoadingState rows={8} />}>{children}</Suspense>;
}

// Renders the public landing page for guests visiting the bare root URL,
// while preserving the existing login-redirect behaviour (with resume
// state) for every other in-app path so authenticated navigation is
// untouched.
function RootGate() {
  const { token } = useAuth();
  const location = useLocation();

  if (!token) {
    if (location.pathname === '/') {
      return <LandingPage />;
    }
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return <AppShell />;
}

export default function AppRoutes() {
  const { hasRole } = useAuth();

  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/privacy-policy" element={<PrivacyPolicyPage />} />
      <Route path="/terms" element={<TermsPage />} />
      <Route path="/401" element={<UnauthorizedPage />} />
      <Route path="/403" element={<ForbiddenPage />} />
      <Route path="/419" element={<SessionExpiredPage />} />
      <Route path="/" element={<RootGate />}>
        <Route index element={hasRole('customer') ? <Navigate to="/resident/dashboard" replace /> : <Protected permissions={['reports.view', 'residents.view', 'billings.view']}><LazyPage><DashboardPage /></LazyPage></Protected>} />
        <Route path="clusters" element={<Protected permissions={['clusters.view']}><LazyPage><ClustersPage /></LazyPage></Protected>} />
        <Route path="clusters/:id" element={<Protected permissions={['clusters.view']}><LazyPage><ClusterDetailPage /></LazyPage></Protected>} />
        <Route path="clusters/:id/map" element={<Protected permissions={['cluster-maps.view']}><LazyPage><ClusterMapPage /></LazyPage></Protected>} />
        <Route path="residents" element={<Protected permissions={['residents.view']}><LazyPage><ResidentsPage /></LazyPage></Protected>} />
        <Route path="residents/:id" element={<Protected permissions={['residents.view']}><LazyPage><ResidentDetailPage /></LazyPage></Protected>} />
        <Route path="units" element={<Protected permissions={['units.view']}><LazyPage><UnitsPage /></LazyPage></Protected>} />
        <Route path="billings" element={<Protected permissions={['billings.view']}><LazyPage><BillingsPage /></LazyPage></Protected>} />
        <Route path="payments" element={<Protected permissions={['payments.view']}><LazyPage><PaymentsPage /></LazyPage></Protected>} />
        <Route path="installments" element={<Protected permissions={['installments.view']}><LazyPage><InstallmentsPage /></LazyPage></Protected>} />
        <Route path="reversals" element={<Protected permissions={['reversals.view']}><LazyPage><ReversalsPage /></LazyPage></Protected>} />
        <Route path="receivables" element={<Protected permissions={['reports.view']}><LazyPage><ReceivablesPage /></LazyPage></Protected>} />
        <Route path="reports" element={<Protected permissions={['reports.view']}><LazyPage><ReportsPage /></LazyPage></Protected>} />
        <Route path="documents" element={<Protected permissions={['documents.generate']}><LazyPage><DocumentsPage /></LazyPage></Protected>} />
        <Route path="users" element={<Protected permissions={['users.view']}><LazyPage><UsersPage /></LazyPage></Protected>} />
        <Route path="users/:id" element={<Protected permissions={['users.view']}><LazyPage><UserDetailPage /></LazyPage></Protected>} />
        <Route path="audit-logs" element={<Protected permissions={['audit-logs.view']}><LazyPage><AuditLogsPage /></LazyPage></Protected>} />
        <Route path="notifications" element={<LazyPage><NotificationsPage /></LazyPage>} />
        <Route path="profile" element={<LazyPage><ProfilePage /></LazyPage>} />
        <Route path="change-password" element={<LazyPage><ChangePasswordPage /></LazyPage>} />
        <Route path="admin/settings/payment-gateway" element={<Protected permissions={['payment-settings.view']}><LazyPage><AdminPaymentGatewaySettingsPage /></LazyPage></Protected>} />
        <Route path="manual-book" element={<LazyPage><ManualBookPage /></LazyPage>} />
        <Route path="manual-book/:module" element={<LazyPage><ManualBookPage /></LazyPage>} />
        <Route path="manual-book/:module/:section" element={<LazyPage><ManualBookPage /></LazyPage>} />
        <Route path="admin/manual-book" element={<Protected permissions={['manual-book.manage']}><LazyPage><AdminManualBookPage /></LazyPage></Protected>} />
        <Route path="admin/help-settings" element={<Protected permissions={['help-settings.manage']}><LazyPage><AdminHelpSettingsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/hero-slides" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsHeroSlidesPage /></LazyPage></Protected>} />
        <Route path="admin/cms/services" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsServicesPage /></LazyPage></Protected>} />
        <Route path="admin/cms/features" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsFeaturesPage /></LazyPage></Protected>} />
        <Route path="admin/cms/statistics" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsStatisticsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/events" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsEventsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/articles" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsArticlesPage /></LazyPage></Protected>} />
        <Route path="admin/cms/testimonials" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsTestimonialsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/partners" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsPartnersPage /></LazyPage></Protected>} />
        <Route path="admin/cms/gallery-albums" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsGalleryAlbumsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/faqs" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsFaqsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/categories" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsCategoriesPage /></LazyPage></Protected>} />
        <Route path="admin/cms/nav-menu-items" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsNavMenuItemsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/social-links" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsSocialLinksPage /></LazyPage></Protected>} />
        <Route path="admin/cms/media" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsMediaLibraryPage /></LazyPage></Protected>} />
        <Route path="admin/cms/settings/header" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsHeaderSettingsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/settings/about" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsAboutSettingsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/settings/contact" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsContactSettingsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/settings/footer" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsFooterSettingsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/settings/seo" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsSeoSettingsPage /></LazyPage></Protected>} />
        <Route path="admin/cms/settings/general" element={<Protected permissions={['landing-cms.manage']}><LazyPage><CmsGeneralSettingsPage /></LazyPage></Protected>} />
        <Route path="admin/collectors/list" element={<Protected permissions={['collector.read']}><LazyPage><CollectorsPage /></LazyPage></Protected>} />
        <Route path="admin/collectors/list/:id" element={<Protected permissions={['collector.detail']}><LazyPage><CollectorDetailPage /></LazyPage></Protected>} />
        <Route path="admin/collectors/assignments" element={<Protected permissions={['collector-assignments.view']}><LazyPage><CollectorAssignmentsPage /></LazyPage></Protected>} />
        <Route path="admin/collectors/targets" element={<Protected permissions={['collector-targets.view']}><LazyPage><CollectorTargetsPage /></LazyPage></Protected>} />
        <Route path="admin/collectors/performance" element={<Protected permissions={['collector-performance.view']}><LazyPage><CollectorPerformancePage /></LazyPage></Protected>} />
        <Route path="admin/collectors/live-map" element={<Protected permissions={['collector-locations.view']}><LazyPage><CollectorLiveMapPage /></LazyPage></Protected>} />
        <Route path="admin/collectors/letters" element={<Protected permissions={['collection-letters.view']}><LazyPage><CollectionLettersPage /></LazyPage></Protected>} />
        <Route path="collector/performance" element={<Protected roles={['collector']}><LazyPage><CollectorPerformancePage /></LazyPage></Protected>} />
        <Route path="collector/route" element={<Protected roles={['collector']}><LazyPage><CollectorRouteMapPage /></LazyPage></Protected>} />
        <Route path="collector/reminders" element={<Protected roles={['collector']}><LazyPage><CollectorRemindersPage /></LazyPage></Protected>} />
        <Route path="collector/letters" element={<Protected roles={['collector']}><LazyPage><CollectionLettersPage /></LazyPage></Protected>} />
        <Route path="resident/dashboard" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="dashboard" /></LazyPage></Protected>} />
        <Route path="resident/account" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="account" /></LazyPage></Protected>} />
        <Route path="resident/profile" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="profile" /></LazyPage></Protected>} />
        <Route path="resident/property" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="property" /></LazyPage></Protected>} />
        <Route path="resident/bills" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="bills" /></LazyPage></Protected>} />
        <Route path="resident/bills/:invoiceId" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="invoice-detail" /></LazyPage></Protected>} />
        <Route path="resident/invoices" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="invoices" /></LazyPage></Protected>} />
        <Route path="resident/invoices/:invoiceId" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="invoice-detail" /></LazyPage></Protected>} />
        <Route path="resident/payments" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="payments" /></LazyPage></Protected>} />
        <Route path="resident/payments/:paymentId" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="payment-detail" /></LazyPage></Protected>} />
        <Route path="resident/payment-methods" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="payment-methods" /></LazyPage></Protected>} />
        <Route path="resident/complaints" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="complaints" /></LazyPage></Protected>} />
        <Route path="resident/documents" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="documents" /></LazyPage></Protected>} />
        <Route path="resident/notifications" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="notifications" /></LazyPage></Protected>} />
        <Route path="resident/activity" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="activity" /></LazyPage></Protected>} />
        <Route path="resident/settings" element={<Protected roles={['customer']}><LazyPage><ResidentPortalPage page="settings" /></LazyPage></Protected>} />
      </Route>
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
