document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const galleryImages = [
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
    ].map(name => `https://cdn.lavira360.com/pioneer/gorseller/${encodeURIComponent(name)}`);

    const body = document.body;
    const header = document.getElementById('header');
    const menuToggle = document.querySelector('.menu-toggle');
    const primaryNav = document.getElementById('primary-nav');
    const openProjectButtons = document.querySelectorAll('[data-open-project]');
    const closeProjectButtons = document.querySelectorAll('[data-close-project]');
    const projectModal = document.getElementById('project-modal');
    const mainImage = document.getElementById('gallery-main-image');
    const mainImageButton = document.getElementById('gallery-main-button');
    const thumbnailList = document.getElementById('gallery-thumbnails');
    const lightbox = document.getElementById('image-lightbox');
    const lightboxImage = document.getElementById('lightbox-image');
    const lightboxCounter = document.getElementById('lightbox-counter');
    const closeLightboxButtons = document.querySelectorAll('[data-close-lightbox]');
    const previousLightboxButton = document.querySelector('[data-lightbox-prev]');
    const nextLightboxButton = document.querySelector('[data-lightbox-next]');
    const contactFromProject = document.querySelector('[data-contact-from-project]');
    const contactForm = document.getElementById('contact-form');
    const formNotice = document.getElementById('form-notice');
    let activeGalleryIndex = 0;
    let lightboxIndex = 0;
    let lastFocusedElement = null;

    document.getElementById('year').textContent = new Date().getFullYear();

    const updateHeader = () => header.classList.toggle('is-scrolled', window.scrollY > 32);
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    const closeMenu = () => {
        primaryNav.classList.remove('is-open');
        menuToggle.classList.remove('is-active');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Menüyü aç');
    };

    menuToggle.addEventListener('click', () => {
        const willOpen = !primaryNav.classList.contains('is-open');
        primaryNav.classList.toggle('is-open', willOpen);
        menuToggle.classList.toggle('is-active', willOpen);
        menuToggle.setAttribute('aria-expanded', String(willOpen));
        menuToggle.setAttribute('aria-label', willOpen ? 'Menüyü kapat' : 'Menüyü aç');
    });
    primaryNav.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));

    const renderThumbnails = () => {
        const fragment = document.createDocumentFragment();
        galleryImages.forEach((source, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'gallery-thumb';
            button.setAttribute('role', 'listitem');
            button.setAttribute('aria-label', `${index + 1}. proje görselini göster`);
            button.setAttribute('aria-pressed', String(index === activeGalleryIndex));
            button.dataset.index = String(index);
            const image = document.createElement('img');
            image.src = source;
            image.alt = '';
            image.loading = index > 5 ? 'lazy' : 'eager';
            button.append(image);
            button.addEventListener('click', () => setActiveGalleryImage(index, true));
            fragment.append(button);
        });
        thumbnailList.append(fragment);
    };

    const setActiveGalleryImage = (index, scrollToItem = false) => {
        activeGalleryIndex = index;
        mainImage.src = galleryImages[index];
        mainImage.alt = `Pioneer karma kullanım projesi görseli ${index + 1}`;
        thumbnailList.querySelectorAll('.gallery-thumb').forEach((thumb, thumbIndex) => {
            const isActive = thumbIndex === index;
            thumb.classList.toggle('is-active', isActive);
            thumb.setAttribute('aria-pressed', String(isActive));
            if (isActive && scrollToItem) {
                thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }
        });
    };

    const setPageLocked = isLocked => body.classList.toggle('is-locked', isLocked);

    const openProject = event => {
        lastFocusedElement = event.currentTarget;
        projectModal.classList.add('is-open');
        projectModal.setAttribute('aria-hidden', 'false');
        setPageLocked(true);
        window.setTimeout(() => projectModal.querySelector('.icon-button').focus(), 30);
    };

    const closeProject = () => {
        projectModal.classList.remove('is-open');
        projectModal.setAttribute('aria-hidden', 'true');
        setPageLocked(false);
        if (lastFocusedElement) lastFocusedElement.focus();
    };

    const openLightbox = () => {
        lightboxIndex = activeGalleryIndex;
        renderLightboxImage();
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        window.setTimeout(() => document.querySelector('.lightbox-close').focus(), 30);
    };

    const closeLightbox = () => {
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        mainImageButton.focus();
    };

    const renderLightboxImage = () => {
        lightboxImage.src = galleryImages[lightboxIndex];
        lightboxImage.alt = `Pioneer karma kullanım projesi büyütülmüş görsel ${lightboxIndex + 1}`;
        lightboxCounter.textContent = `${lightboxIndex + 1} / ${galleryImages.length}`;
    };

    const moveLightboxImage = direction => {
        lightboxIndex = (lightboxIndex + direction + galleryImages.length) % galleryImages.length;
        renderLightboxImage();
    };

    renderThumbnails();
    setActiveGalleryImage(0);
    openProjectButtons.forEach(button => button.addEventListener('click', openProject));
    closeProjectButtons.forEach(button => button.addEventListener('click', closeProject));
    mainImageButton.addEventListener('click', openLightbox);
    closeLightboxButtons.forEach(button => button.addEventListener('click', closeLightbox));
    previousLightboxButton.addEventListener('click', () => moveLightboxImage(-1));
    nextLightboxButton.addEventListener('click', () => moveLightboxImage(1));

    contactFromProject.addEventListener('click', () => {
        closeProject();
        window.setTimeout(() => document.getElementById('name').focus(), 650);
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            if (lightbox.classList.contains('is-open')) closeLightbox();
            else if (projectModal.classList.contains('is-open')) closeProject();
        }
        if (lightbox.classList.contains('is-open') && event.key === 'ArrowLeft') moveLightboxImage(-1);
        if (lightbox.classList.contains('is-open') && event.key === 'ArrowRight') moveLightboxImage(1);
    });

    const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -36px 0px' });
    document.querySelectorAll('.reveal-up').forEach(item => revealObserver.observe(item));

    contactForm.addEventListener('submit', event => {
        event.preventDefault();
        if (!contactForm.checkValidity()) {
            formNotice.textContent = 'Lütfen zorunlu alanları tamamlayın.';
            formNotice.className = 'form-notice is-error';
            contactForm.reportValidity();
            return;
        }
        formNotice.textContent = 'Mesajınız hazır. Gönderim entegrasyonu eklendiğinde doğrudan iletilecektir.';
        formNotice.className = 'form-notice is-success';
    });
});
