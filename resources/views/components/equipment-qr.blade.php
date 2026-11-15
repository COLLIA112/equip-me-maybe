@props(['code', 'size' => 144])

<figure class="w-fit rounded-md bg-white p-2 text-center text-slate-900">
    <div role="img" aria-label="QR code for {{ $code }}">
        {{ (new \SimpleSoftwareIO\QrCode\Generator())->format('svg')->size($size)->margin(4)->generate($code) }}
    </div>
    <figcaption class="font-mono text-sm">{{ $code }}</figcaption>
</figure>
