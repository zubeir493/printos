@auth
@if (filled(config('webpush.vapid.public_key')))
    <script>
        (() => {
            const webPushConfig = {
                publicKey: @js(config('webpush.vapid.public_key')),
                storeUrl: @js(route('webpush.subscriptions.store')),
                destroyUrl: @js(route('webpush.subscriptions.destroy')),
                csrfToken: @js(csrf_token()),
            };

            const isSupported = () => 'serviceWorker' in navigator &&
                'PushManager' in window &&
                'Notification' in window;

            const subscriptionStorageKey = 'printos.webpush.subscribed';

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
                window.dispatchEvent(new CustomEvent('printos-webpush-status', {
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

                const status = {
                    supported: true,
                    subscribed: true,
                    permission,
                };

                await dispatchStatus(status);

                return status;
            };

            const unsubscribe = async () => {
                if (! isSupported()) {
                    const status = {
                        supported: false,
                        subscribed: false,
                        permission: 'unsupported',
                    };

                    await dispatchStatus(status);

                    return status;
                }

                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();

                if (! subscription) {
                    const status = {
                        supported: true,
                        subscribed: false,
                        permission: Notification.permission,
                    };

                    await dispatchStatus(status);

                    return status;
                }

                const response = await fetch(webPushConfig.destroyUrl, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': webPushConfig.csrfToken,
                    },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                    }),
                });

                if (! response.ok) {
                    throw new Error('Unable to delete push subscription.');
                }

                await subscription.unsubscribe();
                window.localStorage.removeItem(subscriptionStorageKey);

                const status = {
                    supported: true,
                    subscribed: false,
                    permission: Notification.permission,
                };

                await dispatchStatus(status);

                return status;
            };

            window.PrintOsWebPush = {
                isSupported,
                status: getStatus,
                subscribe,
                unsubscribe,
            };

            if (isSupported() && Notification.permission === 'granted') {
                subscribe();
            } else {
                dispatchStatus();
            }

            window.dispatchEvent(new CustomEvent('printos-webpush-ready'));
        })();
    </script>
@endif
@endauth
