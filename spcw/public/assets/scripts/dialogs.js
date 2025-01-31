/**
 * Using native <dialog> element to confirm user actions
 * 
 * Usage:
 * 
 * <button data-formaction="/delete/214"
 *         data-confirm="Are you sure you want to delete this?">Delete
 * </button>
 * 
 * And insert the dialog element at the end of the body
 */

document.addEventListener('click', function (event) {
  if (event.target.hasAttribute('data-confirm')) {
    var dialog = document.getElementById('dialog-confirm');
    var message = dialog.querySelector('.dialog-message');
    var form = event.target.closest('form');
    var button = event.target;

    dialog.addEventListener('close', function (event) {
      if (dialog.returnValue == 'confirm') {
        form.action = button.getAttribute('data-formaction');
        form.submit();
      }
    });

    message.textContent = button.getAttribute('data-confirm');
    dialog.showModal();
  }

  if (event.target.hasAttribute('data-dialog')) {
    var dialog = event.target.closest('dialog');
    var returnValue = event.target.getAttribute('data-dialog');
    dialog.close(returnValue);
  }
});

