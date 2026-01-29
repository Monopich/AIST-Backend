<?php

use App\Http\Controllers\AcadamicYearConttroller;
use App\Http\Controllers\attendance\AttendanceController;
use App\Http\Controllers\attendance\QrCodeController;
use App\Http\Controllers\auth\AuthController;
use App\Http\Controllers\department\DepartmentController;
use App\Http\Controllers\department\SubDepartmentController;
use App\Http\Controllers\exam\ImportScoreController;
use App\Http\Controllers\exam\TempStudentListController;
use App\Http\Controllers\feedback\FeedBackController;
use App\Http\Controllers\group\GroupController;
use App\Http\Controllers\location\LocationController;
use App\Http\Controllers\program\ProgramController;
use App\Http\Controllers\program\UserProgramController;
use App\Http\Controllers\role\RoleController;
use App\Http\Controllers\semester\SemesterController;
use App\Http\Controllers\student\StudentController;
use App\Http\Controllers\subject\SubjectController;
use App\Http\Controllers\testController;
use App\Http\Controllers\time_table\TimeSlotByIdController;
use App\Http\Controllers\time_table\TimeTableController;
use App\Http\Controllers\users\UserController;
use App\Http\Controllers\subject\ScoreController;
use App\Http\Controllers\academic_year\AcademicYearByProgramController;
use App\Http\Controllers\exam\TempStudentController;

use App\Http\Controllers\TelegramController;
use App\Http\Controllers\auth\OtpController;
use App\Http\Controllers\cv\CvContactController;
use App\Http\Controllers\cv\CvController;
use App\Http\Controllers\cv\CvEducationController;
use App\Http\Controllers\cv\CvLanguageController;
use App\Http\Controllers\cv\CvSkillController;
use App\Http\Controllers\cv\CvWorkController;
use App\Http\Controllers\mission\MissionController;
use App\Http\Controllers\training\TrainingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/hello', [testController::class, 'hello']); // http://localhost:8000/api/hello

// Telegram webhook route
Route::post('/telegram/webhook', [TelegramController::class, 'handle']);
Route::post('/auth/request-phone-otp', [OtpController::class, 'requestOtp']);
Route::post('/auth/verify-phone-otp', [OtpController::class, 'verifyOtp']);
Route::post('/auth/reset-password', [AuthController::class, 'confirmPassword']);

// group of auth
Route::post('auth/login', [AuthController::class, 'login']);

// routes for authenticated users
Route::middleware(['auth:sanctum'])->prefix('auth')->group(function () {
    Route::put('/change_password', [AuthController::class, 'changePassword']);
    Route::put('/update_user/{id}', [UserController::class, 'updateUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/get_detail_user', [UserController::class, 'getUserDetails']);
});

// routes for admin only
Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'registerUser']);
    Route::post('/create_admin', [AuthController::class, 'createAdmin']);
    Route::put('/update_user/{id}', [UserController::class, 'updateUser']);
    // Route::get('/search_user', [UserController::class,'searchUser']);
});

// routes for all authenticated users
Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    Route::put('/change_picture_profile', [AuthController::class, 'changePictureProfile']);
    Route::get('/get_academic_information', [StudentController::class, 'getAcademicInformation']);

    Route::post('/score_student', [ScoreController::class, 'scoreStudent']);
    Route::get('/get_scores_of_student', [ScoreController::class, 'getScoresOfStudent']);

    Route::get('/get_subject_of_teacher', [SubjectController::class, 'getAllSubjectOfTeacher']);
    Route::get('/get_student_of_teacher', [UserController::class, 'getStudentLearnWithTeacher']);

});


// routes for student management and admin only
Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('students')->group(function () {
    Route::get('/', [StudentController::class, 'listAllStudents']);
    Route::get('/user/{id}', [StudentController::class, 'getStudentById']);
    Route::get('/search_user', [StudentController::class, 'searchUser']);
    Route::get('/paginate', [StudentController::class, 'paginateStudents']);
    Route::get('/filter_by_dept/{id}', [StudentController::class, 'filterByDepartment']);
});

