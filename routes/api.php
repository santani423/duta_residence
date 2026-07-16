<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BackPaymentController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ClusterController;
use App\Http\Controllers\Api\V1\ClusterRateScheduleController;
use App\Http\Controllers\Api\V1\CollectorVisitController;
use App\Http\Controllers\Api\V1\DiscountRuleController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\GuidedTourController;
use App\Http\Controllers\Api\V1\HelpSettingController;
use App\Http\Controllers\Api\V1\InstallmentController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\ManualBookController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentGatewayController;
use App\Http\Controllers\Api\V1\PaymentGatewaySettingController;
use App\Http\Controllers\Api\V1\PaymentPromiseController;
use App\Http\Controllers\Api\V1\PenaltyRuleController;
use App\Http\Controllers\Api\V1\PenaltyWaiverController;
use App\Http\Controllers\Api\V1\ReceivableController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ResidentController;
use App\Http\Controllers\Api\V1\ResidentDetailController;
use App\Http\Controllers\Api\V1\ResidentDocumentController;
use App\Http\Controllers\Api\V1\ResidentPortalController;
use App\Http\Controllers\Api\V1\ReversalController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UnitOccupantController;
use App\Http\Controllers\Api\V1\UnitVehicleController;
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
    Route::post('clusters', [ClusterController::class, 'store'])->middleware('permission:clusters.create');
    Route::get('clusters/{cluster}', [ClusterController::class, 'show'])->middleware('permission:clusters.view');
    Route::put('clusters/{cluster}', [ClusterController::class, 'update'])->middleware('permission:clusters.update-rate');
    Route::delete('clusters/{cluster}', [ClusterController::class, 'destroy'])->middleware('permission:clusters.delete');

    Route::get('clusters/{cluster}/rate-schedules', [ClusterRateScheduleController::class, 'index'])->middleware('permission:clusters.view');
    Route::post('clusters/{cluster}/rate-schedules', [ClusterRateScheduleController::class, 'store'])->middleware('permission:clusters.update-rate');
    Route::put('rate-schedules/{schedule}', [ClusterRateScheduleController::class, 'update'])->middleware('permission:clusters.update-rate');
    Route::delete('rate-schedules/{schedule}', [ClusterRateScheduleController::class, 'destroy'])->middleware('permission:clusters.update-rate');

    Route::get('residents', [ResidentController::class, 'index'])->middleware('permission:residents.view');
    Route::post('residents', [ResidentController::class, 'store'])->middleware('permission:residents.create');
    Route::get('residents/check-availability', [ResidentController::class, 'checkAvailability'])->middleware('permission:residents.create|residents.update');
    Route::get('residents/{resident}', [ResidentController::class, 'show'])->middleware('permission:residents.view');
    Route::put('residents/{resident}', [ResidentController::class, 'update'])->middleware('permission:residents.update');
    Route::delete('residents/{resident}', [ResidentController::class, 'destroy'])->middleware('permission:residents.delete');

    Route::get('residents/{resident}/summary', [ResidentDetailController::class, 'summary'])->middleware('permission:residents.view');
    Route::get('residents/{resident}/billings', [ResidentDetailController::class, 'billings'])->middleware('permission:residents.view');
    Route::get('residents/{resident}/transactions', [ResidentDetailController::class, 'transactions'])->middleware('permission:residents.view');
    Route::get('residents/{resident}/receipts', [ResidentDetailController::class, 'receipts'])->middleware('permission:residents.view');
    Route::post('residents/{resident}/receipts/{receipt}/send', [ResidentDetailController::class, 'sendReceipt'])->middleware('permission:residents.update');
    Route::get('residents/{resident}/complaints', [ResidentDetailController::class, 'complaints'])->middleware('permission:residents.view');
    Route::post('residents/{resident}/complaints', [ResidentDetailController::class, 'storeComplaint'])->middleware('permission:residents.update');
    Route::get('residents/{resident}/maintenance-requests', [ResidentDetailController::class, 'maintenanceRequests'])->middleware('permission:residents.view');
    Route::post('residents/{resident}/maintenance-requests', [ResidentDetailController::class, 'storeMaintenanceRequest'])->middleware('permission:residents.update');
    Route::get('residents/{resident}/notifications', [ResidentDetailController::class, 'notifications'])->middleware('permission:residents.view');
    Route::post('residents/{resident}/notifications/send', [ResidentDetailController::class, 'sendNotification'])->middleware('permission:residents.update');
    Route::get('residents/{resident}/activity', [ResidentDetailController::class, 'activity'])->middleware('permission:residents.view');

    Route::get('residents/{resident}/visits', [CollectorVisitController::class, 'index'])->middleware('permission:visits.view');
    Route::post('units/{unit}/visits', [CollectorVisitController::class, 'store'])->middleware('permission:visits.create');
    Route::put('visits/{visit}', [CollectorVisitController::class, 'update'])->middleware('permission:visits.update');

    Route::get('residents/{resident}/payment-promises', [PaymentPromiseController::class, 'index'])->middleware('permission:payment-promises.view');
    Route::post('units/{unit}/payment-promises', [PaymentPromiseController::class, 'store'])->middleware('permission:payment-promises.create');
    Route::put('payment-promises/{promise}', [PaymentPromiseController::class, 'update'])->middleware('permission:payment-promises.update');

    Route::get('residents/{resident}/resident-documents', [ResidentDocumentController::class, 'indexForResident'])->middleware('permission:resident-documents.view');
    Route::get('units/{unit}/resident-documents', [ResidentDocumentController::class, 'indexForUnit'])->middleware('permission:resident-documents.view');
    Route::post('residents/{resident}/resident-documents', [ResidentDocumentController::class, 'storeForResident'])->middleware('permission:resident-documents.create');
    Route::post('units/{unit}/resident-documents', [ResidentDocumentController::class, 'storeForUnit'])->middleware('permission:resident-documents.create');
    Route::put('resident-documents/{document}', [ResidentDocumentController::class, 'update'])->middleware('permission:resident-documents.update|resident-documents.verify');
    Route::delete('resident-documents/{document}', [ResidentDocumentController::class, 'destroy'])->middleware('permission:resident-documents.delete');

    Route::get('residents/{resident}/vehicles', [UnitVehicleController::class, 'index'])->middleware('permission:vehicles.view');
    Route::post('units/{unit}/vehicles', [UnitVehicleController::class, 'store'])->middleware('permission:vehicles.create');
    Route::put('vehicles/{vehicle}', [UnitVehicleController::class, 'update'])->middleware('permission:vehicles.update');
    Route::delete('vehicles/{vehicle}', [UnitVehicleController::class, 'destroy'])->middleware('permission:vehicles.delete');

    Route::get('residents/{resident}/occupants', [UnitOccupantController::class, 'index'])->middleware('permission:occupants.view');
    Route::post('units/{unit}/occupants', [UnitOccupantController::class, 'store'])->middleware('permission:occupants.create');
    Route::put('occupants/{occupant}', [UnitOccupantController::class, 'update'])->middleware('permission:occupants.update');
    Route::delete('occupants/{occupant}', [UnitOccupantController::class, 'destroy'])->middleware('permission:occupants.delete');

    Route::get('units', [UnitController::class, 'index'])->middleware('permission:units.view');
    Route::post('units', [UnitController::class, 'store'])->middleware('permission:units.create');
    Route::get('units/{unit}', [UnitController::class, 'show'])->middleware('permission:units.view');
    Route::put('units/{unit}', [UnitController::class, 'update'])->middleware('permission:units.update');
    Route::delete('units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:units.delete');
    Route::post('units/{unit}/convert-property', [UnitController::class, 'convertProperty'])->middleware('permission:units.convert-property');
    Route::get('units/{unit}/installments', [InstallmentController::class, 'byUnit'])->middleware('permission:installments.view');

    Route::post('billings/prepare-monthly', [BillingController::class, 'prepareMonthly'])->middleware('permission:billings.prepare');
    Route::post('billings/prepare-special', [BillingController::class, 'prepareSpecial'])->middleware('permission:billings.prepare-special');
    Route::post('billings/prepare-back', [BillingController::class, 'prepareBack'])->middleware('permission:billings.prepare-back');
    Route::get('billings', [BillingController::class, 'index'])->middleware('permission:billings.view');
    Route::get('billings/summary', [BillingController::class, 'summary'])->middleware('permission:billings.view');
    Route::get('billings/pending-approval', [BillingController::class, 'pendingApproval'])->middleware('permission:billings.approve');
    Route::post('billings/{billing}/approve', [BillingController::class, 'approve'])->middleware('permission:billings.approve');
    Route::post('billings/approve-batch', [BillingController::class, 'approveBatch'])->middleware('permission:billings.approve');
    Route::put('billings/{billing}/discount', [BillingController::class, 'updateDiscount'])->middleware('permission:billings.set-discount');

    Route::get('discount-rules', [DiscountRuleController::class, 'index'])->middleware('permission:discount-config.view');
    Route::post('discount-rules', [DiscountRuleController::class, 'store'])->middleware('permission:discount-config.create');
    Route::put('discount-rules/{discountRule}', [DiscountRuleController::class, 'update'])->middleware('permission:discount-config.update');
    Route::delete('discount-rules/{discountRule}', [DiscountRuleController::class, 'destroy'])->middleware('permission:discount-config.delete');

    Route::get('penalty-rules', [PenaltyRuleController::class, 'index'])->middleware('permission:penalty-config.view');
    Route::post('penalty-rules', [PenaltyRuleController::class, 'store'])->middleware('permission:penalty-config.create');
    Route::get('penalty-rules/history', [PenaltyRuleController::class, 'history'])->middleware('permission:penalty-config.view-history');
    Route::put('penalty-rules/{penaltyRule}', [PenaltyRuleController::class, 'update'])->middleware('permission:penalty-config.update');
    Route::delete('penalty-rules/{penaltyRule}', [PenaltyRuleController::class, 'destroy'])->middleware('permission:penalty-config.delete');
    Route::get('admin/settings/penalty', [PenaltyRuleController::class, 'showSetting'])->middleware('permission:penalty-config.view');
    Route::put('admin/settings/penalty', [PenaltyRuleController::class, 'updateSetting'])->middleware('permission:penalty-config.update');

    Route::get('penalty-waivers', [PenaltyWaiverController::class, 'index'])->middleware('permission:billings.view');
    Route::post('penalty-waivers', [PenaltyWaiverController::class, 'store'])->middleware('permission:billings.waive-penalty');
    Route::post('penalty-waivers/{penaltyWaiver}/approve', [PenaltyWaiverController::class, 'approve'])->middleware('permission:billings.approve-penalty-waiver');
    Route::post('penalty-waivers/{penaltyWaiver}/reject', [PenaltyWaiverController::class, 'reject'])->middleware('permission:billings.approve-penalty-waiver');

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

    Route::get('reports/dashboard', [ReportController::class, 'dashboard'])->middleware('permission:reports.view|residents.view|billings.view');
    Route::get('reports/monthly', [ReportController::class, 'monthly'])->middleware('permission:reports.view');
    Route::get('reports/daily-receipt', [ReportController::class, 'dailyReceipt'])->middleware('permission:reports.view');
    Route::get('reports/reconciliation', [ReportController::class, 'reconciliation'])->middleware('permission:reports.view');
    Route::get('reports/collector', [ReportController::class, 'collector'])->middleware('permission:reports.view');

    Route::get('documents/spt/{receipt}', [DocumentController::class, 'spt'])->middleware('permission:documents.generate');
    Route::get('documents/spk/{billing}', [DocumentController::class, 'spk'])->middleware('permission:documents.generate');
    Route::get('documents/billing-recap', [DocumentController::class, 'billingRecap'])->middleware('permission:documents.generate');
    Route::get('documents/resident-list', [DocumentController::class, 'residentList'])->middleware('permission:documents.generate');
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
    Route::get('lookup/roles', [LookupController::class, 'roles']);

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit-logs.view');

    Route::get('admin/settings/payment-gateway', [PaymentGatewaySettingController::class, 'show'])->middleware('permission:payment-settings.view');
    Route::put('admin/settings/payment-gateway', [PaymentGatewaySettingController::class, 'update'])->middleware('permission:payment-settings.update');
    Route::post('admin/settings/payment-gateway/test', [PaymentGatewaySettingController::class, 'test'])->middleware('permission:payment-settings.view');

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);

    // Manual Book & panduan interaktif - baca terbuka untuk semua role yang login,
    // konten difilter otomatis sesuai role di controller. Pengelolaan konten dibatasi permission.
    Route::get('manual-book/sections', [ManualBookController::class, 'index']);
    Route::get('manual-book/sections/{section}', [ManualBookController::class, 'show']);
    Route::post('manual-book/sections/{section}/read', [ManualBookController::class, 'markRead']);

    Route::get('admin/manual-book/sections', [ManualBookController::class, 'adminIndex'])->middleware('permission:manual-book.manage');
    Route::get('admin/manual-book/sections/{section}', [ManualBookController::class, 'adminShow'])->middleware('permission:manual-book.manage');
    Route::post('admin/manual-book/sections', [ManualBookController::class, 'store'])->middleware('permission:manual-book.manage');
    Route::put('admin/manual-book/sections/{section}', [ManualBookController::class, 'update'])->middleware('permission:manual-book.manage');
    Route::delete('admin/manual-book/sections/{section}', [ManualBookController::class, 'destroy'])->middleware('permission:manual-book.manage');
    Route::post('admin/manual-book/sections/reorder', [ManualBookController::class, 'reorder'])->middleware('permission:manual-book.manage');
    Route::post('admin/manual-book/sections/{section}/images', [ManualBookController::class, 'uploadImage'])->middleware('permission:manual-book.manage');
    Route::delete('admin/manual-book/sections/{section}/images/{image}', [ManualBookController::class, 'destroyImage'])->middleware('permission:manual-book.manage');

    Route::get('help-settings', [HelpSettingController::class, 'index']);
    Route::put('admin/help-settings', [HelpSettingController::class, 'upsert'])->middleware('permission:help-settings.manage');
    Route::delete('admin/help-settings/{setting}', [HelpSettingController::class, 'destroy'])->middleware('permission:help-settings.manage');

    Route::get('guided-tours', [GuidedTourController::class, 'index']);
    Route::post('guided-tours/{tour}/progress', [GuidedTourController::class, 'progress']);

    Route::get('admin/guided-tours', [GuidedTourController::class, 'adminIndex'])->middleware('permission:guided-tours.manage');
    Route::post('admin/guided-tours', [GuidedTourController::class, 'store'])->middleware('permission:guided-tours.manage');
    Route::put('admin/guided-tours/{tour}', [GuidedTourController::class, 'update'])->middleware('permission:guided-tours.manage');
    Route::delete('admin/guided-tours/{tour}', [GuidedTourController::class, 'destroy'])->middleware('permission:guided-tours.manage');
});

