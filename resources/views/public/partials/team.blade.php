<div class="team-portraits">
    @foreach (config('studio.team') as $member)
        <article class="team-person">
            <figure class="team-person__portrait">
                <img src="{{ asset($member['portrait']) }}" alt="Retrato profesional de {{ $member['name'] }}"
                    width="800" height="1000" loading="lazy">
            </figure>
            <div class="team-person__info">
                <h3>{{ $member['name'] }}</h3>
                <p class="team-person__qualification">{{ $member['qualification'] }}</p>
                <p>{{ $member['role'] }}</p>
                @unless ($compact ?? false)
                <ul>
                    @foreach ($member['specialties'] as $specialty)
                        <li>{{ $specialty }}</li>
                    @endforeach
                </ul>
                <a class="home-intro-link" href="{{ $member['whatsapp'] }}" target="_blank" rel="noopener noreferrer"
                    aria-label="WhatsApp de {{ $member['name'] }} (abre otra pestaña)">Contactar por WhatsApp <span
                        aria-hidden="true">↗</span></a>
                @endunless
            </div>
        </article>
    @endforeach
</div>