// routes for user management and admin only
Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('users')->group(function () {
    Route::post('/register_new_staff', [UserController::class, 'registerNewStaff']);
    Route::get('/get_all_staff', [UserController::class, 'getAllStaff']);
    Route::get('/paginate_all_staff', [UserController::class, 'paginateAllStaff']);
    Route::get('/get_all_users', [UserController::class, 'getAllUsers']);
    Route::get('/get_all_users_department/{department_id}', [UserController::class, 'getAllUsersWithoutPagination']);
    Route::delete('/remove_user/{userId}', [UserController::class, 'removeUser']);
    Route::get('/get_all_head', [UserController::class, 'getAllHead']);
    // Route::get('/search_user', [StudentController::class, 'searchUser']);
    // Route::get('/paginate', [StudentController::class, 'paginateStudents']);
    // Route::get('/filter_by_dept/{id}', [StudentController::class, 'filterByDepartment']);
    Route::post('/assign_role', [RoleController::class, 'assignToUser']);
    Route::delete('/remove_user_role', [RoleController::class, 'removeRole']);
    Route::get('/get_head_department', [UserController::class, 'getAllHeadDepartment']);
    Route::get('/get_user_by_id/{userId}', [UserController::class, 'getUserById']);
    Route::get('/enrolled_students', [UserProgramController::class, 'getAllEnrolledStudents']);
    Route::post('/get_available_teachers', [UserController::class, 'getAvailableTeachers']);
});
Route::get('users/get_all_academic_year_user', [UserProgramController::class, 'AllAcademicOfUser'])->middleware('auth:sanctum');



