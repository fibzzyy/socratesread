@php
    $account = auth()->user();
    $notificationItems = $account ? $account->notifications()->latest()->limit(8)->get() : collect();
    $unreadNotificationCount = $account ? $account->unreadNotifications()->count() : 0;
@endphp

<details class="notification-center">
    <summary class="icon-button notification-trigger" aria-label="Notifikasi">
        <x-icon name="bell" />
        @if ($unreadNotificationCount > 0)
            <span class="notification-count">{{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}</span>
        @endif
    </summary>
    <section class="notification-panel" aria-label="Notifikasi terbaru">
        <header class="notification-panel-header">
            <h2>Notifikasi</h2>
            @if ($unreadNotificationCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit">Tandai dibaca</button>
                </form>
            @endif
        </header>
        @forelse ($notificationItems as $item)
            <a class="notification-item {{ $item->read_at ? '' : 'is-unread' }}" href="{{ route('notifications.open', $item->id) }}">
                <span class="notification-item-copy">
                    <strong>{{ $item->data['title'] ?? 'Pembaruan' }}</strong>
                    <span>{{ $item->data['message'] ?? '' }}</span>
                    <small>{{ $item->created_at->locale('id')->diffForHumans() }}</small>
                </span>
                @unless ($item->read_at)
                    <i aria-label="Belum dibaca"></i>
                @endunless
            </a>
        @empty
            <p class="notification-empty">Belum ada notifikasi.</p>
        @endforelse
    </section>
</details>

<script>
    (() => {
        if (window.__notificationCenterInitialized) return;
        window.__notificationCenterInitialized = true;

        document.addEventListener('click', (e) => {
            const openPanels = document.querySelectorAll('.notification-center[open]');
            openPanels.forEach(panel => {
                if (!panel.contains(e.target)) {
                    panel.removeAttribute('open');
                }
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const openPanels = document.querySelectorAll('.notification-center[open]');
                openPanels.forEach(panel => panel.removeAttribute('open'));
            }
        });
    })();
</script>
