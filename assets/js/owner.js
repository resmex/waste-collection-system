
        const menuIcon = document.querySelector('.menu-icon');
        menuIcon.addEventListener('click', function() {
            this.classList.toggle('active');
        });

        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('click', function() {
                console.log('Stat card clicked:', this.querySelector('.stat-label').textContent);
            });
        });

        const actionCards = document.querySelectorAll('.action-card');
        actionCards.forEach(card => {
            card.addEventListener('click', function() {
                console.log('Action card clicked:', this.querySelector('.action-label').textContent);
            });
        });