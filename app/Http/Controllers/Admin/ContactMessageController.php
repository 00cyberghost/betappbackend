<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('contact-messages/index', [
            'messages' => ContactMessage::query()
                ->with('user:id,name,email')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function markRead(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->update([
            'status' => 'read',
            'read_at' => now(),
        ]);

        return redirect('/dashboard/contact-messages')->with('success', 'Message marked as read.');
    }
}
