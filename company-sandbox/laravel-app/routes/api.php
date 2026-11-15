<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Middleware\RequireServiceToken;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\CarerController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\FinanceController;

/*
|--------------------------------------------------------------------------
| API Routes for CareOps Legacy Backend Sandbox
|--------------------------------------------------------------------------
*/

// Unauthenticated health check
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Throwable $e) {
        $dbStatus = 'disconnected: ' . $e->getMessage();
    }

    return response()->json([
        'status' => 'ok',
        'service' => 'careops-legacy',
        'database' => $dbStatus,
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authenticated Legacy API Endpoints (Appendix B)
Route::middleware([RequireServiceToken::class])->group(function () {

    // Recruitment
    Route::get('/candidates', [CandidateController::class, 'index']);
    Route::get('/candidates/{id}', [CandidateController::class, 'show']);
    Route::patch('/candidates/{id}/status', [CandidateController::class, 'updateStatus']);
    Route::get('/candidates/{id}/compliance', [CandidateController::class, 'getCompliance']);
    Route::post('/candidates/{id}/interviews', [CandidateController::class, 'bookInterview']);
    Route::post('/candidates/{id}/hire', [CandidateController::class, 'hire']);

    // HR
    Route::get('/carers', [CarerController::class, 'index']);
    Route::get('/carers/{id}', [CarerController::class, 'show']);
    Route::get('/carers/{id}/compliance', [CarerController::class, 'getCompliance']);
    Route::post('/carers/{id}/compliance-checks', [CarerController::class, 'recordCompliance']);
    Route::get('/leave-requests', [CarerController::class, 'listLeave']);
    Route::post('/leave-requests', [CarerController::class, 'requestLeave']);
    Route::patch('/leave-requests/{id}', [CarerController::class, 'decideLeave']);

    // Operations
    Route::get('/clients', [ShiftController::class, 'listClients']);
    Route::get('/clients/{id}', [ShiftController::class, 'showClient']);
    Route::get('/shifts', [ShiftController::class, 'index']);
    Route::get('/shifts/{id}', [ShiftController::class, 'show']);
    Route::post('/shifts', [ShiftController::class, 'store']);
    Route::get('/shifts/{id}/eligible-carers', [ShiftController::class, 'eligibleCarers']);
    Route::post('/shifts/{id}/assign', [ShiftController::class, 'assign']);
    Route::post('/shifts/{id}/cancel', [ShiftController::class, 'cancel']);

    // Finance
    Route::get('/pay-rates', [FinanceController::class, 'payRates']);
    Route::get('/timesheets', [FinanceController::class, 'listTimesheets']);
    Route::post('/timesheets', [FinanceController::class, 'submitTimesheet']);
    Route::patch('/timesheets/{id}', [FinanceController::class, 'updateHours']);
    Route::post('/timesheets/{id}/approve', [FinanceController::class, 'approve']);
    Route::post('/timesheets/{id}/reject', [FinanceController::class, 'reject']);
    Route::get('/invoices', [FinanceController::class, 'listInvoices']);
    Route::get('/invoices/{id}', [FinanceController::class, 'showInvoice']);
    Route::post('/invoices', [FinanceController::class, 'generateInvoice']);
});
