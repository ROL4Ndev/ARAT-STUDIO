<figure class="studio-film {{ $hero ?? false ? 'studio-film--hero' : '' }}" data-studio-film
    data-label="{{ $label }}" data-render="poster">
    <div class="studio-film__frame">
        <img class="studio-film__poster" src="{{ asset('media/studio/' . $file . '-poster.webp') }}" alt="{{ $posterAlt }}"
            width="1280" height="720" loading="{{ $hero ?? false ? 'eager' : 'lazy' }}"
            @if ($hero ?? false) fetchpriority="high" @endif>
        <video id="{{ $id }}" class="studio-film__video" data-film-video
            data-src="{{ asset('media/studio/' . $file . '.mp4') }}"
            poster="{{ asset('media/studio/' . $file . '-poster.webp') }}" width="1280" height="720" preload="none"
            muted playsinline loop aria-hidden="true" tabindex="-1"></video>
        @if ($hero ?? false)
            <div class="studio-film__identity">
                <img src="{{ asset('img/LOGO1.png') }}" alt="ARAT STUDIO" width="561" height="561">
            </div>
        @endif
        <button class="studio-film__control" type="button" data-film-toggle aria-controls="{{ $id }}"
            aria-pressed="false" hidden>Reproducir video</button>
    </div>
    <span data-film-status class="studio-film__status visually-hidden" aria-live="polite"></span>
</figure>
