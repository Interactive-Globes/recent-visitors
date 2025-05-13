<?php
/**
 * Plugin Name: Interactive Globes Addon - Recent Visitors
 * Description: Displays recent visitor locations on an Interactive Globe.
 * Version: 1.0
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
    ];

	$model['meta']['globe_info']['sections']['points']['fields']['recent_visitors_markers'] = [
        'type' => 'fieldset',
		'title' => 'Recent Visitors',
        'desc' => 'Display markers for recent site visitors on the globe.',
		'fields' => $fields,
	];

    $model['meta']['globe_info']['sections']['html_markers']['fields']['recent_visitors_html'] = [
        'type' => 'fieldset',
		'title' => 'Recent Visitors',
        'desc' => 'Display html markers for recent site visitors on the globe.',
		'fields' => $fields,
	];

    $model['meta']['globe_info']['sections']['dotlabel']['fields']['recent_visitors_dotLabels'] = [
        'type' => 'fieldset',
		'title' => 'Recent Visitors',
        'desc' => 'Display html markers for recent site visitors on the globe.',
		'fields' => $fields,
	];




    // add recent visitors to globe info
	return $model;
}
	

// 1. Track visitor and store location
function rvg_track_visitor_location($meta) {
	if (is_admin()) return;

	$ip = rvg_get_user_ip();
	$location = rvg_get_location_from_ip($ip);

	if (!$location) return;

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
    $recent_visitors_html_enabled = isset($meta['recent_visitors_html']['enabled']) && boolval($meta['recent_visitors_html']['enabled']) ? true : false;
    $recent_visitors_dotLabels_enabled = isset($meta['recent_visitors_dotLabels']['enabled']) && boolval($meta['recent_visitors_dotLabels']['enabled']) ? true : false;

    // if all recent visitors are disabled, return
    if (!$recent_visitors_markers_enabled && !$recent_visitors_html_enabled && !$recent_visitors_dotLabels_enabled) return $meta;    

    rvg_track_visitor_location($meta);

    // what type of markers are enabled? multiple can be enabled
    $markers_enabled = [];
    $marker_type_settings_enabled = [];
    if ($recent_visitors_markers_enabled) {
        $markers_enabled[] = 'points';
        $marker_type_settings_enabled[] = 'recent_visitors_markers';
    }
    if ($recent_visitors_html_enabled) {
        $markers_enabled[] = 'html';
        $marker_type_settings_enabled[] = 'recent_visitors_html';
    }
    if ($recent_visitors_dotLabels_enabled) {
        $markers_enabled[] = 'dotLabels';
        $marker_type_settings_enabled[] = 'recent_visitors_dotLabels';
    }

    foreach ($markers_enabled as $marker) {
        if (!isset($meta[$marker]) || !is_array($meta[$marker])) {
            $meta[$marker] = [];
        }
    }

    // Get the minimum time limit from all enabled markers
    $time_limit = 30;
    foreach ($marker_type_settings_enabled as $marker) {
        if (isset($meta[$marker]['time_limit'])) {
            $marker_time_limit = intval($meta[$marker]['time_limit']);
            $time_limit = min($time_limit, $marker_time_limit);
        }
    }

    $visitors = rvg_get_recent_visitor_locations($time_limit);

    // Group visitors by location
    $grouped_visitors = [];
    foreach ($visitors as $visitor) {
        $location = $visitor['location'];
        $key = $location['lat'] . ',' . $location['lon'];
        
        if (!isset($grouped_visitors[$key])) {
            $grouped_visitors[$key] = [
                'count' => 1,
                'location' => $location,
                'cities' => [$location['city']],
                'countries' => [$location['country']]
            ];
        } else {
            $grouped_visitors[$key]['count']++;
            if (!in_array($location['city'], $grouped_visitors[$key]['cities'])) {
                $grouped_visitors[$key]['cities'][] = $location['city'];
            }
            if (!in_array($location['country'], $grouped_visitors[$key]['countries'])) {
                $grouped_visitors[$key]['countries'][] = $location['country'];
            }
        }
    }

    // Add points for each unique location
    foreach ($grouped_visitors as $key => $group) {
        $location = $group['location'];
        $cities = array_unique($group['cities']);
        $countries = array_unique($group['countries']);
        
        $tooltip = sprintf(
            _n(
                '%d visitor from %s, %s',
                '%d visitors from %s, %s',
                $group['count'],
                'globe-visitors'
            ),
            $group['count'],
            implode(', ', $cities),
            implode(', ', $countries)
        );

        // defaults 
        $defaults = [
            'points' => 'pointDefaults',
            'html' => 'htmlDefaults',
            'dotLabels' => 'labelDefaults'
        ];

        foreach ($markers_enabled as $marker) {
            // Skip if defaults don't exist
            if (!isset($defaults[$marker]) || !isset($meta[$defaults[$marker]])) {
                continue;
            }

            $markerDefaults = $meta[$defaults[$marker]];
            $newMarker = [
                'id' => 'visitor_' . md5($key),
                'name' => esc_html($location['city'] . ', ' . $location['country']),
                'title' => $location['city'],
                'latitude' => floatval($location['lat']),
                'longitude' => floatval($location['lon']),
                'tooltipContent' => esc_html($tooltip),
                'useCustom' => '0',
                'location' => $location,
                'globe_id' => absint($id),
                'source' => 'visitors',
                'visitor_count' => $group['count'],
                'visitor_cities' => $group['cities'],
                'visitor_countries' => $group['countries']
            ];

            // Merge with defaults and add to points
            $meta[$marker][] = array_merge($markerDefaults, $newMarker);
        }
    }

    $hook = 'itt_globes/render/content_before';
    add_action($hook, function($content, $id) use ($visitors, $meta, $marker_type_settings_enabled) {
        $visitor_count = count($visitors);
        $countries = array_unique(array_column(array_column($visitors, 'location'), 'country'));
        $country_count = count($countries);
        
        // Get the first message we find from enabled markers
        $message = '';
        foreach ($marker_type_settings_enabled as $marker_settings) {
            if (isset($meta[$marker_settings]['message'])) {
                $message = $meta[$marker_settings]['message'];
                break;
            }
        }
        
        if (empty($message)) {
            $message = '{count} recent visitors from {country_count} countries';
        }
        
        $message = str_replace('{count}', '<span class="visitor-count">' . $visitor_count . '</span>', $message);
        $message = str_replace('{country_count}', '<span class="country-count">' . $country_count . '</span>', $message);
        
        return sprintf(
            '<div class="rvg-recent-visitors" style="text-align: center"><p>%s</p></div>%s',
            wp_kses_post($message),
            $content
        );
    }, 10, 2);

    return $meta;
}

// Add JavaScript tracking
add_action('wp_footer', 'rvg_add_tracking_script');

function rvg_add_tracking_script() {
    ?>
    <script>
    (function() {
        // Only track if we're not in admin
        if (window.location.pathname.indexOf('/wp-admin') !== -1) return;

        // Get visitor's IP and location
        fetch('https://ipinfo.io/json')
            .then(response => response.json())
            .then(data => {
                // Send data to our tracking endpoint
                const formData = new FormData();
                formData.append('action', 'rvg_track_visitor');
                formData.append('ip', data.ip);
                formData.append('location', JSON.stringify({
                    country: data.country,
                    region: data.region,
                    city: data.city,
                    lat: parseFloat(data.loc.split(',')[0]),
                    lon: parseFloat(data.loc.split(',')[1]),
                    isp: data.org
                }));

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
            })
            .catch(error => console.error('Error tracking visitor:', error));
    })();
    </script>
    <?php
}

// Add AJAX endpoint for tracking
add_action('wp_ajax_rvg_track_visitor', 'rvg_ajax_track_visitor');
add_action('wp_ajax_nopriv_rvg_track_visitor', 'rvg_ajax_track_visitor');

function rvg_ajax_track_visitor() {
    // Verify nonce if needed
    // check_ajax_referer('rvg_track_visitor', 'nonce');

    $ip = sanitize_text_field($_POST['ip']);
    $location = json_decode(stripslashes($_POST['location']), true);

    if (!$ip || !$location) {
        wp_send_json_error('Invalid data');
        return;
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
    wp_send_json_success();
}