// routes for department, sub-department, program, subject management and admin only
Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('managements')->group(function () {

    Route::post('/add_new_department', [DepartmentController::class, 'createNewDepartment']);
    Route::get('/get_all_department', [DepartmentController::class, 'listAllDepartment']);
    Route::get('/get_students_department/{department_id}', [DepartmentController::class, 'paginateStudentByDepartment']);
    Route::get('/get_department_detail/{department_id}', [DepartmentController::class, 'findDetailDepartment']);

    Route::put('/update_department/{department_id}', [DepartmentController::class, 'updateDepartment']);
    Route::get('/search_department', [DepartmentController::class, 'searchDepartment']);
    Route::get('/get_department_head', [DepartmentController::class, 'getDepartmentByHead'])->middleware('auth:sanctum');


    Route::delete('/delete_department/{department_id}', [DepartmentController::class, 'deleteDepartment']);
    Route::get('/get_all_staff_department', [DepartmentController::class, 'listAllStaffOfDepartment']);
    Route::post('/assign_head/{department_id}', [DepartmentController::class, 'assignHeadDepartment']);
    Route::put('/change_head/{department_id}', [DepartmentController::class, 'changeHeadDepartment']);

    // Route::get('/get_all_sub_department', [DepartmentController::class, 'listAllSubDepartmentOfDepartment']);

    Route::delete('/delete_sub_department/{subDepartment_id}', [SubDepartmentController::class, 'deleteSubDepartment']);

    Route::get('/get_student_by_sub_department', [SubDepartmentController::class, 'getAllStudentOfSubDepartment']);

    Route::post('/create_sub_department', [SubDepartmentController::class, 'createNewSubDepartment']);
    Route::put('/update_sub_department/{subDepartment_id}', [SubDepartmentController::class, 'updateSubDepartment']);
    Route::get('/get_all_sub_department', [SubDepartmentController::class, 'getAllSubDepartment']);

    Route::post('/create_new_program', [ProgramController::class, 'createNewProgram']);
    Route::put('/update_program/{program_id}', [ProgramController::class, 'updateProgram']);
    Route::delete('/remove_program/{program_id}', [ProgramController::class, 'removeProgram']);
    Route::get('/get_all_program', [ProgramController::class, 'getAllProgram']);
    Route::get('/get_program_by', [ProgramController::class, 'getProgramByDepartment']);

    // create_new_semester
    Route::post('/create_new_semester_program', [ProgramController::class, 'createSemesterForProgram']);
    Route::put('/update_semester/{semester_id}', [ProgramController::class, 'updateSemester']);
    Route::get('/get_all_subject_in_program/{program_id}', [ProgramController::class, 'getSubjectInProgram']);
    Route::delete('/remove_subject_from_program/{program_id}', [ProgramController::class, 'removeSubjectFromProgram']);
    Route::post('/clone_program', [ProgramController::class, 'cloneProgram']);
    Route::get('/list_users_in_programs', [ProgramController::class, 'listUsersInPrograms']);
    Route::post('/add_student_to_program', [UserProgramController::class, 'addStudentToProgram']);
    Route::post('/promote-multiple', [UserProgramController::class, 'promoteMultipleStudents']);
    Route::get('/user-programs/{userId}', [UserProgramController::class, 'getUserPrograms']);
    Route::get('/user-programs', [UserProgramController::class, 'getAllEnrolledStudents']);
    Route::get('/programs', [UserProgramController::class, 'getAllPrograms']);
    Route::get('/departments', [UserProgramController::class, 'getAllDepartments']);
    Route::get('/groups', [UserProgramController::class, 'getAllGroups']);
    Route::get('/degrees', [UserProgramController::class, 'getAllDegrees']);




    Route::post('/add_student_to_program', [UserProgramController::class, 'addStudentToProgram']);
    Route::delete('/remove_student_from_program/{id}', [UserProgramController::class, 'removeStudent']);

    Route::post('/add_new_generation_program/{programId}', [UserProgramController::class, 'createNewGenerationProgram']);
    Route::get('/get_generation_by_program/{programId}', [UserProgramController::class, 'getGenerationByProgram']);


    Route::get('/paginate_all_program', [ProgramController::class, 'paginateProgram']);
    Route::get('/paginate_subject_program/{subject_id}', [ProgramController::class, 'paginateSubjectOfProgram']);
    Route::get('/search_paginate_program', [ProgramController::class, 'searchPaginateProgram']);
    Route::get('/filter_paginate_program', [ProgramController::class, 'filterProgram']);
    Route::put('add_subject_to_program/{program_id}', [ProgramController::class, 'addSubjectToProgram']);
    Route::put('/add_program_to_department/{department_id}', [ProgramController::class, 'addProgramToDepartment']);
    Route::delete('/remove_program_from_department/{department_id}', [ProgramController::class, 'removeProgramFromDepartment']);


    Route::post('/create_subject', [SubjectController::class, 'createSubject']);
    Route::put('/assign_teacher_subject/{subject_id}', [SubjectController::class, 'assignTeacherToSubject']);
    Route::delete('/unassign_teacher_subject', [SubjectController::class, 'UnassignTeacherFromSubject']);

    Route::get('/get_all_teacher_subject', [SubjectController::class, 'getAllTeacherOfSubject']);
    Route::get('/get_subjects_by_teacher/{teacher_id}', [SubjectController::class, 'getSubjectsByTeacher']);
    Route::get('/get_all_subject_with_teacher', [SubjectController::class, 'getAllSubjectWithTeachers']);
    Route::post('/add_subject_to_semester', [SubjectController::class, 'addSubjectToSemester']);
    Route::delete('/remove_subject_from_semester', [SubjectController::class, 'removeSubjectsFromSemester']);

    Route::get('get_all_teachers', [UserController::class, 'getAllTeachers']);

    Route::put('/update_subject/{subject_id}', [SubjectController::class, 'updateSubject']);
    Route::get('/get_all_subjects', [SubjectController::class, 'getAllSubjects']);
    Route::get('/search_paginate_subjects', [SubjectController::class, 'searchSubject']);

    Route::delete('/remove_subject/{subject_id}', [SubjectController::class, 'removeSubject']);
    Route::post('/import_moodle_scores', [ScoreController::class, 'importMoodleScores']);
    Route::get('/scores/subject/{subjectId}', [ScoreController::class, 'getScoresBySubject']);
    Route::get('/scores/user-program/{userProgramId}', [ScoreController::class, 'getScoresByUserProgram']);
    Route::get('/user-programs/{id}/scores', [ScoreController::class, 'getAllSubjectScoresByUserProgram']);
    Route::get('/users/{userId}/academic-history', [ScoreController::class, 'getUserAcademicHistory']);
    Route::get('/certificate/{userId}/year/{year}', [ScoreController::class, 'generateCertificateByYear']);





    Route::get('/get_student_by_generation', [UserProgramController::class, 'listAllStudentByGeneration']);

    // manage semesters
    Route::post('/create_new_semester', [SemesterController::class, 'createNewSemesterProgram']);
    Route::put('/update_semester/{semester_id}', [SemesterController::class, 'updateSemesterProgram']);
    Route::delete('/delete_semester/{semester_id}', [SemesterController::class, 'deleteSemesterProgram']);
    Route::get('/get_semesters_by_program/{semester_id}', [SemesterController::class, 'getAllSemestersByProgram']);
    Route::get('/get_all_semesters', [SemesterController::class, 'getAllSemesters']);

    Route::get('/get_all_semesters_by_academic_year', [SemesterController::class, 'getAllSemesterByAcademicYear']);


    Route::post('/create_new_academic_year', [AcadamicYearConttroller::class, 'createNewAcademicYear']);
    Route::get('/get_academic_year', [UserProgramController::class, 'getAcademicYear']);

    // Get academic years by program ID (for timetable)
    Route::get('/get_academic_years_by_program/{program_id}', [AcademicYearByProgramController::class, 'getAcademicYearsByProgram']);



    Route::get('/get_all_subject_in_semester/{semesterId}', [SemesterController::class, 'getSubjectsBySemester']);

});

