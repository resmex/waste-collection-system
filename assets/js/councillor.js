  const menuIcon = document.querySelector('.menu-icon');
        menuIcon.addEventListener('click', function() {
            this.classList.toggle('active');
        });

        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('click', function() {
                const label = this.querySelector('.stat-label').textContent;
                console.log('Stat card clicked:', label);
            });
        });

        const viewButtons = document.querySelectorAll('.view-btn');
        viewButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const ownerName = this.closest('.truck-owner-item').querySelector('h4').textContent;
                console.log('View button clicked for:', ownerName);
            });
        });