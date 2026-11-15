<x-app-layout>
    <x-slot name="header"><h1 class="page-title">Notifications</h1></x-slot>
    @if ($notifications->isNotEmpty())
        <form method="POST" action="{{ route('notifications.read-all') }}" class="mb-4">@csrf
            <button class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Mark all as read</button>
        </form>
    @endif
    <div class="space-y-3">
        @forelse ($notifications as $notification)
            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $notification->data['title'] ?? 'Notification' }} @unless ($notification->read_at)<span class="ml-2 rounded bg-blue-100 px-2 text-xs text-blue-900">Unread</span>@endunless</h2>
                        <p class="mt-1 text-sm">{{ $notification->data['message'] ?? '' }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ $notification->created_at->format('M j, Y g:i A') }}</p>
                        @php $notificationUrl = $notification->data['url'] ?? ''; @endphp
                        @if (str_starts_with($notificationUrl, '/') && ! str_starts_with($notificationUrl, '//') && ! str_contains($notificationUrl, '\\') && ! preg_match('/[\x00-\x20\x7f]/', $notificationUrl))
                            <a class="mt-2 inline-block text-sm font-semibold text-blue-700 underline" href="{{ $notificationUrl }}">View details</a>
                        @endif
                    </div>
                    @unless ($notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')
                            <button class="text-sm font-semibold text-blue-700 underline">Mark as read</button>
                        </form>
                    @endunless
                </div>
            </x-card>
        @empty
            <x-empty-state>No notifications yet. Reservation updates and return reminders will appear here.</x-empty-state>
        @endforelse
    </div>
    <x-list-summary :paginator="$notifications" />
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-app-layout>