Route::get('/users/student_academic_history', [ScoreController::class, 'getStudentAcademicHistory'])->middleware(['auth:sanctum', 'role:Student']);



Route::middleware(['auth:sanctum'])->prefix('locations')->group(function () {
    Route::post('/create_building', [LocationController::class, 'createNewBuilding']);
    Route::get('/get_all_building', [LocationController::class, 'getAllBuilding']);
    Route::get('/get_detail_building/{building_id}', [LocationController::class, 'getDetailsBuilding']);
    Route::put('/update_building/{id}', [LocationController::class, 'updateBuilding']);
    Route::delete('/remove_building/{id}', [LocationController::class, 'removeBuilding']);
    Route::post('/create_new_room', [LocationController::class, 'createNewRoom']);
    Route::put('/update_room/{id}', [LocationController::class, 'updateRoom']);
    Route::delete('/remove_room/{id}', [LocationController::class, 'removeRoom']);
    Route::get('/filter_rooms_by/{building_id}', [LocationController::class, 'filterRoomByBuilding']);
    Route::get('/search_locations', [LocationController::class, 'searchLocations']);
    Route::get('/get_detail_location/{id}', [LocationController::class, 'getDetailLocation']);
    Route::get('/get_all_locations', [LocationController::class, 'getAllLocations']);
    Route::post('/get_available_locations', [LocationController::class, 'getAvailableLocations']);
    // Route::post('/test_detect_locations/{locationId}', [AttendanceController::class, 'testDetectLocation']);
});

Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('groups')->group(function () {
    Route::post('/create_new_group', [GroupController::class, 'createNewGroup']);
    Route::put('/update_group/{id}', [GroupController::class, 'updateGroup']);
    Route::delete('/delete_group/{id}', [GroupController::class, 'deleteGroup']);

    Route::get('/get_all_groups', [GroupController::class, 'getAllGroups']);
    Route::post('/assign_user_to_group', [GroupController::class, 'assignToUser']);
    Route::post('/assign_multiple_users', [GroupController::class, 'assignMultipleToGroup']);

    Route::get('/get_group_by_id/{id}', [GroupController::class, 'getGroupById']);
    Route::delete('/remove_student_from_group', [GroupController::class, 'removeStudentFromGroup']);

    Route::get('/filter_group_by_program/{id}', [GroupController::class, 'filterGroupsByProgram']);
    Route::get('/search_groups', [GroupController::class, 'searchGroup']);
    Route::get('/filter_group_by_department/{id}', [GroupController::class, 'filterGroupsByDepartment']);
    Route::get('/filter_group_by_sub_department/{id}', [GroupController::class, 'filterGroupsBySubDepartment']);
    Route::delete('/remove_multiple_from_group', [GroupController::class, 'removeMultipleFromGroup']);

    Route::post('/clone_group_histories/{group_id}', [GroupController::class, 'cloneGroup']);
    Route::post('/create_new_group_add_student', [GroupController::class, 'addStudentToNewGroup']);

    Route::get('/get_group_semester/{semesterId}', [SemesterController::class, 'getAllGroupBySemester']);

});

Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('users')->group(function () {
    Route::post('/assign_role', [RoleController::class, 'assignToUser']);
    Route::delete('/remove_user_role', [RoleController::class, 'removeRole']);
    Route::get('/get_head_department', [UserController::class, 'getAllHeadDepartment']);
    Route::get('/get_user_by_id/{userId}', [UserController::class, 'getUserById']);
    // Route::get('/get_all_academic_year_user', [UserProgramController::class, 'AllAcademicOfUser'])->middleware('auth:sanctum');
});

Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('time_table')->group(function () {
    Route::post('/create_new_time_table', [TimeTableController::class, 'createTimeTable']);

    Route::get('/get_all_time_table', [TimeTableController::class, 'getAllTimeTables']); // http://localhost:8000/api/time_table/get_all_time_table
    Route::delete('/remove_time_table/{id}', [TimeTableController::class, 'deleteTimeTable']);
    Route::get('/get_time_table/{id}', [TimeTableController::class, 'getTimeTableById']);
    Route::put('/update_time_table/{id}', [TimeTableController::class, 'updateTimeTable']);
    Route::post('/create_new_time_slot/{timeTableId}', [TimeTableController::class, 'createTimeSlots']);
    Route::get('/get_all_time_slot_by_group/{id}', [TimeTableController::class, 'getTimeSlotsOfGroup']);
    Route::get('/get_all_time_slots', [TimeTableController::class, 'getAllTimeSlots']);
    Route::post('/remove_time_slots', [TimeTableController::class, 'removeMultipleTimeSlots']);
    // Route::get('/get_time_slots_user', [TimeTableController::class, 'getTimeSlotByUser'])->middleware('auth:sanctum');
    // Route::get('/get_time_slots_teacher', [TimeTableController::class, 'getTimeSlotForTeacher'])->middleware('auth:sanctum');
    Route::post('/clone_time_slot/{timeTableId}', [TimeTableController::class, 'cloneWeek']);
    Route::get('/get_a_week_events/{timeTableId}', [TimeTableController::class, 'getAWeekEvents']);
    Route::get('/get_specific_week_events/{timeTableId}/{weekNumber}', [TimeTableController::class, 'getSpecificWeekEvents']);
    Route::delete('/remove_time_slot/{timeTableId}', [TimeTableController::class, 'removeTimeSlot']);
    Route::put('/update_time_slot/{id}', [TimeTableController::class, 'updateTimeSlot']);
    //     Route to get time slot by timetable ID
    Route::get('/get_time_slot_by_timetable/{timetableId}', [TimeSlotByIdController::class, 'getTimeSlotByTimeTableId']);
    //     Route to get time slot by group id
    Route::get('/get_time_slot_by_group/{groupId}', [TimeSlotByIdController::class, 'getTimeSlotByGroupId']);

});
Route::middleware(['auth:sanctum'])->prefix('time_table')->group(function () {
    Route::get('/get_time_slots_user', [TimeTableController::class, 'getTimeSlotByUser']);
    Route::get('/get_time_slots_teacher', [TimeTableController::class, 'getTimeSlotForTeacher']);
});

