// Show notifications when the page loads
//

const MESSAGE_TIMEOUT = 5000,
      SHOW_CLASS = 'msg-show',
      PERSISTENT_CLASS = 'msg-persistent';

const notifications = document.querySelectorAll('#notifications > .wa-callout');
if (notifications.length) {
  // We use requestAnimationFrame to prevent the browser from optimizing
  // the code and skipping the class addition
  requestAnimationFrame(() => {
    notifications.forEach(msg => {
      msg.classList.add(SHOW_CLASS);
    });
    // Remove notifications automatically unless they have the persistent class
    setTimeout(() => {
      notifications.forEach(msg => {
        if (! msg.classList.contains(PERSISTENT_CLASS)) {
          msg.classList.remove(SHOW_CLASS);
        }
      });
    }, MESSAGE_TIMEOUT);
  });
}
