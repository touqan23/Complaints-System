<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CitizenController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::controller(AuthController::class)->group(function () {
    Route::post('/login', 'login')->middleware('throttle:login');
    Route::post('/forgotPassword', 'forgotPassword');
    Route::post('/verifyOtp', 'verifyOtp');
    Route::post('/verifyRegisterOtp', 'verifyRegisterOtp');
    Route::post('/resetPassword', 'resetPassword');
    Route::post('/upload', 'try');
});


Route::controller(CitizenController::class)->group(function () {
    Route::post('/register', 'register');
});


Route::controller(NotificationController::class)->middleware(['auth:sanctum', 'role:citizen,admin'])->group(function () {
    Route::get('/notifications/{notification}/read', 'markAsRead');
    Route::delete('/notifications/{notification}', 'delete');
    Route::get('/notifications', 'getNotifications');
});

Route::controller(AdminController::class)->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/add-employee', 'addEmployee');
    Route::post('/update-employee', 'update');
    Route::delete('/employee/{id}','delete');
    Route::get('/dashboard', 'dashboard');
    Route::get('/export/{type}/{format}', 'export')
        ->where([
            'type' => 'versions|backups|activity',
            'format' => 'pdf|csv'
        ]);    //statistics
    Route::get('/dashboard/statistics', 'statisticsDashboard');
    Route::get('/export/statistics/{format}', 'statisticsExport')
        ->where('format', 'pdf|csv');
    Route::get('/allEmployees', 'AllEmployee');
    Route::get('/allComplaints', 'AllComplaints');
    Route::get('/allDepartments', 'AllDepartments');
    Route::get('/allCitizens', 'AllCitizens');
    Route::get('/citizens/{id}', 'showCitizen');
    Route::post('/update/citizens','updateCitizen');
    Route::delete('/citizens/{id}', 'deleteCitizen');
    Route::get('/logs/error', 'errorLogs');
});
/////////////////////////////////////////////////////////////////////////////من هون لازم عدل
///employee is the only role that can process the complaint
Route::controller(ComplaintController::class)->middleware(['auth:sanctum', 'role:employee'])->group(function () {
    Route::post('/complaints/startProcess' ,'startProcess');
    Route::post('/complaints/finishProcess' ,'finishProcess');
    Route::get('/complaints/department/{id}',  'departmentComplaints');
});
/////////////multi auth
Route::controller(ComplaintController::class)->middleware(['auth:sanctum', 'role:employee,admin'])->group(function () {
    Route::get('/complaints/reference/{ref}',  'showByReference');
    Route::get('/complaints/status/{status}',  'complaintsByStatus');
    Route::post('/add/note',  'addNote');
    Route::get('/get/note/{id}',  'getNotes');
    Route::get('/delete/note/{id}',  'deleteNote');
    Route::post('/update/note',  'updateNote');
    Route::get('/complaints/history/{referenceNumber}' ,'history');
    Route::post('/complaints/status', 'updateStatus');
    Route::get('/complaints/log' ,'activityLog');
});

Route::controller(ComplaintController::class)->middleware(['auth:sanctum', 'role:citizen'])->group(function () {
    Route::post('/create/complaints', 'store');
    Route::get('/complaints/citizen', 'citizenComplaints');
    Route::post('/complaints/citizen/update',  'updateByCitizen');
    Route::get('/get/citizen/note/{nationalNumber}',  'getCitizenNotes');
});




