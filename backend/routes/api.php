<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MessageTemplateController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Local-only REST API consumed by the Vue frontend.
|
*/

// Node.js WhatsApp service -> Laravel callbacks (guarded by token)
Route::prefix('provider')
    ->middleware('provider.token')
    ->group(function () {
        Route::post('campaign-progress', [ProviderController::class, 'progress']);
    });

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);
    Route::get('/admin/roles', [RolePermissionController::class, 'roles']);
    Route::get('/admin/permissions', [RolePermissionController::class, 'permissions']);
    Route::post('/admin/roles', [RolePermissionController::class, 'storeRole']);
    Route::post('/admin/permissions', [RolePermissionController::class, 'storePermission']);
});

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/companies', [CompanyController::class, 'index']);
Route::post('/companies', [CompanyController::class, 'store']);
Route::get('/companies/{company}', [CompanyController::class, 'show']);
Route::put('/companies/{company}', [CompanyController::class, 'update']);
Route::delete('/companies/{company}', [CompanyController::class, 'destroy']);
Route::get('/companies/{company}/accounts', [CompanyController::class, 'accounts']);
Route::post('/companies/{company}/connect-number', [CompanyController::class, 'connectNumber']);
Route::post('/companies/{company}/logout-number', [CompanyController::class, 'logoutNumber']);
Route::get('/api-keys', [ApiKeyController::class, 'index']);
Route::post('/api-keys', [ApiKeyController::class, 'store']);

Route::get('/whatsapp/status', [WhatsAppController::class, 'status']);
Route::post('/whatsapp/connect', [WhatsAppController::class, 'connect']);
Route::post('/whatsapp/logout', [WhatsAppController::class, 'logout']);
Route::post('/whatsapp/test-send', [WhatsAppController::class, 'testSend']);

Route::get('/contacts', [ContactController::class, 'index']);
Route::post('/contacts', [ContactController::class, 'store']);
Route::post('/contacts/import', [ContactController::class, 'import']);
Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);

Route::get('/message-templates', [MessageTemplateController::class, 'index']);
Route::post('/message-templates', [MessageTemplateController::class, 'store']);
Route::put('/message-templates/{template}', [MessageTemplateController::class, 'update']);
Route::delete('/message-templates/{template}', [MessageTemplateController::class, 'destroy']);

Route::get('/campaigns', [CampaignController::class, 'index']);
Route::get('/campaigns/analytics', [CampaignController::class, 'analytics']);
Route::post('/campaigns', [CampaignController::class, 'store']);
Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy']);
Route::post('/campaigns/{campaign}/start', [CampaignController::class, 'start']);

Route::get('/leads', [LeadController::class, 'index']);
Route::get('/leads/stats', [LeadController::class, 'stats']);
Route::post('/leads', [LeadController::class, 'store']);
Route::put('/leads/{lead}', [LeadController::class, 'update']);
Route::patch('/leads/{lead}', [LeadController::class, 'update']);
Route::delete('/leads/{lead}', [LeadController::class, 'destroy']);

Route::get('/appointments', [\App\Http\Controllers\Api\AppointmentController::class, 'index']);
Route::post('/appointments', [\App\Http\Controllers\Api\AppointmentController::class, 'store']);
Route::get('/appointments/summary', [\App\Http\Controllers\Api\AppointmentController::class, 'summary']);
Route::patch('/appointments/{appointment}', [\App\Http\Controllers\Api\AppointmentController::class, 'update']);
Route::delete('/appointments/{appointment}', [\App\Http\Controllers\Api\AppointmentController::class, 'destroy']);

Route::get('/reminders', [\App\Http\Controllers\Api\ReminderController::class, 'index']);
Route::post('/reminders', [\App\Http\Controllers\Api\ReminderController::class, 'store']);
Route::get('/reminders/summary', [\App\Http\Controllers\Api\ReminderController::class, 'summary']);
Route::delete('/reminders/{reminder}', [\App\Http\Controllers\Api\ReminderController::class, 'destroy']);

Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause']);
Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume']);
Route::post('/campaigns/{campaign}/stop', [CampaignController::class, 'stop']);