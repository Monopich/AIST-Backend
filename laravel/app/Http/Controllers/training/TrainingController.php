<?php

namespace App\Http\Controllers\training;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\Trainer;
use App\Models\Trainee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrainingController extends Controller
{
    // Create a new training
    public function createTraining(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'nullable|string|max:255',
            'trainer_ids' => 'array',
            'trainee_ids' => 'array',
        ]);

        $training = Training::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'location' => $validated['location'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        if (!empty($validated['trainer_ids'])) {
            $training->trainers()->sync($validated['trainer_ids']);
        }

        if (!empty($validated['trainee_ids'])) {
            $training->trainees()->sync($validated['trainee_ids']);
        }

        return response()->json(['message' => 'Training created', 'training' => $training], 201);
    }

    // Add external trainer
    public function addTrainer(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'organization_name' => 'nullable|string|max:255',
            'major' => 'nullable|string|max:255',
        ]);

        $trainer = Trainer::create($validated);
        return response()->json(['message' => 'Trainer added', 'trainer' => $trainer], 201);
    }

    // Add external trainee
    public function addTrainee(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'organization_name' => 'nullable|string|max:255',
            'major' => 'nullable|string|max:255',
        ]);

        $trainee = Trainee::create($validated);
        return response()->json(['message' => 'Trainee added', 'trainee' => $trainee]);
    }

    // Assign trainers and trainees to a training
    public function assignParticipants(Request $request, $training_id)
    {
        $validated = $request->validate([
            // 'training_id' => 'required|exists:trainings,id',
            'trainer_ids' => 'nullable|array',
            'trainer_ids.*' => 'exists:trainers,id',
            'trainee_ids' => 'nullable|array',
            'trainee_ids.*' => 'exists:trainees,id',
        ]);

        $training = Training::findOrFail($training_id);
        

        $trainerIds = $validated['trainer_ids'] ?? [];
        $traineeIds = $validated['trainee_ids'] ?? [];

        if (empty($trainerIds) && empty($traineeIds)) {
            return response()->json([
                'message' => 'At least one trainer or trainee is required.'
            ], 422);
        }

        $existingTrainerIds = [];
        $existingTraineeIds = [];

        if (!empty($trainerIds)) {
            $existingTrainerIds = $training->trainers()
                ->whereIn('trainers.id', $trainerIds)
                ->pluck('trainers.id')
                ->all();
        }

        if (!empty($traineeIds)) {
            $existingTraineeIds = $training->trainees()
                ->whereIn('trainees.id', $traineeIds)
                ->pluck('trainees.id')
                ->all();
        }

        if (!empty($existingTrainerIds) || !empty($existingTraineeIds)) {
            return response()->json([
                'message' => 'Some participants are already assigned to this training.',
                'existing_trainer_ids' => $existingTrainerIds,
                'existing_trainee_ids' => $existingTraineeIds,
            ], 422);
        }

        if (!empty($trainerIds)) {
            $training->trainers()->syncWithoutDetaching($trainerIds);
        }

        if (!empty($traineeIds)) {
            $traineeSyncData = [];
            foreach ($traineeIds as $traineeId) {
                $traineeSyncData[$traineeId] = ['status' => 'enrolled'];
            }
            $training->trainees()->syncWithoutDetaching($traineeSyncData);
        }

        return response()->json(['message' => 'Participants assigned successfully'], 200);
    }

    // Get trainer details with user info if internal
    public function getTrainer($id)
    {
        $trainer = Trainer::with('user.userDetail.program')->findOrFail($id);

        $data = [
            'id' => $trainer->id,
            'name' => $trainer->user && $trainer->user->userDetail
                ? $trainer->user->userDetail->latin_name
                : $trainer->name,
            'email' => $trainer->user ? $trainer->user->email : $trainer->email,
            'organization_name' => $trainer->user ? 'RTC_BATTAMBANG' : $trainer->organization_name,
            'major' => $trainer->user && $trainer->user->userDetail && isset($trainer->user->userDetail->program)
                ? $trainer->user->userDetail->program->name
                : $trainer->major,
            'is_internal' => $trainer->user_id ? true : false,
        ];

        return response()->json($data);
    }

    // Get trainee details with user info if internal
    public function getTrainee($id)
    {
        $trainee = Trainee::with('user.userDetail.program')->findOrFail($id);

        $data = [
            'id' => $trainee->id,
            'name' => $trainee->user && $trainee->user->userDetail
                ? $trainee->user->userDetail->latin_name
                : $trainee->name,
            'email' => $trainee->user ? $trainee->user->email : $trainee->email,
            'organization_name' => $trainee->user ? 'RTC_BATTAMBANG' : $trainee->organization_name,
            'major' => $trainee->user && $trainee->user->userDetail && isset($trainee->user->userDetail->program)
                ? $trainee->user->userDetail->program->name
                : $trainee->major,
            'is_internal' => $trainee->user_id ? true : false,
        ];

        return response()->json($data);
    }

    // Get training with all trainers and trainees with proper details
    public function getTraining($id)
    {
        $training = Training::with([
            'trainers.user.userDetail.program',
            'trainees.user.userDetail.program'
        ])->findOrFail($id);

        $trainers = $training->trainers->map(function ($trainer) {
            return [
                'id' => $trainer->id,
                'name' => $trainer->user && $trainer->user->userDetail
                    ? $trainer->user->userDetail->latin_name
                    : $trainer->name,
                'email' => $trainer->user ? $trainer->user->email : $trainer->email,
                'organization_name' => $trainer->user ? 'RTC_BATTAMBANG' : $trainer->organization_name,
                'major' => $trainer->user && $trainer->user->userDetail && isset($trainer->user->userDetail->program)
                    ? $trainer->user->userDetail->program->name
                    : $trainer->major,
                'is_internal' => $trainer->user_id ? true : false,
            ];
        });

        $trainees = $training->trainees->map(function ($trainee) {
            return [
                'id' => $trainee->id,
                'name' => $trainee->user && $trainee->user->userDetail
                    ? $trainee->user->userDetail->latin_name
                    : $trainee->name,
                'email' => $trainee->user ? $trainee->user->email : $trainee->email,
                'organization_name' => $trainee->user ? 'RTC_BATTAMBANG' : $trainee->organization_name,
                'major' => $trainee->user && $trainee->user->userDetail && isset($trainee->user->userDetail->program)
                    ? $trainee->user->userDetail->program->name
                    : $trainee->major,
                'status' => $trainee->pivot->status,
                'is_internal' => $trainee->user_id ? true : false,
            ];
        });

        $data = [
            'id' => $training->id,
            'title' => $training->title,
            'description' => $training->description,
            'start_date' => $training->start_date,
            'end_date' => $training->end_date,
            'location' => $training->location,
            'status' => $training->status,
            'trainers' => $trainers,
            'trainees' => $trainees,
        ];

        return response()->json($data);
    }

    // Get all trainings with trainers and trainees details
    public function getAllTrainings(Request $request)
    {
        $trainings = Training::with([
            'trainers.user.userDetail.program',
            'trainees.user.userDetail.program'
        ])->orderBy('start_date', 'desc')->get();

        $data = $trainings->map(function ($training) {
            $trainers = $training->trainers->map(function ($trainer) {
                return [
                    'id' => $trainer->id,
                    'name' => $trainer->user && $trainer->user->userDetail
                        ? $trainer->user->userDetail->latin_name
                        : $trainer->name,
                    'email' => $trainer->user ? $trainer->user->email : $trainer->email,
                    'organization_name' => $trainer->user ? 'RTC_BATTAMBANG' : $trainer->organization_name,
                    'major' => $trainer->user && $trainer->user->userDetail && isset($trainer->user->userDetail->program)
                        ? $trainer->user->userDetail->program->name
                        : $trainer->major,
                    'is_internal' => $trainer->user_id ? true : false,
                ];
            });

            $trainees = $training->trainees->map(function ($trainee) {
                return [
                    'id' => $trainee->id,
                    'name' => $trainee->user && $trainee->user->userDetail
                        ? $trainee->user->userDetail->latin_name
                        : $trainee->name,
                    'email' => $trainee->user ? $trainee->user->email : $trainee->email,
                    'organization_name' => $trainee->user ? 'RTC_BATTAMBANG' : $trainee->organization_name,
                    'major' => $trainee->user && $trainee->user->userDetail && isset($trainee->user->userDetail->program)
                        ? $trainee->user->userDetail->program->name
                        : $trainee->major,
                    'status' => $trainee->pivot->status,
                    'is_internal' => $trainee->user_id ? true : false,
                ];
            });

            return [
                'id' => $training->id,
                'title' => $training->title,
                'description' => $training->description,
                'start_date' => $training->start_date,
                'end_date' => $training->end_date,
                'location' => $training->location,
                'status' => $training->status,
                'trainers' => $trainers,
                'trainees' => $trainees,
            ];
        });

        return response()->json($data);
    }

    public function markStatusOfTrainee(Request $request, $trainee_id)
    {
        $validated = $request->validate([
            'training_id' => 'required|exists:trainings,id',
            'status' => 'required|string|max:50',
        ]);

        $training = Training::findOrFail($validated['training_id']);
        $trainee = Trainee::findOrFail($trainee_id);
        $allowStatus = ['enrolled','attended','completed'];

        if(in_array($validated['status'], $allowStatus) == false){
            return response()->json([
                'status' => 'error',
                'code' => 422,
                'message' => 'Invalid status value. Allowed values are: '.implode(',', $allowStatus),
            ], 422);
        }

        $training->trainees()->updateExistingPivot($trainee->id, ['status' => $validated['status']]);

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Trainee status updated successfully',
            'trainee_id' => $trainee->id,
            'training_id' => $training->id,
            'new_status' => $validated['status'],
        ], 200);
    }

    public function setTrainingStatus(Request $request, $training_id)
    {
        $validated = $request->validate([
            'status' => 'required|string|max:50',
        ]);

        $allowStatus = ['planned','ongoing','completed'];

        if (in_array($validated['status'], $allowStatus) == false) {
            return response()->json([
                'status' => 'error',
                'code' => 422,
                'message' => 'Invalid status value. Allowed values are: '.implode(',', $allowStatus),
            ], 422);
        }

        $training = Training::findOrFail($training_id);
        $training->status = $validated['status'];
        $training->save();

        if ($training->status === 'completed') {
            DB::table('training_trainee')
                ->where('training_id', $training->id)
                ->where('status', 'attended')
                ->update(['status' => 'completed']);
        }

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Training status updated successfully',
            'training_id' => $training->id,
            'new_status' => $training->status,
            'trainees_updated' => $training->status === 'completed' ? true : false,
        ], 200);
    }
}
