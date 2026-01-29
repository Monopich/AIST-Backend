<?php

namespace App\Http\Controllers\feedback;

use App\Http\Controllers\Controller;
use App\Models\FeedBack;
use Illuminate\Http\Request;

class FeedBackController extends Controller
{
    public function submitFeedback(Request $request){

        $validated = $request->validate([
            'email' => 'nullable|string',
            'remark' => 'required|string'
        ]);
        $feedback = FeedBack::create([
            'email' => $validated['email'] ?? null,
            'remark' => $validated['remark'],
        ]);

        return response()->json([
            'message' => "Your feedback submitted successful .",
            'feedback' => $feedback
        ]);

    }

    public function getFeedback(Request $request){

        $perPage = $request->input('per_page');
        $feedbacks = FeedBack::orderBy('created_at', 'desc')->paginate($perPage);

        if($feedbacks->isEmpty()){
            return response()->json([
            'message' => "Feedbacks not available .",
        ],404);
        }
        return response()->json([
            'message' => "Received feedbacks successful .",
            'feedbacks' => $feedbacks
        ]);
    }

    // public function getAllFeedMyFeedbacks(){
    //     $perPage = $request->input('per_page');
    //     $feedbacks = FeedBack::orderBy('created_at', 'desc')->paginate($perPage);
    // }


}
