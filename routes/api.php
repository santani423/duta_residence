<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BackPaymentController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ClusterController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerPortalController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\InstallmentController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentGatewayController;
use App\Http\Controllers\Api\V1\PaymentGatewaySettingController;
use App\Http\Controllers\Api\V1\ReceivableController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReversalController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::post('payments/webhooks/xendit', [PaymentGatewayController::class, 'xenditWebhook']);
Route::post('payments/webhooks/midtrans', [PaymentGatewayController::class, 'midtransWebhook']);

Route::middleware(['auth:sanctum', 'audit'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/change-password', [AuthController::class, 'changePassword']);

    Route::get('clusters', [ClusterController::class, 'index'])->middleware('permission:clusters.view');
    Route::get('clusters/{cluster}', [ClusterController::class, 'show'])->middleware('permission:clusters.view');
    Route::put('clusters/{cluster}', [ClusterController::class, 'update'])->middleware('permission:clusters.update-rate');

    Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
    Route::post('customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');
    Route::post('customers/{customer}/convert-property', [CustomerController::class, 'convertProperty'])->middleware('permission:customers.convert-property');
    Route::get('customers/{customer}/installments', [InstallmentController::class, 'byCustomer'])->middleware('permission:installments.view');

    Route::post('billings/prepare-monthly', [BillingController::class, 'prepareMonthly'])->middleware('permission:billings.prepare');
    Route::post('billings/prepare-special', [BillingController::class, 'prepareSpecial'])->middleware('permission:billings.prepare-special');
    Route::post('billings/prepare-back', [BillingController::class, 'prepareBack'])->middleware('permission:billings.prepare-back');
    Route::get('billings', [BillingController::class, 'index'])->middleware('permission:billings.view');
    Route::get('billings/pending-approval', [BillingController::class, 'pendingApproval'])->middleware('permission:billings.approve');
    Route::post('billings/{billing}/approve', [BillingController::class, 'approve'])->middleware('permission:billings.approve');
    Route::post('billings/approve-batch', [BillingController::class, 'approveBatch'])->middleware('permission:billings.approve');

    Route::get('payments/search', [PaymentController::class, 'search'])->middleware('permission:payments.view');
    Route::post('payments/preview', [PaymentController::class, 'preview'])->middleware('permission:payments.view');
    Route::post('payments/process', [PaymentController::class, 'process'])->middleware('permission:payments.process');
    Route::get('payments/receipts', [PaymentController::class, 'receipts'])->middleware('permission:payments.view');
    Route::get('payments/receipts/{receipt}', [PaymentController::class, 'showReceipt'])->middleware('permission:payments.view');
    Route::get('payments/gateway/config', [PaymentGatewayController::class, 'config'])->middleware('permission:payments.view');
    Route::get('payments/gateway/transactions', [PaymentGatewayController::class, 'index'])->middleware('permission:payments.view');
    Route::get('payments/gateway/transactions/{transaction}', [PaymentGatewayController::class, 'show'])->middleware('permission:payments.view');
    Route::post('payments/gateway', [PaymentGatewayController::class, 'create'])->middleware('permission:payments.create');
    Route::post('payments/{transaction}/manual-proof', [PaymentGatewayController::class, 'uploadManualProof'])->middleware('permission:payments.create');
    Route::post('payments/{transaction}/verify', [PaymentGatewayController::class, 'verifyManual'])->middleware('permission:payments.verify');
    Route::post('payments/{transaction}/reject', [PaymentGatewayController::class, 'rejectManual'])->middleware('permission:payments.verify');

    Route::post('installments', [InstallmentController::class, 'store'])->middleware('permission:installments.create');
    Route::get('installments', [InstallmentController::class, 'index'])->middleware('permission:installments.view');
    Route::post('back-payments/process', [BackPaymentController::class, 'process'])->middleware('permission:payments.process');

    Route::post('reversals', [ReversalController::class, 'store'])->middleware('permission:reversals.submit');
    Route::get('reversals', [ReversalController::class, 'index'])->middleware('permission:reversals.view');
    Route::post('reversals/{reversal}/approve', [ReversalController::class, 'approve'])->middleware('permission:reversals.approve');
    Route::post('reversals/{reversal}/reject', [ReversalController::class, 'reject'])->middleware('permission:reversals.approve');

    Route::get('receivables', [ReceivableController::class, 'index'])->middleware('permission:reports.view');
    Route::get('receivables/aging', [ReceivableController::class, 'aging'])->middleware('permission:reports.view');

    Route::get('reports/dashboard', [ReportController::class, 'dashboard'])->middleware('permission:reports.view|customers.view|billings.view');
    Route::get('reports/monthly', [ReportController::class, 'monthly'])->middleware('permission:reports.view');
    Route::get('reports/daily-receipt', [ReportController::class, 'dailyReceipt'])->middleware('permission:reports.view');
    Route::get('reports/reconciliation', [ReportController::class, 'reconciliation'])->middleware('permission:reports.view');
    Route::get('reports/collector', [ReportController::class, 'collector'])->middleware('permission:reports.view');

    Route::get('documents/spt/{receipt}', [DocumentController::class, 'spt'])->middleware('permission:documents.generate');
    Route::get('documents/spk/{billing}', [DocumentController::class, 'spk'])->middleware('permission:documents.generate');
    Route::get('documents/billing-recap', [DocumentController::class, 'billingRecap'])->middleware('permission:documents.generate');
    Route::get('documents/customer-list', [DocumentController::class, 'customerList'])->middleware('permission:documents.generate');
    Route::get('documents/cluster-recap', [DocumentController::class, 'clusterRecap'])->middleware('permission:documents.generate');

    Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.reset-password');
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:users.activate');
    Route::get('users/{user}/activities', [UserController::class, 'activities'])->middleware('permission:audit-logs.view');

    Route::get('lookup/regencies', [LookupController::class, 'regencies']);
    Route::get('lookup/districts', [LookupController::class, 'districts']);

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit-logs.view');

    Route::get('admin/settings/payment-gateway', [PaymentGatewaySettingController::class, 'show'])->middleware('permission:payment-settings.view');
    Route::put('admin/settings/payment-gateway', [PaymentGatewaySettingController::class, 'update'])->middleware('permission:payment-settings.update');
    Route::post('admin/settings/payment-gateway/test', [PaymentGatewaySettingController::class, 'test'])->middleware('permission:payment-settings.view');

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
});

