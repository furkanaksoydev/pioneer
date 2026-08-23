import './script-luxury-v5.js';

document.addEventListener('DOMContentLoaded', () => {
    const frame = document.querySelector('[data-showcase-frame]');
    const cluster = document.querySelector('[data-showcase-cluster]');
    const mainImage = document.querySelector('#showcase-main-image');

    if (!frame || !cluster || !mainImage) {
        return;
    }

    const imageNames = [
        'WhatsApp Image 2026-08-20 at 22.24.23 (1).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.24 (2).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.24 (4).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.25 (2).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.26 (1).jpeg'
    ];
    const clusterClasses = [
        'showcase-tile--one',
        'showcase-tile--two',
        'showcase-tile--three',
        'showcase-tile--four',
        'showcase-tile--five'
    ];
    const imageSources = imageNames.map((imageName) => (
        `https://cdn.lavira360.com/pioneer/gorseller/${encodeURIComponent(imageName)}`
    ));
    let selectionLocked = false;

    cluster.innerHTML = imageSources.map((source, index) => `
        <button class="showcase-tile ${clusterClasses[index]}" type="button" data-showcase-source="${source}" aria-label="Proje görseli ${index + 1} seç">
            <img src="${source}" alt="Pioneer projesi detay görünümü ${index + 1}" loading="lazy">
        </button>
    `).join('');

    const setClusterState = (isOpen) => {
        frame.classList.toggle('is-showcase-open', isOpen);
    };

    const updateMainImage = (source, selectedTile) => {
        selectionLocked = true;
        setClusterState(false);
        frame.classList.add('is-showcase-switching');

        cluster.querySelectorAll('.showcase-tile').forEach((tile) => {
            tile.setAttribute('aria-pressed', String(tile === selectedTile));
        });

        window.setTimeout(() => {
            mainImage.src = source;
        }, 110);

        window.setTimeout(() => {
            frame.classList.remove('is-showcase-switching');
        }, 720);
    };

    frame.addEventListener('mouseenter', () => {
        if (!selectionLocked) {
            setClusterState(true);
        }
    });

    frame.addEventListener('mouseleave', () => {
        setClusterState(false);
        selectionLocked = false;
    });

    frame.addEventListener('focusin', () => setClusterState(true));
    frame.addEventListener('focusout', () => {
        window.setTimeout(() => {
            if (!frame.contains(document.activeElement)) {
                setClusterState(false);
                selectionLocked = false;
            }
        }, 0);
    });

    cluster.addEventListener('click', (event) => {
        const tile = event.target.closest('[data-showcase-source]');

        if (tile) {
            updateMainImage(tile.dataset.showcaseSource, tile);
        }
    });
});
