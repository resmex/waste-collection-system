const menuIcon = document.querySelector('.menu-icon');
        menuIcon.addEventListener('click', function() {
            this.classList.toggle('active');
        });

        const maintenanceButtons = document.querySelectorAll('.maintenance-btn');
        maintenanceButtons.forEach(button => {
            button.addEventListener('click', function() {
                const action = this.textContent;
                console.log(`Executing: ${action}`);
                alert(`${action} initiated...`);
            });
        });

        const suspendButtons = document.querySelectorAll('.tag.suspend');
        suspendButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userItem = this.closest('.user-item');
                const statusBadge = userItem.querySelector('.status-badge');
                const actionButtons = userItem.querySelector('.action-buttons');
                
                if (statusBadge.classList.contains('status-active')) {
                    statusBadge.className = 'status-badge status-suspended';
                    statusBadge.textContent = 'Suspended';
                    actionButtons.innerHTML = `
                        <button class="action-btn activate">Activate User</button>
                        <button class="action-btn edit">Edit User</button>
                        <button class="action-btn delete">Delete User</button>
                    `;
                    attachActionListeners();
                }
            });
        });

        const deleteButtons = document.querySelectorAll('.action-btn.delete');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userName = this.closest('.user-item').querySelector('h4').textContent;
                if (confirm(`Are you sure you want to delete ${userName}?`)) {
                    this.closest('.user-item').remove();
                }
            });
        });

        const editButtons = document.querySelectorAll('.action-btn.edit');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userName = this.closest('.user-item').querySelector('h4').textContent;
                alert(`Edit user: ${userName}`);
            });
        });

        function attachActionListeners() {
            const activateButtons = document.querySelectorAll('.action-btn.activate');
            activateButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userItem = this.closest('.user-item');
                    const statusBadge = userItem.querySelector('.status-badge');
                    const actionButtons = userItem.querySelector('.action-buttons');
                    
                    statusBadge.className = 'status-badge status-active';
                    statusBadge.textContent = 'Active';
                    actionButtons.innerHTML = `
                        <button class="action-btn edit">Edit User</button>
                        <button class="action-btn delete">Delete User</button>
                    `;
                    attachActionListeners();
                });
            });
        }

        // Menu functions
function toggleMenu() {
    const menu = document.getElementById('sideMenu');
    const overlay = document.getElementById('menuOverlay');
    menu.classList.toggle('active');
    overlay.classList.toggle('active');
}

function closeMenu() {
    const menu = document.getElementById('sideMenu');
    const overlay = document.getElementById('menuOverlay');
    menu.classList.remove('active');
    overlay.classList.remove('active');
}

// System actions
function executeAction(action) {
    closeMenu();
    const actions = {
        'logs': 'Opening system logs...',
        'restart': 'Restarting services...',
        'backup': 'Starting database backup...',
        'cache': 'Clearing system cache...'
    };
    alert(actions[action] || 'Action executed: ' + action);
    // In real implementation, this would make an API call
}

// User management functions
function suspendUser(userId) {
    if(confirm('Are you sure you want to suspend this user?')) {
        // Implement suspend functionality
        console.log('Suspending user:', userId);
    }
}

function warnUser(userId) {
    const message = prompt('Enter warning message:');
    if(message) {
        // Implement warn functionality
        console.log('Warning user:', userId, 'Message:', message);
    }
}

function viewActivity(userId) {
    // Implement view activity functionality
    console.log('Viewing activity for user:', userId);
    alert('Activity log would open for user ' + userId);
}

// Add hover effects
document.addEventListener('DOMContentLoaded', function() {
    const userItems = document.querySelectorAll('.user-item');
    userItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
        });
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
});

        attachActionListeners();