Route::middleware(['auth:sanctum'])->prefix('attendance')->group(function () {
    Route::post('/scan_attendance', [AttendanceController::class, 'scanAttendance']);
    Route::get('/get_attendances', [AttendanceController::class, 'getAttendance']);
});

Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('attendance')->group(function () {
    Route::get('/get_all_qr_code', [QrCodeController::class, 'getAllQrCodes']);
    Route::post('/generate_qr_code', [QrCodeController::class, 'generateQrCode']);
    Route::post('/re_generate_qr_code/{id}', [QrCodeController::class, 'reGenerateQrCode']);
    Route::get('/get_all_attendances', [AttendanceController::class, 'getAllAttendance']);
    Route::delete('/remove_attendance/{attendance_id}', [AttendanceController::class, 'removeAttendance']);
});

// feedback routes
Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('feedbacks')->group(function () {
    // Route::post('/submit_feedback', [FeedBackController::class, 'submitFeedback']);
    Route::get('/get_feedbacks', [FeedBackController::class, 'getFeedback']);
});
Route::middleware(['auth:sanctum'])->prefix('feedbacks')->group(function () {
    Route::post('/submit_feedback', [FeedBackController::class, 'submitFeedback']);
    // Route::get('/get_feedbacks', [FeedBackController::class, 'getFeedback']);
});

// leave request routes student + staff
Route::middleware('auth:sanctum')->prefix('request')->group(function () {
    Route::post('/create_leave_request', [AttendanceController::class, 'createLeaveRequest']);
    Route::get('/get_leave_request', [AttendanceController::class, 'getAllLeaveRequests']);
});

