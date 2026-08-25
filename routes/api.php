<?php

use App\Http\Controllers\Api\V1\ApprovalRequestController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BackPaymentController;
use App\Http\Controllers\Api\V1\BalanceController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BroadcastController;
use App\Http\Controllers\Api\V1\ClusterController;
use App\Http\Controllers\Api\V1\ClusterMapComponentTypeController;
use App\Http\Controllers\Api\V1\ClusterMapController;
use App\Http\Controllers\Api\V1\ClusterRateScheduleController;
use App\Http\Controllers\Api\V1\CollectionLetterController;
use App\Http\Controllers\Api\V1\CollectorAssignmentController;
use App\Http\Controllers\Api\V1\CollectorController;
use App\Http\Controllers\Api\V1\CollectorLocationController;
use App\Http\Controllers\Api\V1\CollectorPerformanceController;
use App\Http\Controllers\Api\V1\CollectorReminderController;
use App\Http\Controllers\Api\V1\CollectorTargetController;
use App\Http\Controllers\Api\V1\CollectorVisitController;
use App\Http\Controllers\Api\V1\CollectorVisitEvidenceController;
use App\Http\Controllers\Api\V1\DiscountRuleController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EmergencyAlertController;
use App\Http\Controllers\Api\V1\Cms\HeroSlideController;
use App\Http\Controllers\Api\V1\Cms\LandingAboutSettingController;
use App\Http\Controllers\Api\V1\Cms\LandingArticleController;
use App\Http\Controllers\Api\V1\Cms\LandingContactSettingController;
use App\Http\Controllers\Api\V1\Cms\LandingContentCategoryController;
use App\Http\Controllers\Api\V1\Cms\LandingEventController;
use App\Http\Controllers\Api\V1\Cms\LandingFaqController;
use App\Http\Controllers\Api\V1\Cms\LandingFeatureController;
use App\Http\Controllers\Api\V1\Cms\LandingFooterSettingController;
use App\Http\Controllers\Api\V1\Cms\LandingGalleryAlbumController;
use App\Http\Controllers\Api\V1\Cms\LandingGalleryItemController;
use App\Http\Controllers\Api\V1\Cms\LandingHeaderSettingController;
use App\Http\Controllers\Api\V1\Cms\LandingPartnerController;
use App\Http\Controllers\Api\V1\Cms\LandingSeoSettingController;
use App\Http\Controllers\Api\V1\Cms\LandingServiceController;
use App\Http\Controllers\Api\V1\Cms\LandingSocialLinkController;
use App\Http\Controllers\Api\V1\Cms\LandingStatisticController;
use App\Http\Controllers\Api\V1\Cms\LandingTestimonialController;
use App\Http\Controllers\Api\V1\Cms\NavMenuItemController;
use App\Http\Controllers\Api\V1\Cms\SiteSettingController;
use App\Http\Controllers\Api\V1\GuidedTourController;
use App\Http\Controllers\Api\V1\HelpSettingController;
use App\Http\Controllers\Api\V1\InstallmentController;
use App\Http\Controllers\Api\V1\LandingController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\ManualBookController;
use App\Http\Controllers\Api\V1\MediaController;
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
use App\Http\Controllers\Api\V1\SupervisorAssignmentController;
use App\Http\Controllers\Api\V1\SupervisorCollectorController;
use App\Http\Controllers\Api\V1\SupervisorController;
use App\Http\Controllers\Api\V1\SupervisorDashboardController;
use App\Http\Controllers\Api\V1\SupervisorMapController;
use App\Http\Controllers\Api\V1\SupervisorMonitoringController;
use App\Http\Controllers\Api\V1\SupervisorNotificationController;
use App\Http\Controllers\Api\V1\SupervisorReportController;
use App\Http\Controllers\Api\V1\SupervisorTunggakanController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UnitOccupantController;
use App\Http\Controllers\Api\V1\UnitVehicleController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::post('payments/webhooks/xendit', [PaymentGatewayController::class, 'xenditWebhook']);
Route::post('payments/webhooks/midtrans', [PaymentGatewayController::class, 'midtransWebhook']);

