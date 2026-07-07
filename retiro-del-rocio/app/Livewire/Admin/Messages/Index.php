<?php

namespace App\Livewire\Admin\Messages;

use App\Mail\ContactReply;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $statusFilter = '';

    public ?int $selectedId = null;

    public string $reply = '';

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->reply = '';

        // Mark a brand-new message as read when it's first opened.
        $message = ContactMessage::find($id);
        if ($message && $message->status === 'new') {
            $message->update(['read_at' => now()]);
        }
    }

    public function clearSelection(): void
    {
        $this->selectedId = null;
        $this->reply = '';
        $this->resetValidation();
    }

    public function sendReply(): void
    {
        $message = ContactMessage::find($this->selectedId);
        if (! $message) {
            return;
        }

        $this->validate([
            'reply' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        try {
            Mail::to($message->email)->send(new ContactReply($message, $this->reply));
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Could not send the reply email. Please try again.');

            return;
        }

        $message->update([
            'reply' => $this->reply,
            'status' => 'replied',
            'replied_at' => now(),
            'read_at' => $message->read_at ?? now(),
        ]);

        $this->reply = '';
        $this->dispatch('toast', type: 'success', message: 'Reply sent to '.$message->email.'.');
    }

    public function archive(int $id): void
    {
        $message = ContactMessage::find($id);
        if (! $message) {
            return;
        }

        $message->update(['status' => 'archived']);
        $this->dispatch('toast', type: 'success', message: 'Message archived.');
    }

    public function delete(int $id): void
    {
        ContactMessage::where('id', $id)->delete();

        if ($this->selectedId === $id) {
            $this->clearSelection();
        }

        $this->dispatch('toast', type: 'success', message: 'Message deleted.');
    }

    public function render()
    {
        $messages = ContactMessage::query()
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('message', 'like', "%{$this->search}%")))
            ->latest()
            ->get();

        $selected = $this->selectedId ? ContactMessage::find($this->selectedId) : null;

        $counts = [
            'all' => ContactMessage::count(),
            'new' => ContactMessage::where('status', 'new')->count(),
            'replied' => ContactMessage::where('status', 'replied')->count(),
            'archived' => ContactMessage::where('status', 'archived')->count(),
        ];

        return view('admin.messages.index', compact('messages', 'selected', 'counts'))
            ->layout('components.admin.app', [
                'title' => 'Messages',
                'subtitle' => 'Enquiries from the website contact form',
            ]);
    }
}
