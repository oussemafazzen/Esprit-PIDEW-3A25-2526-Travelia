document.addEventListener('DOMContentLoaded', () => {
  console.log("Travelia Admin Portal — Initializing luxury systems...");

  // Minimalist chart animation logic
  const animateCircularChart = () => {
    const circle = document.querySelector('.circle-val');
    if (circle) {
      // 82% retention = 440 * (1 - 0.82) = 79.2
      setTimeout(() => {
        circle.style.strokeDashoffset = "79.2";
      }, 500);
    }
  };

  // Run on load
  animateCircularChart();

  // Simple sidebar navigation highlights
  const navItems = document.querySelectorAll('.nav-item');
  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      // For now, it's a prototype so we just toggle the active state visually
      // e.preventDefault();
      navItems.forEach(i => i.classList.remove('active'));
      item.classList.add('active');
    });
  });

});
