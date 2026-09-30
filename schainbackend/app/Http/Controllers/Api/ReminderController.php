<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        // Get all reminders
        $reminders = Reminder::with(['addedBy', 'assignTo'])->get();
        return response()->json($reminders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'assign_to' => 'required|exists:user_details,user_id',
            'remainder_at' => 'nullable|date',
        ]);

        $reminder = new Reminder($validated);
        $reminder->added_by = $request->user()->user_id ?? null;
        $reminder->save();

        return response()->json($reminder, 201);
    }

    public function show($id)
    {
        $reminder = Reminder::with(['addedBy', 'assignTo'])->findOrFail($id);
        return response()->json($reminder);
    }

    public function update(Request $request, $id)
    {
        $reminder = Reminder::findOrFail($id);
        
        $validated = $request->validate([
            'description' => 'sometimes|string',
            'is_completed' => 'sometimes|boolean',
            'assign_to' => 'sometimes|exists:user_details,user_id',
            'remainder_at' => 'sometimes|date',
            'is_viewed' => 'sometimes|boolean',
        ]);

        $reminder->update($validated);

        return response()->json($reminder);
    }

    public function destroy($id)
    {
        $reminder = Reminder::findOrFail($id);
        $reminder->delete();

        return response()->json(null, 204);
    }
}
