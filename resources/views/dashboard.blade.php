<x-app-layout>

    <flux:main container>

        <div class="grid min-h-[calc(100vh-6rem)] grid-cols-1 gap-4 lg:grid-cols-[1fr_380px]">

            <!-- Left: Video -->
            <section class="grid min-h-0 grid-rows-[1fr_180px] gap-4">

                <!-- Main Video -->
                <div class="flex items-center justify-center rounded-2xl bg-zinc-950 p-4 shadow-sm">

                    <div class="relative flex aspect-video w-full items-center justify-center overflow-hidden rounded-xl bg-zinc-900 text-zinc-400">

                        <video
                            id="remoteVideo"
                            autoplay
                            playsinline
                            class="h-full w-full object-cover"
                        ></video>

                        <span
                            id="remotePlaceholder"
                            class="absolute text-zinc-400"
                        >
                            Waiting for video...
                        </span>

                    </div>

                </div>


                <!-- Bottom Video / Controls -->
                <div class="grid grid-cols-2 gap-4">

                    <!-- Your Video -->
                    <div class="relative flex items-center justify-center overflow-hidden rounded-2xl bg-zinc-950 text-zinc-400">

                        <video
                            id="localVideo"
                            autoplay
                            muted
                            playsinline
                            class="h-full w-full object-cover"
                        ></video>

                        <span
                            id="localPlaceholder"
                            class="absolute text-zinc-400"
                        >
                            Camera Off
                        </span>
                        

                    </div>


                    <!-- Video Controls -->
                    <div class="flex items-center justify-center rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">

                        <div class="flex gap-3">

                            <flux:button
                                id="cameraButton"
                                type="button"
                                variant="primary"
                                color="cyan"
                            >
                                Camera
                            </flux:button>

                            <flux:button
                                id="micButton"
                                type="button"
                                variant="primary"
                            >
                                Mic
                            </flux:button>

                        </div>

                    </div>

                </div>

            </section>


            <!-- Chat Section -->
            <aside class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">

                <div class="flex w-full flex-col px-4 py-2">

                    <!-- Messages -->
                    <div class="scrollbar-thin h-96 overflow-auto px-4 py-2">

                        <livewire:chat.index />

                    </div>

                    <!-- Form -->
                    <div class="w-full">

                        <livewire:chat.create :userId="auth()->id()" />

                    </div>

                </div>

            </aside>

        </div>

    </flux:main>