// leave request routes admin
Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('request')->group(function () {
    Route::put('/approve_leave_request', [AttendanceController::class, 'approveRequest']);
    Route::put('/reject_leave_request', [AttendanceController::class, 'rejectRequest']);
    Route::get('/get_all_leave_request', [AttendanceController::class, 'getAllLeaveRequestsByAdmin']);
});

// logs and test
Route::get('check_logs', [testController::class, 'checkLogs'])->middleware('auth:sanctum', 'role:Admin'); // http://localhost:8000/api/check_logs
Route::get('check_time_server', [TimeTableController::class, 'getTimeServer'])->middleware('auth:sanctum');
Route::get('/activity_logs', [testController::class, 'getActivitiesLog'])->middleware('auth:sanctum', 'role:Admin');

Route::middleware(['auth:sanctum', 'role:Head Department'])
    ->get('/users/by_department', [UserController::class, 'getUsersByDepartment']);

Route::middleware(['auth:sanctum', 'role:Staff'])->group(function () {
    // Get students by teacher's department
    Route::get('/users/by_teacher', [UserController::class, 'getStudentsByDepartment']);

    // Get leave requests submitted by the authenticated teacher only
    Route::get('/request/get_leave_request_teacher', [AttendanceController::class, 'getLeaveRequestTeacher']);

    // Create leave request (teacher only)
    Route::post('/request/create_leave_request_teacher', [AttendanceController::class, 'createTeacherLeaveRequest']);
});

Route::middleware(['auth:sanctum', 'role:Student'])->group(function () {
    // Get leave requests submitted by the authenticated student only
    Route::get('/request/get_leave_request_student', [AttendanceController::class, 'getLeaveRequestStudent']);

    // Create leave request (student only)
    Route::post('/request/create_leave_request_student', [AttendanceController::class, 'createStudentLeaveRequest']);
});

Route::middleware(['auth:sanctum', 'role:Head Department'])->group(function () {
    // Get leave requests submitted by the authenticated HOD only
    Route::get('/request/get_leave_request_hod', [AttendanceController::class, 'getLeaveRequestHod']);

    // Create leave request (HOD only)
    Route::post('/request/create_leave_request_hod', [AttendanceController::class, 'createHodLeaveRequest']);
    Route::get('/users_by_hod_department/{department_id}', [DepartmentController::class, 'getUserByHeadDepartment']);
    Route::get('/request/leave-requests', [AttendanceController::class, 'getLeaveRequestsByHod']);
    Route::post('/request/leave-requests/{id}/approve', [AttendanceController::class, 'approveLeaveRequestByHod']);
    Route::post('/request/leave-requests/{id}/reject', [AttendanceController::class, 'rejectLeaveRequestByHod']);

});

Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('external_exam')->group(function () {
    Route::post('/add_temp_student', [TempStudentController::class, 'store']);
    Route::get('/get_temp_students', [TempStudentController::class, 'index']);
    Route::get('/get_temp_student/{id}', [TempStudentController::class, 'show']);
    Route::delete('/delete_temp_student/{id}', [TempStudentController::class, 'destroy']);
    Route::put('/temp-students/{id}', [TempStudentController::class, 'update']);

    Route::post('/preview', [ImportScoreController::class, 'preview']);
    Route::post('/upload_score', [ImportScoreController::class, 'storeFile']);
    Route::get('/scores', [ImportScoreController::class, 'index']);
    Route::get('/scores/{id}', [ImportScoreController::class, 'show']);
    Route::get('/scores/{id}/download', [ImportScoreController::class, 'download']);

    Route::post('/finalize', [ImportScoreController::class, 'finalize']);
    Route::get('/temp_student_list', [TempStudentListController::class, 'index']);
    Route::post('/enroll', [ImportScoreController::class, 'enroll']);
    Route::post('/enroll-one/{tempStudentId}', [ImportScoreController::class, 'enrollOne']);
    Route::post('/enroll-temp-students/{id}', [ImportScoreController::class, 'enrollTempStudents']);


});


Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('missions')->group(function () {
    Route::post('/create_mission', [MissionController::class, 'makeMission']);
    Route::get('/get_all_missions', [MissionController::class, 'getAllMissions']);
    Route::put('/cancel_mission/{mission_id}', [MissionController::class, 'cancelMission']);
    Route::put('/update_mission/{mission_id}', [MissionController::class, 'updateMission']);
    Route::put('/complete_mission/{mission_id}', [MissionController::class, 'markAsComplete']);
    Route::get('/get_mission_detail/{mission_id}', [MissionController::class, 'getMissionDetails']);

});
Route::middleware(['auth:sanctum', 'roles:Staff|Head Department'])->prefix('missions')->group(function () {
    Route::get('/get_personal_missions', [MissionController::class, 'GetPersonalMissions']);
});
Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('users')->group(function () {
    Route::get('/get_users_for_mission', [MissionController::class, 'selectUsersForMission']);
});
Route::middleware(['auth:sanctum', 'role:Staff'])
    ->get('/users/get_student_of_teacher', [UserController::class, 'getStudentLearnWithTeacher']);

Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('cvs')->group(function () {
    Route::post('/create_new_cv', [CvController::class, 'createNewCv']);
    Route::get('/get_all_cvs', [CvController::class, 'getAllCvs']);
    Route::get("/get_cv_by_id/{cvId}", [CvController::class, 'getCv']);
    Route::delete('/delete_cv/{cvId}', [CvController::class, 'deleteCv']);

    Route::post("/add_new_works/{cvId}", [CvWorkController::class, 'addNewWorks']);
    Route::delete("/delete_works/{cvId}", [CvWorkController::class, 'deleteWorks']);

    Route::post('/add_new_educations/{cvId}', [CvEducationController::class, 'addEducationToCv']);
    Route::delete('/delete_educations/{cvId}', [CvEducationController::class, 'deleteEducations']);

    Route::post('/add_new_languages/{cvId}', [CvLanguageController::class, 'addNewLanguages']);
    Route::delete('/delete_languages/{cvId}', [CvLanguageController::class, 'deleteLanguageFromCv']);

    Route::post('/add_new_skills/{cvId}', [CvSkillController::class, 'addNewSkillToCv']);
    Route::delete('/delete_skills/{cvId}', [CvSkillController::class, 'deleteSkills']);


    Route::post('/add_new_contacts/{cvId}', [CvContactController::class, 'addNewContactsToCv']);
    Route::delete('/delete_contacts/{cvId}', [CvContactController::class, 'deleteContacts']);

    Route::get('/profile_picture/{cvId}', [CvController::class, 'showProfilePicture']);
});



