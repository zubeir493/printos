<div
    x-data="{
        busy: false,
        status: {
            supported: window.PrintOsWebPush?.isSupported() ?? false,
            subscribed: false,
            permission: window.Notification?.permission ?? 'default',
        },
        async refresh() {
            this.status = await window.PrintOsWebPush?.status() ?? this.status
        },
        async toggle(enabled) {
            this.busy = true
            try {
                let nextStatus = null

                if (enabled) {
                    nextStatus = await window.PrintOsWebPush?.subscribe()
                } else {
                    nextStatus = await window.PrintOsWebPush?.unsubscribe()
                }

                if (nextStatus) {
                    this.status = nextStatus
                }
            } finally {
                this.busy = false
            }
        },
    }"
    x-init="refresh()"
    x-on:printos-webpush-ready.window="refresh()"
    x-on:printos-webpush-status.window="status = $event.detail"
    class="flex items-center justify-between gap-4"
>
    <label
        for="webpush-enabled"
        class="text-sm font-medium text-gray-950 dark:text-white"
    >
        Enable push notifications?
    </label>

    <button
        id="webpush-enabled"
        type="button"
        role="switch"
        x-bind:aria-checked="status.subscribed.toString()"
        x-bind:disabled="busy || ! status.supported || status.permission === 'denied'"
        x-on:click="toggle(! status.subscribed)"
        class="fi-toggle"
        x-bind:class="{
            'fi-toggle-on bg-primary-600 dark:bg-primary-500': status.subscribed,
            'fi-toggle-off': ! status.subscribed,
        }"
    >
        <div>
            <div aria-hidden="true"></div>
            <div aria-hidden="true"></div>
        </div>
    </button>
</div>
