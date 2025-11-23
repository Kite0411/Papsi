<style>
            .logout-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    animation: fadeInBg 0.3s ease;
}

.logout-modal {
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    width: 90%;
    max-width: 420px;
    overflow: hidden;
    transform: scale(0.9);
    animation: fadeInModal 0.25s ease forwards;
}

.logout-modal-header {
    background: var(--gradient-primary);
    color: white;
    padding: 20px 25px;
    text-align: center;
}

.logout-modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: white;
}

.logout-modal-body {
    padding: 25px 30px;
    text-align: center;
}

.logout-modal-body p {
    color: var(--dark-gray);
    font-size: 1.05rem;
    margin: 0;
}

.logout-modal-footer {
    padding: 15px 25px 25px;
    display: flex;
    justify-content: center;
    gap: 15px;
}

.btn-cancel,
.btn-logout {
    border: none;
    border-radius: var(--radius-md);
    padding: 10px 30px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s ease;
    font-size: 1rem;
}

.btn-cancel {
    background: #e0e0e0;
    color: #333;
}

.btn-cancel:hover {
    background: #cacaca;
}

.btn-logout {
    background: var(--gradient-primary);
    color: white;
    box-shadow: var(--shadow-md);
}

.btn-logout:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

@keyframes fadeInBg {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeInModal {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}
</style>
<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="logout-modal-overlay">
    <div class="logout-modal">
        <div class="logout-modal-header">
            <h2>Confirm Logout</h2>
        </div>
        <div class="logout-modal-body">
            <p>Are you sure you want to log out of your admin account?</p>
        </div>
        <div class="logout-modal-footer">
            <button class="btn-cancel" onclick="closeLogoutModal()">Cancel</button>
            <button class="btn-logout" onclick="confirmLogout()">Logout</button>
        </div>
    </div>
</div>

<script>
    function openLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function confirmLogout() {
    // Add a short fade before redirect for a polished feel
    const modal = document.getElementById('logoutModal');
    modal.style.opacity = '0';
    setTimeout(() => {
        window.location.href = '../auth/logout.php';
    }, 250);
}
</script>