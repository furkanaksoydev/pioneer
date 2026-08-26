document.addEventListener('DOMContentLoaded', () => {
    const footerNavigation = document.querySelector('[data-service-footer-links]');
    const year = document.querySelector('[data-service-year]');
    const services = [
        ['Kat Karşılığı', 'kat-karsiligi.html'], ['Yap-Sat', 'yap-sat.html'], ['Kentsel Dönüşüm', 'kentsel-donusum.html'], ['Arsa Geliştirme', 'arsa-gelistirme.html'], ['Anahtar Teslim', 'anahtar-teslim.html'], ['Mimari Proje', 'mimari-proje.html'], ['Ruhsat Takibi', 'ruhsat-takibi.html'], ['Tadilat', 'tadilat.html'], ['İç Mimari', 'ic-mimari.html'], ['Ofis & Dükkan', 'ofis-dukkan.html'], ['Villa İnşaatı', 'villa-insaati.html'], ['Cephe Yenileme', 'cephe-yenileme.html'], ['İzolasyon İşleri', 'izolasyon-isleri.html'], ['Depo & Sanayi Yapıları', 'depo-sanayi-yapilari.html'], ['Arsa Fizibilitesi', 'arsa-fizibilitesi.html'], ['Gayrimenkul Danışmanlığı', 'gayrimenkul-danismanligi.html'], ['Proje Yönetimi', 'proje-yonetimi.html'], ['Şantiye Yönetimi', 'santiye-yonetimi.html']
    ];

    if (footerNavigation) {
        footerNavigation.innerHTML = services.map(([label, href]) => `<a href="${href}">${label}</a>`).join('');
    }

    if (year) {
        year.textContent = new Date().getFullYear();
    }
});
