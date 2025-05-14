(function($) {
    'use strict';

    // Country code to name mapping
    const countryNames = {
        'AF': 'Afghanistan', 'AL': 'Albania', 'DZ': 'Algeria', 'AD': 'Andorra', 'AO': 'Angola', 'AG': 'Antigua and Barbuda', 'AR': 'Argentina', 'AM': 'Armenia', 'AU': 'Australia', 'AT': 'Austria', 'AZ': 'Azerbaijan',
        'BS': 'Bahamas', 'BH': 'Bahrain', 'BD': 'Bangladesh', 'BB': 'Barbados', 'BY': 'Belarus', 'BE': 'Belgium', 'BZ': 'Belize', 'BJ': 'Benin', 'BT': 'Bhutan', 'BO': 'Bolivia', 'BA': 'Bosnia and Herzegovina', 'BW': 'Botswana', 'BR': 'Brazil', 'BN': 'Brunei', 'BG': 'Bulgaria', 'BF': 'Burkina Faso', 'BI': 'Burundi',
        'KH': 'Cambodia', 'CM': 'Cameroon', 'CA': 'Canada', 'CV': 'Cape Verde', 'CF': 'Central African Republic', 'TD': 'Chad', 'CL': 'Chile', 'CN': 'China', 'CO': 'Colombia', 'KM': 'Comoros', 'CG': 'Congo', 'CR': 'Costa Rica', 'HR': 'Croatia', 'CU': 'Cuba', 'CY': 'Cyprus', 'CZ': 'Czech Republic',
        'DK': 'Denmark', 'DJ': 'Djibouti', 'DM': 'Dominica', 'DO': 'Dominican Republic',
        'EC': 'Ecuador', 'EG': 'Egypt', 'SV': 'El Salvador', 'GQ': 'Equatorial Guinea', 'ER': 'Eritrea', 'EE': 'Estonia', 'ET': 'Ethiopia',
        'FJ': 'Fiji', 'FI': 'Finland', 'FR': 'France',
        'GA': 'Gabon', 'GM': 'Gambia', 'GE': 'Georgia', 'DE': 'Germany', 'GH': 'Ghana', 'GR': 'Greece', 'GD': 'Grenada', 'GT': 'Guatemala', 'GN': 'Guinea', 'GW': 'Guinea-Bissau', 'GY': 'Guyana',
        'HT': 'Haiti', 'HN': 'Honduras', 'HU': 'Hungary',
        'IS': 'Iceland', 'IN': 'India', 'ID': 'Indonesia', 'IR': 'Iran', 'IQ': 'Iraq', 'IE': 'Ireland', 'IL': 'Israel', 'IT': 'Italy', 'JM': 'Jamaica', 'JP': 'Japan', 'JO': 'Jordan',
        'KZ': 'Kazakhstan', 'KE': 'Kenya', 'KI': 'Kiribati', 'KP': 'North Korea', 'KR': 'South Korea', 'KW': 'Kuwait', 'KG': 'Kyrgyzstan',
        'LA': 'Laos', 'LV': 'Latvia', 'LB': 'Lebanon', 'LS': 'Lesotho', 'LR': 'Liberia', 'LY': 'Libya', 'LI': 'Liechtenstein', 'LT': 'Lithuania', 'LU': 'Luxembourg',
        'MK': 'North Macedonia', 'MG': 'Madagascar', 'MW': 'Malawi', 'MY': 'Malaysia', 'MV': 'Maldives', 'ML': 'Mali', 'MT': 'Malta', 'MH': 'Marshall Islands', 'MR': 'Mauritania', 'MU': 'Mauritius', 'MX': 'Mexico', 'FM': 'Micronesia', 'MD': 'Moldova', 'MC': 'Monaco', 'MN': 'Mongolia', 'ME': 'Montenegro', 'MA': 'Morocco', 'MZ': 'Mozambique', 'MM': 'Myanmar',
        'NA': 'Namibia', 'NR': 'Nauru', 'NP': 'Nepal', 'NL': 'Netherlands', 'NZ': 'New Zealand', 'NI': 'Nicaragua', 'NE': 'Niger', 'NG': 'Nigeria', 'NO': 'Norway',
        'OM': 'Oman',
        'PK': 'Pakistan', 'PW': 'Palau', 'PS': 'Palestine', 'PA': 'Panama', 'PG': 'Papua New Guinea', 'PY': 'Paraguay', 'PE': 'Peru', 'PH': 'Philippines', 'PL': 'Poland', 'PT': 'Portugal',
        'QA': 'Qatar',
        'RO': 'Romania', 'RU': 'Russia', 'RW': 'Rwanda',
        'KN': 'Saint Kitts and Nevis', 'LC': 'Saint Lucia', 'VC': 'Saint Vincent and the Grenadines', 'WS': 'Samoa', 'SM': 'San Marino', 'ST': 'Sao Tome and Principe', 'SA': 'Saudi Arabia', 'SN': 'Senegal', 'RS': 'Serbia', 'SC': 'Seychelles', 'SL': 'Sierra Leone', 'SG': 'Singapore', 'SK': 'Slovakia', 'SI': 'Slovenia', 'SB': 'Solomon Islands', 'SO': 'Somalia', 'ZA': 'South Africa', 'SS': 'South Sudan', 'ES': 'Spain', 'LK': 'Sri Lanka', 'SD': 'Sudan', 'SR': 'Suriname', 'SE': 'Sweden', 'CH': 'Switzerland', 'SY': 'Syria',
        'TW': 'Taiwan', 'TJ': 'Tajikistan', 'TZ': 'Tanzania', 'TH': 'Thailand', 'TL': 'Timor-Leste', 'TG': 'Togo', 'TO': 'Tonga', 'TT': 'Trinidad and Tobago', 'TN': 'Tunisia', 'TR': 'Turkey', 'TM': 'Turkmenistan', 'TV': 'Tuvalu',
        'UG': 'Uganda', 'UA': 'Ukraine', 'AE': 'United Arab Emirates', 'GB': 'United Kingdom', 'US': 'United States', 'UY': 'Uruguay', 'UZ': 'Uzbekistan',
        'VU': 'Vanuatu', 'VA': 'Vatican City', 'VE': 'Venezuela', 'VN': 'Vietnam',
        'YE': 'Yemen',
        'ZM': 'Zambia', 'ZW': 'Zimbabwe'
    };

    // Function to get country name from code
    function getCountryName(code) {
        return countryNames[code] || code;
    }

    // Function to track visitor
    function trackVisitor() {
        // Send AJAX request to track visitor
        $.ajax({
            url: rvg_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'rvg_track_visitor',
                time_limit: rvg_ajax.data.time_limit,
                nonce: rvg_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (window.location.search.includes('?debug')) {
                        console.log('Response:', response);
                        console.log('all_visitors type:', typeof response.data.all_visitors);
                        console.log('all_visitors:', response.data.all_visitors);
                    }

                    if (typeof ittGlobes === 'undefined') {
                        return;
                    }

                    // Convert object to array
                    const visitors = Object.values(response.data.all_visitors || {});

                    // get current globe
                    const thisGlobe = ittGlobes.globesIndex[rvg_ajax.data.globe_id];
                    
                    // Transform visitors into points data
                    let pointsData = visitors
                        .filter(visitor => visitor.timestamp >= (Date.now() / 1000) - (rvg_ajax.data.time_limit * 60))
                        .map(visitor => ({
                            ...rvg_ajax.data.defaults,
                            coordinates: {
                                latitude: visitor.location.lat,
                                longitude: visitor.location.lon
                            },
                            city: visitor.location.city,
                            country: getCountryName(visitor.location.country),
                            id: visitor.location.city,
                            title: `${visitor.location.city}, ${getCountryName(visitor.location.country)}`,
                            tooltipContent: `${visitor.location.city}, ${getCountryName(visitor.location.country)}`,
                            time: new Date(visitor.timestamp * 1000).toLocaleString(),
                            timestamp: visitor.timestamp,
                        }));

                    let countryCount = {};
                    pointsData.forEach(point => {
                        if(countryCount[point.country]){
                            countryCount[point.country]++;
                        } else {
                            countryCount[point.country] = 1;
                        }
                    }); 

                    let mergedPointsData = [];

                    pointsData.forEach(point => {
                        const existingPoint = mergedPointsData.find(p => p.city === point.city && p.country === point.country);
                        if (existingPoint) {
                            existingPoint.visitorCount += 1;
                            existingPoint.tooltipContent = `${existingPoint.city}, ${existingPoint.country} - ${existingPoint.visitorCount} visitors`;
                        } else {
                            point.visitorCount = 1;
                            point.tooltipContent = `${point.city}, ${point.country} - 1 visitor`;
                            mergedPointsData.push(point);
                        }
                    });

                    if (window.location.search.includes('?debug')) {
                        console.log('mergedPointsData', mergedPointsData);
                    }

                    // Replace pointsData with mergedPointsData
                    pointsData = mergedPointsData;

                    
                    const recentVisitorsElement = document.querySelector('.rvg-recent-visitors');
                    if (recentVisitorsElement) {
                        const visitorCountElement = recentVisitorsElement.querySelector('.visitor-count');
                        const countryCountElement = recentVisitorsElement.querySelector('.country-count');

                        if (visitorCountElement) {
                            visitorCountElement.textContent = pointsData.reduce((acc, point) => acc + point.visitorCount, 0);
                        }

                        if (countryCountElement) {
                            countryCountElement.textContent = Object.keys(countryCount).length;
                        }
                    }

                    // Add points to the globe
                    if(rvg_ajax.data.defaults.type !== 'cylinder'){
                        thisGlobe.objectsData(pointsData);
                    } else {
                        thisGlobe.pointsData(pointsData);
                    }

                } else {
                    console.error('Error tracking visitor:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    }

    // Track visitor when page loads
    $(document).ready(function() {
        trackVisitor();
    });

})(jQuery); 