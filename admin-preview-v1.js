(() => {
    const titles = { comments: 'Yorum <em>merkezi.</em>', finance: 'Finans <em>özeti.</em>', payables: 'Ödeme <em>yapılacaklar.</em>', receivables: 'Ödeme <em>alınacaklar.</em>' };
    document.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => {
        const tab = button.dataset.tab;
        document.querySelectorAll('[data-tab]').forEach(item => item.classList.toggle('is-active', item === button));
        document.querySelectorAll('[data-view]').forEach(view => view.classList.toggle('is-active', view.dataset.view === tab));
        document.querySelector('[data-title]').innerHTML = titles[tab];
    }));
    const typeInputs = document.querySelectorAll('[data-finance-form] input[name="type"]');
    const due = document.querySelector('[data-finance-form] .due');
    const method = document.querySelector('[data-finance-form] .method');
    typeInputs.forEach(input => input.addEventListener('change', () => { const pending = typeInputs[1].checked; due.hidden = !pending; method.hidden = pending; }));
})();
