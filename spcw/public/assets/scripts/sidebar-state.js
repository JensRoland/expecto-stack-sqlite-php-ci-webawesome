// Sidebar state (open vs. closed)
//

// Global user preference (override) stored in localStorage
if (localStorage.getItem('sidebarChoice') === null) {
  localStorage.setItem('sidebarChoice', 'auto');
}

window.toggleSidebarState = function () {
  const isOpen = document.documentElement.classList.contains('sidebar-open');
  window.setSidebarState(isOpen ? 'closed' : 'open');
}

window.setSidebarState = function (ss) {
  if (! ['auto', 'open', 'closed'].includes(ss)) {
    console.error('Invalid sidebar state:', ss);
    return;
  }
  localStorage.setItem('sidebarChoice', ss);
  applySidebarState();
}

const systemLargeViewport = window.matchMedia('screen and (min-width: 900px)');
const applySidebarState = function (event = systemLargeViewport) {
  const sidebarChoice = localStorage.getItem('sidebarChoice');
  const shouldShow = sidebarChoice === 'auto' ? event.matches : sidebarChoice === 'open';
  document.documentElement.classList.toggle('sidebar-open', shouldShow);
};
systemLargeViewport.addEventListener('change', applySidebarState);
applySidebarState();
