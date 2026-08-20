// ======================== BUTTON CLICK HANDLER ========================
function handleButtonClick(buttonText) {
    console.log(`${buttonText} button clicked`);
    // Add your custom functionality here
    // Example: alert(`${buttonText} clicked`);
    // Example: Redirect to another page
    // window.location.href = 'admissions.html';
}

// ======================== SMOOTH SCROLLING ========================
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '#portal') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
});

// ======================== STICKY HEADER EFFECT ========================
window.addEventListener('scroll', function() {
    const header = document.querySelector('header');
    if (window.scrollY > 50) {
        header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.2)';
    } else {
        header.style.boxShadow = '0 2px 12px rgba(0, 0, 0, 0.15)';
    }
});

// ======================== ANIMATION ON SCROLL ========================
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.animation = 'fadeInUp 0.6s ease-in-out forwards';
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Observe feature cards and program items
document.querySelectorAll('.feature-card, .program-item').forEach(el => {
    el.style.opacity = '0';
    observer.observe(el);
});

// ======================== ADD CSS ANIMATION ========================
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);

// ======================== MOBILE MENU TOGGLE ========================
// Uncomment and use if you add a hamburger menu
/*
const hamburgerBtn = document.querySelector('.hamburger-btn');
const nav = document.querySelector('nav');

if (hamburgerBtn) {
    hamburgerBtn.addEventListener('click', function() {
        nav.classList.toggle('active');
    });
}
*/

// ======================== FORM VALIDATION (if needed) ========================
function validateContactForm(form) {
    const email = form.querySelector('input[type="email"]');
    const message = form.querySelector('textarea');
    
    if (!email.value || !email.value.includes('@')) {
        alert('Please enter a valid email address');
        return false;
    }
    
    if (!message.value.trim()) {
        alert('Please enter a message');
        return false;
    }
    
    return true;
}

// ======================== LAZY LOADING FOR IMAGES ========================
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                imageObserver.unobserve(img);
            }
        });
    });
    
    document.querySelectorAll('img[data-src]').forEach(img => imageObserver.observe(img));
}

// ======================== SCROLL TO TOP BUTTON ========================
const scrollTopBtn = document.createElement('button');
scrollTopBtn.innerHTML = '↑';
scrollTopBtn.className = 'scroll-to-top';
scrollTopBtn.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 40px;
    height: 40px;
    background-color: #fbbf24;
    color: #1e3a8a;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: none;
    z-index: 99;
    font-size: 20px;
    font-weight: bold;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
`;

document.body.appendChild(scrollTopBtn);

window.addEventListener('scroll', () => {
    if (window.pageYOffset > 300) {
        scrollTopBtn.style.display = 'block';
    } else {
        scrollTopBtn.style.display = 'none';
    }
});

scrollTopBtn.addEventListener('click', () => {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

scrollTopBtn.addEventListener('mouseover', () => {
    scrollTopBtn.style.backgroundColor = '#f59e0b';
    scrollTopBtn.style.transform = 'scale(1.1)';
});

scrollTopBtn.addEventListener('mouseout', () => {
    scrollTopBtn.style.backgroundColor = '#fbbf24';
    scrollTopBtn.style.transform = 'scale(1)';
});

// ======================== ACTIVE LINK HIGHLIGHTING ========================
function setActiveLink() {
    const sections = document.querySelectorAll('section');
    const navLinks = document.querySelectorAll('nav a:not(.login-btn)');
    
    window.addEventListener('scroll', () => {
        let current = '';
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            if (pageYOffset >= sectionTop - 200) {
                current = section.getAttribute('id');
            }
        });
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${current}`) {
                link.classList.add('active');
            }
        });
    });
}

setActiveLink();

// ======================== CONSOLE MESSAGE ========================
console.log('%c🎓 Welcome to GGCAST Landing Page', 'font-size: 20px; color: #2c5282; font-weight: bold;');
console.log('Assets folder structure:', {
    logo: 'assets/images/logo.png',
    heroBanner: 'assets/images/hero-banner.jpg',
    otherImages: 'assets/images/'
});