Route::middleware(['auth:sanctum', 'audit', 'role:customer'])->prefix('resident')->group(function () {
    Route::get('dashboard', [ResidentPortalController::class, 'dashboard']);
    Route::get('account', [ResidentPortalController::class, 'account']);
    Route::get('profile', [ResidentPortalController::class, 'profile']);
    Route::match(['put', 'post'], 'profile', [ResidentPortalController::class, 'updateProfile']);
    Route::get('property', [ResidentPortalController::class, 'property']);
    Route::get('bills', [ResidentPortalController::class, 'bills']);
    Route::get('invoices', [ResidentPortalController::class, 'bills']);
    Route::get('invoices/{billing}', [ResidentPortalController::class, 'invoice']);
    Route::get('invoices/{billing}/download', [ResidentPortalController::class, 'downloadInvoice']);
    Route::post('invoices/{billing}/payments', [ResidentPortalController::class, 'createPayment']);
    Route::get('payment-config', [ResidentPortalController::class, 'paymentConfig']);
    Route::get('payment-methods', [ResidentPortalController::class, 'paymentConfig']);
    Route::get('payments', [ResidentPortalController::class, 'payments']);
    Route::get('payments/{payment}', [ResidentPortalController::class, 'payment']);
    Route::get('payments/{payment}/status', [ResidentPortalController::class, 'paymentStatus']);
    Route::get('payments/{payment}/receipt/download', [ResidentPortalController::class, 'downloadReceipt']);
    Route::post('payments/{payment}/manual-proof', [ResidentPortalController::class, 'uploadManualProof']);
    Route::get('receipts/{receipt}/download', [ResidentPortalController::class, 'downloadCashReceipt']);
    Route::get('complaints', [ResidentPortalController::class, 'complaints']);
    Route::post('complaints', [ResidentPortalController::class, 'storeComplaint']);
    Route::get('complaints/{complaint}', [ResidentPortalController::class, 'complaint']);
    Route::match(['put', 'post'], 'complaints/{complaint}', [ResidentPortalController::class, 'updateComplaint']);
    Route::post('complaints/{complaint}/comments', [ResidentPortalController::class, 'addComplaintComment']);
    Route::post('complaints/{complaint}/close', [ResidentPortalController::class, 'closeComplaint']);
    Route::get('maintenance-requests', [ResidentPortalController::class, 'maintenanceRequests']);
    Route::post('maintenance-requests', [ResidentPortalController::class, 'storeMaintenanceRequest']);
    Route::get('maintenance-requests/{maintenance}', [ResidentPortalController::class, 'maintenanceRequest']);
    Route::match(['put', 'post'], 'maintenance-requests/{maintenance}', [ResidentPortalController::class, 'updateMaintenanceRequest']);
    Route::post('maintenance-requests/{maintenance}/rating', [ResidentPortalController::class, 'rateMaintenanceRequest']);
    Route::get('documents', [ResidentPortalController::class, 'documents']);
    Route::get('notifications', [ResidentPortalController::class, 'notifications']);
    Route::post('notifications/{notification}/read', [ResidentPortalController::class, 'readNotification']);
    Route::post('notifications/read-all', [ResidentPortalController::class, 'readAllNotifications']);
    Route::get('activity', [ResidentPortalController::class, 'activity']);
    Route::get('settings', [ResidentPortalController::class, 'settings']);
    Route::put('settings', [ResidentPortalController::class, 'updateSettings']);
});
