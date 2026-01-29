<?php

namespace App\Http\Controllers\attendance;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\QrCode;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode as FacadesQrCode;
use Str;
use Intervention\Image\Facades\Image;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;




class QrCodeController extends Controller
{

    public function generateQrCode(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
        ]);

        // Only one QR per location
        $existing = QrCode::where('location_id', $validated['location_id'])->first();
        if ($existing) {
            return response()->json([
                'message' => 'This location already has a QR code.',
                'qr_code' => $existing
            ], 422);
        }

        $token = Str::random(32);


        $location = Location::findOrFail($validated['location_id']);
        $floorNumber = $location->floor ?? 'unknown';
        $fileName = 'qr_location_' . $location->name . '_floor_' . $floorNumber . '.png';
        $filePath = 'qr_codes/' . $fileName;

        DB::beginTransaction();
        try {
            // Generate QR code and store it using Storage facade
            $qrCodeContent = json_encode(['location_id' => $validated['location_id'], 'code' => $token]);

            $qrCodeImage = Builder::create()
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

            Storage::disk('public')->put($filePath, $qrCodeImage->getString());

            $qrCode = QrCode::create([
                'location_id' => $validated['location_id'],
                'code' => $token,
                'image_path' => $filePath,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            Log::error('QR code generation failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to generate QR code.', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'QR Code generated successfully.',
            'qr_code' => [
                'token' => $token,
                'location_id' => $validated['location_id'],
                'image_path' => asset('storage/' . $qrCode->image_path),
                'image_path_online' => asset('api_smis_hospital/storage/' . $qrCode->image_path),
            ]
        ]);
    }

    public function reGenerateQrCode($id)
    {
        $qrCode = QrCode::with('location')->findOrFail($id);

        // Delete old QR image if it exists
        if (Storage::disk('public')->exists($qrCode->image_path)) {
            Storage::disk('public')->delete($qrCode->image_path);
        }

        // Generate new token
        $qrCode->code = Str::random(32);

        // Filename format
        $floorNumber = $qrCode->location ? $qrCode->location->floor : 'unknown';
        $fileName = 'qr_location_' . $qrCode->location->name . '_floor_' . $floorNumber . '.png';
        $filePath = 'qr_codes/' . $fileName;

        DB::beginTransaction();
        try {

            // Generate QR code with logo
            $qr = Builder::create()
                ->data(json_encode(['location_id' => $qrCode->location_id, 'code' => $qrCode->code]))
                ->encoding(new Encoding('UTF-8'))
                ->size(1000)
                ->margin(2)
                ->errorCorrectionLevel(ErrorCorrectionLevel::High)
                ->logoPath(storage_path('app/public/logo.png')) // adjust path
                ->logoResizeToWidth(300)
                ->logoResizeToHeight(300)
                // ->logoPunchoutBackground(true)

                ->foregroundColor(new Color(26, 35, 126))    // Deep Indigo Blue
                ->backgroundColor(new Color(255, 255, 255))  // White
                ->build();

            // $filePath = 'qr_codes/qr_location_'.$qrCode->location_id.'.png';
            // Save QR image
            Storage::disk('public')->put($filePath, $qr->getString());

            $qrCode->image_path = $filePath;
            $qrCode->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            Log::error('QR regeneration failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Failed to regenerate QR code.',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'QR Code regenerated successfully.',
            'qr_code' => [
                'token' => $qrCode->code,
                'location_id' => $qrCode->location_id,
                'image_path' => asset('storage/' . $qrCode->image_path)
            ]
        ]);
    }



    public function getAllQrCodes()
    {
        $qrCodes = QrCode::with('location')->get();

        $qrCodesData = $qrCodes->map(function ($qr) {
            return [
                'id' => $qr->id,
                'token' => $qr->code,
                'location' => $qr->location,
                'qr_url' => asset('storage/' . $qr->image_path), // correct public URL
                // 'image_path_online' => asset('api_smis_hospital/storage/' . $qr->image_path), // fixed
            ];
        });

        return response()->json([
            'message' => 'QR Codes retrieved successfully.',
            'qr_codes' => $qrCodesData
        ]);
    }
}
