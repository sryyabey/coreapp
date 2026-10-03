
  const header = document.querySelector('[data-header]');
  const button = document.querySelector('[data-menu-button]');
  const menu = document.querySelector('[data-menu]');

  const closeMenu = () => {
    button?.setAttribute('aria-expanded', 'false');
    menu?.classList.remove('is-open');
  };

  button?.addEventListener('click', () => {
    const isOpen = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!isOpen));
    menu?.classList.toggle('is-open', !isOpen);
  });

  menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
  window.addEventListener('scroll', () => header?.classList.toggle('is-scrolled', window.scrollY > 16), { passive: true });


  document.querySelectorAll('[data-project-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('[data-project-track]');
    const slides = [...carousel.querySelectorAll('[data-project-slide]')];
    const current = carousel.querySelector('[data-project-current]');
    const previous = carousel.querySelector('[data-project-prev]');
    const next = carousel.querySelector('[data-project-next]');
    if (!track || !slides.length) return;

    let activeIndex = 0;
    let scrollFrame = 0;
    let pointerStart = 0;
    let scrollStart = 0;
    let dragging = false;

    const setActive = (index) => {
      activeIndex = Math.max(0, Math.min(slides.length - 1, index));
      if (current) current.textContent = String(activeIndex + 1).padStart(2, '0');
      if (previous) previous.disabled = activeIndex === 0;
      if (next) next.disabled = activeIndex === slides.length - 1;
    };

    const goTo = (index) => {
      const target = Math.max(0, Math.min(slides.length - 1, index));
      track.scrollTo({ left: slides[target].offsetLeft - track.offsetLeft, behavior: 'smooth' });
      setActive(target);
    };

    track.addEventListener('scroll', () => {
      cancelAnimationFrame(scrollFrame);
      scrollFrame = requestAnimationFrame(() => {
        const nearest = slides.reduce((best, slide, index) => Math.abs(slide.offsetLeft - track.offsetLeft - track.scrollLeft) < Math.abs(slides[best].offsetLeft - track.offsetLeft - track.scrollLeft) ? index : best, 0);
        setActive(nearest);
      });
    }, { passive: true });

    previous?.addEventListener('click', () => goTo(activeIndex - 1));
    next?.addEventListener('click', () => goTo(activeIndex + 1));
    track.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowLeft') { event.preventDefault(); goTo(activeIndex - 1); }
      if (event.key === 'ArrowRight') { event.preventDefault(); goTo(activeIndex + 1); }
    });

    track.addEventListener('pointerdown', (event) => {
      if (event.pointerType === 'touch') return;
      if (event.target instanceof Element && event.target.closest('a, button')) return;
      pointerStart = event.clientX;
      scrollStart = track.scrollLeft;
      dragging = true;
      track.classList.add('is-dragging');
      track.setPointerCapture(event.pointerId);
    });
    track.addEventListener('pointermove', (event) => {
      if (!dragging) return;
      track.scrollLeft = scrollStart - (event.clientX - pointerStart);
    });
    const stopDragging = (event) => {
      if (!dragging) return;
      dragging = false;
      track.classList.remove('is-dragging');
      if (track.hasPointerCapture(event.pointerId)) track.releasePointerCapture(event.pointerId);
      goTo(activeIndex);
    };
    track.addEventListener('pointerup', stopDragging);
    track.addEventListener('pointercancel', stopDragging);
    setActive(0);
  });

const menuButton = document.querySelector('[data-menu-button]');
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && menuButton?.getAttribute('aria-expanded') === 'true') {
    menuButton.setAttribute('aria-expanded', 'false');
    document.querySelector('[data-menu]')?.classList.remove('is-open');
    menuButton.focus();
  }
});
