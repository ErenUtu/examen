<?php

if ( ! defined( 'WP_DEBUG' ) ) {
    die( 'Directe toegang verboden.' );
}

// Laad de stijl van het thema
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
});

function get_lat_lon_from_address($address) {
    $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode($address . ', Netherlands') . '&countrycodes=NL';
    $response = wp_remote_get($url);
    $data = wp_remote_retrieve_body($response);
    $json = json_decode($data, true);
    if (isset($json[0])) {
        $lat = $json[0]['lat'];
        $lon = $json[0]['lon'];
        error_log("Geocoding voor adres '$address' gaf lat: $lat, lon: $lon");
        return array('lat' => $lat, 'lon' => $lon);
    }
    error_log("Geocoding mislukt voor adres '$address'. Geen lat/lon beschikbaar.");
    return array('lat' => 52.379189, 'lon' => 4.90093); // Amsterdam
}

if (isset($_POST['submit_dealer'])) {
    global $wpdb;
    $dealer_name = sanitize_text_field($_POST['dealer_name']);
    $dealer_address = sanitize_text_field($_POST['dealer_address']);
    $dealer_postcode = sanitize_text_field($_POST['dealer_postcode']);
    $dealer_city = sanitize_text_field($_POST['dealer_city']);
    $dealer_url = sanitize_text_field($_POST['dealer_url']);
    $full_address = $dealer_address . ' ' . $dealer_postcode . ' ' . $dealer_city;
    $geolocation = get_lat_lon_from_address($full_address);
    $lat = $geolocation['lat'];
    $lon = $geolocation['lon'];
    $last_ccdeb = $wpdb->get_var("SELECT MAX(ccdeb) FROM wp_dealers");
    $new_ccdeb = $last_ccdeb ? $last_ccdeb + 1 : 300000;
    $wpdb->insert('wp_dealers', array(
        'ccdeb' => $new_ccdeb,
        'naamorg' => $dealer_name,
        'adres' => $dealer_address,
        'huisnummer' => '',
        'postcode' => $dealer_postcode,
        'stad' => $dealer_city,
        'url' => $dealer_url,
        'lat' => $lat,
        'lon' => $lon,
    ));
    echo '<div class="updated"><p>Dealer toegevoegd!</p></div>';
    echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
    exit;
}

if (isset($_POST['upload_csv'])) {
    if (!empty($_FILES['csv_file']['name'])) {
        $allowed_extensions = ['csv'];
        $file_info = pathinfo($_FILES['csv_file']['name']);
        $file_extension = strtolower($file_info['extension']);
        if (!in_array($file_extension, $allowed_extensions)) {
            echo '<div class="error"><p>Ongeldig bestandstype. Alleen CSV-bestanden zijn toegestaan.</p></div>';
        } else {
            if (($handle = fopen($_FILES['csv_file']['tmp_name'], "r")) !== FALSE) {
                $row = 0;
                while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    if ($row > 0) {
                        $full_address = $data[3] . ' ' . $data[5] . ' ' . $data[6];
                        $geolocation = get_lat_lon_from_address($full_address);
                        $lat = $geolocation['lat'];
                        $lon = $geolocation['lon'];
                        $wpdb->insert('wp_dealers', array(
                            'ccdeb' => $wpdb->get_var("SELECT MAX(ccdeb) FROM wp_dealers") + 1,
                            'naamorg' => sanitize_text_field($data[1]),
                            'adres' => sanitize_text_field($data[3]),
                            'huisnummer' => sanitize_text_field($data[4]),
                            'postcode' => sanitize_text_field($data[5]),
                            'stad' => sanitize_text_field($data[6]),
                            'url' => !empty($data[2]) ? sanitize_text_field($data[2]) : '',
                            'lat' => $lat,
                            'lon' => $lon,
                        ));
                    }
                    $row++;
                }
                fclose($handle);
                echo '<div class="updated"><p>CSV-bestand succesvol verwerkt!</p></div>';
                echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
                exit;
            }
        }
    } else {
        echo '<div class="error"><p>Geen bestand geselecteerd.</p></div>';
    }
}

