import './script-luxury-v6.js';

document.addEventListener('DOMContentLoaded', () => {
    const constructionPanel = document.querySelector('#construction-panel');
    const constructionView = document.querySelector('[data-construction-view]');
    const openButtons = document.querySelectorAll('[data-open-construction]');
    const closeButtons = document.querySelectorAll('[data-close-construction]');
    const constructionYear = document.querySelector('[data-construction-year]');
    let lastFocusedElement = null;

    if (constructionYear) {
        constructionYear.textContent = new Date().getFullYear();
    }

    if (!constructionPanel || !constructionView) {
        return;
    }

    const setConstructionState = (isOpen) => {
        document.body.classList.toggle('construction-active', isOpen);
        constructionPanel.setAttribute('aria-hidden', String(!isOpen));
    };

    const openConstruction = (event) => {
        lastFocusedElement = event.currentTarget;
        setConstructionState(true);
        window.setTimeout(() => constructionView.focus({ preventScroll: true }), 520);
    };

    const closeConstruction = () => {
        setConstructionState(false);
        if (lastFocusedElement) {
            window.setTimeout(() => lastFocusedElement.focus({ preventScroll: true }), 520);
        }
    };

    openButtons.forEach((button) => button.addEventListener('click', openConstruction));
    closeButtons.forEach((button) => button.addEventListener('click', closeConstruction));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('construction-active')) {
            closeConstruction();
        }
    });
});
