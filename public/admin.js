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

  // ── Delete Modal Global Logic ──
  const deleteModal = document.getElementById('deleteConfirmModal');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  let formToSubmit = null;

  document.querySelectorAll('.open-delete-modal').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      formToSubmit = btn.closest('form');
      if (deleteModal) deleteModal.classList.add('active');
    });
  });

  const closeDeleteModal = () => {
    if (deleteModal) deleteModal.classList.remove('active');
    formToSubmit = null;
  };

  if (deleteModal) {
    deleteModal.querySelector('.delete-cancel-btn').addEventListener('click', closeDeleteModal);
    deleteModal.addEventListener('click', (e) => {
      if (e.target === deleteModal) closeDeleteModal();
    });
  }

  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', () => {
      if (formToSubmit) formToSubmit.submit();
    });
  }

});
