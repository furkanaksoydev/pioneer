document.addEventListener('DOMContentLoaded', () => {
    const imageRoot = 'https://cdn.lavira360.com/pioneer/gorseller/';
    const serviceImages = [
        'WhatsApp Image 2026-08-20 at 22.24.23.jpeg', 'WhatsApp Image 2026-08-20 at 22.24.23 (1).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.23 (2).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.24.jpeg', 'WhatsApp Image 2026-08-20 at 22.24.24 (2).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.24 (3).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.24 (4).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.24 (5).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.25.jpeg', 'WhatsApp Image 2026-08-20 at 22.24.25 (1).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.25 (2).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.25 (3).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.25 (4).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.25 (5).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.25 (6).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.26.jpeg', 'WhatsApp Image 2026-08-20 at 22.24.26 (1).jpeg', 'WhatsApp Image 2026-08-20 at 22.24.26 (2).jpeg'
    ].map(name => `${imageRoot}${encodeURIComponent(name)}`);
    const cards = document.querySelectorAll('.construction-service-grid article');

    cards.forEach((card, index) => {
        const image = document.createElement('img');
        image.className = 'construction-service-card__image';
        image.src = serviceImages[index];
        image.alt = `${card.querySelector('h3')?.textContent || 'Pioneer İnşaat'} hizmetinden proje detayı`;
        image.loading = index < 4 ? 'eager' : 'lazy';
        image.decoding = 'async';
        card.prepend(image);
        card.classList.add('construction-service-card--visual');
    });

    const statement = document.querySelector('.construction-statement');
    if (!statement || document.querySelector('.construction-visual-gallery')) return;
    const gallery = document.createElement('section');
    gallery.className = 'construction-visual-gallery';
    gallery.setAttribute('aria-label', 'Pioneer İnşaat proje detayları');
    gallery.innerHTML = `<figure><img src="${serviceImages[10]}" alt="Pioneer İnşaat proje iç mekân detayı" loading="lazy"><figcaption><span>01</span> Uygulama detayları</figcaption></figure><figure><img src="${serviceImages[15]}" alt="Pioneer İnşaat mimari proje görünümü" loading="lazy"><figcaption><span>02</span> Mekân ve malzeme</figcaption></figure><figure><img src="${serviceImages[17]}" alt="Pioneer İnşaat yapı görünümü" loading="lazy"><figcaption><span>03</span> Kalıcı değer</figcaption></figure>`;
    statement.insertAdjacentElement('afterend', gallery);
});
