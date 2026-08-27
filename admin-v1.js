(() => {
    const paymentChoices = document.querySelectorAll('input[name="payment_type"]');
    const dueField = document.querySelector('.due-date-field');
    const dueInput = dueField?.querySelector('input');
    const syncPaymentFields = () => {
        const isPending = document.querySelector('input[name="payment_type"]:checked')?.value === 'pending';
        if (!dueField || !dueInput) return;
        dueField.hidden = !isPending;
        dueInput.required = isPending;
        if (!isPending) dueInput.value = '';
    };
    paymentChoices.forEach(choice => choice.addEventListener('change', syncPaymentFields));
    syncPaymentFields();

    document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm || 'Bu işlemi yapmak istediğinizden emin misiniz?')) event.preventDefault();
    }));

    document.querySelectorAll('.settle-form').forEach(form => form.addEventListener('submit', event => {
        const raw = form.querySelector('input[name="amount"]')?.value || '';
        const cents = Math.round(Number(raw.replaceAll('.', '').replace(',', '.')) * 100);
        const remaining = Number(form.dataset.remaining || 0);
        if (!Number.isFinite(cents) || cents <= 0 || cents > remaining) {
            event.preventDefault();
            window.alert('İşlenecek tutar sıfırdan büyük olmalı ve kalan tutarı aşmamalıdır.');
        }
    }));
})();
