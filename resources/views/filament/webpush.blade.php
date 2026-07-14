@auth
@if (filled(config('webpush.vapid.public_key')))
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

            const canShowPermissionAlert = () => 'Notification' in window &&
                Notification.permission !== 'granted';

            const runAfterUiReady = (callback) => {
                const run = () => window.setTimeout(callback, 500);

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', run, { once: true });

                    return;
                }

                run();
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

                return {
                    supported,
                    subscribed: subscription !== null && subscription !== undefined,
                    permission: Notification.permission,
                };
            };

            const dispatchStatus = async (status = null) => {
                window.dispatchEvent(new CustomEvent('packledge-webpush-status', {
                    detail: status ?? await getStatus(),
                }));
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

            const showPermissionAlert = () => {
                if (! canShowPermissionAlert()) {
                    return;
                }

                const title = Notification.permission === 'denied'
                    ? 'Browser notifications are blocked'
                    : 'Turn on browser notifications';
                const body = Notification.permission === 'denied'
                    ? 'Enable notifications for this site in your browser settings to receive important alerts.'
                    : 'Enable notifications for this browser to receive important alerts as they happen.';

                if (window.FilamentNotification) {
                    const notification = new window.FilamentNotification()
                        .title(title)
                        .body(body)
                        .warning()
                        .persistent();

                    if (Notification.permission === 'default' && isSupported() && window.FilamentNotificationAction) {
                        notification.actions([
                            new window.FilamentNotificationAction('enableBrowserNotifications')
                                .label('Enable')
                                .button()
                                .close()
                                .dispatch('packledge-webpush-enable'),
                        ]);
                    }

                    notification.send();

                    return;
                }

                window.alert(`${title}\n\n${body}`);
            };

            const requestPermission = async () => {
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
                const status = {
                    supported: true,
                    subscribed: false,
                    permission,
                };

                if (permission !== 'granted') {
                    await dispatchStatus(status);
                    runAfterUiReady(showPermissionAlert);

                    return status;
                }

                return {
                    ...status,
                    subscribed: (await getStatus()).subscribed,
                };
            };

            const subscribe = async ({ requestBrowserPermission = true } = {}) => {
                if (! isSupported()) {
                    const status = {
                        supported: false,
                        subscribed: false,
                        permission: 'unsupported',
                    };

                    await dispatchStatus(status);

                    return status;
                }

                const permission = requestBrowserPermission
                    ? (await requestPermission()).permission
                    : Notification.permission;

                if (permission !== 'granted') {
                    const status = {
                        supported: true,
                        subscribed: false,
                        permission,
                    };

                    await dispatchStatus(status);
                    runAfterUiReady(showPermissionAlert);

                    return status;
                }

                const registration = await navigator.serviceWorker.register('/sw.js');
                const existingSubscription = await registration.pushManager.getSubscription();
                const subscription = existingSubscription ?? await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(webPushConfig.publicKey),
                });

                await sendSubscription(subscription);

                const status = {
                    supported: true,
                    subscribed: true,
                    permission,
                };

                await dispatchStatus(status);

                return status;
            };

            window.PackledgeWebPush = {
                isSupported,
                requestPermission,
                status: getStatus,
                subscribe,
            };

            window.addEventListener('packledge-webpush-enable', async () => {
                const status = await requestPermission();

                if (status.permission === 'granted') {
                    await subscribe({ requestBrowserPermission: false });
                }
            });

            if (isSupported() && Notification.permission === 'granted') {
                subscribe({ requestBrowserPermission: false });
            } else {
                dispatchStatus();

                if (shouldShowPermissionAlert) {
                    runAfterUiReady(showPermissionAlert);
                }
            }

            window.dispatchEvent(new CustomEvent('packledge-webpush-ready'));
        })();
    </script>
@endif
@endauth
