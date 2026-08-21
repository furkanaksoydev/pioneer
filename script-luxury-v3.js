import './script-luxury-v2.js';

document.addEventListener('DOMContentLoaded', () => {
    const sceneObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('scene-entered');
            sceneObserver.unobserve(entry.target);
        });
    }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });

    document.querySelectorAll('.scroll-scene').forEach(scene => sceneObserver.observe(scene));
});
