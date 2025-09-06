// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})

// Auto-hide toasts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const toasts = document.querySelectorAll('.toast');
    
    toasts.forEach(toast => {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease';
            
            setTimeout(() => {
                toast.remove();
            }, 500);
        }, 5000);
    });
    
    // Initialize sidebar dropdowns to show active module
    const activeModule = document.querySelector('.sidebar-link.active');
    if (activeModule) {
        const targetId = activeModule.getAttribute('href');
        const targetCollapse = document.querySelector(targetId);
        if (targetCollapse) {
            new bootstrap.Collapse(targetCollapse, { toggle: true });
        }
    }
    
    // Add smooth scrolling to anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add animation to cards on scroll
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe all cards for animation
    document.querySelectorAll('.card, .stat-card, .module-card, .quick-action').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(el);
    });
});

// Refresh data function
function refreshData() {
    const refreshBtn = document.querySelector('.btn-outline-primary');
    const originalText = refreshBtn.innerHTML;
    
    refreshBtn.innerHTML = '<i class="bx bx-loader bx-spin"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        location.reload();
    }, 1500);
}

// Add event listener to refresh button
document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.querySelector('.btn-outline-primary');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshData);
    }
});