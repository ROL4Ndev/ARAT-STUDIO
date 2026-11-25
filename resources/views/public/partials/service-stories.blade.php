@forelse ($services as $group => $serviceItems)
    <h2 class="services-group-label">{{ $group }}</h2>
    <div class="service-stories">
        @foreach ($serviceItems as $service)
            <article id="servicio-{{ $service['id'] }}" class="service-story {{ $service['image'] ? '' : 'service-story--text' }}">
                @if ($service['image'])
                    <figure class="service-story__figure">
                        <img src="{{ asset($service['image']) }}" alt="{{ $service['image_alt'] }}" width="960"
                            height="720" loading="lazy">
                        <figcaption>{{ $service['caption'] }}</figcaption>
                    </figure>
                @endif
                <div class="service-story__copy">
                    <h3>{{ $service['name'] }}</h3>
                    <p class="section-body">{{ $service['description'] }}</p>
                    <div class="service-story__actions">
                        <a class="btn btn-dark"
                            href="{{ route('service-requests.create', ['service_id' => $service['id']]) }}">Consultar
                            este servicio</a>
                        @if ($service['project'])
                            <a class="home-intro-link" href="{{ route('projects.show', $service['project']) }}">Ver
                                {{ $service['project']->title }}</a>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@empty
    <p class="section-body">Estamos actualizando nuestro catálogo de servicios. Escríbenos para conversar sobre tu
        proyecto.</p>
@endforelse