Route::middleware(['auth:sanctum', 'audit', 'role:customer'])->prefix('customer')->group(function () {
    Route::get('dashboard', [CustomerPortalController::class, 'dashboard']);
    Route::get('account', [CustomerPortalController::class, 'account']);
    Route::get('profile', [CustomerPortalController::class, 'profile']);
    Route::match(['put', 'post'], 'profile', [CustomerPortalController::class, 'updateProfile']);
    Route::get('property', [CustomerPortalController::class, 'property']);
    Route::get('bills', [CustomerPortalController::class, 'bills']);
    Route::get('invoices', [CustomerPortalController::class, 'bills']);
    Route::get('invoices/{billing}', [CustomerPortalController::class, 'invoice']);
    Route::get('invoices/{billing}/download', [CustomerPortalController::class, 'downloadInvoice']);
    Route::post('invoices/{billing}/payments', [CustomerPortalController::class, 'createPayment']);
    Route::get('payment-config', [CustomerPortalController::class, 'paymentConfig']);
    Route::get('payment-methods', [CustomerPortalController::class, 'paymentConfig']);
    Route::get('payments', [CustomerPortalController::class, 'payments']);
    Route::get('payments/{payment}', [CustomerPortalController::class, 'payment']);
    Route::get('payments/{payment}/status', [CustomerPortalController::class, 'paymentStatus']);
    Route::get('payments/{payment}/receipt/download', [CustomerPortalController::class, 'downloadReceipt']);
    Route::post('payments/{payment}/manual-proof', [CustomerPortalController::class, 'uploadManualProof']);
    Route::get('receipts/{receipt}/download', [CustomerPortalController::class, 'downloadCashReceipt']);
    Route::get('complaints', [CustomerPortalController::class, 'complaints']);
    Route::post('complaints', [CustomerPortalController::class, 'storeComplaint']);
    Route::get('complaints/{complaint}', [CustomerPortalController::class, 'complaint']);
    Route::match(['put', 'post'], 'complaints/{complaint}', [CustomerPortalController::class, 'updateComplaint']);
    Route::post('complaints/{complaint}/comments', [CustomerPortalController::class, 'addComplaintComment']);
    Route::post('complaints/{complaint}/close', [CustomerPortalController::class, 'closeComplaint']);
    Route::get('maintenance-requests', [CustomerPortalController::class, 'maintenanceRequests']);
    Route::post('maintenance-requests', [CustomerPortalController::class, 'storeMaintenanceRequest']);
    Route::get('maintenance-requests/{maintenance}', [CustomerPortalController::class, 'maintenanceRequest']);
    Route::match(['put', 'post'], 'maintenance-requests/{maintenance}', [CustomerPortalController::class, 'updateMaintenanceRequest']);
    Route::post('maintenance-requests/{maintenance}/rating', [CustomerPortalController::class, 'rateMaintenanceRequest']);
    Route::get('documents', [CustomerPortalController::class, 'documents']);
    Route::get('notifications', [CustomerPortalController::class, 'notifications']);
    Route::post('notifications/{notification}/read', [CustomerPortalController::class, 'readNotification']);
    Route::post('notifications/read-all', [CustomerPortalController::class, 'readAllNotifications']);
    Route::get('activity', [CustomerPortalController::class, 'activity']);
    Route::get('settings', [CustomerPortalController::class, 'settings']);
    Route::put('settings', [CustomerPortalController::class, 'updateSettings']);
});
