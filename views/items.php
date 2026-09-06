<section>
    <div class="page-header">
        <h1>Vahendid</h1>
        <p class="muted">Vali vahend ja loo uus laenutus.</p>
    </div>

    <div class="card">
        <h2>Uus laenutus</h2>
        <form id="loan-form" class="loan-form">
            <label for="item-select">Vahend</label>
            <select id="item-select" required></select>

            <div class="form-row">
                <div>
                    <label for="start-date">Algus</label>
                    <input type="date" id="start-date" required>
                </div>
                <div>
                    <label for="end-date">Lõpp</label>
                    <input type="date" id="end-date" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Laenuta</button>
        </form>
    </div>

    <div class="card">
        <h2>Kõik vahendid</h2>
        <div id="items-loading" class="muted">Laadin...</div>
        <table id="items-table" class="data-table hidden">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nimi</th>
                    <th>Kategooria</th>
                    <th>Staatus</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    LaenutusApp.requireAuth();

    try {
        const items = await LaenutusApp.api('/items');
        const tbody = document.querySelector('#items-table tbody');
        const select = document.getElementById('item-select');

        items.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.id}</td>
                <td>${item.name}</td>
                <td>${item.category}</td>
                <td><span class="badge badge-${item.status}">${item.status}</span></td>
            `;
            tbody.appendChild(tr);

            if (item.status === 'available') {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = `${item.name} (${item.id})`;
                select.appendChild(opt);
            }
        });

        document.getElementById('items-loading').classList.add('hidden');
        document.getElementById('items-table').classList.remove('hidden');
    } catch (err) {
        LaenutusApp.showToast(err.message, 'error');
    }

    document.getElementById('loan-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const user = LaenutusApp.getUser();

        try {
            const loan = await LaenutusApp.api('/loans', {
                method: 'POST',
                body: JSON.stringify({
                    userId: user.id,
                    itemId: document.getElementById('item-select').value,
                    startDate: document.getElementById('start-date').value,
                    endDate: document.getElementById('end-date').value,
                }),
            });
            LaenutusApp.showToast(`Laenutus ${loan.id} loodud (${loan.status})`, 'success');
            setTimeout(() => window.location.href = `/loan/${loan.id}`, 800);
        } catch (err) {
            LaenutusApp.showToast(err.message, 'error');
        }
    });
});
</script>
