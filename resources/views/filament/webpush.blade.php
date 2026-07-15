@auth
@if (filled(config('webpush.vapid.public_key')))
    <style>
        [wire\:key^="printos-webpush-permission-prompt.notifications."] .fi-no-notification-icon {
            height: 2rem;
            width: 2rem;
        }
    </style>

    <script>
        (() => {
            const webPushConfig = {
                publicKey: @js(config('webpush.vapid.public_key')),
                storeUrl: @js(route('webpush.subscriptions.store')),
                csrfToken: @js(csrf_token()),
            };
            const shouldShowPermissionAlert = @js(session()->has('show_webpush_permission_prompt'));

            const isSupported = () => 'serviceWorker' in navigator &&
                'PushManager' in window &&
                'Notification' in window;

            const subscriptionStorageKey = 'printos.webpush.subscribed';
            const promptNotificationId = 'printos-webpush-permission-prompt';
            let isPromptNotificationDismissed = false;
            let hasPromptNotificationBeenShown = false;

            const closePromptNotification = () => {
                window.dispatchEvent(new CustomEvent('close-notification', {
                    detail: {
                        id: promptNotificationId,
                    },
                }));
            };

            const showPromptNotification = (status) => {
                if (! status.supported || status.subscribed) {
                    closePromptNotification();

                    return;
                }

                if (
                    ! shouldShowPermissionAlert ||
                    hasPromptNotificationBeenShown ||
                    isPromptNotificationDismissed ||
                    ! window.FilamentNotification
                ) {
                    return;
                }

                closePromptNotification();
                hasPromptNotificationBeenShown = true;

                const notification = new window.FilamentNotification()
                    .id(promptNotificationId)
                    .title('Enable notifications')
                    .warning()
                    .icon('heroicon-o-bell-alert')
                    .persistent()
                    .body(status.permission === 'denied'
                        ? 'Enable notifications for this site in your browser settings to receive important alerts.'
                        : 'Enable notifications to receive important alerts.');

                if (status.permission !== 'denied') {
                    notification.actions([
                        new window.FilamentNotificationAction('enableNotifications')
                            .label('Enable notifications')
                            .button()
                            .color('primary')
                            .dispatch('printos-webpush-enable-requested'),
                    ]);
                }

                notification.send();
            };

            const getStatus = async () => {
                const supported = isSupported();

                if (! supported) {
                    return {
                        supported,
                        subscribed: false,
                        permission: 'unsupported',
                    };
                }

                const registration = await navigator.serviceWorker.getRegistration();
                const subscription = await registration?.pushManager.getSubscription();
                const storedSubscriptionState = window.localStorage.getItem(subscriptionStorageKey) === 'true';

                return {
                    supported,
                    subscribed: (subscription !== null && subscription !== undefined) || (
                        storedSubscriptionState &&
                        Notification.permission === 'granted'
                    ),
                    permission: Notification.permission,
                };
            };

            const dispatchStatus = async (status = null) => {
                const nextStatus = status ?? await getStatus();

                window.dispatchEvent(new CustomEvent('printos-webpush-status', {
                    detail: nextStatus,
                }));

                window.dispatchEvent(new CustomEvent('packledge-webpush-status', {
                    detail: nextStatus,
                }));

                showPromptNotification(nextStatus);
            };

            const urlBase64ToUint8Array = (base64String) => {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding)
                    .replace(/-/g, '+')
                    .replace(/_/g, '/');
                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);

                for (let index = 0; index < rawData.length; index += 1) {
                    outputArray[index] = rawData.charCodeAt(index);
                }

                return outputArray;
            };

            const sendSubscription = async (subscription) => {
                const subscriptionData = subscription.toJSON();

                const response = await fetch(webPushConfig.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': webPushConfig.csrfToken,
                    },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                        keys: subscriptionData.keys,
                        contentEncoding: PushManager.supportedContentEncodings?.[0] ?? 'aes128gcm',
                    }),
                });

                if (! response.ok) {
                    throw new Error('Unable to save push subscription.');
                }
            };

            const subscribe = async () => {
                if (! isSupported()) {
                    const status = {
                        supported: false,
                        subscribed: false,
                        permission: 'unsupported',
                    };

                    await dispatchStatus(status);

                    return status;
                }

                const permission = await Notification.requestPermission();

                if (permission !== 'granted') {
                    const status = {
                        supported: true,
                        subscribed: false,
                        permission,
                    };

                    await dispatchStatus(status);

                    return status;
                }

                const registration = await navigator.serviceWorker.register('/sw.js');
                const existingSubscription = await registration.pushManager.getSubscription();
                const subscription = existingSubscription ?? await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(webPushConfig.publicKey),
                });

                await sendSubscription(subscription);
                window.localStorage.setItem(subscriptionStorageKey, 'true');
                closePromptNotification();

                const status = {
                    supported: true,
                    subscribed: true,
                    permission,
                };

                await dispatchStatus(status);

                return status;
            };

            const webPush = {
                isSupported,
                status: getStatus,
                subscribe,
            };

            window.PrintOsWebPush = webPush;
            window.PackledgeWebPush = webPush;

            window.addEventListener('printos-webpush-enable-requested', () => {
                isPromptNotificationDismissed = true;
                closePromptNotification();
                subscribe();
            });

            window.addEventListener('packledge-webpush-enable', () => {
                subscribe();
            });

            window.addEventListener('notificationClosed', (event) => {
                if (event.detail.id === promptNotificationId) {
                    isPromptNotificationDismissed = true;
                }
            });

            if (isSupported() && Notification.permission === 'granted') {
                subscribe();
            } else {
                dispatchStatus();

                if (shouldShowPermissionAlert) {
                    window.setTimeout(() => dispatchStatus(), 500);
                }
            }

            window.dispatchEvent(new CustomEvent('printos-webpush-ready'));
            window.dispatchEvent(new CustomEvent('packledge-webpush-ready'));
        })();
    </script>
@endif
@endauth
