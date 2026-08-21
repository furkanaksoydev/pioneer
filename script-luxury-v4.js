import './script-luxury-v3.js';

document.addEventListener('DOMContentLoaded', () => {
    const accordionItems = Array.from(document.querySelectorAll('.expertise-list details'));

    accordionItems.forEach((item, index) => {
        const summary = item.querySelector('summary');
        const startsOpen = index === 0;

        // Keep content in the DOM so its height can animate; class controls visibility.
        item.open = true;
        item.classList.toggle('is-expanded', startsOpen);
        summary.setAttribute('aria-expanded', String(startsOpen));

        summary.addEventListener('click', event => {
            event.preventDefault();
            const isCurrentItemOpen = item.classList.contains('is-expanded');

            accordionItems.forEach(otherItem => {
                const shouldOpen = otherItem === item && !isCurrentItemOpen;
                otherItem.classList.toggle('is-expanded', shouldOpen);
                otherItem.querySelector('summary').setAttribute('aria-expanded', String(shouldOpen));
            });
        });
    });
});
