<section>
    <div class="page-header">
        <h1>Laenutuse detail</h1>
        <a href="/loans-page" class="back-link">← Tagasi laenutuste juurde</a>
    </div>

    <div id="detail-loading" class="muted">Laadin...</div>
    <div id="detail-card" class="card hidden">
        <dl class="detail-list">
            <dt>Laenutuse ID</dt>
            <dd id="detail-loan-id"></dd>
            <dt id="detail-user-label" class="hidden">Laenutaja</dt>
            <dd id="detail-user-id" class="hidden"></dd>
            <dt>Staatus</dt>
            <dd id="detail-loan-status"></dd>
            <dt>Vahend</dt>
            <dd id="detail-item-name"></dd>
            <dt>Vahendi staatus</dt>
            <dd id="detail-item-status"></dd>
            <dt>Periood</dt>
            <dd id="detail-period"></dd>
        </dl>

        <div id="admin-loan-panel" class="admin-panel hidden">
            <h3>Admin: laenutuse haldus</h3>
            <form id="admin-status-form" class="inline-form">
                <label for="admin-status">Muuda staatust</label>
                <select id="admin-status" name="status">
                    <option value="pending_item">pending_item</option>
                    <option value="confirmed">confirmed</option>
                    <option value="rejected">rejected</option>
                    <option value="cancelled">cancelled</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Salvesta</button>
            </form>
        </div>

        <button type="button" id="cancel-btn" class="btn btn-danger hidden">Tühista laenutus</button>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    LaenutusApp.requireAuth();
    const loanId = <?= json_encode($loanId ?? '') ?>;
    const isAdmin = LaenutusApp.isAdmin();

    try {
        const view = await LaenutusApp.api(`/loan-view/${loanId}`);

        document.getElementById('detail-loan-id').textContent = view.loanId;
        document.getElementById('detail-loan-status').innerHTML =
            `<span class="badge badge-${view.loanStatus}">${view.loanStatus}</span>`;
        document.getElementById('detail-item-name').textContent = `${view.itemName} (${view.itemId})`;
        document.getElementById('detail-item-status').innerHTML =
            `<span class="badge badge-${view.itemStatus}">${view.itemStatus}</span>`;
        document.getElementById('detail-period').textContent = `${view.startDate} – ${view.endDate}`;

        if (isAdmin) {
            document.getElementById('detail-user-label').classList.remove('hidden');
            document.getElementById('detail-user-id').classList.remove('hidden');
            document.getElementById('detail-user-id').textContent = view.userId;

            document.getElementById('admin-loan-panel').classList.remove('hidden');
            document.getElementById('admin-status').value = view.loanStatus;

            document.getElementById('admin-status-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const status = document.getElementById('admin-status').value;
                try {
                    await LaenutusApp.api(`/loans/${loanId}`, {
                        method: 'PATCH',
                        body: JSON.stringify({ status }),
                    });
                    LaenutusApp.showToast('Laenutuse staatus uuendatud', 'success');
                    setTimeout(() => window.location.reload(), 600);
                } catch (err) {
                    LaenutusApp.showToast(err.message, 'error');
                }
            });
        }

        document.getElementById('detail-loading').classList.add('hidden');
        document.getElementById('detail-card').classList.remove('hidden');

        if (!isAdmin && (view.loanStatus === 'confirmed' || view.loanStatus === 'pending_item')) {
            const btn = document.getElementById('cancel-btn');
            btn.classList.remove('hidden');
            btn.addEventListener('click', async () => {
                if (!confirm('Kas oled kindel, et soovid laenutuse tühistada?')) return;
                try {
                    await LaenutusApp.api(`/loans/${loanId}`, { method: 'DELETE' });
                    LaenutusApp.showToast('Laenutus tühistatud', 'success');
                    setTimeout(() => window.location.href = '/loans-page', 800);
                } catch (err) {
                    LaenutusApp.showToast(err.message, 'error');
                }
            });
        }
    } catch (err) {
        document.getElementById('detail-loading').textContent = err.message;
    }
});
</script>
