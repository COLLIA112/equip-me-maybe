<x-app-layout>
    <x-slot name="header"><h1 class="page-title">{{ __('Profile') }}</h1></x-slot>

    <div class="mx-auto max-w-3xl space-y-5">
        <x-card>@include('profile.partials.update-profile-information-form')</x-card>
        <x-card>@include('profile.partials.update-password-form')</x-card>
        <x-card class="border-red-200">@include('profile.partials.delete-user-form')</x-card>
    </div>
</x-app-layout>
