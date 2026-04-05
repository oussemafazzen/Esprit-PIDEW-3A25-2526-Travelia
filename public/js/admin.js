document.addEventListener('DOMContentLoaded', () => {
  console.log("Travelia Admin Portal — Initializing luxury systems...");

  // Minimalist chart animation logic
  const animateCircularChart = () => {
    const circle = document.querySelector('.circle-val');
    if (circle) {
      // Retention animation: from 440 (empty) to 79.2 (82% full)
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
      // Visual feedback only for demo
      navItems.forEach(i => i.classList.remove('active'));
      item.classList.add('active');
    });
  });

  // Handle flash messages fade out
  const alerts = document.querySelectorAll('.alert-success');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    }, 3000);
  });

});
