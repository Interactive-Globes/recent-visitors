(function($) {
    'use strict';

    // Only track if we're not in admin
    if (window.location.pathname.indexOf('/wp-admin') !== -1) return;

    // Get visitor's IP and location
    fetch('https://ipinfo.io/json')
        .then(response => response.json())
        .then(data => {
            // Send data to our tracking endpoint
            const formData = new FormData();
            formData.append('action', 'rvg_track_visitor');
            formData.append('nonce', rvgTracking.nonce);
            formData.append('ip', data.ip);
            formData.append('location', JSON.stringify({
                country: data.country,
                region: data.region,
                city: data.city,
                lat: parseFloat(data.loc.split(',')[0]),
                lon: parseFloat(data.loc.split(',')[1]),
                isp: data.org
            }));

            // Try to send the data
            fetch(rvgTracking.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).catch(error => {
                console.error('Error tracking visitor:', error);
            });
        })
        .catch(error => {
            console.error('Error getting visitor location:', error);
        });

})(jQuery); 