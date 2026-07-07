{{-- Shared site container: standard 1440px max content width, centered, responsive padding.
     Pass extra classes (grid/flex/gap/etc.) and they merge with the base. --}}
<div {{ $attributes->merge(['class' => 'mx-auto w-full max-w-[1440px] px-5 sm:px-6 lg:px-8 xl:px-10']) }}>
    {{ $slot }}
</div>
