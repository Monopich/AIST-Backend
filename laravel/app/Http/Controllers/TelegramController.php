<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class TelegramController extends Controller
{
    public function handle(Request $request)
    {
        $update = $request->all();
        $message = $update['message'] ?? null;

        if (!$message) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'];
        $text = trim($message['text'] ?? '');

        if ($text === '/start') {
            $this->sendTelegramMessage(
                $chatId,
                "Hi! Please send your phone number to link your account (e.g. +959123456789)."
            );
            return response()->json(['ok' => true]);
        }

        // Treat anything that looks like a phone as phone number
        if (preg_match('/^\+?\d{6,15}$/', $text)) {
            $phone = $text;

            $user = User::whereHas('userDetail', function ($query) use ($phone) {
                $query->where('phone_number', $phone);
            })->first();

            if (!$user) {
                $this->sendTelegramMessage($chatId, "Phone number not found in our system.");
                return response()->json(['ok' => true]);
            }

            $user->telegram_user_id = (string) $chatId;
            $user->save();

            $this->sendTelegramMessage($chatId, "✅ Your account is linked successfully.");
            return response()->json(['ok' => true]);
        }

        // Fallback
        $this->sendTelegramMessage(
            $chatId,
            "Please send /start first, then send your phone number (e.g. +959123456789)."
        );

        return response()->json(['ok' => true]);
    }

    protected function sendTelegramMessage($chatId, $text)
    {
        $token = config('services.telegram.bot_token');

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
}