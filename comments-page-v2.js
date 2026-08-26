document.addEventListener('DOMContentLoaded', () => {
    const fallbackComments = [
        { full_name: 'Mert A.', location: 'Süleymanpaşa', rating: 5, body: 'Arsamız için yaptığımız ilk görüşmede fizibiliteyi ve olası proje senaryolarını çok net anlattılar. Süreç yaklaşımı güven verdi.', image_url: null, created_at: '2026-08-25 09:20:00' },
        { full_name: 'Selin K.', location: 'Tekirdağ', rating: 5, body: 'Ofis yenileme projesinde ihtiyaçlarımızı doğru okudular. Malzeme ve uygulama kararları beklediğimizden daha derli toplu ilerledi.', image_url: null, created_at: '2026-08-23 14:35:00' },
        { full_name: 'Kaan D.', location: 'Altınova', rating: 4, body: 'Cephe ve iç mekân detaylarını yerinde görmek karar vermemizi kolaylaştırdı. Görüşme süreci özenli ve anlaşılırdı.', image_url: 'https://cdn.lavira360.com/pioneer/gorseller/WhatsApp%20Image%202026-08-20%20at%2022.24.24%20(2).jpeg', created_at: '2026-08-21 11:10:00' },
        { full_name: 'Ece T.', location: 'Değirmenaltı', rating: 5, body: 'Villa projemiz için planlama aşamasında sundukları alternatifler sayesinde arsayı çok daha doğru değerlendirme şansı bulduk.', image_url: 'https://cdn.lavira360.com/pioneer/gorseller/WhatsApp%20Image%202026-08-20%20at%2022.24.26%20(1).jpeg', created_at: '2026-08-19 16:40:00' }
    ];
    const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
    const validImageUrl = value => /^https:\/\//i.test(value || '') ? value : '';
    const initials = (name = 'Pioneer') => name.trim().charAt(0).toLocaleUpperCase('tr-TR');
    let reviewImages = [];
    let activeImageIndex = 0;
    let lastImageTrigger = null;

    const lightbox = document.createElement('div');
    lightbox.className = 'review-image-lightbox';
    lightbox.setAttribute('aria-hidden', 'true');
    lightbox.innerHTML = '<div class="review-image-lightbox__backdrop" data-close-review-lightbox></div><div class="review-image-lightbox__stage" role="dialog" aria-modal="true" aria-label="Yorum görseli"><button class="review-image-lightbox__close" type="button" data-close-review-lightbox aria-label="Görseli kapat">×</button><button class="review-image-lightbox__arrow review-image-lightbox__arrow--previous" type="button" data-review-image-previous aria-label="Önceki görsel">‹</button><img src="" alt=""><button class="review-image-lightbox__arrow review-image-lightbox__arrow--next" type="button" data-review-image-next aria-label="Sonraki görsel">›</button><p class="review-image-lightbox__counter" aria-live="polite"></p></div>';
    document.body.append(lightbox);
    const lightboxImage = lightbox.querySelector('img');
    const lightboxCounter = lightbox.querySelector('.review-image-lightbox__counter');

    const renderLightbox = () => {
        const image = reviewImages[activeImageIndex];
        if (!image) return;
        lightboxImage.src = image.url;
        lightboxImage.alt = image.alt;
        lightboxCounter.textContent = `${activeImageIndex + 1} / ${reviewImages.length}`;
    };
    const closeLightbox = () => {
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        lastImageTrigger?.focus();
    };
    const moveLightbox = direction => {
        activeImageIndex = (activeImageIndex + direction + reviewImages.length) % reviewImages.length;
        renderLightbox();
    };
    const openLightbox = trigger => {
        const source = trigger.dataset.reviewImage;
        const index = reviewImages.findIndex(image => image.url === source);
        if (index < 0) return;
        lastImageTrigger = trigger;
        activeImageIndex = index;
        renderLightbox();
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        window.setTimeout(() => lightbox.querySelector('.review-image-lightbox__close').focus(), 20);
    };

    const reviewMarkup = comment => {
        const imageUrl = validImageUrl(comment.image_url);
        const rating = '★'.repeat(Math.max(1, Math.min(5, Number(comment.rating) || 5)));
        const imageAlt = `${comment.full_name || 'Yorum sahibi'} tarafından paylaşılan proje görseli`;
        const image = imageUrl ? `<button class="review-card__image-button" type="button" data-review-image="${escapeHtml(imageUrl)}" data-review-alt="${escapeHtml(imageAlt)}" aria-label="Yorum görselini büyüt"><img class="review-card__image" src="${escapeHtml(imageUrl)}" alt="${escapeHtml(imageAlt)}" loading="lazy"><span>Görseli incele <b aria-hidden="true">↗</b></span></button>` : '';
        return `<article class="review-card${imageUrl ? ' review-card--has-image' : ''}">${image}<div class="review-card__identity"><span class="review-card__initial">${escapeHtml(initials(comment.full_name))}</span><div><strong class="review-card__name">${escapeHtml(comment.full_name)}</strong><p class="review-card__meta">${escapeHtml(comment.location || 'Tekirdağ')} · ${rating}</p></div></div><p class="review-card__text">${escapeHtml(comment.body)}</p></article>`;
    };
    const renderComments = comments => {
        const stream = document.querySelector('[data-review-stream]');
        const feed = document.querySelector('[data-comments-feed]');
        const safeComments = comments.length ? comments : fallbackComments;
        reviewImages = safeComments.map(comment => ({ url: validImageUrl(comment.image_url), alt: `${comment.full_name || 'Yorum sahibi'} tarafından paylaşılan proje görseli` })).filter(image => image.url);
        if (stream) stream.innerHTML = [...safeComments, ...safeComments].map(reviewMarkup).join('');
        if (feed) feed.innerHTML = safeComments.map(reviewMarkup).join('') || '<p class="comments-empty">Henüz yayınlanmış yorum bulunmuyor.</p>';
    };
    const loadComments = async () => {
        try {
            const response = await fetch('comments-api.php?action=list&limit=12', { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            renderComments(payload.ok ? payload.comments : fallbackComments);
        } catch {
            renderComments(fallbackComments);
        }
    };

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-review-image]');
        if (trigger) openLightbox(trigger);
        if (event.target.closest('[data-close-review-lightbox]')) closeLightbox();
        if (event.target.closest('[data-review-image-previous]') && reviewImages.length) moveLightbox(-1);
        if (event.target.closest('[data-review-image-next]') && reviewImages.length) moveLightbox(1);
    });
    document.addEventListener('keydown', event => {
        if (!lightbox.classList.contains('is-open')) return;
        if (event.key === 'Escape') closeLightbox();
        if (event.key === 'ArrowLeft') moveLightbox(-1);
        if (event.key === 'ArrowRight') moveLightbox(1);
    });

    const form = document.querySelector('[data-comment-form]');
    if (form) {
        const imageInput = form.querySelector('[data-comment-image]');
        const uploadLabel = form.querySelector('[data-comment-upload-label]');
        const status = form.querySelector('[data-comment-status]');
        imageInput?.addEventListener('change', () => {
            const file = imageInput.files?.[0];
            if (!file) return;
            const validType = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type);
            const validSize = file.size <= 10 * 1024 * 1024;
            if (!validType || !validSize) { imageInput.value = ''; uploadLabel.textContent = 'Yalnızca JPG, PNG veya WEBP ve en fazla 10 MB dosya seçebilirsiniz.'; return; }
            uploadLabel.innerHTML = `<strong>${escapeHtml(file.name)}</strong> seçildi. R2 bağlantısı etkinleştirildiğinde görsel ayrıca yüklenecek; şu anda cihazınızdan hiçbir dosya gönderilmiyor.`;
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            status.className = 'comment-form-status';
            if (!form.checkValidity()) { status.textContent = 'Lütfen zorunlu alanları eksiksiz doldurun.'; status.classList.add('is-error'); form.reportValidity(); return; }
            const bodyText = form.elements.body.value.trim();
            if (bodyText.length < 20) { status.textContent = 'Lütfen deneyiminizi en az 20 karakterle paylaşın.'; status.classList.add('is-error'); return; }
            const submit = form.querySelector('button[type="submit"]');
            const selectedFile = imageInput?.files?.[0];
            submit.disabled = true; submit.textContent = 'İnceleme için kaydediliyor…';
            try {
                const response = await fetch('comments-api.php?action=submit', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ full_name: form.elements.full_name.value, email: form.elements.email.value, location: form.elements.location.value, rating: form.elements.rating.value, body: bodyText, image_name: selectedFile?.name || '', consent: form.elements.consent.checked, website: form.elements.website.value }) });
                const payload = await response.json();
                if (!response.ok || !payload.ok) throw new Error(payload.message || 'Yorum kaydedilemedi.');
                form.reset(); uploadLabel.textContent = 'İsterseniz görsel seçin. R2 bağlantısı tamamlanana kadar dosya cihazınızda kalır; herhangi bir yükleme yapılmaz.'; status.textContent = payload.message; status.classList.add('is-success');
            } catch (error) { status.textContent = error.message || 'Yorumunuz kaydedilemedi. Lütfen tekrar deneyin.'; status.classList.add('is-error'); }
            finally { submit.disabled = false; submit.textContent = 'Yorumu incelemeye gönder'; }
        });
    }
    loadComments();
});
