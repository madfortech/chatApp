<?php

use App\Models\Message;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view([
            'messages' => Message::all(),
        ]);
    }
};
?>

<div>
    @foreach ($messages as $message)
    <div class="w-full border-r mb-4 border-b border-l border-grey-200 lg:border-l-0 lg:border-t rounded-b lg:rounded-b-none lg:rounded-r p-4 flex flex-col justify-between leading-normal">
        
        <div class="flex items-center">
            
            <div class="text-sm">
                <p class="text-black leading-none">{{ $message->user->name }}</p>
                <p class="text-black leading-none">{{ $message->message_text }}</p>
                <p class="text-grey-dark">{{ $message->created_at->diffForHumans() }}</p>
            </div>
        </div>

    </div>
    @endforeach
</div>