function dealer_locator_shortcode() {
    global $wpdb;
    $table_name = 'wp_dealers';
    $sql = "SELECT ccdeb, naamorg, adres, huisnummer, postcode, stad, url, lat, lon FROM $table_name";
    $dealers = $wpdb->get_results($sql, ARRAY_A);

    ob_start();
    ?>
    <style>
    .dealer-locator-flex-container {
        display: flex;
        align-items: flex-start;
        gap: 2%;
        width: 100%;
    }
    .dealer-locator-map-container {
        height: 500px;
        width: 70%;
    }
    .dealer-locator-details-container {
        width: 28%;
        padding-left: 20px;
        margin-top: 0;
    }
    @media (max-width: 900px) {
        .dealer-locator-flex-container { flex-direction: column; }
        .dealer-locator-map-container, .dealer-locator-details-container { width: 100%; }
        .dealer-locator-details-container { padding-left: 0; }
    }
    .google-maps-route-btn {
        background-color: white;
        color: #689ca4;
        border: 2px solid #689ca4;
        border-radius: 4px;
        padding: 6px 16px;
        margin-top: 7px;
        cursor: pointer;
        font-size: 15px;
        transition: background 0.2s, color 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: bold;
    }
    .google-maps-route-btn:hover {
        background-color: #689ca4;
        color: white;
        border-color: #689ca4;
    }
    .dealer-name-highlight {
        color: #689ca4 !important;
    }
    .afstand-label {
        color: #689ca4 !important;
    }
    </style>
    <div id="dealer-locator-container" class="dealer-locator-container">
        <h1 class="dealer-locator-title">Vind een verkooppunt</h1>
        <div class="dealer-locator-search-container">
            <input type="text" id="postcode" class="dealer-locator-input-field" placeholder="Voer postcode en plaats in, bijv. 8388 Oosterstreek">
            <button onclick="zoekDealer()" class="dealer-locator-search-button">Zoeken</button>
        </div>
        <div class="dealer-locator-flex-container">
            <div id="map" class="dealer-locator-map-container"></div>
            <div id="dealer-details" class="dealer-locator-details-container">
                <h3>Dealers:</h3>
                <ul id="dealer-list"></ul>
            </div>
        </div>
    </div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.locatecontrol@0.71.1/dist/L.Control.Locate.min.js"></script>
    <script>
        var map;
        var markers = [];
        var userMarker = null;
        var userCoords = null;
        var dealers = <?php echo json_encode($dealers); ?>;

        function formatUrl(url) {
            if (!url) return "#";
            if (!/^https?:\/\//i.test(url)) {
                return "https://" + url;
            }
            return url;
        }

        function initMap() {
            map = L.map('map').setView([52.379189, 4.90093], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            L.control.locate({
                position: 'topleft',
                locateOptions: {
                    enableHighAccuracy: true,
                    maxZoom: 16
                }
            }).addTo(map);
        }

        function geocodeAddress(address, callback) {
            var url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}, Netherlands&countrycodes=NL`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data && data[0]) {
                        var lat = data[0].lat;
                        var lon = data[0].lon;
                        callback(lat, lon);
                    } else {
                        callback(52.379189, 4.90093);
                    }
                });
        }

        function calculateDistance(lat1, lon1, lat2, lon2) {
            var R = 6371;
            var dLat = (lat2 - lat1) * Math.PI / 180;
            var dLon = (lon2 - lon1) * Math.PI / 180;
            var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLon / 2) * Math.sin(dLon / 2);
            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            var distance = R * c;
            return distance.toFixed(2);
        }

        function zoekDealer() {
            var postcodePlaats = document.getElementById('postcode').value;
            geocodeAddress(postcodePlaats, function(lat, lon) {
                if (lat && lon) {
                    map.setView([lat, lon], 14);
                    if (userMarker) userMarker.remove();
                    userMarker = L.marker([lat, lon], {
                        icon: L.icon({
                            iconUrl: 'https://maps.gstatic.com/mapfiles/ms2/micons/blue.png',
                            iconSize: [40, 40]
                        })
                    }).addTo(map).bindPopup('Jouw locatie').openPopup();
                    userCoords = {lat: parseFloat(lat), lon: parseFloat(lon)};
                    filterDealers([lat, lon]);
                } else {
                    alert('Geocoding failed for postcode: ' + postcodePlaats);
                }
            });
        }

        function filterDealers(userLocation) {
            var maxDistance = 50;
            clearMarkers();
            var dealersSorted = dealers.map(function(dealer) {
                var dealerLocation = [dealer.lat, dealer.lon];
                if (dealer.lat && dealer.lon) {
                    var distance = map.distance(userLocation, dealerLocation) / 1000;
                    dealer.distance = distance.toFixed(2);
                } else {
                    dealer.distance = Infinity;
                }
                return dealer;
            }).sort(function(a, b) {
                return a.distance - b.distance;
            });

            document.getElementById('dealer-list').innerHTML = '';
            for (var i = 0; i < Math.min(dealersSorted.length, 5); i++) {
                var dealer = dealersSorted[i];
                if (dealer.lat && dealer.lon) {
                    var dealerLocation = [dealer.lat, dealer.lon];
                    var dealerItem = document.createElement('li');
                    dealerItem.innerHTML = `
                        <strong class="dealer-name-highlight">${dealer.naamorg}</strong><br>
                        ${dealer.adres} ${dealer.huisnummer}<br>
                        ${dealer.postcode} ${dealer.stad}<br>
                        <span class="afstand-label"><strong>Afstand:</strong></span> ${dealer.distance} km
                        <br>
                        <button type="button" class="google-maps-route-btn" onclick="openRoute(${dealer.lat},${dealer.lon})">
                            📍 Start route naar deze dealer
                        </button>
                    `;
                    document.getElementById('dealer-list').appendChild(dealerItem);
                    var marker = L.marker(dealerLocation).addTo(map)
                        .bindPopup(`
                            <strong class="dealer-name-highlight">${dealer.naamorg}</strong><br>
                            ${dealer.adres} ${dealer.huisnummer}<br>
                            ${dealer.postcode} ${dealer.stad}<br>
                            <span class="afstand-label"><strong>Afstand:</strong></span> ${dealer.distance} km<br>
                            <a href="${formatUrl(dealer.url)}" target="_blank">Website</a><br>
                            <button type="button" class="google-maps-route-btn" onclick="openRoute(${dealer.lat},${dealer.lon})">
                                📍 Start route naar deze dealer
                            </button>
                        `);
                    markers.push(marker);
                }
            }
        }

        function clearMarkers() {
            markers.forEach(function(marker) {
                marker.remove();
            });
            markers = [];
        }

        function openRoute(destLat, destLon) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    var originLat = position.coords.latitude;
                    var originLon = position.coords.longitude;
                    window.open(`https://www.google.com/maps/dir/?api=1&origin=${originLat},${originLon}&destination=${destLat},${destLon}`, '_blank');
                }, function(error) {
                    var origin = userCoords
                        ? `${userCoords.lat},${userCoords.lon}`
                        : "52.379189,4.90093";
                    window.open(`https://www.google.com/maps/dir/?api=1&origin=${origin}&destination=${destLat},${destLon}`, '_blank');
                });
            } else {
                var origin = userCoords
                    ? `${userCoords.lat},${userCoords.lon}`
                    : "52.379189,4.90093";
                window.open(`https://www.google.com/maps/dir/?api=1&origin=${origin}&destination=${destLat},${destLon}`, '_blank');
            }
        }

        window.onload = initMap;
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('dealer_locator', 'dealer_locator_shortcode');
?>