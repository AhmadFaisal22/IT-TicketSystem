<?php

use App\Http\Controllers\Api\ApprovalLevelController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetOptionController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SlaController;
use App\Http\Controllers\Api\TicketApprovalController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// OAuth + password login
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->middleware('throttle:5,1');
    Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::get('redirect/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('redirect/microsoft', [AuthController::class, 'redirectToMicrosoft']);
    Route::get('callback/google', [AuthController::class, 'handleGoogleCallback'])->middleware('throttle:10,1');
    Route::get('callback/microsoft', [AuthController::class, 'handleMicrosoftCallback'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::patch('locale', [AuthController::class, 'updateLocale']);
    });
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Tickets
    Route::apiResource('tickets', TicketController::class);
    Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
    Route::patch('tickets/{ticket}/assign', [TicketController::class, 'assign']);

    // Attachments (authorization mirrors the parent ticket/asset)
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download']);

    // Comments
    Route::get('tickets/{ticket}/comments', [CommentController::class, 'index']);
    Route::post('tickets/{ticket}/comments', [CommentController::class, 'store']);
    Route::delete('tickets/{ticket}/comments/{comment}', [CommentController::class, 'destroy']);

    // Assets (IT only — enforced in controller)
    // Static paths MUST precede assets/{asset} so they are not captured as a wildcard.
    Route::get('assets', [AssetController::class, 'index']);
    Route::get('assets/meta', [AssetController::class, 'meta']);
    Route::get('assets/export', [AssetController::class, 'export']);
    Route::post('assets/import', [AssetController::class, 'import']);
    Route::get('assets/{asset}', [AssetController::class, 'show']);
    Route::post('assets', [AssetController::class, 'store']);
    Route::put('assets/{asset}', [AssetController::class, 'update']);
    Route::delete('assets/{asset}', [AssetController::class, 'destroy']);
    Route::patch('assets/{asset}/assign', [AssetController::class, 'assign']);
    Route::patch('assets/{asset}/status', [AssetController::class, 'updateStatus']);
    Route::post('assets/{asset}/attachments', [AssetController::class, 'storeAttachments']);
    Route::delete('assets/{asset}/attachments/{attachment}', [AssetController::class, 'destroyAttachment']);

    // Asset options (categories/locations — list: IT staff, manage: admin)
    Route::get('asset-categories', [AssetOptionController::class, 'categories']);
    Route::post('asset-categories', [AssetOptionController::class, 'storeCategory']);
    Route::put('asset-categories/{assetCategory}', [AssetOptionController::class, 'updateCategory']);
    Route::delete('asset-categories/{assetCategory}', [AssetOptionController::class, 'destroyCategory']);
    Route::get('asset-locations', [AssetOptionController::class, 'locations']);
    Route::post('asset-locations', [AssetOptionController::class, 'storeLocation']);
    Route::put('asset-locations/{assetLocation}', [AssetOptionController::class, 'updateLocation']);
    Route::delete('asset-locations/{assetLocation}', [AssetOptionController::class, 'destroyLocation']);

    // Departments
    Route::apiResource('departments', DepartmentController::class);

    // Users (admin only)
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/it-staff', [UserController::class, 'itStaff']);
    Route::get('users/assignable', [UserController::class, 'assignable']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);
    Route::patch('users/{user}/role', [UserController::class, 'updateRole']);
    Route::patch('users/{user}/department', [UserController::class, 'updateDepartment']);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive']);

    // Dashboard
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('dashboard/sla', [DashboardController::class, 'sla']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);

    // SLA Policies
    Route::get('sla-policies', [SlaController::class, 'index']);
    Route::post('sla-policies', [SlaController::class, 'store']);
    Route::delete('sla-policies/{slaPolicy}', [SlaController::class, 'destroy']);

    // Approval Levels (admin only)
    Route::get('approval-levels', [ApprovalLevelController::class, 'index']);
    Route::post('approval-levels', [ApprovalLevelController::class, 'store']);
    Route::put('approval-levels/{approvalLevel}', [ApprovalLevelController::class, 'update']);
    Route::delete('approval-levels/{approvalLevel}', [ApprovalLevelController::class, 'destroy']);
    Route::post('approval-levels/reorder', [ApprovalLevelController::class, 'reorder']);

    // Ticket Approvals
    Route::post('tickets/{ticket}/approve', [TicketApprovalController::class, 'approve']);
    Route::post('tickets/{ticket}/reject', [TicketApprovalController::class, 'reject']);
});
