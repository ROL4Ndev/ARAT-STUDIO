const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const connection = navigator.connection ?? navigator.mozConnection ?? navigator.webkitConnection;
const supportsObserver = typeof window.IntersectionObserver === 'function';
const minimumVisibility = 0.25;
const films = [];
let selectedFilm = null;
let preferredFilm = null;

function automaticPlaybackAllowed() {
    return !reducedMotion.matches && !connection?.saveData;
}

function visibleRatio(element) {
    const bounds = element.getBoundingClientRect();
    if (!bounds.width || !bounds.height) return 0;

    const width = Math.max(0, Math.min(bounds.right, innerWidth) - Math.max(bounds.left, 0));
    const height = Math.max(0, Math.min(bounds.bottom, innerHeight) - Math.max(bounds.top, 0));
    return (width * height) / (bounds.width * bounds.height);
}

class StudioFilm {
    constructor(figure) {
        this.figure = figure;
        this.video = figure.querySelector('[data-film-video]');
        this.button = figure.querySelector('[data-film-toggle]');
        this.status = figure.querySelector('[data-film-status]');
        this.visible = false;
        this.userPaused = false;
        this.blocked = false;
        this.failed = false;
        this.wantsPlayback = false;
        this.manualPlayback = false;
        this.requestVersion = 0;

        this.figure.dataset.render = 'poster';
        this.updateState('idle');
        this.button.hidden = false;
        this.video.muted = true;
        this.video.playsInline = true;

        this.button.addEventListener('click', () => {
            if (this.wantsPlayback || !this.video.paused) this.pause('manual');
            else this.play(true);
        });
        this.video.addEventListener('playing', () => {
            if (!this.canContinue()) {
                this.pause('outside');
                return;
            }
            this.figure.dataset.render = 'video';
            this.updateState('playing', this.manualPlayback ? 'Video en reproducción.' : '');
        });
        this.video.addEventListener('pause', () => {
            // Un evento antiguo de pausa no debe cancelar un intento nuevo de reproducción.
            if (!this.video.paused || this.figure.dataset.state === 'loading') return;
            if (this.wantsPlayback) this.pause('manual');
        });
        this.video.addEventListener('error', () => {
            if (this.video.hasAttribute('src')) this.showFailure('error');
        });
        this.video.addEventListener('ended', () => this.pause('manual'));
    }

    canPlayAutomatically() {
        return supportsObserver && automaticPlaybackAllowed() && !document.hidden
            && !this.userPaused && !this.blocked && !this.failed
            && this.visible && visibleRatio(this.figure) >= minimumVisibility;
    }

    canContinue() {
        return this.wantsPlayback && selectedFilm === this && !document.hidden
            && visibleRatio(this.figure) >= minimumVisibility
            && (this.manualPlayback || automaticPlaybackAllowed());
    }

    updateState(state, message = '') {
        this.figure.dataset.state = state;
        const text = this.wantsPlayback ? 'Pausar video' : 'Reproducir video';
        this.button.textContent = text;
        this.button.setAttribute('aria-label', `${text}: ${this.figure.dataset.label ?? 'video'}`);
        this.button.setAttribute('aria-pressed', String(state === 'playing'));
        if (this.status) this.status.textContent = message;
    }

    detachSource() {
        if (!this.video.hasAttribute('src')) return;
        // Pausar por sí solo permite que el navegador continúe descargando el archivo oculto.
        this.video.removeAttribute('src');
        this.video.load();
    }

    pause(reason) {
        this.requestVersion++;
        this.wantsPlayback = false;
        this.manualPlayback = false;
        if (reason === 'manual') this.userPaused = true;
        if (selectedFilm === this) selectedFilm = null;
        this.video.pause();

        if (reason !== 'manual') {
            this.detachSource();
            this.figure.dataset.render = 'poster';
        }
        this.updateState('paused', reason === 'manual' ? 'Video en pausa.' : '');
    }

    showFailure(state) {
        this.requestVersion++;
        this.wantsPlayback = false;
        this.manualPlayback = false;
        this.blocked = true;
        this.failed = state === 'error';
        if (selectedFilm === this) selectedFilm = null;
        this.video.pause();
        this.detachSource();
        this.figure.dataset.render = 'poster';
        this.updateState(state, state === 'blocked'
            ? 'La reproducción automática no está disponible. Puedes iniciar el video con el control.'
            : 'No se pudo cargar el video. Puedes reintentarlo con el control.');
    }

