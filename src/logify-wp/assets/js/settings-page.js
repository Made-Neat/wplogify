jQuery(($) => {
    // Remove query string params we don't need anymore.
    if (history.replaceState) {
        let url = new URL(window.location);
        url.searchParams.delete('reset');
        url.searchParams.delete('migrated');
        url.searchParams.delete('logify_wp_nonce');
        window.history.replaceState({}, document.title, url);
    }
});
