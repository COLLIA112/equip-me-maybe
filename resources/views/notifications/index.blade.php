
<x-app-layout>

    <x-slot name="header">

        <div class="flex w-full flex-wrap items-center justify-between gap-4">

            <div class="min-w-0">

                <p class="mb-1 text-sm font-semibold text-blue-700">
                    Communication Center
                </p>

                <h1 class="page-title">
                    Notifications
                </h1>

                <p class="mt-2 text-sm text-slate-600">
                    Stay updated on reservations, equipment activity
                    and return reminders.
                </p>

            </div>

            @if ($notifications->isNotEmpty())

                <form
                    method="POST"
                    action="{{ route('notifications.read-all') }}">

                    @csrf

                    <x-primary-button>

                        <svg
                            class="h-5 w-5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true">

                            <path d="m3 12 4 4 9-9"/>
                            <path d="m11 16 3 3 7-10"/>

                        </svg>

                        Mark all as read

                    </x-primary-button>

                </form>

            @endif

        </div>

    </x-slot>

    <section aria-labelledby="notification-list-heading">

        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">

            <div>

                <h2
                    id="notification-list-heading"
                    class="text-lg font-bold text-slate-950">

                    Recent notifications

                </h2>

                <p class="mt-1 text-sm text-slate-600">

                    Review updates and important system messages.

                </p>

            </div>

            <x-list-summary :paginator="$notifications" />

        </div>

        <div class="space-y-4">

            @forelse ($notifications as $notification)

                @php

                    $isUnread = ! $notification->read_at;

                    $notificationTitle = $notification->data['title']
                        ?? 'Notification';

                    $notificationUrl = $notification->data['url']
                        ?? '';

                @endphp

                <article
                    class="ui-card notification-card {{ $isUnread ? 'notification-unread' : '' }}"
                    aria-labelledby="notification-title-{{ $notification->id }}">

                    <div class="flex items-start gap-4">

                        {{-- Notification icon --}}

                        <span
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $isUnread ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600' }}"
                            aria-hidden="true">

                            <svg
                                class="h-6 w-6"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.75"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                focusable="false">

                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                                <path d="M10 21h4"/>

                            </svg>

                        </span>

                        {{-- Notification content --}}

                        <div class="min-w-0 flex-1">

                            <div class="flex flex-wrap items-start justify-between gap-3">

                                <div class="min-w-0">

                                    <div class="flex flex-wrap items-center gap-2">

                                        <h3
                                            id="notification-title-{{ $notification->id }}"
                                            class="break-words text-base font-bold text-slate-950">

                                            {{ $notificationTitle }}

                                        </h3>

                                        @if ($isUnread)

                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-900">

                                                <span
                                                    class="h-2 w-2 rounded-full bg-blue-500"
                                                    aria-hidden="true">
                                                </span>

                                                Unread

                                            </span>

                                        @else

                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">

                                                Read

                                            </span>

                                        @endif

                                    </div>

                                    <p class="mt-2 text-xs text-slate-600">

                                        <time datetime="{{ $notification->created_at->toIso8601String() }}">

                                            {{ $notification->created_at->format('M j, Y g:i A') }}

                                        </time>

                                    </p>

                                </div>

                            </div>

                            <p class="mt-3 break-words text-sm leading-relaxed text-slate-700">

                                {{ $notification->data['message'] ?? '' }}

                            </p>

                            {{-- Notification actions --}}

                            <div class="mt-4 flex flex-wrap items-center gap-3">

                                @if (
                                    is_string($notificationUrl)
                                    && str_starts_with($notificationUrl, '/')
                                    && ! str_starts_with($notificationUrl, '//')
                                    && ! str_contains($notificationUrl, '\\')
                                    && ! preg_match('/[\x00-\x20\x7f]/', $notificationUrl)
                                )

                                    <a
                                        href="{{ $notificationUrl }}"
                                        aria-label="View details: {{ $notificationTitle }}"
                                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-slate-100">

                                        View details

                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            aria-hidden="true">

                                            <path d="M5 12h14 M12 5l7 7-7 7"/>

                                        </svg>

                                    </a>

                                @endif

                                @if ($isUnread)

                                    <form
                                        method="POST"
                                        action="{{ route('notifications.read', $notification->id) }}">

                                        @csrf
                                        @method('PATCH')

                                        <x-secondary-button
                                            type="submit"
                                            aria-label="Mark as read: {{ $notificationTitle }}">

                                            <svg
                                                class="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true">

                                                <path d="m5 12 4 4L19 6"/>

                                            </svg>

                                            Mark as read

                                        </x-secondary-button>

                                    </form>

                                @endif

                            </div>

                        </div>

                    </div>

                </article>

            @empty

                <x-card class="text-center">

                    <div class="mx-auto max-w-md py-10">

                        <span
                            class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-2xl bg-blue-50 text-blue-700"
                            aria-hidden="true">

                            <svg
                                class="h-10 w-10"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round">

                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                                <path d="M10 21h4"/>

                            </svg>

                        </span>

                        <h3 class="text-lg font-bold text-slate-950">

                            No notifications yet

                        </h3>

                        <p class="mt-3 text-sm leading-relaxed text-slate-600">

                            Reservation updates, equipment activity
                            and return reminders will appear here.

                        </p>

                        <a
                            href="{{ route('dashboard') }}"
                            class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">

                            Back to dashboard

                        </a>

                    </div>

                </x-card>

            @endforelse

        </div>

    </section>

    <div class="mt-6">

        {{ $notifications->links() }}

    </div>

</x-app-layout>
