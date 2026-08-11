<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Services\HybridAttendanceScanService;

class AttendanceController extends Controller
{
    public function showScanner()
    {
        return view('attendance.scan', [
            'logoutFeedbackEnabled' => Setting::logoutFeedbackEnabled(),
        ]);
    }

    public function feedbackSettings()
    {
        return view('attendance.feedback_settings', [
            'enabled' => Setting::logoutFeedbackEnabled(),
        ]);
    }

    public function updateFeedbackSettings(Request $request)
    {
        $request->validate([
            'enabled' => 'required|in:0,1',
        ]);

        Setting::setLogoutFeedbackEnabled($request->input('enabled') === '1');

        return back()->with(
            'success',
            $request->input('enabled') === '1'
                ? 'Logout feedback is now enabled on the attendance scanner.'
                : 'Logout feedback is now disabled on the attendance scanner.'
        );
    }

    public function scan(Request $request, HybridAttendanceScanService $scanService)
    {
        $request->validate(['qrcode' => 'required|string']);

        $patron = $scanService->resolvePatron($request->qrcode);

        if (! $patron) {
            return response()->json([
                'type' => 'error',
                'message' => 'RFID not recognized.',
            ]);
        }

        $model = $patron['type'] === 'student' ? $patron['student'] : $patron['employee'];
        $log = $scanService->recordLiveScan($model);

        return response()->json($scanService->patronJsonResponse($model, $log));
    }

    // Show the change video page
    public function showChangeVideo() {
        return view('attendance.change_video');
    }
    
    // Handle video upload
    public function uploadVideo(Request $request) {
        $request->validate([
            'video' => 'required|file|mimes:mp4|max:512000', // 500MB
        ]);
    
        $video = $request->file('video');
        $filename = 'area51_product_slideshow.mp4'; // overwrite existing
        $video->move(base_path('videos'), $filename);
    
        return redirect()->route('attendance.changeVideo')->with('success', 'Video uploaded successfully!');
    }

}
