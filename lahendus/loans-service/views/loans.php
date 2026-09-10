<section>
    <div class="page-header">
        <h1 id="loans-title">Minu laenutused</h1>
        <p class="muted" id="loans-subtitle">Sinu laenutuste ajalugu.</p>
    </div>

    <div class="card">
        <div id="loans-loading" class="muted">Laadin...</div>
        <table id="loans-table" class="data-table hidden">
            <thead>
                <tr id="loans-table-head">
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
    const isAdmin = LaenutusApp.isAdmin();

    if (isAdmin) {
        document.getElementById('loans-title').textContent = 'Kõik laenutused';
        document.getElementById('loans-subtitle').textContent =
            'Admin vaade: kõigi kasutajate laenutused süsteemis.';

        const headRow = document.getElementById('loans-table-head');
        const th = document.createElement('th');
        th.textContent = 'Laenutaja';
        headRow.insertBefore(th, headRow.lastElementChild);
    }

    try {
        const loans = await LaenutusApp.api('/loans');
        const tbody = document.querySelector('#loans-table tbody');
        document.getElementById('loans-loading').classList.add('hidden');

        if (loans.length === 0) {
            document.getElementById('no-loans').textContent =
                isAdmin ? 'Laenutusi pole veel loodud.' : 'Laenutusi pole.';
            document.getElementById('no-loans').classList.remove('hidden');
            return;
        }

        document.getElementById('loans-table').classList.remove('hidden');

        loans.forEach(loan => {
            const tr = document.createElement('tr');
            const userCell = isAdmin ? `<td>${loan.userId}</td>` : '';
            tr.innerHTML = `
                <td>${loan.id}</td>
                <td>${loan.itemId}</td>
                <td>${loan.startDate} – ${loan.endDate}</td>
                <td><span class="badge badge-${loan.status}">${loan.status}</span></td>
                ${userCell}
                <td><a href="/loan/${loan.id}">Vaata</a></td>
            `;
            tbody.appendChild(tr);
        });
    } catch (err) {
        LaenutusApp.showToast(err.message, 'error');
    }
});
</script>
