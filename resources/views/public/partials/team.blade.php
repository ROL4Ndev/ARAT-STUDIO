<div class="team-portraits {{ $compact ?? false ? 'team-portraits--compact' : '' }}">
    @foreach (config('studio.team') as $member)
        <article class="team-person" aria-labelledby="member-{{ $loop->index }}">
            <figure class="team-person__portrait" data-reveal="image">
                <img src="{{ asset($member['portrait']) }}" alt="Retrato profesional de {{ $member['name'] }}"
                    width="800" height="1000" loading="lazy">
            </figure>
            <div class="team-person__info" data-reveal="copy">
                <p class="team-person__role">{{ $member['role'] }}</p>
                <h3 id="member-{{ $loop->index }}">{{ $member['name'] }}</h3>
                <p class="team-person__qualification">{{ $member['qualification'] }}</p>
                @unless ($compact ?? false)
                    <ul class="team-person__specialties">
                        @foreach ($member['specialties'] as $specialty)
                            <li>{{ $specialty }}</li>
                        @endforeach
                    </ul>
                    <a class="editorial-link" href="{{ $member['whatsapp'] }}" target="_blank" rel="noopener noreferrer"
                        aria-label="WhatsApp de {{ $member['name'] }} (abre otra pestaña)">Contactar por WhatsApp</a>
                @endunless
            </div>
        </article>
    @endforeach
</div>
