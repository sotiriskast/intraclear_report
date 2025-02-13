<?php

namespace App\Http\Controllers;

use App\Models\UserNotificationRecipient;
use Illuminate\Http\Request;

class UserNotificationRecipientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $recipients = auth()->user()
            ->notificationRecipients()
            ->where('type', 'settlement_report')
            ->get();

        return view('notification-recipients.index', compact('recipients'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:user_notification_recipients,email,NULL,id,user_id,' . auth()->id() . ',type,settlement_report'
            ],
        ]);

        auth()->user()->notificationRecipients()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'type' => 'settlement_report'
        ]);

        return back()->with('success', 'Recipient added successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(UserNotificationRecipient $userNotificationRecipient)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UserNotificationRecipient $userNotificationRecipient)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UserNotificationRecipient $userNotificationRecipient)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UserNotificationRecipient $recipient)
    {
        if ($recipient->user_id !== auth()->id() && !auth()->user()->hasRole('super-admin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $recipient->delete();
            return back()->with('success', 'Recipient removed successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to delete recipient', [
                'error' => $e->getMessage(),
                'recipient_id' => $recipient->id
            ]);
            return back()->with('error', 'Failed to remove recipient');
        }
    }
    public function toggleActive(UserNotificationRecipient $recipient)
    {
        if ($recipient->user_id !== auth()->id()) {
            abort(403);
        }

        $recipient->update(['active' => !$recipient->active]);
        return back()->with('success', 'Recipient status updated successfully');
    }
}
