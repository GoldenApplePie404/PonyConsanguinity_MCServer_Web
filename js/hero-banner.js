document.addEventListener('DOMContentLoaded', function() {
    const heroBg = document.querySelector('.hero-bg');
    if (!heroBg) return;

    const backgroundImages = [
        'assets/img/pc1.png',
        'assets/img/pc2.png',
        'assets/img/pc3.png',
        'assets/img/pc4.png'
    ];

    let currentIndex = 0;
    const rotationInterval = 8000;
    const transitionDuration = 2000;

    function preloadImages() {
        backgroundImages.forEach(src => {
            const img = new Image();
            img.src = src;
        });
    }

    function setBackgroundWithTransition(imageUrl) {
        heroBg.style.transition = `background-image ${transitionDuration}ms ease-in-out`;
        heroBg.style.backgroundImage = `url('${imageUrl}')`;
    }

    function rotateBackground() {
        currentIndex = (currentIndex + 1) % backgroundImages.length;
        setBackgroundWithTransition(backgroundImages[currentIndex]);
    }

    function startRotation() {
        setBackgroundWithTransition(backgroundImages[0]);
        setInterval(rotateBackground, rotationInterval);
    }

    preloadImages();
    startRotation();
});