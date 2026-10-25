/* Runs in <head> before first paint so there is no light/dark flash. */
(function () {
    try {
        var saved = localStorage.getItem('hhh-admin-theme');
        var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    } catch (e) { document.documentElement.setAttribute('data-theme', 'light'); }
})();