Route::middleware(['auth:sanctum', 'role:Head Department'])->prefix('time_table')->group(function () {
    Route::get('/get_time_slot_department', [TimeTableController::class, 'getTimeSlotsByDepartmentAndGroup']);
    Route::post('/create_time_slot_by_group/{group_id}', [TimeTableController::class, 'createTimeSlotByHeadDepartment']);
});
Route::middleware(['auth:sanctum', 'role:Head Department'])->prefix('departments')->group(function () {
    Route::get('/get_program_by_department/{id}', [DepartmentController::class, 'getProgramByDepartment']);
    Route::get('/get_sub_department_by_department/{id}', [DepartmentController::class, 'getSubDepartmentByDepartment']);
    Route::get('/get_groups_by_program/{program_id}', [ProgramController::class, 'getGroupsByProgram']);
    Route::get('/get_semesters_by_program/{program_id}', [ProgramController::class, 'getSemestersByProgramForHeadDepartment']);

});
Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('training')->group(function () {
    Route::post('/create_new_training', [TrainingController::class, 'createTraining']);

    Route::post('/add_new_trainer', [TrainingController::class, 'addTrainer']);
    Route::post('/add_new_trainee', [TrainingController::class, 'addTrainee']);
    Route::post('/assign_participants/{training_id}', [TrainingController::class, 'assignParticipants']);

    Route::get('/get_all_trainings', [TrainingController::class, 'getAllTrainings']);
    Route::get('/get_training/{training_id}', [TrainingController::class, 'getTraining']);
    Route::get('/get_trainee/{trainee_id}', [TrainingController::class, 'getTrainee']);
    Route::get('/get_trainer/{trainer_id}', [TrainingController::class, 'getTrainer']);
    Route::put('/mark_status_of_trainee/{trainee_id}', [TrainingController::class, 'markStatusOfTrainee']);
    Route::put('/set_training_status/{training_id}', [TrainingController::class, 'setTrainingStatus']);

});



