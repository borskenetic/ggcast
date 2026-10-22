(function () {
    let pendingResolve = null;

    function loanTermsModal() {
        return document.getElementById('loanTermsModal');
    }

    function hideLoanTermsModal() {
        const modal = loanTermsModal();
        if (!modal) return;
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    }

    function resetLoanTermsForm() {
        const defaultRadio = document.getElementById('loanTermsDefault');
        const dueInput = document.getElementById('loanTermsDueDate');
        const daysInput = document.getElementById('loanTermsLoanDays');
        const panel = document.getElementById('loanTermsCustomPanel');

        if (defaultRadio) defaultRadio.checked = true;
        if (dueInput) dueInput.value = '';
        if (daysInput) daysInput.value = '';
        if (panel) panel.classList.add('d-none');
    }

    function toggleCustomPanel() {
        const customRadio = document.getElementById('loanTermsCustom');
        const panel = document.getElementById('loanTermsCustomPanel');
        if (panel && customRadio) {
            panel.classList.toggle('d-none', !customRadio.checked);
        }
    }

    function bindLoanTermsModalOnce() {
        const modal = loanTermsModal();
        if (!modal || modal.dataset.bound === '1') return;
        modal.dataset.bound = '1';

        document.getElementById('loanTermsDefault')?.addEventListener('change', toggleCustomPanel);
        document.getElementById('loanTermsCustom')?.addEventListener('change', toggleCustomPanel);

        document.getElementById('loanTermsConfirmBtn')?.addEventListener('click', function () {
            const customRadio = document.getElementById('loanTermsCustom');
            const result = {};

            if (customRadio?.checked) {
                const due = document.getElementById('loanTermsDueDate')?.value?.trim();
                const daysRaw = document.getElementById('loanTermsLoanDays')?.value?.trim();
                const days = daysRaw ? parseInt(daysRaw, 10) : 0;

                if (due) {
                    result.due_date = due;
                } else if (days > 0) {
                    result.loan_duration_days = days;
                } else {
                    alert('Enter a due date or number of loan days.');
                    return;
                }
            }

            hideLoanTermsModal();
            if (pendingResolve) {
                const resolve = pendingResolve;
                pendingResolve = null;
                resolve(result);
            }
        });

        document.getElementById('loanTermsCancelBtn')?.addEventListener('click', function () {
            hideLoanTermsModal();
            pendingResolve = null;
        });

        document.getElementById('loanTermsModalClose')?.addEventListener('click', function () {
            hideLoanTermsModal();
            pendingResolve = null;
        });
    }

    function refreshLoanTermsCopy() {
        const regular = Number(window.LOAN_DEFAULT_DAYS) || 7;
        const reserve = Number(window.RESERVE_LOAN_DAYS) || 1;
        const maxReserve = Number(window.MAX_RESERVE_LOANS);
        const reserveCap = Number.isFinite(maxReserve) ? maxReserve : 1;
        const defaultLabel = document.getElementById('loanTermsDefaultDays');
        const reserveLabel = document.getElementById('loanTermsReserveDays');
        const note = document.getElementById('loanTermsReserveNote');

        if (defaultLabel) defaultLabel.textContent = String(regular);
        if (reserveLabel) reserveLabel.textContent = String(reserve);

        if (!note) return;

        const honorsCustom = window.LOAN_TERMS_RESERVE_HONORS_CUSTOM === true;
        const customClause = honorsCustom
            ? 'A custom period replaces the default for this checkout, including reserve copies.'
            : 'On self-checkout, reserve copies always keep the reserve due date.';
        note.textContent = 'Reserve limit: ' + reserveCap + ' book' + (reserveCap === 1 ? '' : 's')
            + ', counted inside the total loan limit. ' + customClause;
    }

    window.initLoanTermsModal = function (defaultDays, reserveDays) {
        window.LOAN_DEFAULT_DAYS = defaultDays || window.LOAN_DEFAULT_DAYS || 7;
        if (reserveDays) window.RESERVE_LOAN_DAYS = reserveDays;
        refreshLoanTermsCopy();
        bindLoanTermsModalOnce();
    };

    window.promptLoanTerms = function () {
        bindLoanTermsModalOnce();
        const modal = loanTermsModal();
        if (!modal) {
            return Promise.resolve({});
        }

        resetLoanTermsForm();
        refreshLoanTermsCopy();

        return new Promise(function (resolve) {
            pendingResolve = resolve;
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.LOAN_DEFAULT_DAYS !== 'undefined') {
            initLoanTermsModal(window.LOAN_DEFAULT_DAYS);
        } else {
            bindLoanTermsModalOnce();
        }
    });
})();
