<?php
/**
 * Plugin Name: Interactive Globes Addon - Recent Visitors
 * Description: Displays recent visitor locations on an Interactive Globe.
 * Version: 1.1.2
 * Author: Interactive Globes Team
 */

add_filter('itt_globes/render/post_setup_meta', 'rvg_add_recent_visitors_to_globe', 20, 2);
add_filter('itt_globes/shortcode/post_setup_meta', 'rvg_add_recent_visitors_to_globe', 20, 2);
add_filter('itt_globes/globe/model', 'rvg_extend_globe_model', 10, 1 );

// add recent visitors meta to globe
function rvg_extend_globe_model( $model ) {

    $fields = [
        'enabled' => [
            'type' => 'switcher',
            'title' => 'Enabled',
            'desc' => 'Enable or disable the recent visitors section.',
            'default' => false,
            
        ],
        'time_limit' => [
            'type' => 'number',
            'title' => 'Time Frame (minutes)',
            'desc' => 'The time frame to display recent visitors.',
            'default' => 30,
            'dependency' => ['enabled', '==', true],
        ],
        'message' => [
            'type' => 'textarea',
            'title' => 'Message',
            'desc' => 'The message to display in the recent visitors section. You can use {count} to display the number of recent visitors and {country_count} to display the number of different countries.',
            'default' => '{count} recent visitors from {country_count} countries',
            'dependency' => ['enabled', '==', true],
        ],
        'random' => [
            'type' => 'number',
            'title' => 'Random',
            'desc' => 'The number of random visitors to display.',
            'default' => 0,
            'dependency' => ['enabled', '==', true],
        ]
    ];

	$model['meta']['globe_info']['sections']['points']['fields']['recent_visitors_markers'] = [
        'type' => 'fieldset',
		'title' => 'Recent Visitors',
        'desc' => 'Display markers for recent site visitors on the globe.',
		'fields' => $fields,
	];
    // add recent visitors to globe info
	return $model;
}

// 2. Get IP

function rvg_get_user_ip() {
    // For local development/testing
    if (defined('RVG_TEST_IP') && RVG_TEST_IP) {
        return filter_var(RVG_TEST_IP, FILTER_VALIDATE_IP) ? RVG_TEST_IP : '';
    }
    // Get IP with proxy support
    $ip_headers = array(
        'REMOTE_ADDR',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED'
    );

    foreach ($ip_headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim($_SERVER[$header]);
            // If comma-separated list, get first IP
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }

            // check if ip is localhost
            if ($ip === '::1' || $ip === '127.0.0.1') {
                // safely get public ip
                $ip = wp_remote_get('https://api.ipify.org', 
                    array('timeout' => 5)
                );
                $ip = wp_remote_retrieve_body($ip);
            }

            // Validate IP format
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '';
}

