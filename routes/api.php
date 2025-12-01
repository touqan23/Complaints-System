<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CitizenController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\EmployeeController;
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
    Route::post('/login', 'login');
    Route::post('/forgotPassword', 'forgotPassword');
    Route::post('/verifyOtp', 'verifyOtp');
    Route::post('/verifyRegisterOtp', 'verifyRegisterOtp');
    Route::post('/resetPassword', 'resetPassword');
    //Route::post('/resend-otp', 'resendOtp');
    Route::post('/upload', 'try');

});

Route::controller(CitizenController::class)/*->middleware(['auth:sanctum', 'role:citizen'])*/->group(function () {
    Route::post('/register', 'register');

});

Route::controller(AdminController::class)/*->middleware(['auth:sanctum', 'role:admin'])*/->group(function () {
    Route::post('/add-employee', 'addEmployee');
});

Route::controller(EmployeeController::class)/*->middleware(['auth:sanctum', 'role:admin'])*/->group(function () {
});

Route::controller(ComplaintController::class)->middleware(['auth:sanctum', 'role:citizen,employee'])->group(function () {
    Route::post('/create/complaints', 'store');
    Route::get('/complaints/reference/{ref}',  'showByReference');
    Route::get('/complaints/citizen/{id}', 'citizenComplaints');
    Route::get('/complaints/status/{status}',  'complaintsByStatus');
    Route::get('/complaints/entity/{id}',  'entityComplaints');
    Route::get('/complaints/department/{id}',  'departmentComplaints');
    Route::post('/complaints/citizen/update',  'updateByCitizen');
    Route::get('/complaints/citizen/nationalNumber/{nationalNumber}', 'getcitizenComplaintsbynationalnumber');
    Route::get('/complaints/history/{referenceNumber}' ,'history');
    Route::get('/complaints/log' ,'activityLog');


    Route::post('/complaints/status', 'updateStatus');
    //notes
    Route::post('/add/note',  'addNote');
    Route::get('/get/note/{id}',  'getNotes');
    Route::get('/get/citizen/note/{id}',  'getCitizenNotes');
    Route::get('/delete/note/{id}',  'deleteNote');
    Route::post('/update/note',  'updateNote');



});