<!-- WebRTC Camera -->
<script>
    /*
    |--------------------------------------------------------------------------
    | WebRTC Random Video Chat
    |--------------------------------------------------------------------------
    */

    let localStream = null;
    let peerConnection = null;

    let remoteUserId = null;

    let cameraEnabled = false;
    let micEnabled = true;

    let matchTimer = null;
    let secondsRemaining = 60;

    let pendingIceCandidates = [];
    let outgoingIceCandidates = [];
    let iceBatchTimer = null;

    let isMakingOffer = false;
    let isLeavingCall = false;


    /*
    |--------------------------------------------------------------------------
    | Current User
    |--------------------------------------------------------------------------
    */

    const currentUserId = {{ auth()->id() }};


    /*
    |--------------------------------------------------------------------------
    | Elements
    |--------------------------------------------------------------------------
    */

    const localVideo =
        document.getElementById('localVideo');

    const remoteVideo =
        document.getElementById('remoteVideo');

    const localPlaceholder =
        document.getElementById('localPlaceholder');

    const remotePlaceholder =
        document.getElementById('remotePlaceholder');

    const cameraButton =
        document.getElementById('cameraButton');

    const micButton =
        document.getElementById('micButton');


    /*
    |--------------------------------------------------------------------------
    | WebRTC Configuration
    |--------------------------------------------------------------------------
    */
    const rtcConfiguration = {
        iceServers: [
            {
                urls: "stun:stun.relay.metered.ca:80",
            },
            {
                urls: "turn:global.relay.metered.ca:80",
                username: @json(config('services.metered.turn_username')),
                credential: @json(config('services.metered.turn_credential')),
            },
            {
                urls: "turn:global.relay.metered.ca:80?transport=tcp",
                username: @json(config('services.metered.turn_username')),
                credential: @json(config('services.metered.turn_credential')),
            },
            {
                urls: "turn:global.relay.metered.ca:443",
                username: @json(config('services.metered.turn_username')),
                credential: @json(config('services.metered.turn_credential')),
            },
            {
                urls: "turns:global.relay.metered.ca:443?transport=tcp",
                username: @json(config('services.metered.turn_username')),
                credential: @json(config('services.metered.turn_credential')),
            },
        ],
    };


    /*
    |--------------------------------------------------------------------------
    | CSRF Token
    |--------------------------------------------------------------------------
    */

    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');


    /*
    |--------------------------------------------------------------------------
    | Start Camera
    |--------------------------------------------------------------------------
    */

    async function startCamera() {

        try {

            /*
             * Agar already camera hai
             * to dobara permission mat maango.
             */
            if (localStream) {

                const existingVideo =
                    localStream.getVideoTracks()[0];

                if (
                    existingVideo &&
                    existingVideo.readyState === 'live'
                ) {

                    cameraEnabled = true;

                    updateCameraButton();

                    return;
                }
            }


            /*
             * Camera + Mic
             */
            localStream =
                await navigator.mediaDevices.getUserMedia({

                    video: true,

                    audio: true

                });


            /*
             * Local video
             */
            if (localVideo) {

                localVideo.srcObject =
                    localStream;

                try {

                    await localVideo.play();

                } catch (error) {

                    console.log(
                        'Local video play:',
                        error
                    );
                }
            }


            /*
             * Camera state
             */
            cameraEnabled = true;


            /*
             * Placeholder hide
             */
            if (localPlaceholder) {

                localPlaceholder.classList.add(
                    'hidden'
                );

            }


            /*
             * Mic state
             */
            const audioTracks =
                localStream.getAudioTracks();

            audioTracks.forEach(track => {

                track.enabled =
                    micEnabled;

            });


            /*
             * Existing WebRTC connection
             */
            if (peerConnection) {

                const videoTrack =
                    localStream.getVideoTracks()[0];

                const videoSender =
                    peerConnection
                        .getSenders()
                        .find(sender =>
                            sender.track?.kind === 'video'
                        );


                if (videoSender) {

                    await videoSender.replaceTrack(
                        videoTrack
                    );

                } else if (videoTrack) {

                    peerConnection.addTrack(
                        videoTrack,
                        localStream
                    );

                }


                /*
                 * Audio
                 */
                const audioTrack =
                    localStream.getAudioTracks()[0];

                const audioSender =
                    peerConnection
                        .getSenders()
                        .find(sender =>
                            sender.track?.kind === 'audio'
                        );


                if (audioSender && audioTrack) {

                    await audioSender.replaceTrack(
                        audioTrack
                    );

                } else if (audioTrack) {

                    peerConnection.addTrack(
                        audioTrack,
                        localStream
                    );

                }

            }


            updateCameraButton();

            console.log(
                'Camera ON'
            );


        } catch (error) {

            console.error(
                'Camera error:',
                error
            );

            cameraEnabled = false;

            updateCameraButton();

            alert(
                'Camera aur microphone permission allow karein.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Stop Camera
    |--------------------------------------------------------------------------
    */

    function stopCamera() {

        if (!localStream) {

            cameraEnabled = false;

            updateCameraButton();

            return;
        }


        /*
         * Stop all tracks
         */
        localStream
            .getTracks()
            .forEach(track => {

                track.stop();

            });


        localStream = null;


        /*
         * Clear local video
         */
        if (localVideo) {

            localVideo.srcObject = null;

        }


        cameraEnabled = false;


        /*
         * Placeholder show
         */
        if (localPlaceholder) {

            localPlaceholder.classList.remove(
                'hidden'
            );

        }


        /*
         * WebRTC video sender stop
         */
        if (peerConnection) {

            const videoSender =
                peerConnection
                    .getSenders()
                    .find(sender =>
                        sender.track?.kind === 'video'
                    );


            if (videoSender) {

                videoSender.replaceTrack(
                    null
                );

            }

        }


        updateCameraButton();

        console.log(
            'Camera OFF'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Camera Button
    |--------------------------------------------------------------------------
    */

    cameraButton?.addEventListener(
        'click',
        async () => {

            if (cameraEnabled) {

                stopCamera();

            } else {

                await startCamera();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Microphone Button
    |--------------------------------------------------------------------------
    */

    micButton?.addEventListener(
        'click',
        () => {

            if (!localStream) {

                alert(
                    'Pehle Camera ON karein.'
                );

                return;
            }


            const audioTracks =
                localStream.getAudioTracks();


            if (!audioTracks.length) {

                return;
            }


            micEnabled =
                !micEnabled;


            audioTracks.forEach(track => {

                track.enabled =
                    micEnabled;

            });


            updateMicButton();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Camera Button UI
    |--------------------------------------------------------------------------
    */

    function updateCameraButton() {

        if (!cameraButton) {

            return;

        }


        cameraButton.textContent =
            cameraEnabled
                ? '📷 Camera Off'
                : '📷 Camera';

    }


    /*
    |--------------------------------------------------------------------------
    | Mic Button UI
    |--------------------------------------------------------------------------
    */

    function updateMicButton() {

        if (!micButton) {

            return;

        }


        micButton.textContent =
            micEnabled
                ? '🎤 Mic'
                : '🔇 Mic Off';

    }


    /*
    |--------------------------------------------------------------------------
    | Create Peer Connection
    |--------------------------------------------------------------------------
    */

    function createPeerConnection() {

        /*
         * Existing connection close
         */
        if (peerConnection) {

            try {

                peerConnection.close();

            } catch (error) {

                console.log(error);

            }

        }


        pendingIceCandidates = [];


        /*
         * New connection
         */
        peerConnection =
            new RTCPeerConnection(
                rtcConfiguration
            );


        /*
         |--------------------------------------------------------------------------
         | Add Local Tracks
         |--------------------------------------------------------------------------
         */

        if (localStream) {

            localStream
                .getTracks()
                .forEach(track => {

                    peerConnection.addTrack(
                        track,
                        localStream
                    );

                });

        }


        /*
         |--------------------------------------------------------------------------
         | Remote Track
         |--------------------------------------------------------------------------
         */

        peerConnection.ontrack =
            async event => {

                console.log(
                    'Remote track received'
                );


                if (
                    event.streams &&
                    event.streams[0]
                ) {

                    remoteVideo.srcObject =
                        event.streams[0];


                    try {

                        await remoteVideo.play();

                    } catch (error) {

                        console.log(
                            'Remote video play:',
                            error
                        );

                    }


                    if (remotePlaceholder) {

                        remotePlaceholder.classList.add(
                            'hidden'
                        );

                    }

                }

            };


        /*
         |--------------------------------------------------------------------------
         | ICE Candidate
         |--------------------------------------------------------------------------
         */

        peerConnection.onicecandidate = event => {

            if (!event.candidate) {
                return;
            }

            outgoingIceCandidates.push(
                event.candidate.toJSON()
            );

            clearTimeout(iceBatchTimer);

            iceBatchTimer = setTimeout(async () => {

                if (!outgoingIceCandidates.length) {
                    return;
                }

                const candidates =
                    [...outgoingIceCandidates];

                outgoingIceCandidates = [];

                console.log(
                    'Sending ICE batch:',
                    candidates.length
                );

                await sendSignal(
                    'ice-batch',
                    {
                        candidates: candidates
                    }
                );

            }, 200);
        };

        /*
         |--------------------------------------------------------------------------
         | Connection State
         |--------------------------------------------------------------------------
         */

        peerConnection.onconnectionstatechange =
            () => {

                const state =
                    peerConnection.connectionState;


                console.log(
                    'WebRTC:',
                    state
                );


                if (state === 'connected') {

                    console.log(
                        'Video call connected'
                    );

                }


                if (
                    state === 'failed' ||
                    state === 'disconnected' ||
                    state === 'closed'
                ) {

                    console.log(
                        'Video call ended'
                    );

                }

            };


        return peerConnection;
    }


    /*
    |--------------------------------------------------------------------------
    | Send Signal Through Laravel
    |--------------------------------------------------------------------------
    */

    async function sendSignal(
        type,
        data = {}
    ) {

        if (!remoteUserId) {

            console.warn(
                'No remote user'
            );

            return;

        }


        try {

            const response =
                await fetch(
                    '/webrtc/signal',
                    {

                        method: 'POST',

                        headers: {

                            'Content-Type':
                                'application/json',

                            'X-CSRF-TOKEN':
                                csrfToken,

                            'Accept':
                                'application/json'

                        },

                        body:
                            JSON.stringify({

                                type:
                                    type,

                                data:
                                    data,

                                receiverId:
                                    Number(
                                        remoteUserId
                                    )

                            })

                    }
                );


            if (!response.ok) {
                const errorText = await response.text();

                console.error(
                    'Signal HTTP error:',
                    response.status
                );

                console.error(
                    'Signal response:',
                    errorText
                );

                return false;
            }

            return true;


        } catch (error) {

            console.error(
                'Signal error:',
                error
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Create Offer
    |--------------------------------------------------------------------------
    */

    async function createOffer() {

        if (!peerConnection) {

            createPeerConnection();

        }


        if (!remoteUserId) {

            return;

        }


        if (isMakingOffer) {

            return;

        }


        try {

            isMakingOffer = true;


            const offer =
                await peerConnection.createOffer();


            await peerConnection.setLocalDescription(
                offer
            );


            await sendSignal(
                'offer',
                {
                    offer:
                        peerConnection.localDescription
                }
            );


            console.log(
                'Offer sent'
            );


        } catch (error) {

            console.error(
                'Offer error:',
                error
            );

        } finally {

            isMakingOffer = false;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Handle Offer
    |--------------------------------------------------------------------------
    */

    async function handleOffer(data) {

        if (!peerConnection) {

            createPeerConnection();

        }


        try {

            await peerConnection.setRemoteDescription(
                new RTCSessionDescription(
                    data.offer
                )
            );


            /*
             * Pending ICE candidates
             */
            await processPendingIceCandidates();


            const answer =
                await peerConnection.createAnswer();


            await peerConnection.setLocalDescription(
                answer
            );


            await sendSignal(
                'answer',
                {
                    answer:
                        peerConnection.localDescription
                }
            );


            console.log(
                'Answer sent'
            );


        } catch (error) {

            console.error(
                'Offer handling error:',
                error
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Handle Answer
    |--------------------------------------------------------------------------
    */

    async function handleAnswer(data) {

        if (!peerConnection) {

            return;

        }


        try {

            await peerConnection.setRemoteDescription(
                new RTCSessionDescription(
                    data.answer
                )
            );


            await processPendingIceCandidates();


            console.log(
                'Answer received'
            );


        } catch (error) {

            console.error(
                'Answer error:',
                error
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Handle ICE
    |--------------------------------------------------------------------------
    */

    async function handleIceCandidate(
        data
    ) {

        if (
            !data ||
            !data.candidate
        ) {

            return;

        }


        /*
         * Remote description abhi set nahi hai.
         * Candidate ko queue mein rakho.
         */
        if (
            !peerConnection ||
            !peerConnection.remoteDescription
        ) {

            pendingIceCandidates.push(
                data.candidate
            );

            return;

        }


        try {

            await peerConnection.addIceCandidate(

                new RTCIceCandidate(
                    data.candidate
                )

            );


        } catch (error) {

            console.error(
                'ICE candidate error:',
                error
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Process Pending ICE
    |--------------------------------------------------------------------------
    */

    async function processPendingIceCandidates() {

        if (!peerConnection) {

            return;

        }


        if (
            !peerConnection.remoteDescription
        ) {

            return;

        }


        for (
            const candidate
            of pendingIceCandidates
        ) {

            try {

                await peerConnection.addIceCandidate(

                    new RTCIceCandidate(
                        candidate
                    )

                );

            } catch (error) {

                console.error(
                    'Pending ICE error:',
                    error
                );

            }

        }


        pendingIceCandidates = [];

    }


    /*
    |--------------------------------------------------------------------------
    | Handle WebRTC Signal
    |--------------------------------------------------------------------------
    */

    async function handleSignal(event) {

        console.log(
            'WEBRTC SIGNAL:',
            event
        );


        const type =
            event.type;

        const data =
            event.data;

        const senderId =
            Number(
                event.senderId
            );


        /*
         * Own signal ignore
         */
        if (
            senderId ===
            Number(currentUserId)
        ) {

            return;

        }


        /*
         * Remote user automatically remember
         */
        if (!remoteUserId) {

            remoteUserId =
                senderId;

        }


        /*
         |--------------------------------------------------------------------------
         | READY
         |--------------------------------------------------------------------------
         */

        if (type === 'ready') {

            /*
             * Lower ID caller banega.
             * Isse dono users simultaneously
             * offer create nahi karenge.
             */

            if (
                Number(currentUserId) <
                Number(senderId)
            ) {

                await createOffer();

            }


            return;
        }


        /*
         |--------------------------------------------------------------------------
         | OFFER
         |--------------------------------------------------------------------------
         */

        if (type === 'offer') {

            await handleOffer(
                data
            );

            return;
        }


        /*
         |--------------------------------------------------------------------------
         | ANSWER
         |--------------------------------------------------------------------------
         */

        if (type === 'answer') {

            await handleAnswer(
                data
            );

            return;
        }


        /*
         |--------------------------------------------------------------------------
         | ICE
         |--------------------------------------------------------------------------
         */

        if (type === 'ice-candidate') {

            await handleIceCandidate(data);

            return;
        }

        if (type === 'ice-batch') {

            if (
                data &&
                Array.isArray(data.candidates)
            ) {

                for (
                    const candidate
                    of data.candidates
                ) {

                    await handleIceCandidate({
                        candidate: candidate
                    });

                }

            }

            return;
        }


        /*
         |--------------------------------------------------------------------------
         | NEXT MATCH
         |--------------------------------------------------------------------------
         */

        if (type === 'next-match') {

            await startNextMatch();

            return;
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Reverb Listener
    |--------------------------------------------------------------------------
    */

    function connectToReverb() {

        if (!currentUserId) {
            console.error('Current user ID not available');
            return;
        }

        if (!window.Echo) {
            console.log('Waiting for Laravel Echo...');

            setTimeout(() => {
                connectToReverb();
            }, 500);

            return;
        }

        window.Echo
            .private(`webrtc.${currentUserId}`)
            .listen('.webrtc.signal', event => {

                handleSignal(event);

            });

        console.log(
            'Reverb WebRTC channel connected'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Start Matching
    |--------------------------------------------------------------------------
    */

    async function startMatching() {

        try {

            console.log(
                'Searching for next user...'
            );


            const response =
                await fetch(
                    '/video-match/start',
                    {

                        method: 'POST',

                        headers: {

                            'Content-Type':
                                'application/json',

                            'X-CSRF-TOKEN':
                                csrfToken,

                            'Accept':
                                'application/json'

                        }

                    }
                );


            if (!response.ok) {

                throw new Error(
                    'Match request failed'
                );

            }


            const result =
                await response.json();


            console.log(
                'Match response:',
                result
            );


            if (
                result.matched_user_id
            ) {

                await connectToUser(
                    result.matched_user_id
                );

            } else {

                showWaiting();

            }


        } catch (error) {

            console.error(
                'Matching error:',
                error
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Connect To User
    |--------------------------------------------------------------------------
    */

    async function connectToUser(
        userId
    ) {

        remoteUserId =
            Number(userId);


        console.log(
            'Matched user:',
            remoteUserId
        );


        /*
         * Clear previous remote video
         */
        if (remoteVideo) {

            remoteVideo.srcObject = null;

        }


        if (remotePlaceholder) {

            remotePlaceholder.classList.remove(
                'hidden'
            );

        }


        /*
         * New peer connection
         */
        createPeerConnection();


        /*
         * Camera required
         */
        if (!cameraEnabled) {

            await startCamera();

        }


        /*
         * Tell other user we are ready
         */
        await sendSignal(
            'ready'
        );


        /*
         * Start 60 second timer
         */
        startCallTimer();

    }


    /*
    |--------------------------------------------------------------------------
    | Waiting State
    |--------------------------------------------------------------------------
    */

    function showWaiting() {

        remoteUserId = null;


        if (remoteVideo) {

            remoteVideo.srcObject = null;

        }


        if (remotePlaceholder) {

            remotePlaceholder.textContent =
                'Waiting for video...';

            remotePlaceholder.classList.remove(
                'hidden'
            );

        }


        console.log(
            'Waiting for another user...'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | 60 Second Timer
    |--------------------------------------------------------------------------
    */

    function startCallTimer() {

        stopCallTimer();


        secondsRemaining = 60;


        /*
         * Optional browser console timer
         */
        console.log(
            'Call timer started: 60 seconds'
        );


        matchTimer =
            setInterval(
                async () => {

                    secondsRemaining--;


                    console.log(
                        'Time remaining:',
                        secondsRemaining
                    );


                    if (
                        secondsRemaining <= 0
                    ) {

                        stopCallTimer();

                        await startNextMatch();

                    }

                },
                1000
            );

    }


    /*
    |--------------------------------------------------------------------------
    | Stop Timer
    |--------------------------------------------------------------------------
    */

    function stopCallTimer() {

        if (matchTimer) {

            clearInterval(
                matchTimer
            );

            matchTimer = null;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Start Next Match
    |--------------------------------------------------------------------------
    */

    async function startNextMatch() {

        if (isLeavingCall) {

            return;

        }


        isLeavingCall = true;


        try {

            console.log(
                '60 seconds finished. Finding next user...'
            );


            /*
             * Close current WebRTC
             */
            closePeerConnection();


            /*
             * Tell current user we are moving
             */
            if (remoteUserId) {

                await sendSignal(
                    'next-match'
                );

            }


            remoteUserId = null;


            /*
             * Clear remote video
             */
            if (remoteVideo) {

                remoteVideo.srcObject =
                    null;

            }


            /*
             * Show waiting
             */
            showWaiting();


            /*
             * Ask Laravel for next user
             */
            const response =
                await fetch(
                    '/video-match/next',
                    {

                        method: 'POST',

                        headers: {

                            'Content-Type':
                                'application/json',

                            'X-CSRF-TOKEN':
                                csrfToken,

                            'Accept':
                                'application/json'

                        }

                    }
                );


            if (!response.ok) {

                throw new Error(
                    'Next match request failed'
                );

            }


            const result =
                await response.json();


            console.log(
                'Next match:',
                result
            );


            if (
                result.matched_user_id
            ) {

                await connectToUser(
                    result.matched_user_id
                );

            } else {

                showWaiting();

            }


        } catch (error) {

            console.error(
                'Next match error:',
                error
            );

        } finally {

            isLeavingCall = false;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Close Peer Connection
    |--------------------------------------------------------------------------
    */

    function closePeerConnection() {

        stopCallTimer();


        if (peerConnection) {

            try {

                peerConnection.ontrack =
                    null;

                peerConnection.onicecandidate =
                    null;

                peerConnection.close();

            } catch (error) {

                console.log(
                    'Peer close:',
                    error
                );

            }

        }


        peerConnection = null;

        pendingIceCandidates = [];

        outgoingIceCandidates = [];

        clearTimeout(iceBatchTimer);

        iceBatchTimer = null;


        console.log(
            'Peer connection closed'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Page Unload
    |--------------------------------------------------------------------------
    */

    window.addEventListener(
        'beforeunload',
        () => {

            stopCallTimer();

            closePeerConnection();

            /*
             * Local camera stop
             */
            if (localStream) {

                localStream
                    .getTracks()
                    .forEach(track => {

                        track.stop();

                    });

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Initialize
    |--------------------------------------------------------------------------
    */

    updateCameraButton();
    updateMicButton();

    connectToReverb();


    /*
     * Automatically start matching.
     *
     * Camera permission browser ke user
     * interaction par depend kar sakti hai,
     * isliye matching start ho sakti hai
     * bina camera permission ke.
     */
    startMatching();


    console.log(
        'WebRTC system initialized.',
        'User ID:',
        currentUserId
    );
</script>
</x-app-layout>