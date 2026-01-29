<?php

namespace App\Http\Controllers\location;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Location;

use App\Models\QrCode;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode as FacadesQrCode;
use Str;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;

class LocationController extends Controller
{
    public function createNewBuilding(Request $request)
    {

        $buildingValidated = $request->validate([
            'name' => 'required|string|unique:buildings,name'
        ]);

        $building = Building::create([
            'name' => $buildingValidated['name']
        ]);

        return response()->json([
            'message' => 'Create new building successful.',
            'building' => $building,
        ]);
    }

    public function getAllBuilding(Request $request)
    {
        $perPage = $request->input('per_page', 14);
        $buildings = Building::paginate($perPage);

        if ($buildings->isEmpty()) {
            return response()->json([
                'message' => 'No building available .'
            ]);
        }

        return response()->json([
            'message' => "list all buildings",
            'buildings' => $buildings
        ]);
    }

    public function updateBuilding(Request $request, $id)
    {

        $foundBuilding = Building::find($id);

        $buildingValidated = $request->validate([
            'name' => 'required|string'
        ]);

        $foundBuilding->update([
            'name' => $buildingValidated['name']
        ]);

        return response()->json([
            'message' => 'Updated building successful.',
            'building' => $foundBuilding,
        ]);
    }

    public function removeBuilding(Request $request, $id)
    {
        $foundBuilding = Building::find($id);

        if (!$foundBuilding) {
            return response()->json([
                'message' => 'Building is not found.'
            ]);
        }

        $foundBuilding->delete();

        return response()->json([
            'message' => 'Building deleted successful.'
        ]);
    }


