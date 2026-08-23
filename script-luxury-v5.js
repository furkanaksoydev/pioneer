import './script-luxury-v4.js';

document.addEventListener('DOMContentLoaded', () => {
    const leftTrack = document.querySelector('[data-gallery-left]');
    const rightTrack = document.querySelector('[data-gallery-right]');

    if (!leftTrack || !rightTrack) {
        return;
    }

    const imageNames = [
        'WhatsApp Image 2026-08-20 at 22.24.23.jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.23 (1).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.23 (2).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.24.jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.24 (2).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.24 (3).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.24 (4).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.24 (5).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.25.jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.25 (1).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.25 (2).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.25 (3).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.25 (4).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.25 (5).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.25 (6).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.26.jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.26 (1).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.26 (2).jpeg',
        'WhatsApp Image 2026-08-20 at 22.24.26 (3).jpeg'
    ];

    const imageSources = imageNames.map((imageName) => (
        `https://cdn.lavira360.com/pioneer/gorseller/${encodeURIComponent(imageName)}`
    ));
    const splitIndex = Math.ceil(imageSources.length / 2);

    const renderTrack = (track, sources, startIndex) => {
        const repeatedSources = [...sources, ...sources];

        track.innerHTML = repeatedSources.map((source, index) => {
            const imageNumber = startIndex + (index % sources.length) + 1;

            return `
                <figure class="project-gallery-item">
                    <img src="${source}" alt="Pioneer projesi görseli ${imageNumber}" loading="lazy">
                </figure>
            `;
        }).join('');
    };

    renderTrack(leftTrack, imageSources.slice(0, splitIndex), 0);
    renderTrack(rightTrack, imageSources.slice(splitIndex), splitIndex);
});
