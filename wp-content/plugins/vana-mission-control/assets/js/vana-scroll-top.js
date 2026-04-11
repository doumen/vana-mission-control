(function () {
    var threshold = 300;
    window.addEventListener('scroll', function () {
        document.body.classList.toggle('scrolled', window.scrollY > threshold);
    }, { passive: true });
})();
