(() => {
    'use strict';

    const body = document.body;
    const root = body.dataset.rootPrefix || '';
    const page = body.dataset.sharedPage || 'standard';
    const services = [['Kat Karşılığı', 'kat-karsiligi.html'], ['Yap-Sat', 'yap-sat.html'], ['Kentsel Dönüşüm', 'kentsel-donusum.html'], ['Arsa Geliştirme', 'arsa-gelistirme.html'], ['Anahtar Teslim', 'anahtar-teslim.html'], ['Mimari Proje', 'mimari-proje.html'], ['Ruhsat Takibi', 'ruhsat-takibi.html'], ['Tadilat', 'tadilat.html'], ['İç Mimari', 'ic-mimari.html'], ['Ofis & Dükkan', 'ofis-dukkan.html'], ['Villa İnşaatı', 'villa-insaati.html'], ['Cephe Yenileme', 'cephe-yenileme.html'], ['İzolasyon İşleri', 'izolasyon-isleri.html'], ['Depo & Sanayi Yapıları', 'depo-sanayi-yapilari.html'], ['Arsa Fizibilitesi', 'arsa-fizibilitesi.html'], ['Gayrimenkul Danışmanlığı', 'gayrimenkul-danismanligi.html'], ['Proje Yönetimi', 'proje-yonetimi.html'], ['Şantiye Yönetimi', 'santiye-yonetimi.html']];
    const whatsapp = 'https://wa.me/905309582252?text=Merhaba%2C%20Pioneer%20ile%20projem%20i%C3%A7in%20%C3%B6n%20g%C3%B6r%C3%BC%C5%9Fme%20yapmak%20istiyorum.';
    const url = path => `${root}${path}`;
    const nav = `<a href="${url('index.html#hero')}">Ana Sayfa</a><a href="${url('index.html#projects')}">Proje</a><a href="${url('yorumlar.html')}">Yorumlar</a><a href="${url('index.html#contact')}">İletişim</a>`;
    const serviceNav = services.map(([label, href]) => `<a href="${url(`hizmetler/${href}`)}">${label}</a>`).join('');

    document.querySelectorAll('[data-shared-header]').forEach(slot => {
        const context = slot.dataset.sharedContext || '';
        const navId = context ? `primary-nav-${context}` : 'primary-nav';
        const headerId = context ? '' : ' id="header"';
        slot.outerHTML = `<a class="skip-link" href="#main-content">İçeriğe geç</a><header class="site-header"${headerId} data-shared-shell-header="${context || 'page'}"><div class="site-shell header-inner"><a href="${url('index.html#hero')}" class="brand" aria-label="Pioneer ana sayfa"><img src="https://cdn.lavira360.com/pioneer/logo.png" alt="Pioneer Mimarlık ve İnşaat"></a><nav class="primary-nav" id="${navId}" aria-label="Ana menü">${nav}</nav><a href="${whatsapp}" class="header-cta" target="_blank" rel="noopener">Görüşme Planla <span aria-hidden="true">↗</span></a><button class="menu-toggle" type="button" aria-label="Menüyü aç" aria-controls="${navId}" aria-expanded="false"><span></span><span></span><span></span></button></div></header>`;
    });

    document.querySelectorAll('[data-shared-footer]').forEach((slot, index) => {
        const yearId = index === 0 ? ' id="year"' : '';
        slot.outerHTML = `<footer class="site-footer" data-shared-shell-footer><div class="site-shell footer-top"><div class="footer-intro"><a class="footer-brand" href="${url('index.html#hero')}"><img src="https://cdn.lavira360.com/pioneer/logo.png" alt="Pioneer Mimarlık ve İnşaat"></a><p>Tekirdağ'da mimarlık, iç mimari, inşaat ve yatırım geliştirme süreçlerini aynı titiz çizgide buluşturuyoruz.</p><a href="${whatsapp}" class="footer-contact-link" target="_blank" rel="noopener">Projenizi anlatın <span aria-hidden="true">↗</span></a></div><div class="footer-nav"><p>Keşfet</p>${nav}</div><address class="footer-address"><p>İletişim</p><a href="tel:+905309582252">+90 (530) 958 22 52</a><a href="https://wa.me/905309582252" target="_blank" rel="noopener">WhatsApp ile yazın</a><a href="mailto:info@pioneermimarlik.com">info@pioneermimarlik.com</a><span>Süleymanpaşa, Tekirdağ<br>Türkiye</span></address></div><nav class="site-shell footer-service-links" aria-label="Pioneer inşaat hizmet sayfaları"><p>İnşaat hizmetleri</p><div>${serviceNav}</div></nav><div class="site-shell footer-bottom"><p>© <span class="shared-year"${yearId}></span> Pioneer Mimarlık ve İnşaat. Tüm hakları saklıdır.</p><p>Seçkin mekânlar · Kalıcı değer</p></div></footer>`;
    });
    document.querySelectorAll('.shared-year').forEach(year => { year.textContent = String(new Date().getFullYear()); });
    if (page === 'home') return;

    document.querySelectorAll('[data-shared-shell-header]').forEach(header => {
        const menu = header.querySelector('.menu-toggle');
        const primaryNav = header.querySelector('.primary-nav');
        const close = () => { primaryNav.classList.remove('is-open'); menu.classList.remove('is-active'); menu.setAttribute('aria-expanded', 'false'); menu.setAttribute('aria-label', 'Menüyü aç'); };
        menu.addEventListener('click', () => { const open = !primaryNav.classList.contains('is-open'); primaryNav.classList.toggle('is-open', open); menu.classList.toggle('is-active', open); menu.setAttribute('aria-expanded', String(open)); menu.setAttribute('aria-label', open ? 'Menüyü kapat' : 'Menüyü aç'); });
        primaryNav.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
        const scrollState = () => header.classList.toggle('is-scrolled', window.scrollY > 32);
        scrollState(); window.addEventListener('scroll', scrollState, { passive: true });
    });
})();