    public function createNewRoom(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:locations,name',
            'floor' => 'nullable|numeric',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'wifi_ssid' => 'nullable|string',
            'building_id' => 'required|integer|exists:buildings,id',
        ]);

        DB::beginTransaction();

        try {
            // Create new room
            $room = Location::create([
                'name' => $validated['name'],
                'floor' => $validated['floor'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'building_id' => $validated['building_id'],
                'wifi_ssid' => $validated['wifi_ssid'],
            ]);

            $token = Str::random(32);

            // QR filename
            $floorNumber = $room->floor ?? 'unknown';
            $fileName = 'qr_location_' . $room->name . '_floor_' . $floorNumber . '.png';
            $filePath = 'qr_codes/' . $fileName;

            // QR content
            $qrCodeContent = json_encode([
                'location_id' => $room->id,
                'code' => $token
            ]);

            // Build QR code with Endroid Builder
            $qr = Builder::create()
                ->data($qrCodeContent)
                ->encoding(new Encoding('UTF-8'))
                 ->size(1000)
                ->margin(2)
                ->errorCorrectionLevel(ErrorCorrectionLevel::High) 
                ->logoPath(storage_path('app/public/logo.png')) // adjust path
                ->logoResizeToWidth(300)
                ->logoResizeToHeight(300)
                ->foregroundColor(new Color(26, 35, 126))    // Deep Indigo Blue
                ->backgroundColor(new Color(255, 255, 255))  // White
                ->build();

            // Save QR image
            Storage::disk('public')->put($filePath, $qr->getString());

            // Save QR record
            $qrCode = QrCode::create([
                'location_id' => $room->id,
                'code' => $token,
                'image_path' => $filePath,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($filePath) && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            Log::error('QR code generation failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Failed to generate QR code.',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'New location created successfully with QR code.',
            'room' => $room,
            'image_path' => asset('storage/' . $qrCode->image_path),
            // 'image_path_online' => asset('api_smis_hospital/storage/' . $qrCode->image_path),
        ]);
    }

    public function updateRoom(Request $request, $id)
    {
        // Find the existing room or fail with 404
        $room = Location::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('locations', 'name')
                    ->where('building_id', $request->building_id)
                    ->ignore($id)
            ],
            'floor' => 'nullable|numeric',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'building_id' => 'required|integer|exists:buildings,id',
            'wifi_ssid' => 'nullable|string'
        ], [
            'name.unique' => 'The location name must be unique within the selected building.',
        ]);

        // Update the room
        $room->update($validated);

        return response()->json([
            'message' => 'Location updated successfully.',
            'room' => $room,
        ]);
    }

    public function removeRoom($id)
    {
        $room = Location::find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Location not found.'
            ], 404);
        }

        // Delete QR code image if exists
        $qrCode = QrCode::where('location_id', $room->id)->first();
        if ($qrCode && Storage::disk('public')->exists($qrCode->image_path)) {
            Storage::disk('public')->delete($qrCode->image_path);
        }

        // Delete QR record
        if ($qrCode) {
            $qrCode->delete();
        }

        // Delete the room
        $room->delete();

        return response()->json([
            'message' => 'Location and QR code deleted successfully.'
        ]);
    }


    public function filterRoomByBuilding(Request $request, $building_id)
    {

        $per_page = $request->input('per_page', 14);
        $floor = $request->input('floor');

        $locationsQuery = Location::where('building_id', $building_id);

        if (!is_null($floor)) {
            $locationsQuery->where('floor', $floor);
        }

        $locations = $locationsQuery->paginate($per_page);


        if ($locations->isEmpty()) {
            return response()->json([
                'message' => 'Not found any locations for this building.',
            ], 404);
        }

        $building = Building::findOrFail($building_id);
        return response()->json([
            'message' => "Locations for '{$building->name}' retrieved successfully.",
            'building' => $building,
            'Locations' => $locations,
        ]);
    }

    public function getDetailLocation(Request $request, $id)
    {
        $location = Location::with('building', 'qrCode')->find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Location not found.'
            ], 404);
        }

        $qrCodeUrl = $location->qrCode ? asset('storage/' . $location->qrCode->image_path) : null;
        $qrCodeUrlOnline = $location->qrCode ? asset('api_smis_hospital/storage/' . $location->qrCode->image_path) : null;


        return response()->json([
            'message' => 'Location details retrieved successfully.',
            'location' => $location,
            'qr_code_url' => $qrCodeUrl,
            // 'qr_code_url_online' => $qrCodeUrlOnline
        ]);
    }


    public function getAllLocations(Request $request)
    {

        $perPage = $request->input('per_page', 14);
        $locations = Location::with('building')->paginate($perPage);

        if ($locations->isEmpty()) {
            return response()->json([
                'message' => 'No location available .'
            ]);
        }

        return response()->json([
            'message' => "list all location",
            'locations' => $locations
        ]);
    }


    public function searchLocations(Request $request)
    {
        $query = $request->query('search');
        $per_page = $request->input('per_page');

        if (!$per_page) {
            $per_page = 14;
        }
        // $locations = Location::where('name', 'like', $query)->paginate($per_page);

        $locations = Location::with([
            'building' => function ($q) {
                $q->select('id', 'name');
            }
        ])
            ->where('name', 'like', "%{$query}%")
            ->orWhereHas('building', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->paginate($per_page);

        if ($locations->isEmpty()) {
            return response()->json([
                'message' => "No result that match with $query",
            ]);
        }
        return response()->json([
            'message' => "Result that match with $query",
            'locations' => $locations
        ]);
    }

    /**
     * Get available locations for a specific date and time range
     * Excludes locations that are already booked by other groups
     */
    public function getAvailableLocations(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s',
            'exclude_slot_id' => 'nullable|exists:time_slots,id', // For edit mode
        ]);

        $date = $validated['date'];
        $startTime = $validated['start_time'];
        $endTime = $validated['end_time'];
        $excludeSlotId = $validated['exclude_slot_id'] ?? null;

        // Get all booked location IDs for this date and time
        $bookedLocationIds = \App\Models\TimeSlot::where('time_slot_date', $date)
            ->when($excludeSlotId, function ($query) use ($excludeSlotId) {
                // Exclude the current slot being edited
                $query->where('id', '!=', $excludeSlotId);
            })
            ->get()
            ->filter(function ($slot) use ($startTime, $endTime) {
                $slotTime = is_array($slot->time_slot) ? $slot->time_slot : json_decode($slot->time_slot, true);
                $slotStart = $slotTime['start_time'];
                $slotEnd = $slotTime['end_time'];
                
                // Check for time overlap
                return $startTime < $slotEnd && $endTime > $slotStart;
            })
            ->pluck('location_id')
            ->unique()
            ->filter(); // Remove null values

        // Get all locations except the booked ones
        $availableLocations = Location::with('building')
            ->whereNotIn('id', $bookedLocationIds)
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Available locations retrieved successfully.',
            'locations' => $availableLocations,
            'booked_count' => $bookedLocationIds->count(),
        ]);
    }
}