// 3. Get location using ipinfo.io
function rvg_get_location_from_ip($ip) {
    // Validate IP
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }

    // Check if we already have this IP's location cached
    $cache_key = 'rvg_ip_location_' . md5($ip);
    $cached_location = get_transient($cache_key);
    if ($cached_location !== false) {
        return $cached_location;
    }

    $url = "https://ipinfo.io/" . urlencode($ip) . "/json";
    $response = wp_remote_get($url, array(
        'timeout' => 5,
        'headers' => array('User-Agent' => 'WordPress/Globe-Visitors-Plugin')
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if ($data && !isset($data['error'])) {
        // Split the loc string into lat/lon
        $loc = isset($data['loc']) ? explode(',', $data['loc']) : [0, 0];
        
        $location = array(
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : '',
            'region' => isset($data['region']) ? sanitize_text_field($data['region']) : '',
            'city' => isset($data['city']) ? sanitize_text_field($data['city']) : '',
            'lat' => isset($loc[0]) ? floatval($loc[0]) : 0,
            'lon' => isset($loc[1]) ? floatval($loc[1]) : 0,
            'isp' => isset($data['org']) ? sanitize_text_field($data['org']) : ''
        );
        
        // Cache the location data for 1 week
        set_transient($cache_key, $location, 7 * DAY_IN_SECONDS);
        
        return $location;
    }
    return false;
}

// 4. Retrieve saved visitors
function rvg_get_recent_visitor_locations($time_limit = 30) {
	$visitors = get_option('rvg_recent_visitors', []);
	if (!is_array($visitors)) return [];

	// Filter out entries older than the time limit
	$threshold = time() - ($time_limit * 60);
	return array_filter($visitors, function($entry) use ($threshold) {
		return $entry['timestamp'] >= $threshold;
	});
}

// 5. Add data to globe points
function rvg_add_recent_visitors_to_globe($meta, $id) {
    // check if recent visitors are enabled
    $recent_visitors_markers_enabled = isset($meta['recent_visitors_markers']['enabled']) && boolval($meta['recent_visitors_markers']['enabled']) ? true : false;
    // if all recent visitors are disabled, return
    if (!$recent_visitors_markers_enabled) return $meta;    
    
    if (!isset($meta['points']) || !is_array($meta['points'])) {
        $meta['points'] = [];
    }
    
    rvg_enqueue_scripts(
        [
            'globe_id' => $id,
            'time_limit' => $meta['recent_visitors_markers']['time_limit'],
            'defaults' => $meta['pointDefaults'],
            'recent_visitors_markers' => $meta['recent_visitors_markers']
        ]
    );

    $hook = 'itt_globes/render/content_before';
    add_action($hook, function($content, $id) use ($meta) {
        
        // Get the first message we find from enabled markers
        $message = '';
        if (isset($meta['recent_visitors_markers']['message'])) {
            $message = $meta['recent_visitors_markers']['message'];
        }
        
        if (empty($message)) {
            $message = '{count} recent visitors from {country_count} countries';
        }
        
        $message = str_replace('{count}', '<span class="visitor-count"></span>', $message);
        $message = str_replace('{country_count}', '<span class="country-count"></span>', $message);
        
        return sprintf(
            '<div class="rvg-recent-visitors" style="text-align: center"><p>%s</p></div>%s',
            wp_kses_post($message),
            $content
        );
    }, 10, 2);

    return $meta;
}

// Add AJAX endpoints
add_action('wp_ajax_rvg_track_visitor', 'rvg_ajax_track_visitor');
add_action('wp_ajax_nopriv_rvg_track_visitor', 'rvg_ajax_track_visitor');

// Function to generate random location data
function rvg_get_random_locations() {
    $locations = [
        // North America
        ['country' => 'US', 'region' => 'California', 'city' => 'Los Angeles', 'lat' => 34.0522, 'lon' => -118.2437, 'isp' => 'AT&T'],
        ['country' => 'US', 'region' => 'New York', 'city' => 'New York', 'lat' => 40.7128, 'lon' => -74.0060, 'isp' => 'Verizon'],
        ['country' => 'US', 'region' => 'Texas', 'city' => 'Houston', 'lat' => 29.7604, 'lon' => -95.3698, 'isp' => 'Comcast'],
        ['country' => 'CA', 'region' => 'Ontario', 'city' => 'Toronto', 'lat' => 43.6532, 'lon' => -79.3832, 'isp' => 'Rogers'],
        ['country' => 'MX', 'region' => 'Mexico City', 'city' => 'Mexico City', 'lat' => 19.4326, 'lon' => -99.1332, 'isp' => 'Telmex'],
        
        // Europe
        ['country' => 'GB', 'region' => 'England', 'city' => 'London', 'lat' => 51.5074, 'lon' => -0.1278, 'isp' => 'BT Group'],
        ['country' => 'DE', 'region' => 'Berlin', 'city' => 'Berlin', 'lat' => 52.5200, 'lon' => 13.4050, 'isp' => 'Deutsche Telekom'],
        ['country' => 'FR', 'region' => 'Île-de-France', 'city' => 'Paris', 'lat' => 48.8566, 'lon' => 2.3522, 'isp' => 'Orange'],
        ['country' => 'IT', 'region' => 'Lazio', 'city' => 'Rome', 'lat' => 41.9028, 'lon' => 12.4964, 'isp' => 'TIM'],
        ['country' => 'ES', 'region' => 'Madrid', 'city' => 'Madrid', 'lat' => 40.4168, 'lon' => -3.7038, 'isp' => 'Telefónica'],
        
        // Asia
        ['country' => 'JP', 'region' => 'Tokyo', 'city' => 'Tokyo', 'lat' => 35.6762, 'lon' => 139.6503, 'isp' => 'NTT'],
        ['country' => 'CN', 'region' => 'Beijing', 'city' => 'Beijing', 'lat' => 39.9042, 'lon' => 116.4074, 'isp' => 'China Telecom'],
        ['country' => 'IN', 'region' => 'Maharashtra', 'city' => 'Mumbai', 'lat' => 19.0760, 'lon' => 72.8777, 'isp' => 'Airtel'],
        ['country' => 'SG', 'region' => 'Singapore', 'city' => 'Singapore', 'lat' => 1.3521, 'lon' => 103.8198, 'isp' => 'SingTel'],
        ['country' => 'KR', 'region' => 'Seoul', 'city' => 'Seoul', 'lat' => 37.5665, 'lon' => 126.9780, 'isp' => 'KT'],
        
        // South America
        ['country' => 'BR', 'region' => 'São Paulo', 'city' => 'São Paulo', 'lat' => -23.5505, 'lon' => -46.6333, 'isp' => 'Vivo'],
        ['country' => 'AR', 'region' => 'Buenos Aires', 'city' => 'Buenos Aires', 'lat' => -34.6037, 'lon' => -58.3816, 'isp' => 'Telecom Argentina'],
        ['country' => 'CO', 'region' => 'Bogotá', 'city' => 'Bogotá', 'lat' => 4.7110, 'lon' => -74.0721, 'isp' => 'Claro'],
        ['country' => 'PE', 'region' => 'Lima', 'city' => 'Lima', 'lat' => -12.0464, 'lon' => -77.0428, 'isp' => 'Movistar'],
        ['country' => 'CL', 'region' => 'Santiago', 'city' => 'Santiago', 'lat' => -33.4489, 'lon' => -70.6693, 'isp' => 'Entel'],
        
        // Africa
        ['country' => 'ZA', 'region' => 'Gauteng', 'city' => 'Johannesburg', 'lat' => -26.2041, 'lon' => 28.0473, 'isp' => 'Vodacom'],
        ['country' => 'EG', 'region' => 'Cairo', 'city' => 'Cairo', 'lat' => 30.0444, 'lon' => 31.2357, 'isp' => 'Orange Egypt'],
        ['country' => 'NG', 'region' => 'Lagos', 'city' => 'Lagos', 'lat' => 6.5244, 'lon' => 3.3792, 'isp' => 'MTN'],
        ['country' => 'KE', 'region' => 'Nairobi', 'city' => 'Nairobi', 'lat' => -1.2921, 'lon' => 36.8219, 'isp' => 'Safaricom'],
        ['country' => 'MA', 'region' => 'Casablanca', 'city' => 'Casablanca', 'lat' => 33.5731, 'lon' => -7.5898, 'isp' => 'Maroc Telecom'],
        
        // Oceania
        ['country' => 'AU', 'region' => 'New South Wales', 'city' => 'Sydney', 'lat' => -33.8688, 'lon' => 151.2093, 'isp' => 'Telstra'],
        ['country' => 'NZ', 'region' => 'Auckland', 'city' => 'Auckland', 'lat' => -36.8509, 'lon' => 174.7645, 'isp' => 'Spark'],
        ['country' => 'FJ', 'region' => 'Suva', 'city' => 'Suva', 'lat' => -18.1416, 'lon' => 178.4419, 'isp' => 'Digicel'],
        ['country' => 'PG', 'region' => 'Port Moresby', 'city' => 'Port Moresby', 'lat' => -9.4780, 'lon' => 147.1500, 'isp' => 'Digicel'],
        ['country' => 'SB', 'region' => 'Honiara', 'city' => 'Honiara', 'lat' => -9.4456, 'lon' => 159.9729, 'isp' => 'Our Telekom']
    ];
    
    return $locations;
}

// AJAX handler for tracking visitors
function rvg_ajax_track_visitor() {
    check_ajax_referer('rvg_nonce', 'nonce');

    $ip = rvg_get_user_ip();
    if (empty($ip)) {
        wp_send_json_error('Could not detect IP address');
    }

    $location = rvg_get_location_from_ip($ip);
    if (!$location) {
        wp_send_json_error('Could not get location data');
    }

    $new_entry = [
        'ip' => $ip,
        'timestamp' => time(),
        'location' => $location,
    ];

    $visitors = get_option('rvg_recent_visitors', []);
    if (!is_array($visitors)) $visitors = [];

    // Update timestamp for existing IP or add new visitor
    $found = false;
    foreach ($visitors as &$entry) {
        if ($entry['ip'] === $ip) {
            $entry['timestamp'] = time();
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $visitors[] = $new_entry;
    }
    
    // Clean up old entries (older than 24 hours)
    $threshold = time() - (24 * HOUR_IN_SECONDS);
    
    $visitors = array_filter($visitors, function($entry) use ($threshold) {
        return $entry['timestamp'] >= $threshold;
    });
    
    update_option('rvg_recent_visitors', $visitors);

    $time_limit = isset($_POST['time_limit']) ? intval($_POST['time_limit']) : 30;
    $random_count = isset($_POST['random']) ? intval($_POST['random']) : 0;

    // only send entries that are within the time limit
    $visitors = array_filter($visitors, function($entry) use ($time_limit) {
        return $entry['timestamp'] >= (time() - ($time_limit * 60));
    });

    // Add random entries if enabled
    if ($random_count > 0) {
        // Get or create cached random entries
        $cache_key = 'rvg_random_entries';
        $random_entries = get_transient($cache_key);
        
        if ($random_entries === false) {
            // Generate new random entries
            $all_locations = rvg_get_random_locations();
            shuffle($all_locations);
            
            // If we need more entries than we have unique locations
            if ($random_count > count($all_locations)) {
                $random_entries = [];
                // Fill with all locations first
                $random_entries = $all_locations;
                // Then add more by randomly selecting from all locations
                while (count($random_entries) < $random_count) {
                    $random_entries[] = $all_locations[array_rand($all_locations)];
                }
            } else {
                $random_entries = array_slice($all_locations, 0, $random_count);
            }
            
            // Cache for 5 minutes
            set_transient($cache_key, $random_entries, 5 * MINUTE_IN_SECONDS);
        }
        
        // Add random entries to visitors
        foreach ($random_entries as $random_location) {
            $visitors[] = [
                'timestamp' => time() - rand(1, $time_limit * 60),
                'location' => $random_location
            ];
        }
    }

    // Remove 'ip' from the current visitor data
    $current_visitor = $new_entry;
    unset($current_visitor['ip']);

    // Remove 'ip' from all visitors data
    $visitors_without_ip = array_map(function($entry) {
        unset($entry['ip']);
        return $entry;
    }, $visitors);

    wp_send_json_success([
        'current_visitor' => $current_visitor,
        'all_visitors' => $visitors_without_ip
    ]);
}

// Enqueue scripts
function rvg_enqueue_scripts( $data ) {
    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'rvg-visitor-tracker',
        plugins_url('js/visitor-tracker.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script('rvg-visitor-tracker', 'rvg_ajax', array(
        'globe_id' => $data['globe_id'],
        'data' => array_merge($data, [
            'recent_visitors_markers' => isset($data['recent_visitors_markers']) ? $data['recent_visitors_markers'] : []
        ]),
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rvg_nonce')
    ));
}