    play(manual = false) {
        if (document.hidden || visibleRatio(this.figure) < minimumVisibility) return;
        if (!manual && !this.canPlayAutomatically()) return;
        if (this.wantsPlayback) return;

        // También se libera una fuente que haya quedado detenida por una pausa manual.
        for (const other of films) {
            if (other !== this && (other.wantsPlayback || other.video.hasAttribute('src'))) other.pause('other');
        }
        this.userPaused = false;
        this.blocked = false;
        this.failed = false;
        this.manualPlayback = manual;
        this.wantsPlayback = true;
        selectedFilm = preferredFilm = this;
        const version = ++this.requestVersion;
        this.updateState('loading');

        // No hay src al inicializar. Este es el único punto que inicia una descarga.
        if (!this.video.hasAttribute('src')) this.video.src = this.video.dataset.src;
        try {
            const attempt = this.video.play();
            Promise.resolve(attempt).then(() => {
                if (version !== this.requestVersion) return;
                if (!this.canContinue()) this.pause('outside');
            }).catch(error => {
                // Salir de pantalla, cambiar de video o de preferencia puede abortar play().
                if (version !== this.requestVersion || !this.wantsPlayback) return;
                this.showFailure(error.name === 'NotAllowedError' ? 'blocked' : 'error');
            });
        } catch (error) {
            this.showFailure(error.name === 'NotAllowedError' ? 'blocked' : 'error');
        }
    }
}

for (const figure of document.querySelectorAll('[data-studio-film]')) {
    if (!figure.querySelector('[data-film-video][data-src]') || !figure.querySelector('[data-film-toggle]')) continue;
    films.push(new StudioFilm(figure));
}

function playVisibleFilm() {
    if (document.hidden || !supportsObserver || !automaticPlaybackAllowed()) return;
    if (selectedFilm?.canContinue()) return;
    // No sustituir la decisión de una persona que acaba de pausar el video visible.
    if (preferredFilm?.userPaused && visibleRatio(preferredFilm.figure) >= minimumVisibility) return;

    const candidates = films.filter(film => film.canPlayAutomatically());
    candidates.sort((left, right) => visibleRatio(right.figure) - visibleRatio(left.figure));
    const preferred = candidates.includes(preferredFilm) ? preferredFilm : candidates[0];
    preferred?.play();
}

if (supportsObserver) {
    const observer = new IntersectionObserver(entries => {
        for (const entry of entries) {
            const film = films.find(item => item.figure === entry.target);
            film.visible = entry.isIntersecting && entry.intersectionRatio >= minimumVisibility;
            if (!film.visible && (film.wantsPlayback || film.video.hasAttribute('src'))) film.pause('outside');
        }
        // Elegir después de procesar el lote evita cargar dos videos visibles a la vez.
        playVisibleFilm();
    }, { threshold: [0, minimumVisibility] });
    films.forEach(film => observer.observe(film.figure));
} else {
    // Respaldo sin autoplay: los controles manuales funcionan y el scroll detiene un video oculto.
    let scheduled = false;
    const pauseHiddenFilms = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            for (const film of films) {
                if (film.video.hasAttribute('src') && visibleRatio(film.figure) < minimumVisibility) film.pause('outside');
            }
        });
    };
    window.addEventListener('scroll', pauseHiddenFilms, { passive: true });
    window.addEventListener('resize', pauseHiddenFilms, { passive: true });
}

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        for (const film of films) {
            if (film.wantsPlayback || film.video.hasAttribute('src')) film.pause('hidden');
        }
    } else playVisibleFilm();
});

function applyPlaybackPreferences() {
    if (!automaticPlaybackAllowed()) {
        for (const film of films) film.pause('preference');
    } else playVisibleFilm();
}

if (reducedMotion.addEventListener) reducedMotion.addEventListener('change', applyPlaybackPreferences);
else reducedMotion.addListener(applyPlaybackPreferences);
connection?.addEventListener?.('change', applyPlaybackPreferences);
