<x-app-layout>

    <flux:main container>

        <div class="grid min-h-[calc(100vh-6rem)] grid-cols-1 gap-4 lg:grid-cols-[1fr_380px]">

            <!-- =========================================================
                 LEFT: VIDEO AREA
            ========================================================== -->
            <section class="grid min-h-0 grid-rows-[1fr_180px] gap-4">

                <!-- =====================================================
                     METERED VIDEO
                ====================================================== -->
                <div class="flex items-center justify-center rounded-2xl bg-zinc-950 p-4 shadow-sm">

                    <div
                        id="metered-frame"
                        class="relative flex h-full min-h-[400px] w-full items-center justify-center overflow-hidden rounded-xl bg-zinc-900"
                    >

                        <!-- Waiting message -->
                        <div
                            id="videoWaiting"
                            class="absolute inset-0 z-10 flex items-center justify-center bg-zinc-900 text-zinc-400"
                        >
                            <div class="text-center">
                                <div class="mb-2 text-3xl">
                                    📹
                                </div>

                                <div id="waitingText">
                                    Searching for next user...
                                </div>
                            </div>
                        </div>

                    </div>

                </div>


                <!-- =====================================================
                     BOTTOM CONTROLS / DEBUG
                ====================================================== -->
                <div class="grid grid-cols-2 gap-4">

                    <!-- Your video status -->
                    <div
                        class="relative flex items-center justify-center overflow-hidden rounded-2xl bg-zinc-950 text-zinc-400"
                    >

                        <div class="text-center">

                            <div
                                id="cameraStatusIcon"
                                class="mb-2 text-4xl"
                            >
                                📷
                            </div>

                            <div
                                id="cameraStatusText"
                                class="text-sm"
                            >
                                Camera Off
                            </div>

                        </div>

                    </div>


                    <!-- Controls + Debug -->
                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"
                    >

                        <!-- Controls -->
                        <div class="flex gap-3">

                            <flux:button
                                id="cameraButton"
                                type="button"
                                variant="primary"
                                color="cyan"
                            >
                                📷 Camera
                            </flux:button>

                            <flux:button
                                id="micButton"
                                type="button"
                                variant="primary"
                            >
                                🎤 Mic
                            </flux:button>

                            <flux:button
                                id="nextButton"
                                type="button"
                                variant="primary"
                            >
                                Next
                            </flux:button>

                        </div>


                        <!-- Debug -->
                        <div
                            class="rounded-xl bg-zinc-950 p-3 text-xs text-zinc-200"
                        >

                            <div class="mb-2 flex items-center justify-between">

                                <strong>
                                    Metered Debug
                                </strong>

                                <button
                                    id="clearWebrtcDebug"
                                    type="button"
                                    class="text-zinc-400 hover:text-white"
                                >
                                    Clear
                                </button>

                            </div>


                            <div class="grid grid-cols-2 gap-x-3 gap-y-1">

                                <div>
                                    Camera:
                                    <b id="debugCamera">
                                        WAIT
                                    </b>
                                </div>

                                <div>
                                    Mic:
                                    <b id="debugMic">
                                        WAIT
                                    </b>
                                </div>

                                <div>
                                    Remote:
                                    <b id="debugRemote">
                                        WAIT
                                    </b>
                                </div>

                                <div>
                                    Reverb:
                                    <b id="debugReverb">
                                        WAIT
                                    </b>
                                </div>

                                <div>
                                    Match:
                                    <b id="debugMatch">
                                        WAIT
                                    </b>
                                </div>

                                <div>
                                    Metered:
                                    <b id="debugMetered">
                                        WAIT
                                    </b>
                                </div>

                            </div>


                            <div
                                id="webrtcDebugLog"
                                class="mt-2 h-28 overflow-auto rounded bg-zinc-900 p-2 font-mono text-[10px] leading-4"
                            ></div>

                        </div>

                    </div>

                </div>

            </section>


            <!-- =========================================================
                 CHAT
            ========================================================== -->
            <aside
                class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
            >

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


    <!-- ================================================================
         METERED SDK
    ================================================================= -->
    <script src="https://cdn.metered.ca/sdk/frame/1.4.3/sdk-frame.min.js"></script>


    <script>
        /*
        |--------------------------------------------------------------------------
        | Yahoo Messenger - Metered Video Chat
        |--------------------------------------------------------------------------
        */

        (() => {

            'use strict';


            /*
            |--------------------------------------------------------------------------
            | USER
            |--------------------------------------------------------------------------
            */

            const currentUserId =
                Number(@json(auth()->id()));


            /*
            |--------------------------------------------------------------------------
            | CSRF
            |--------------------------------------------------------------------------
            */

            const csrfToken =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content');


            /*
            |--------------------------------------------------------------------------
            | ELEMENTS
            |--------------------------------------------------------------------------
            */

            const meteredContainer =
                document.getElementById('metered-frame');

            const waitingBox =
                document.getElementById('videoWaiting');

            const waitingText =
                document.getElementById('waitingText');

            const cameraButton =
                document.getElementById('cameraButton');

            const micButton =
                document.getElementById('micButton');

            const nextButton =
                document.getElementById('nextButton');

            const cameraStatusIcon =
                document.getElementById('cameraStatusIcon');

            const cameraStatusText =
                document.getElementById('cameraStatusText');


            /*
            |--------------------------------------------------------------------------
            | DEBUG ELEMENTS
            |--------------------------------------------------------------------------
            */

            const debugLogElement =
                document.getElementById('webrtcDebugLog');


            /*
            |--------------------------------------------------------------------------
            | STATE
            |--------------------------------------------------------------------------
            */

            let meteredFrame = null;

            let currentMatchId = null;

            let currentRoomName = null;

            let currentRoomURL = null;

            let currentRemoteUserId = null;

            let currentAccessToken = null;

            let meetingJoined = false;

            let callActive = false;

            let isLeaving = false;

            let matchTimer = null;

            let secondsRemaining = 60;


            /*
            |--------------------------------------------------------------------------
            | DEBUG
            |--------------------------------------------------------------------------
            */

            function debugLog(
                label,
                message,
                data = null
            ) {

                const time =
                    new Date().toLocaleTimeString();

                const line =
                    `[${time}] ${label}: ${message}`;


                console.log(
                    line,
                    data ?? ''
                );


                if (!debugLogElement) {
                    return;
                }


                const row =
                    document.createElement('div');


                row.textContent =
                    data
                        ? `${line} ${safeJson(data)}`
                        : line;


                debugLogElement.appendChild(row);


                debugLogElement.scrollTop =
                    debugLogElement.scrollHeight;
            }


            function safeJson(data) {

                try {

                    return JSON.stringify(data);

                } catch (error) {

                    return '[unserializable]';

                }

            }


            function debugStatus(
                id,
                value
            ) {

                const element =
                    document.getElementById(id);


                if (element) {

                    element.textContent =
                        value;

                }

            }


            function debugError(
                label,
                error
            ) {

                debugLog(
                    label,
                    `❌ ${error?.message || String(error)}`
                );


                console.error(
                    label,
                    error
                );

            }


            document
                .getElementById('clearWebrtcDebug')
                ?.addEventListener(
                    'click',
                    () => {

                        if (debugLogElement) {

                            debugLogElement.innerHTML = '';

                        }

                        debugLog(
                            'DEBUG',
                            'Log cleared'
                        );

                    }
                );


            debugLog(
                'SYSTEM',
                `🟢 Metered system loaded. User ID: ${currentUserId}`
            );


            /*
            |--------------------------------------------------------------------------
            | WAITING UI
            |--------------------------------------------------------------------------
            */

            function showWaiting(
                text = 'Searching for next user...'
            ) {

                if (waitingText) {

                    waitingText.textContent =
                        text;

                }


                if (waitingBox) {

                    waitingBox.classList.remove(
                        'hidden'
                    );

                }

            }


            function hideWaiting() {

                if (waitingBox) {

                    waitingBox.classList.add(
                        'hidden'
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | CAMERA UI
            |--------------------------------------------------------------------------
            */

            function updateCameraUI(
                enabled
            ) {

                if (enabled) {

                    cameraStatusIcon.textContent =
                        '📷';

                    cameraStatusText.textContent =
                        'Camera On';

                    debugStatus(
                        'debugCamera',
                        '🟢 ON'
                    );

                    if (cameraButton) {

                        cameraButton.textContent =
                            '📷 Camera Off';

                    }

                } else {

                    cameraStatusIcon.textContent =
                        '📷';

                    cameraStatusText.textContent =
                        'Camera Off';

                    debugStatus(
                        'debugCamera',
                        '🔴 OFF'
                    );

                    if (cameraButton) {

                        cameraButton.textContent =
                            '📷 Camera';

                    }

                }

            }


            /*
            |--------------------------------------------------------------------------
            | METERED TOKEN
            |--------------------------------------------------------------------------
            */

            async function getMeteredToken(
                matchId
            ) {

                debugLog(
                    'METERED',
                    `🔑 Requesting access token for match ${matchId}`
                );


                debugStatus(
                    'debugMetered',
                    '🟡 TOKEN'
                );


                const response =
                    await fetch(
                        "{{ route('video-call.metered-token') }}",
                        {

                            method: 'POST',

                            headers: {

                                'Content-Type':
                                    'application/json',

                                'Accept':
                                    'application/json',

                                'X-CSRF-TOKEN':
                                    csrfToken

                            },

                            body:
                                JSON.stringify({
                                    match_id:
                                        Number(matchId)
                                })

                        }
                    );


                const data =
                    await response.json();


                debugLog(
                    'METERED',
                    'Token response received',
                    {
                        ok: response.ok,
                        status: response.status,
                        success: data?.success
                    }
                );


                if (!response.ok || !data.success) {

                    throw new Error(
                        data.message ||
                        'Metered token request failed.'
                    );

                }


                if (!data.accessToken) {

                    throw new Error(
                        'Metered access token missing.'
                    );

                }


                return data;

            }


            /*
            |--------------------------------------------------------------------------
            | DESTROY METERED
            |--------------------------------------------------------------------------
            */

            async function closeMeteredCall() {

                debugLog(
                    'METERED',
                    'Closing current Metered call'
                );


                stopCallTimer();


                if (meteredFrame) {

                    try {

                        meteredFrame.leave();

                    } catch (error) {

                        debugError(
                            'METERED LEAVE',
                            error
                        );

                    }

                }


                meteredFrame = null;

                meetingJoined = false;

                callActive = false;

                currentAccessToken = null;


                if (meteredContainer) {

                    meteredContainer.innerHTML = '';

                }


                showWaiting(
                    'Searching for next user...'
                );


                debugStatus(
                    'debugMetered',
                    'WAIT'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | OPEN METERED ROOM
            |--------------------------------------------------------------------------
            */

            async function openMeteredRoom(
                matchId,
                remoteUserId = null
            ) {

                if (!matchId) {

                    throw new Error(
                        'Match ID missing.'
                    );

                }


                /*
                 * Close previous meeting first.
                 */
                if (meteredFrame) {

                    await closeMeteredCall();

                }


                currentMatchId =
                    Number(matchId);


                currentRemoteUserId =
                    remoteUserId
                        ? Number(remoteUserId)
                        : currentRemoteUserId;


                debugStatus(
                    'debugMatch',
                    `🟢 #${currentMatchId}`
                );


                if (currentRemoteUserId) {

                    debugStatus(
                        'debugRemote',
                        `🟢 USER ${currentRemoteUserId}`
                    );

                }


                debugLog(
                    'METERED',
                    `Opening room for match #${currentMatchId}`
                );


                /*
                 * Get token from Laravel.
                 */
                const tokenData =
                    await getMeteredToken(
                        currentMatchId
                    );


                currentRoomName =
                    tokenData.roomName;

                currentRoomURL =
                    tokenData.roomURL;

                currentAccessToken =
                    tokenData.accessToken;


                debugLog(
                    'METERED',
                    'Room information received',
                    {
                        roomName:
                            currentRoomName,
                        roomURL:
                            currentRoomURL
                    }
                );


                debugStatus(
                    'debugMetered',
                    '🟡 LOADING'
                );


                if (!window.MeteredFrame) {

                    throw new Error(
                        'MeteredFrame SDK not loaded.'
                    );

                }


                if (!meteredContainer) {

                    throw new Error(
                        'Metered container not found.'
                    );

                }


                meteredContainer.innerHTML =
                    '';


                /*
                 * Create frame.
                 */
                meteredFrame =
                    new MeteredFrame();


                /*
                 * Initialize.
                 *
                 * Metered Embed supports:
                 * autoJoin
                 * name
                 * accessToken
                 * joinVideoOn
                 * joinAudioOn
                 */
                meteredFrame.init(
                    {

                        roomURL:
                            currentRoomURL,

                        width:
                            '100%',

                        height:
                            '100%',

                        autoJoin:
                            true,

                        name:
                            tokenData.userName ||
                            `User ${currentUserId}`,

                        accessToken:
                            currentAccessToken,

                        joinVideoOn:
                            true,

                        joinAudioOn:
                            true,

                        showInviteBox:
                            false,

                        disableChat:
                            false,

                        disableScreenSharing:
                            false

                    },

                    meteredContainer
                );


                /*
                 * SDK READY
                 */
                meteredFrame.on(
                    'ready',
                    () => {

                        debugStatus(
                            'debugMetered',
                            '🟢 READY'
                        );


                        debugLog(
                            'METERED',
                            '🟢 Frame ready'
                        );

                    }
                );


                /*
                 * JOINED
                 */
                meteredFrame.on(
                    'meetingJoined',
                    info => {

                        meetingJoined =
                            true;

                        callActive =
                            true;


                        hideWaiting();


                        debugStatus(
                            'debugMetered',
                            '🟢 JOINED'
                        );


                        debugLog(
                            'METERED',
                            '🟢 Meeting joined',
                            info
                        );


                        updateCameraUI(
                            true
                        );


                        debugStatus(
                            'debugMic',
                            '🟢 ON'
                        );


                        startCallTimer();

                    }
                );


                /*
                 * LEFT
                 */
                meteredFrame.on(
                    'meetingLeft',
                    info => {

                        debugLog(
                            'METERED',
                            '🔴 Meeting left',
                            info
                        );


                        meetingJoined =
                            false;

                        callActive =
                            false;


                        debugStatus(
                            'debugMetered',
                            '🔴 LEFT'
                        );


                        showWaiting(
                            'Call ended'
                        );

                    }
                );


                /*
                 * ONLINE PARTICIPANTS
                 */
                meteredFrame.on(
                    'onlineParticipants',
                    participants => {

                        debugLog(
                            'METERED',
                            `👥 Online participants: ${participants?.length || 0}`,
                            participants
                        );


                        if (
                            Array.isArray(participants) &&
                            participants.length > 1
                        ) {

                            hideWaiting();

                            debugStatus(
                                'debugRemote',
                                `🟢 ${participants.length - 1} REMOTE`
                            );

                        } else {

                            debugStatus(
                                'debugRemote',
                                '🟡 WAITING'
                            );

                        }

                    }
                );


                /*
                 * PARTICIPANT JOINED
                 */
                meteredFrame.on(
                    'participantJoined',
                    participant => {

                        debugLog(
                            'METERED',
                            '👤 Participant joined',
                            participant
                        );


                        debugStatus(
                            'debugRemote',
                            '🟢 JOINED'
                        );


                        hideWaiting();

                    }
                );


                /*
                 * PARTICIPANT LEFT
                 */
                meteredFrame.on(
                    'participantLeft',
                    participant => {

                        debugLog(
                            'METERED',
                            '👋 Participant left',
                            participant
                        );


                        debugStatus(
                            'debugRemote',
                            '🔴 LEFT'
                        );

                    }
                );


                /*
                 * CAMERA / MIC / SCREEN STATE
                 */
                meteredFrame.on(
                    'participantSharingStateUpdated',
                    state => {

                        debugLog(
                            'METERED',
                            `Sharing state: ${state?.action || 'unknown'}`,
                            state
                        );


                        if (
                            state?.sharingVideo !== undefined
                        ) {

                            updateCameraUI(
                                Boolean(
                                    state.sharingVideo
                                )
                            );

                        }


                        if (
                            state?.sharingAudio !== undefined
                        ) {

                            debugStatus(
                                'debugMic',
                                state.sharingAudio
                                    ? '🟢 ON'
                                    : '🔴 OFF'
                            );

                        }

                    }
                );


                debugLog(
                    'METERED',
                    '🟢 Metered initialization complete'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CAMERA BUTTON
            |--------------------------------------------------------------------------
            */

            cameraButton?.addEventListener(
                'click',
                async () => {

                    if (!meteredFrame) {

                        alert(
                            'Pehle kisi user se connect hone dein.'
                        );

                        return;

                    }


                    try {

                        debugLog(
                            'CAMERA',
                            'Camera button clicked'
                        );


                        /*
                         * Current state determine karna
                         * difficult hai because iframe manages it.
                         *
                         * Button click par startCamera()
                         * call kar rahe hain.
                         */
                        await meteredFrame.startCamera();


                        updateCameraUI(
                            true
                        );


                        debugLog(
                            'CAMERA',
                            '🟢 Camera start requested'
                        );


                    } catch (error) {

                        debugError(
                            'CAMERA',
                            error
                        );

                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | MICROPHONE BUTTON
            |--------------------------------------------------------------------------
            */

            micButton?.addEventListener(
                'click',
                async () => {

                    if (!meteredFrame) {

                        alert(
                            'Pehle kisi user se connect hone dein.'
                        );

                        return;

                    }


                    /*
                     * Metered Embed SDK directly
                     * start/stop microphone method
                     * provide nahi karta.
                     *
                     * Isliye microphone toggle
                     * iframe ke own control se hoga.
                     */
                    debugLog(
                        'MIC',
                        'Use Metered iframe microphone control'
                    );


                    alert(
                        'Microphone ko Metered video panel ke microphone button se control karein.'
                    );

                }
            );


            /*
            |--------------------------------------------------------------------------
            | NEXT BUTTON
            |--------------------------------------------------------------------------
            */

            nextButton?.addEventListener(
                'click',
                async () => {

                    await startNextMatch();

                }
            );


            /*
            |--------------------------------------------------------------------------
            | TIMER
            |--------------------------------------------------------------------------
            */

            function startCallTimer() {

                stopCallTimer();


                secondsRemaining =
                    60;


                debugLog(
                    'MATCH',
                    '⏱️ 60 second timer started'
                );


                matchTimer =
                    setInterval(
                        async () => {

                            secondsRemaining--;


                            if (
                                secondsRemaining <= 0
                            ) {

                                stopCallTimer();


                                debugLog(
                                    'MATCH',
                                    '⏱️ 60 seconds completed'
                                );


                                await startNextMatch();

                            }

                        },
                        1000
                    );

            }


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
            | START NEXT MATCH
            |--------------------------------------------------------------------------
            */

            async function startNextMatch() {

                if (isLeaving) {

                    return;

                }


                isLeaving =
                    true;


                try {

                    debugLog(
                        'MATCH',
                        '➡️ Requesting next match'
                    );


                    await closeMeteredCall();


                    currentMatchId =
                        null;

                    currentRoomName =
                        null;

                    currentRoomURL =
                        null;

                    currentRemoteUserId =
                        null;


                    debugStatus(
                        'debugRemote',
                        'WAIT'
                    );

                    debugStatus(
                        'debugMatch',
                        'WAIT'
                    );


                    /*
                     * Laravel next endpoint.
                     */
                    const response =
                        await fetch(
                            '/video-match/next',
                            {

                                method:
                                    'POST',

                                headers: {

                                    'Content-Type':
                                        'application/json',

                                    'Accept':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        csrfToken

                                }

                            }
                        );


                    if (!response.ok) {

                        throw new Error(
                            `Next match failed: HTTP ${response.status}`
                        );

                    }


                    const result =
                        await response.json();


                    debugLog(
                        'MATCH',
                        'Next match response',
                        result
                    );


                    if (
                        result.status ===
                            'matched' &&
                        result.match_id &&
                        result.matched_user_id
                    ) {

                        currentRemoteUserId =
                            Number(
                                result.matched_user_id
                            );


                        /*
                         * Tell the matched user.
                         */
                        await sendMatchNotification(
                            result.matched_user_id,
                            result.match_id
                        );


                        /*
                         * Open Metered.
                         */
                        await openMeteredRoom(
                            result.match_id,
                            result.matched_user_id
                        );

                    } else {

                        showWaiting(
                            'Waiting for another user...'
                        );

                    }


                } catch (error) {

                    debugError(
                        'NEXT MATCH',
                        error
                    );


                    showWaiting(
                        'Unable to find next user'
                    );


                } finally {

                    isLeaving =
                        false;

                }

            }


            /*
            |--------------------------------------------------------------------------
            | START MATCHING
            |--------------------------------------------------------------------------
            */

            async function startMatching() {

                try {

                    showWaiting(
                        'Searching for next user...'
                    );


                    debugStatus(
                        'debugMatch',
                        '🟡 SEARCHING'
                    );


                    debugLog(
                        'MATCH',
                        '🔎 Searching for next user...'
                    );


                    const response =
                        await fetch(
                            '/video-match/start',
                            {

                                method:
                                    'POST',

                                headers: {

                                    'Content-Type':
                                        'application/json',

                                    'Accept':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        csrfToken

                                }

                            }
                        );


                    if (!response.ok) {

                        throw new Error(
                            `Match request failed: HTTP ${response.status}`
                        );

                    }


                    const result =
                        await response.json();


                    debugLog(
                        'MATCH',
                        'Match response',
                        result
                    );


                    /*
                     * MATCHED
                     */
                    if (
                        result.status ===
                            'matched' &&
                        result.matched_user_id &&
                        result.match_id
                    ) {

                        currentRemoteUserId =
                            Number(
                                result.matched_user_id
                            );


                        debugStatus(
                            'debugRemote',
                            `🟢 USER ${currentRemoteUserId}`
                        );


                        debugStatus(
                            'debugMatch',
                            `🟢 #${result.match_id}`
                        );


                        /*
                         * Notify second user.
                         */
                        await sendMatchNotification(
                            result.matched_user_id,
                            result.match_id
                        );


                        /*
                         * Caller opens room.
                         */
                        await openMeteredRoom(
                            result.match_id,
                            result.matched_user_id
                        );


                    } else {

                        /*
                         * WAITING
                         */
                        debugStatus(
                            'debugMatch',
                            '🟡 WAITING'
                        );


                        showWaiting(
                            'Waiting for another user...'
                        );

                    }


                } catch (error) {

                    debugError(
                        'MATCHING',
                        error
                    );


                    debugStatus(
                        'debugMatch',
                        '🔴 ERROR'
                    );


                    showWaiting(
                        'Matching error'
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | REVERB MATCH NOTIFICATION
            |--------------------------------------------------------------------------
            */

            async function sendMatchNotification(
                receiverId,
                matchId
            ) {

                if (!receiverId || !matchId) {

                    return false;

                }


                debugLog(
                    'REVERB',
                    `📤 Sending match notification → ${receiverId}`
                );


                try {

                    const response =
                        await fetch(
                            '/webrtc/signal',
                            {

                                method:
                                    'POST',

                                headers: {

                                    'Content-Type':
                                        'application/json',

                                    'Accept':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        csrfToken

                                },

                                body:
                                    JSON.stringify({

                                        type:
                                            'metered-match',

                                        data: {

                                            match_id:
                                                Number(matchId)

                                        },

                                        receiverId:
                                            Number(receiverId)

                                    })

                            }
                        );


                    if (!response.ok) {

                        const errorText =
                            await response.text();


                        debugLog(
                            'REVERB',
                            `❌ Match notification failed HTTP ${response.status}`,
                            errorText
                        );


                        return false;

                    }


                    debugLog(
                        'REVERB',
                        '🟢 Match notification sent'
                    );


                    return true;


                } catch (error) {

                    debugError(
                        'REVERB',
                        error
                    );


                    return false;

                }

            }


            /*
            |--------------------------------------------------------------------------
            | REVERB SIGNAL LISTENER
            |--------------------------------------------------------------------------
            */

            function connectToReverb() {

                if (!currentUserId) {

                    debugLog(
                        'REVERB',
                        '❌ Current user ID missing'
                    );

                    return;

                }


                if (!window.Echo) {

                    debugLog(
                        'REVERB',
                        '🟡 Echo not ready, retrying...'
                    );


                    setTimeout(
                        connectToReverb,
                        500
                    );


                    return;

                }


                const channelName =
                    `webrtc.${currentUserId}`;


                debugLog(
                    'REVERB',
                    `🔌 Subscribing to ${channelName}`
                );


                try {

                    window.Echo
                        .private(channelName)
                        .listen(
                            '.webrtc.signal',
                            event => {

                                debugStatus(
                                    'debugReverb',
                                    '🟢 EVENT'
                                );


                                debugLog(
                                    'REVERB',
                                    '🔥 Event received',
                                    event
                                );


                                handleReverbEvent(
                                    event
                                ).catch(
                                    error => {

                                        debugError(
                                            'REVERB EVENT',
                                            error
                                        );

                                    }
                                );

                            }
                        );


                    debugStatus(
                        'debugReverb',
                        '🟢 LISTENER'
                    );


                    debugLog(
                        'REVERB',
                        '🟢 Private channel listener registered'
                    );


                    /*
                     * Pusher/Reverb connection debug.
                     */
                    try {

                        const pusher =
                            window.Echo.connector?.pusher;


                        if (pusher) {

                            pusher.connection.bind(
                                'connected',
                                () => {

                                    debugStatus(
                                        'debugReverb',
                                        '🟢 CONNECTED'
                                    );


                                    debugLog(
                                        'REVERB',
                                        '🟢 Reverb connected'
                                    );

                                }
                            );


                            pusher.connection.bind(
                                'state_change',
                                states => {

                                    debugLog(
                                        'REVERB',
                                        `State ${states.previous} → ${states.current}`
                                    );

                                }
                            );


                            pusher.connection.bind(
                                'error',
                                error => {

                                    debugStatus(
                                        'debugReverb',
                                        '🔴 ERROR'
                                    );


                                    debugError(
                                        'REVERB',
                                        error
                                    );

                                }
                            );

                        }

                    } catch (error) {

                        debugError(
                            'REVERB DEBUG',
                            error
                        );

                    }

                } catch (error) {

                    debugError(
                        'REVERB SUBSCRIBE',
                        error
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | HANDLE REVERB EVENT
            |--------------------------------------------------------------------------
            */

            async function handleReverbEvent(
                event
            ) {

                if (!event) {

                    return;

                }


                const type =
                    event.type;


                const data =
                    event.data || {};


                const senderId =
                    event.senderId
                        ? Number(event.senderId)
                        : null;


                debugLog(
                    'SIGNAL',
                    `📥 ${type}`,
                    {
                        senderId,
                        data
                    }
                );


                /*
                 * =============================================================
                 * METERED MATCH
                 * =============================================================
                 */
                if (
                    type ===
                    'metered-match'
                ) {

                    const matchId =
                        Number(
                            data.match_id
                        );


                    if (!matchId) {

                        debugLog(
                            'METERED',
                            '❌ match_id missing in Reverb event'
                        );

                        return;

                    }


                    currentRemoteUserId =
                        senderId;


                    currentMatchId =
                        matchId;


                    debugStatus(
                        'debugRemote',
                        senderId
                            ? `🟢 USER ${senderId}`
                            : '🟢 MATCH'
                    );


                    debugStatus(
                        'debugMatch',
                        `🟢 #${matchId}`
                    );


                    debugLog(
                        'METERED',
                        `📥 Incoming match #${matchId}`
                    );


                    /*
                     * Receiver joins SAME room.
                     */
                    await openMeteredRoom(
                        matchId,
                        senderId
                    );


                    return;

                }


                /*
                 * Old WebRTC signals are intentionally ignored.
                 *
                 * Metered handles WebRTC itself.
                 */
                if (
                    type === 'offer' ||
                    type === 'answer' ||
                    type === 'ice-candidate' ||
                    type === 'ice-batch' ||
                    type === 'ready'
                ) {

                    debugLog(
                        'SIGNAL',
                        `Ignoring old WebRTC signal: ${type}`
                    );


                    return;

                }

            }


            /*
            |--------------------------------------------------------------------------
            | PAGE UNLOAD
            |--------------------------------------------------------------------------
            */

            window.addEventListener(
                'beforeunload',
                () => {

                    stopCallTimer();


                    if (meteredFrame) {

                        try {

                            meteredFrame.leave();

                        } catch (error) {

                            console.log(
                                error
                            );

                        }

                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | INITIALIZE
            |--------------------------------------------------------------------------
            */

            updateCameraUI(
                false
            );


            debugStatus(
                'debugMic',
                'WAIT'
            );


            debugStatus(
                'debugRemote',
                'WAIT'
            );


            debugStatus(
                'debugMatch',
                'WAIT'
            );


            debugStatus(
                'debugMetered',
                'WAIT'
            );


            /*
             * Reverb first.
             */
            connectToReverb();


            /*
             * Then matching.
             */
            startMatching();


            debugLog(
                'SYSTEM',
                '🟢 Dashboard video system initialized'
            );


        })();
    </script>

</x-app-layout>