// Dark mode state
//

// Global user preference (override) stored in localStorage
if (localStorage.getItem('colorScheme') === null) {
  localStorage.setItem('colorScheme', 'auto');
}

window.toggleColorScheme = function () {
  const colorSchemePreference = localStorage.getItem('colorScheme');
  const isDark = colorSchemePreference === 'auto' ? systemDark.matches : colorSchemePreference === 'dark';
  window.setColorScheme(isDark ? 'light' : 'dark');
}

window.setColorScheme = function (cs) {
  if (! ['auto', 'light', 'dark'].includes(cs)) {
    console.error('Invalid color scheme:', cs);
    return;
  }
  localStorage.setItem('colorScheme', cs);
  applyColorScheme();
}

const systemDark = window.matchMedia('(prefers-color-scheme: dark)');
const applyColorScheme = function (event = systemDark) {
  const colorSchemePreference = localStorage.getItem('colorScheme');
  const isDark = colorSchemePreference === 'auto' ? event.matches : colorSchemePreference === 'dark';
  document.documentElement.classList.toggle('wa-dark', isDark);
};
systemDark.addEventListener('change', applyColorScheme);
applyColorScheme();
