<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class OtpController extends Controller
{
    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'phone_number' => 'required|string',
        ]);

        $user = User::whereHas('userDetail', function ($query) use ($data) {
            $query->where('phone_number', $data['phone_number']);
        })->first();

        if (!$user) {
            return response()->json(['message' => 'User with this phone not found.'], 404);
        }

        if (!$user->telegram_user_id) {
            return response()->json([
                'message' => 'Your account is not linked to our Telegram bot. Open the bot and send /start.',
            ], 400);
        }

        $otp = random_int(100000, 999999);

        $user->otp_code = Hash::make($otp);
        $user->otp_expires_at = Carbon::now()->addMinutes(3);
        $user->save();

        Http::post("https://api.telegram.org/bot" . config('services.telegram.bot_token') . "/sendMessage", [
            'chat_id' => $user->telegram_user_id,
            'text' => "🔐 Your OTP is: {$otp}\nIt expires in 3 minutes.",
        ]);

        return response()->json(['message' => 'OTP sent to your Telegram.']);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'phone_number' => 'required|string',
            'otp' => 'required|string',
        ]);

        $user = User::whereHas('userDetail', function ($query) use ($data) {
            $query->where('phone_number', $data['phone_number']);
        })->first();

        if (!$user || !$user->otp_code || !$user->otp_expires_at) {
            return response()->json(['message' => 'OTP not requested or user not found.'], 400);
        }

        if (Carbon::parse($user->otp_expires_at)->isPast()) {
            return response()->json(['message' => 'OTP has expired. Please request a new one.'], 400);
        }

        $isValid = Hash::check($data['otp'], $user->otp_code);

        if (!$isValid) {
            return response()->json(['message' => 'Invalid OTP.'], 400);
        }

        // OTP valid: generate reset token
        $resetToken = bin2hex(random_bytes(32));

        $user->otp_code = Hash::make($resetToken);
        $user->otp_expires_at = Carbon::now()->addMinutes(10); // 10 minutes to reset password
        $user->save();

        return response()->json([
            'message' => 'Phone-based OTP verification successful',
            'reset_token' => $resetToken
        ]);
    }
}
