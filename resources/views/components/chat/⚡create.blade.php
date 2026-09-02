<?php

use App\Models\Message;
use Livewire\Component;

new class extends Component
{
    public int $userId;
    public int $minLength = 3;
    public string $messageText = '';

    public function sendMessage(): void
    {
        $this->validate([
            'messageText' => ['required', 'string', 'min:' . $this->minLength, 'max:100'],
        ]);

        Message::create([
            'user_id' => $this->userId,
            'message_text' => $this->messageText,
        ]);

        $this->reset('messageText');
    }
};
?>

<div>
    <div class="flex items-center gap-2 p-4">
        <form wire:submit="sendMessage">
            <flux:field class="flex-1">
                <flux:input
                    wire:model="messageText"
                    class="border border-gray-300 rounded-full"
                />
                <flux:error name="messageText" />
            </flux:field> 

            <flux:button type="submit" variant="primary" color="cyan">
                Send
            </flux:button>
        </form>
    </div>
</div>