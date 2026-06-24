import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import AppShell from '../components/AppShell.jsx';
import { LoadingState } from '../components/common/ApiState.jsx';
import { useAuth } from '../state/AuthContext.jsx';
import LoginPage from '../pages/LoginPage.jsx';
import ForgotPasswordPage from '../pages/auth/ForgotPasswordPage.jsx';
import ResetPasswordPage from '../pages/auth/ResetPasswordPage.jsx';
import UnauthorizedPage from '../pages/errors/UnauthorizedPage.jsx';
import ForbiddenPage from '../pages/errors/ForbiddenPage.jsx';
import SessionExpiredPage from '../pages/errors/SessionExpiredPage.jsx';
import NotFoundPage from '../pages/errors/NotFoundPage.jsx';

const DashboardPage = lazy(() => import('../pages/DashboardPage.jsx'));
const ClustersPage = lazy(() => import('../pages/ClustersPage.jsx'));
const CustomersPage = lazy(() => import('../pages/CustomersPage.jsx'));
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

function Protected({ children, permissions = [] }) {
  const { token, canAny } = useAuth();
  const location = useLocation();

  if (!token) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  if (permissions.length && !canAny(permissions)) {
    return <Navigate to="/403" replace />;
  }

  return children;
}

function LazyPage({ children }) {
  return <Suspense fallback={<LoadingState rows={8} />}>{children}</Suspense>;
}

export default function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/401" element={<UnauthorizedPage />} />
      <Route path="/403" element={<ForbiddenPage />} />
      <Route path="/419" element={<SessionExpiredPage />} />
      <Route
        path="/"
        element={
          <Protected>
            <AppShell />
          </Protected>
        }
      >
        <Route index element={<LazyPage><DashboardPage /></LazyPage>} />
        <Route path="clusters" element={<Protected permissions={['clusters.view']}><LazyPage><ClustersPage /></LazyPage></Protected>} />
        <Route path="customers" element={<Protected permissions={['customers.view']}><LazyPage><CustomersPage /></LazyPage></Protected>} />
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
      </Route>
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
