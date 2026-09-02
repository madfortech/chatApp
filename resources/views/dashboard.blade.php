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
    let localStream = null;
    let peerConnection = null;

    const currentUserId = {{ auth()->id() }};

    const rtcConfiguration = {
        iceServers: [
            {
                urls: 'stun:stun.l.google.com:19302'
            }
        ]
    };

    async function startCamera() {
        try {
            localStream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: true,
            });

            const localVideo = document.getElementById('localVideo');

            if (!localVideo) {
                return false;
            }

            localVideo.srcObject = localStream;

            document
                .getElementById('localPlaceholder')
                ?.remove();

            console.log('Camera started');

            return true;

        } catch (error) {
            console.error('Camera/Microphone error:', error);

            return false;
        }
    }


    function createPeerConnection() {

        peerConnection = new RTCPeerConnection(rtcConfiguration);

        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
        });


        peerConnection.ontrack = (event) => {

            const remoteVideo = document.getElementById('remoteVideo');

            if (!remoteVideo) {
                return;
            }

            remoteVideo.srcObject = event.streams[0];

            document
                .getElementById('remotePlaceholder')
                ?.remove();

            console.log('Remote video received');
        };


        peerConnection.onicecandidate = async (event) => {

            if (!event.candidate) {
                return;
            }

            await sendSignal('ice-candidate', {
                candidate: event.candidate,
            });
        };


        peerConnection.onconnectionstatechange = () => {
            console.log(
                'Connection state:',
                peerConnection.connectionState
            );
        };
    }


    async function sendSignal(type, data) {

        await fetch('/webrtc/signal', {
            method: 'POST',

            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute('content'),
                'Accept': 'application/json',
            },

            body: JSON.stringify({
                type: type,
                data: data,
            }),
        });
    }


    async function createOffer() {

        const offer = await peerConnection.createOffer();

        await peerConnection.setLocalDescription(offer);

        await sendSignal('offer', {
            offer: offer,
        });

        console.log('Offer sent');
    }


    async function handleOffer(data) {

        if (!peerConnection) {
            return;
        }

        await peerConnection.setRemoteDescription(
            new RTCSessionDescription(data.offer)
        );

        const answer = await peerConnection.createAnswer();

        await peerConnection.setLocalDescription(answer);

        await sendSignal('answer', {
            answer: answer,
        });

        console.log('Answer sent');
    }


    async function handleAnswer(data) {

        if (!peerConnection) {
            return;
        }

        await peerConnection.setRemoteDescription(
            new RTCSessionDescription(data.answer)
        );

        console.log('Answer received');
    }


    async function handleIceCandidate(data) {

        if (!peerConnection) {
            return;
        }

        try {

            await peerConnection.addIceCandidate(
                new RTCIceCandidate(data.candidate)
            );

            console.log('ICE candidate added');

        } catch (error) {

            console.error(
                'ICE candidate error:',
                error
            );

        }
    }


    function listenForSignals() {

        window.Echo
            .channel('webrtc')
            .listen('.webrtc.signal', async (event) => {

                console.log('WebRTC signal received:', event);

                // Apna hi signal ignore karo
                if (event.senderId === currentUserId) {
                    return;
                }

                switch (event.type) {

                    case 'offer':
                        await handleOffer(event.data);
                        break;

                    case 'answer':
                        await handleAnswer(event.data);
                        break;

                    case 'ice-candidate':
                        await handleIceCandidate(event.data);
                        break;
                }
            });
    }


    async function startVideoCall() {

        const cameraStarted = await startCamera();

        if (!cameraStarted) {
            return;
        }

        createPeerConnection();

        listenForSignals();

        console.log('WebRTC ready');
    }


    startVideoCall();
</script>
</x-app-layout>