<?php

namespace App\Http\Controllers;

use File;
use Illuminate\Http\Request;
use Storage;

class testController extends Controller
{
    public function hello(){

        return response()->json([
            'message' => 'Hello, World!'
        ]);
    }
    public function checkLogs(Request $request)
    {
        // Path to Laravel log file
        $logFile = storage_path('logs/laravel.log');

        if (!File::exists($logFile)) {
            return response()->json(['message' => 'Log file not found'], 404);
        }

        // Get last 60 lines
        $lines = $this->tailCustom($logFile, 60);

        return response()->json([
            'logs' => $lines
        ]);
    }

    private function tailCustom($file, $lines = 60)
    {
        $handle = fopen($file, "r");
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];

        while ($linecounter > 0) {
            $t = "";
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            $text[] = fgets($handle);
            if ($beginning) break;
        }
        fclose($handle);

        return array_reverse($text); // last logs in correct order
    }

     protected $logFile = 'logs/activity.log';

    /**
     * List all activity logs.
     */
    public function getActivitiesLog(Request $request)
    {
        $filePath = storage_path('logs/activity.log'); // Direct path to your log file

        if (!File::exists($filePath)) {
            return response()->json([
                'message' => 'No activity logs found.'
            ], 404);
        }

        $logs = File::get($filePath);

        $logLines = collect(explode("\n", $logs))
            ->filter() // remove empty lines
            ->reverse() // newest first
            ->take(1000) // get only newest 1000
            ->values();

        return response()->json([
            'message' => 'Activity logs retrieved successfully.',
            'data' => $logLines,
            'total' => $logLines->count(),
        ]);
    }
}
