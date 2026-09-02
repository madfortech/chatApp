<x-app-layout>

    <flux:header
        container
        class="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700"
    >
        <div class="grid min-h-[320px] grid-cols-1 items-center gap-10 py-10 md:grid-cols-2">

            {{-- Left --}}
            <div>
                <h1 class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    Connect, Chat & Video Call
                </h1>

                <p class="mt-4 max-w-xl text-lg text-zinc-600 dark:text-zinc-400">
                    Real-time messaging, video calls, voice calls and more.
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <flux:button variant="primary" color="orange">
                        Start Chatting
                    </flux:button>

                    <flux:button variant="primary" color="amber">
                        Video Call
                    </flux:button>
                </div>

                <div class="mt-6 flex flex-wrap gap-5 text-sm text-zinc-500">
                    <span>💬 Real-time Chat</span>
                    <span>📹 Video Call</span>
                    <span>🎤 Voice Call</span>
                </div>
            </div>


            {{-- Right Image --}}
            <div class="flex justify-center md:justify-end">
                <div class="w-full max-w-md overflow-hidden rounded-3xl border border-zinc-200 bg-white p-3 shadow-xl dark:border-zinc-700 dark:bg-zinc-800">

                    <img
                        src="{{ asset('images/video-chat.png') }}"
                        alt="Chat and video call"
                        class="h-auto w-full rounded-2xl object-cover"
                    >

                </div>
            </div>

        </div>
    </flux:header>

</x-app-layout>