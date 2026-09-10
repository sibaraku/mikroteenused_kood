<section class="card auth-card">
    <h1>Sisselogimine</h1>
    <p class="muted">Logi sisse, et vahendeid laenutada.</p>

    <form id="login-form">
        <label for="email">E-post</label>
        <input type="email" id="email" name="email" required placeholder="student@kool.ee">

        <label for="password">Parool</label>
        <input type="password" id="password" name="password" required placeholder="••••••••">

        <button type="submit" class="btn btn-primary">Logi sisse</button>
    </form>

    <div class="hint">
        <p><strong>Testkontod (erinev vaade):</strong></p>
        <ul>
            <li><strong>Õpilane:</strong> student@kool.ee / student123 — laenutab vahendeid</li>
            <li><strong>Admin:</strong> admin@kool.ee / admin123 — haldab vahendeid ja laenutusi</li>
        </ul>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (LaenutusApp.getToken()) {
        window.location.href = '/items-page';
        return;
    }

    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        try {
            await LaenutusApp.login(email, password);
            window.location.href = '/items-page';
        } catch (err) {
            LaenutusApp.showToast(err.message, 'error');
        }
    });
});
</script>
