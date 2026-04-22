document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('deleteConfirmModal');
  const confirmBtn = document.getElementById('confirmDeleteBtn');
  const cancelBtns = document.querySelectorAll('[data-close-delete-modal]');
  let targetForm = null;

  document.querySelectorAll('.open-delete-modal').forEach(button => {
    button.addEventListener('click', (e) => {
      e.preventDefault();
      targetForm = button.closest('form');
      if (modal) modal.classList.add('show');
    });
  });

  cancelBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      if (modal) modal.classList.remove('show');
      targetForm = null;
    });
  });

  if (confirmBtn) {
    confirmBtn.addEventListener('click', () => {
      if (targetForm) targetForm.submit();
    });
  }

  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.remove('show');
        targetForm = null;
      }
    });
  }
});
