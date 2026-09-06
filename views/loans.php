<section>
    <div class="page-header">
        <h1>Minu laenutused</h1>
        <p class="muted">Sinu laenutuste ajalugu.</p>
    </div>

    <div class="card">
        <div id="loans-loading" class="muted">Laadin...</div>
        <table id="loans-table" class="data-table hidden">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Vahend</th>
                    <th>Periood</th>
                    <th>Staatus</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <p id="no-loans" class="muted hidden">Laenutusi pole.</p>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    LaenutusApp.requireAuth();

    try {
        const loans = await LaenutusApp.api('/loans');
        const tbody = document.querySelector('#loans-table tbody');
        document.getElementById('loans-loading').classList.add('hidden');

        if (loans.length === 0) {
            document.getElementById('no-loans').classList.remove('hidden');
            return;
        }

        document.getElementById('loans-table').classList.remove('hidden');

        loans.forEach(loan => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${loan.id}</td>
                <td>${loan.itemId}</td>
                <td>${loan.startDate} – ${loan.endDate}</td>
                <td><span class="badge badge-${loan.status}">${loan.status}</span></td>
                <td><a href="/loan/${loan.id}">Vaata</a></td>
            `;
            tbody.appendChild(tr);
        });
    } catch (err) {
        LaenutusApp.showToast(err.message, 'error');
    }
});
</script>
