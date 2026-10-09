<section>
    <div class="page-header">
        <h1>Vahendid</h1>
        <p class="muted" id="items-subtitle">Vali vahend ja loo uus laenutus.</p>
    </div>

    <div class="card" id="loan-form-card">
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
        <h2 id="items-table-title">Kõik vahendid</h2>
        <div id="items-loading" class="muted">Laadin...</div>
        <table id="items-table" class="data-table hidden">
            <thead>
                <tr id="items-table-head">
                    <th>ID</th>
                    <th>Nimi</th>
                    <th>Kategooria</th>
                    <th>Staatus</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="card" id="reservations-card">
        <h2>Minu ootejärjekorrad</h2>
        <div id="reservations-loading" class="muted">Laadin...</div>
        <table id="reservations-table" class="data-table hidden">
            <thead><tr><th>Vahend</th><th>Staatus</th><th>Lisatud</th><th></th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    LaenutusApp.requireAuth();
    const isAdmin = LaenutusApp.isAdmin();

    if (isAdmin) {
        document.getElementById('items-subtitle').textContent =
            'Admin vaade: vahendite ülevaade ja staatuse haldus.';
        document.getElementById('loan-form-card').classList.add('hidden');
        document.getElementById('items-table-title').textContent = 'Vahendite haldus';
        document.getElementById('reservations-card').classList.add('hidden');

        const headRow = document.getElementById('items-table-head');
        const th = document.createElement('th');
        th.textContent = 'Muuda staatust';
        headRow.appendChild(th);
    } else {
        const th = document.createElement('th');
        th.textContent = 'Tegevused';
        document.getElementById('items-table-head').appendChild(th);
    }

    try {
        const items = await LaenutusApp.api('/items');
        const tbody = document.querySelector('#items-table tbody');
        const select = document.getElementById('item-select');

        items.forEach(item => {
            const tr = document.createElement('tr');

            if (isAdmin) {
                tr.innerHTML = `
                    <td>${item.id}</td>
                    <td>${item.name}</td>
                    <td>${item.category}</td>
                    <td><span class="badge badge-${item.status}">${item.status}</span></td>
                    <td>
                        <form class="inline-form item-status-form" data-id="${item.id}">
                            <select name="status">
                                <option value="available" ${item.status === 'available' ? 'selected' : ''}>available</option>
                                <option value="reserved" ${item.status === 'reserved' ? 'selected' : ''}>reserved</option>
                                <option value="broken" ${item.status === 'broken' ? 'selected' : ''}>broken</option>
                                <option value="maintenance" ${item.status === 'maintenance' ? 'selected' : ''}>maintenance</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Salvesta</button>
                        </form>
                    </td>
                `;
            } else {
                tr.innerHTML = `
                    <td>${item.id}</td>
                    <td>${item.name}</td>
                    <td>${item.category}</td>
                    <td><span class="badge badge-${item.status}">${item.status}</span></td>
                    <td>${item.status === 'reserved' ? `<button type="button" class="btn btn-primary btn-sm reservation-create" data-item-id="${item.id}">Teavita vabanemisel</button>` : '—'}</td>
                `;

                if (item.status === 'available') {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = `${item.name} (${item.id})`;
                    select.appendChild(opt);
                }
            }

            tbody.appendChild(tr);
        });

        document.getElementById('items-loading').classList.add('hidden');
        document.getElementById('items-table').classList.remove('hidden');

        if (!isAdmin) {
            document.querySelectorAll('.reservation-create').forEach(button => {
                button.addEventListener('click', async () => {
                    button.disabled = true;
                    try {
                        const reservation = await LaenutusApp.api('/reservations', {
                            method: 'POST',
                            body: JSON.stringify({ itemId: button.dataset.itemId }),
                        });
                        LaenutusApp.showToast(`Olete ootejärjekorras: ${reservation.itemName}`, 'success');
                        setTimeout(() => window.location.reload(), 600);
                    } catch (err) {
                        button.disabled = false;
                        LaenutusApp.showToast(err.message, 'error');
                    }
                });
            });

            const reservations = await LaenutusApp.api('/reservations');
            const reservationBody = document.querySelector('#reservations-table tbody');
            reservations.forEach(reservation => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${reservation.itemName}</td>
                    <td>${reservation.status}</td>
                    <td>${reservation.createdAt || '—'}</td>
                    <td>${reservation.status !== 'cancelled' ? `<button type="button" class="btn btn-sm reservation-cancel" data-id="${reservation.id}">Tühista</button>` : '—'}</td>
                `;
                reservationBody.appendChild(tr);
            });
            document.getElementById('reservations-loading').classList.add('hidden');
            document.getElementById('reservations-table').classList.remove('hidden');
            document.querySelectorAll('.reservation-cancel').forEach(button => {
                button.addEventListener('click', async () => {
                    try {
                        await LaenutusApp.api(`/reservations/${button.dataset.id}`, { method: 'DELETE' });
                        window.location.reload();
                    } catch (err) {
                        LaenutusApp.showToast(err.message, 'error');
                    }
                });
            });
        }

        if (isAdmin) {
            document.querySelectorAll('.item-status-form').forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const id = form.dataset.id;
                    const status = form.querySelector('select').value;
                    try {
                        await LaenutusApp.api(`/items/${id}`, {
                            method: 'PATCH',
                            body: JSON.stringify({ status }),
                        });
                        LaenutusApp.showToast(`Vahend ${id} staatus uuendatud`, 'success');
                        setTimeout(() => window.location.reload(), 600);
                    } catch (err) {
                        LaenutusApp.showToast(err.message, 'error');
                    }
                });
            });
        }
    } catch (err) {
        LaenutusApp.showToast(err.message, 'error');
    }

    if (!isAdmin) {
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
    }
});
</script>