// Public landing page content - no auth, cached aggregate + slug-detail lookups.
Route::get('landing/content', [LandingController::class, 'content']);
Route::get('landing/articles/{slug}', [LandingController::class, 'article']);
Route::get('landing/events/{slug}', [LandingController::class, 'event']);
Route::get('landing/gallery-albums/{slug}', [LandingController::class, 'galleryAlbum']);
Route::get('site-identity', [LandingController::class, 'identity']);

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

    Route::get('clusters/{cluster}/map', [ClusterMapController::class, 'show'])->middleware('permission:cluster-maps.view');
    Route::post('clusters/{cluster}/map', [ClusterMapController::class, 'store'])->middleware('permission:cluster-maps.edit');
    Route::post('cluster-maps/{clusterMap}/background', [ClusterMapController::class, 'uploadBackground'])->middleware('permission:cluster-maps.edit');
    Route::put('cluster-maps/{clusterMap}', [ClusterMapController::class, 'updateSettings'])->middleware('permission:cluster-maps.edit');
    Route::put('cluster-maps/{clusterMap}/objects', [ClusterMapController::class, 'saveObjects'])->middleware('permission:cluster-maps.edit');
    Route::get('cluster-maps/{clusterMap}/versions', [ClusterMapController::class, 'versions'])->middleware('permission:cluster-maps.edit');
    Route::post('cluster-map-versions/{clusterMapVersion}/restore', [ClusterMapController::class, 'restoreVersion'])->middleware('permission:cluster-maps.edit');

    Route::get('cluster-map-component-types', [ClusterMapComponentTypeController::class, 'index'])->middleware('permission:cluster-maps.view');
    Route::post('cluster-map-component-types', [ClusterMapComponentTypeController::class, 'store'])->middleware('permission:cluster-map-components.manage');
    Route::put('cluster-map-component-types/{clusterMapComponentType}', [ClusterMapComponentTypeController::class, 'update'])->middleware('permission:cluster-map-components.manage');
    Route::delete('cluster-map-component-types/{clusterMapComponentType}', [ClusterMapComponentTypeController::class, 'destroy'])->middleware('permission:cluster-map-components.manage');

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

    Route::get('visits/{visit}/evidence', [CollectorVisitEvidenceController::class, 'index'])->middleware('permission:collector-evidence.view');
    Route::post('visits/{visit}/evidence', [CollectorVisitEvidenceController::class, 'store'])->middleware('permission:collector-evidence.upload');
    Route::delete('visit-evidence/{evidence}', [CollectorVisitEvidenceController::class, 'destroy'])->middleware('permission:collector-evidence.delete');

    Route::get('collectors', [CollectorController::class, 'index'])->middleware('permission:collector.read');
    Route::post('collectors', [CollectorController::class, 'store'])->middleware('permission:collector.create');
    Route::get('collectors/{collector}', [CollectorController::class, 'show'])->middleware('permission:collector.detail');
    Route::put('collectors/{collector}', [CollectorController::class, 'update'])->middleware('permission:collector.update');
    Route::delete('collectors/{collector}', [CollectorController::class, 'destroy'])->middleware('permission:collector.delete');
    Route::patch('collectors/{collector}/status', [CollectorController::class, 'updateStatus'])->middleware('permission:collector.activate');
    Route::get('collectors/{collector}/assignment-history', [CollectorController::class, 'assignmentHistory'])->middleware('permission:collector.assignment_history|collector.detail');
    Route::post('collectors/{collector}/photo', [CollectorController::class, 'uploadPhoto'])->middleware('permission:collector.update');

    Route::get('collector-assignments', [CollectorAssignmentController::class, 'index'])->middleware('permission:collector-assignments.view');
    Route::post('collector-assignments', [CollectorAssignmentController::class, 'store'])->middleware('permission:collector-assignments.assign|collector.assign');
    Route::put('collector-assignments/{collectorAssignment}', [CollectorAssignmentController::class, 'update'])->middleware('permission:collector-assignments.update');
    Route::delete('collector-assignments/{collectorAssignment}', [CollectorAssignmentController::class, 'destroy'])->middleware('permission:collector-assignments.delete');
    Route::post('collector-assignments/{collectorAssignment}/reassign', [CollectorAssignmentController::class, 'reassign'])->middleware('permission:collector.reassign');

    Route::post('collector-locations', [CollectorLocationController::class, 'store'])->middleware('role:collector');
    Route::get('collector-locations', [CollectorLocationController::class, 'index'])->middleware('permission:collector-locations.view|collector.location_view');
    Route::get('collector-locations/latest', [CollectorLocationController::class, 'latest'])->middleware('permission:collector-locations.view|collector.location_view');

    Route::get('collector-targets', [CollectorTargetController::class, 'index'])->middleware('permission:collector-targets.view|collector.target_manage');
    Route::post('collector-targets', [CollectorTargetController::class, 'store'])->middleware('permission:collector-targets.create|collector.target_manage');
    Route::put('collector-targets/{collectorTarget}', [CollectorTargetController::class, 'update'])->middleware('permission:collector-targets.update|collector.target_manage');
    Route::delete('collector-targets/{collectorTarget}', [CollectorTargetController::class, 'destroy'])->middleware('permission:collector-targets.delete|collector.target_manage');
    Route::get('collector-performance', [CollectorPerformanceController::class, 'index'])->middleware('permission:collector-performance.view');
    Route::get('collector-performance/me', [CollectorPerformanceController::class, 'mine'])->middleware('role:collector');

    Route::get('collection-letters', [CollectionLetterController::class, 'index'])->middleware('permission:collection-letters.view');
    Route::post('collection-letters', [CollectionLetterController::class, 'store'])->middleware('permission:collection-letters.create');
    Route::get('collection-letters/{collectionLetter}/download', [CollectionLetterController::class, 'download'])->middleware('permission:collection-letters.download');

    Route::get('collector-reminders', [CollectorReminderController::class, 'index'])->middleware('permission:reports.view');
    Route::post('collector-reminders', [CollectorReminderController::class, 'store'])->middleware('role:collector');

    Route::get('supervisors', [SupervisorController::class, 'index'])->middleware('permission:supervisor.view');
    Route::post('supervisors', [SupervisorController::class, 'store'])->middleware('permission:supervisor.create');
    Route::get('supervisors/{supervisor}', [SupervisorController::class, 'show'])->middleware('permission:supervisor.detail');
    Route::put('supervisors/{supervisor}', [SupervisorController::class, 'update'])->middleware('permission:supervisor.update');
    Route::delete('supervisors/{supervisor}', [SupervisorController::class, 'destroy'])->middleware('permission:supervisor.delete');
    Route::patch('supervisors/{supervisor}/status', [SupervisorController::class, 'updateStatus'])->middleware('permission:supervisor.activate');
    Route::get('supervisors/{supervisor}/activity-history', [SupervisorController::class, 'activityHistory'])->middleware('permission:supervisor.detail');
    Route::post('supervisors/{supervisor}/photo', [SupervisorController::class, 'uploadPhoto'])->middleware('permission:supervisor.update');

    Route::get('supervisor-assignments', [SupervisorAssignmentController::class, 'index'])->middleware('permission:supervisor-assignments.view');
    Route::post('supervisor-assignments', [SupervisorAssignmentController::class, 'store'])->middleware('permission:supervisor-assignments.assign');
    Route::put('supervisor-assignments/{supervisorAssignment}', [SupervisorAssignmentController::class, 'update'])->middleware('permission:supervisor-assignments.assign');
    Route::delete('supervisor-assignments/{supervisorAssignment}', [SupervisorAssignmentController::class, 'destroy'])->middleware('permission:supervisor-assignments.assign');
    Route::post('supervisor-assignments/{supervisorAssignment}/reassign', [SupervisorAssignmentController::class, 'reassign'])->middleware('permission:supervisor-assignments.reassign');

    Route::get('supervisor/dashboard', [SupervisorDashboardController::class, 'summary'])->middleware('permission:collector-monitoring.view');
    Route::get('supervisor/collectors', [SupervisorCollectorController::class, 'index'])->middleware('permission:collector-monitoring.view');
    Route::get('supervisor/collectors/{collector}', [SupervisorCollectorController::class, 'show'])->middleware('permission:collector-monitoring.view');
    Route::get('supervisor/targets', [SupervisorMonitoringController::class, 'targets'])->middleware('permission:collector-monitoring.view');
    Route::get('supervisor/payment-promises', [SupervisorMonitoringController::class, 'paymentPromises'])->middleware('permission:collector-monitoring.view');
    Route::get('supervisor/payments', [SupervisorMonitoringController::class, 'payments'])->middleware('permission:collector-monitoring.view');
    Route::get('supervisor/tunggakan', [SupervisorTunggakanController::class, 'index'])->middleware('permission:tunggakan-analysis.view');
    Route::get('supervisor/tunggakan/{cluster}', [SupervisorTunggakanController::class, 'show'])->middleware('permission:tunggakan-analysis.view');
    Route::get('supervisor/map', [SupervisorMapController::class, 'index'])->middleware('permission:collector-locations.view|collector-locations.track');

    Route::get('supervisor-notifications', [SupervisorNotificationController::class, 'index'])->middleware('permission:supervisor-notifications.view');
    Route::post('supervisor-notifications/{supervisorNotification}/read', [SupervisorNotificationController::class, 'markRead'])->middleware('permission:supervisor-notifications.view');
    Route::post('supervisor-notifications/{supervisorNotification}/handled', [SupervisorNotificationController::class, 'markHandled'])->middleware('permission:supervisor-notifications.view');
    Route::post('supervisor-notifications/{supervisorNotification}/escalate', [SupervisorNotificationController::class, 'escalate'])->middleware('permission:supervisor-notifications.escalate');

    Route::get('broadcasts', [BroadcastController::class, 'index'])->middleware('permission:broadcasts.view');
    Route::get('broadcasts/{broadcast}', [BroadcastController::class, 'show'])->middleware('permission:broadcasts.view');
    Route::post('broadcasts', [BroadcastController::class, 'store'])->middleware('permission:broadcasts.send');

    Route::get('report-exports', [SupervisorReportController::class, 'index'])->middleware('permission:reports.export');
    Route::post('report-exports', [SupervisorReportController::class, 'store'])->middleware('permission:reports.export');
    Route::get('report-exports/{reportExport}', [SupervisorReportController::class, 'show'])->middleware('permission:reports.export');
    Route::get('report-exports/{reportExport}/download', [SupervisorReportController::class, 'download'])->middleware('permission:reports.export');

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

    Route::get('balances/reconciliation', [BalanceController::class, 'reconciliation'])->middleware('permission:balances.view');
    Route::get('units/{unit}/balance/ledger', [BalanceController::class, 'ledger'])->middleware('permission:balances.view');
    Route::get('units/{unit}/balance/reconciliation', [BalanceController::class, 'unitReconciliation'])->middleware('permission:balances.view');
    Route::post('units/{unit}/balance/adjustments', [BalanceController::class, 'adjust'])->middleware('permission:balances.adjust');
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

    Route::get('approval-requests', [ApprovalRequestController::class, 'index'])->middleware('permission:approvals.view');
    Route::get('approval-requests/{approvalRequest}', [ApprovalRequestController::class, 'show'])->middleware('permission:approvals.view');
    Route::post('approval-requests/installment-plans', [ApprovalRequestController::class, 'submitInstallmentPlan'])->middleware('permission:installment-plans.submit');
    Route::post('approval-requests/billing-adjustments', [ApprovalRequestController::class, 'submitBillingAdjustment'])->middleware('permission:billing-adjustments.submit');
    Route::post('approval-requests/{approvalRequest}/approve', [ApprovalRequestController::class, 'approve'])->middleware('permission:approvals.approve');
    Route::post('approval-requests/{approvalRequest}/reject', [ApprovalRequestController::class, 'reject'])->middleware('permission:approvals.reject');
    Route::post('approval-requests/{approvalRequest}/documents', [ApprovalRequestController::class, 'uploadDocument'])->middleware('permission:approvals.view');

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
    Route::get('documents/billing-recap-excel', [DocumentController::class, 'billingRecapExcel'])->middleware('permission:documents.generate');
    Route::get('documents/payment-transactions', [DocumentController::class, 'paymentTransactions'])->middleware('permission:documents.generate');
    Route::get('documents/payment-transactions-excel', [DocumentController::class, 'paymentTransactionsExcel'])->middleware('permission:documents.generate');
    Route::get('documents/payment-receipts', [DocumentController::class, 'paymentReceipts'])->middleware('permission:documents.generate');
    Route::get('documents/payment-receipts-excel', [DocumentController::class, 'paymentReceiptsExcel'])->middleware('permission:documents.generate');
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

    Route::get('emergency-alerts', [EmergencyAlertController::class, 'index'])->middleware('permission:emergency-alerts.view');
    Route::post('emergency-alerts', [EmergencyAlertController::class, 'store'])->middleware('permission:emergency-alerts.create');
    Route::post('emergency-alerts/{emergencyAlert}/acknowledge', [EmergencyAlertController::class, 'acknowledge'])->middleware('permission:emergency-alerts.acknowledge');

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

    // Landing Page CMS - every route below is gated on the single catch-all
    // `landing-cms.manage` permission, matching the manual-book/help-settings/
    // guided-tours ".manage" idiom already used elsewhere in this file.
    Route::middleware('permission:landing-cms.manage')->prefix('cms')->group(function () {
        Route::get('media', [MediaController::class, 'index']);
        Route::post('media', [MediaController::class, 'store']);
        Route::delete('media/{media}', [MediaController::class, 'destroy']);

        Route::get('settings/header', [LandingHeaderSettingController::class, 'show']);
        Route::put('settings/header', [LandingHeaderSettingController::class, 'update']);
        Route::get('settings/about', [LandingAboutSettingController::class, 'show']);
        Route::put('settings/about', [LandingAboutSettingController::class, 'update']);
        Route::get('settings/contact', [LandingContactSettingController::class, 'show']);
        Route::put('settings/contact', [LandingContactSettingController::class, 'update']);
        Route::get('settings/footer', [LandingFooterSettingController::class, 'show']);
        Route::put('settings/footer', [LandingFooterSettingController::class, 'update']);
        Route::get('settings/seo', [LandingSeoSettingController::class, 'show']);
        Route::put('settings/seo', [LandingSeoSettingController::class, 'update']);
        Route::get('settings/general', [SiteSettingController::class, 'show']);
        Route::put('settings/general', [SiteSettingController::class, 'update']);

        $collections = [
            'hero-slides' => [HeroSlideController::class, 'heroSlide'],
            'services' => [LandingServiceController::class, 'landingService'],
            'features' => [LandingFeatureController::class, 'landingFeature'],
            'statistics' => [LandingStatisticController::class, 'landingStatistic'],
            'testimonials' => [LandingTestimonialController::class, 'landingTestimonial'],
            'partners' => [LandingPartnerController::class, 'landingPartner'],
            'faqs' => [LandingFaqController::class, 'landingFaq'],
            'categories' => [LandingContentCategoryController::class, 'landingContentCategory'],
            'nav-menu-items' => [NavMenuItemController::class, 'navMenuItem'],
            'social-links' => [LandingSocialLinkController::class, 'landingSocialLink'],
            'events' => [LandingEventController::class, 'landingEvent'],
            'articles' => [LandingArticleController::class, 'landingArticle'],
            'gallery-albums' => [LandingGalleryAlbumController::class, 'landingGalleryAlbum'],
        ];

        foreach ($collections as $path => [$controller, $param]) {
            Route::get($path, [$controller, 'index']);
            Route::post($path, [$controller, 'store']);
            Route::put("{$path}/{{$param}}", [$controller, 'update']);
            Route::delete("{$path}/{{$param}}", [$controller, 'destroy']);
            Route::post("{$path}/{{$param}}/reorder", [$controller, 'reorder']);
        }

        Route::get('gallery-albums/{album}/items', [LandingGalleryItemController::class, 'index']);
        Route::post('gallery-albums/{album}/items', [LandingGalleryItemController::class, 'store']);
        Route::put('gallery-items/{item}', [LandingGalleryItemController::class, 'update']);
        Route::delete('gallery-items/{item}', [LandingGalleryItemController::class, 'destroy']);
        Route::post('gallery-items/{item}/reorder', [LandingGalleryItemController::class, 'reorder']);
    });
});

Route::middleware(['auth:sanctum', 'audit', 'role:customer', 'single-session'])->prefix('resident')->group(function () {
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
    Route::get('balance', [ResidentPortalController::class, 'balance']);
    Route::get('balance/ledger', [ResidentPortalController::class, 'balanceLedger']);
    Route::post('balance/preview', [ResidentPortalController::class, 'previewBalanceUsage']);
    Route::post('balance/use', [ResidentPortalController::class, 'useBalance']);
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
    Route::post('emergency', [ResidentPortalController::class, 'emergency']);
    Route::get('emergency/{emergencyAlert}', [ResidentPortalController::class, 'emergencyStatus']);
    Route::post('emergency/{emergencyAlert}/cancel', [ResidentPortalController::class, 'cancelEmergency']);
    Route::get('tenant', [ResidentPortalController::class, 'tenantInfo']);
    Route::post('tenant', [ResidentPortalController::class, 'storeTenant']);
    Route::put('tenant/billing-payer', [ResidentPortalController::class, 'updateBillingPayer']);
    Route::delete('tenant', [ResidentPortalController::class, 'removeTenant']);
});
