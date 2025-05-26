<?php

if ( ! defined( 'WP_DEBUG' ) ) {
    die( 'Directe toegang verboden.' );
}

// Laad de stijl van het thema
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
});

// Functie om geolocatie op te halen met behulp van de Nominatim API
function get_lat_lon_from_address($address) {
    $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode($address . ', Netherlands') . '&countrycodes=NL';

    // Haal de JSON-data op
    $response = wp_remote_get($url);
    $data = wp_remote_retrieve_body($response);
    $json = json_decode($data, true);

    // Haal het eerste resultaat en geef de latitude en longitude terug
    if (isset($json[0])) {
        $lat = $json[0]['lat'];
        $lon = $json[0]['lon'];
        
        // Debugging output: Controleer de geolocatie
        error_log("Geocoding voor adres '$address' gaf lat: $lat, lon: $lon");

        return array('lat' => $lat, 'lon' => $lon);
    }

    // Als geocoding niet lukt, loggen en fallback gebruiken
    error_log("Geocoding mislukt voor adres '$address'. Geen lat/lon beschikbaar.");
    
    // Fallback coördinaten (bijvoorbeeld centrum van Nederland)
    return array('lat' => 52.379189, 'lon' => 4.90093); // Centrum van Nederland (Amsterdam)
}

// Voeg dealer toe als het formulier is ingediend
if (isset($_POST['submit_dealer'])) {
    global $wpdb;

    // Verzamelen van gegevens uit het formulier
    $dealer_name = sanitize_text_field($_POST['dealer_name']);
    $dealer_address = sanitize_text_field($_POST['dealer_address']);
    $dealer_postcode = sanitize_text_field($_POST['dealer_postcode']);
    $dealer_city = sanitize_text_field($_POST['dealer_city']);
    $dealer_url = sanitize_text_field($_POST['dealer_url']);

    // Combineer adres voor geocoding
    $full_address = $dealer_address . ' ' . $dealer_postcode . ' ' . $dealer_city;

    // Verkrijg lat en lon via geocoding
    $geolocation = get_lat_lon_from_address($full_address);
    $lat = $geolocation['lat'];
    $lon = $geolocation['lon'];

    // Haal het hoogste ccdeb nummer op en verhoog met 1
    $last_ccdeb = $wpdb->get_var("SELECT MAX(ccdeb) FROM wp_dealers");
    $new_ccdeb = $last_ccdeb ? $last_ccdeb + 1 : 300000; // Start bij 300000 als het de eerste dealer is

    // Toevoegen aan de wp_dealers tabel, met automatisch gegenereerd ccdeb nummer en geolocatie
    $wpdb->insert('wp_dealers', array(
        'ccdeb' => $new_ccdeb,
        'naamorg' => $dealer_name,
        'adres' => $dealer_address,
        'huisnummer' => '', // Indien gewenst kun je huisnummer apart beheren
        'postcode' => $dealer_postcode,
        'stad' => $dealer_city,
        'url' => $dealer_url,
        'lat' => $lat,
        'lon' => $lon,
    ));

    // Success message en laat de lijst van dealers opnieuw zien
    echo '<div class="updated"><p>Dealer toegevoegd!</p></div>';

    // Herlaad de pagina na het toevoegen van de dealer
    echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
    exit;
}

// Voeg dealer toe als het CSV-bestand wordt geüpload
if (isset($_POST['upload_csv'])) {
    // Controleer of er een bestand is geüpload
    if (!empty($_FILES['csv_file']['name'])) {
        // Bestandstype controleren
        $allowed_extensions = ['csv'];
        $file_info = pathinfo($_FILES['csv_file']['name']);
        $file_extension = strtolower($file_info['extension']);

        if (!in_array($file_extension, $allowed_extensions)) {
            echo '<div class="error"><p>Ongeldig bestandstype. Alleen CSV-bestanden zijn toegestaan.</p></div>';
        } else {
            // Verwerk het CSV-bestand met puntkomma als scheidingsteken
            if (($handle = fopen($_FILES['csv_file']['tmp_name'], "r")) !== FALSE) {
                $row = 0;
                while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) { // Aangepast naar puntkomma
                    if ($row > 0) { // Skip de header
                        // Verwerk elke rij en voeg de dealer toe aan de database
                        
                        // Combineer adres voor geocoding
                        $full_address = $data[3] . ' ' . $data[5] . ' ' . $data[6];
                        
                        // Verkrijg lat en lon via geocoding
                        $geolocation = get_lat_lon_from_address($full_address);
                        $lat = $geolocation['lat'];
                        $lon = $geolocation['lon'];
                        
                        // Voeg de dealer toe met de geocoding resultaten
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
                // Herlaad de pagina na het verwerken van het bestand
                echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
                exit;
            }
        }
    } else {
        echo '<div class="error"><p>Geen bestand geselecteerd.</p></div>';
    }
}

// Dealer locator shortcode
function dealer_locator_shortcode() {
    global $wpdb;

    // Verbind met de database en haal dealers op uit de wp_dealers tabel in de hrdesign database
    $table_name = 'wp_dealers'; // De juiste tabelnaam
    $sql = "SELECT ccdeb, naamorg, adres, huisnummer, postcode, stad, url, lat, lon FROM $table_name"; // Haal ccdeb, naamorg, adres, huisnummer, postcode, stad, url, lat, lon op
    $dealers = $wpdb->get_results($sql, ARRAY_A); // Haal de dealers op als een associatieve array

    ob_start();
    ?>
    <div id="dealer-locator-container" class="dealer-locator-container">
        <h1 class="dealer-locator-title">Vind een dealer</h1>
        <div class="dealer-locator-search-container">
            <input type="text" id="postcode" class="dealer-locator-input-field" placeholder="Voer postcode en plaats in, bijv. 8388 Oosterstreek">
            <button onclick="zoekDealer()" class="dealer-locator-search-button">Zoeken</button>
        </div>
        
        <!-- Kaartweergave -->
        <div id="map" class="dealer-locator-map-container" style="height: 500px; width: 70%; float: left;"></div>

        <!-- Dealers sectie met nieuwe class -->
        <div id="dealer-details" class="dealer-locator-details-container" style="width: 28%; float: right; padding-left: 20px; margin-top: 30px;">
            <h3>Dealers:</h3>
            <ul id="dealer-list">
                <!-- Dynamisch gegenereerde dealer details komen hier -->
            </ul>
        </div>
    </div>

    <!-- Include Leaflet.js CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

    <!-- Include Leaflet Locate Control -->
    <script src="https://unpkg.com/leaflet.locatecontrol@0.71.1/dist/L.Control.Locate.min.js"></script>

    <script>
        var map;
        var markers = [];  // Array om markers op te slaan
        var userMarker = null; // Variabele voor de gebruikersmarker
        var dealers = <?php echo json_encode($dealers); ?>; // PHP dealers naar JavaScript doorgeven

        // Functie om de kaart te initialiseren
        function initMap() {
            map = L.map('map').setView([52.379189, 4.90093], 6); // Default to Amsterdam
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // Add geolocate control to the map
            L.control.locate({
                position: 'topleft',
                locateOptions: {
                    enableHighAccuracy: true,
                    maxZoom: 16
                }
            }).addTo(map);
        }

        // Geocodeer adres en haal latitude/longitude op (Nominatim API gebruiken)
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
                        console.log('Geocoding failed for: ' + address);
                        callback(52.379189, 4.90093);  // Centrum van Nederland (Amsterdam)
                    }
                });
        }

        // Haversine-formule om de afstand tussen twee coördinaten te berekenen (in kilometers)
        function calculateDistance(lat1, lon1, lat2, lon2) {
            var R = 6371; // Aardstraal in km
            var dLat = (lat2 - lat1) * Math.PI / 180;
            var dLon = (lon2 - lon1) * Math.PI / 180;
            var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLon / 2) * Math.sin(dLon / 2);
            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            var distance = R * c; // Afstand in kilometers
            return distance.toFixed(2); // Beperk tot twee decimalen
        }

        // Zoekdealer functie voor het zoeken op basis van postcode + plaatsnaam
        function zoekDealer() {
            var postcodePlaats = document.getElementById('postcode').value;
            geocodeAddress(postcodePlaats, function(lat, lon) {
                if (lat && lon) {
                    // Stel de kaart in op de locatie van de gebruiker
                    map.setView([lat, lon], 14);

                    // Voeg de gebruikersmarker toe
                    if (userMarker) {
                        userMarker.remove();
                    }

                    userMarker = L.marker([lat, lon], {
                        icon: L.icon({
                            iconUrl: 'https://maps.gstatic.com/mapfiles/ms2/micons/blue.png',
                            iconSize: [40, 40]
                        })
                    }).addTo(map).bindPopup('Your Location').openPopup();

                    // Filter dealers binnen 50 km van de gebruikerslocatie
                    filterDealers([lat, lon]);
                } else {
                    alert('Geocoding failed for postcode: ' + postcodePlaats);
                }
            });
        }

        // Filter dealers binnen 50 km
        function filterDealers(userLocation) {
            var maxDistance = 50; // 50 km
            clearMarkers(); // Verwijder oude markers

            // Sorteer de dealers op afstand
            var dealersSorted = dealers.map(function(dealer) {
                var dealerLocation = [dealer.lat, dealer.lon];

                if (dealer.lat && dealer.lon) {
                    // Bereken de afstand tussen de gebruiker en de dealer
                    var distance = map.distance(userLocation, dealerLocation) / 1000; // Afstand in km

                    // Voeg de afstand toe aan het dealerobject
                    dealer.distance = distance.toFixed(2); // Rond de afstand af naar twee decimalen
                } else {
                    dealer.distance = Infinity; // Markeer dealers zonder locatie als ver weg
                }

                return dealer;
            }).sort(function(a, b) {
                return a.distance - b.distance; // Sorteer op afstand
            });

            // Maak de dealer lijst aan de zijkant leeg voordat we nieuwe dealers toevoegen
            document.getElementById('dealer-list').innerHTML = '';

            // Toon maximaal 5 dichtstbijzijnde dealers
            for (var i = 0; i < Math.min(dealersSorted.length, 5); i++) {
                var dealer = dealersSorted[i];

                if (dealer.lat && dealer.lon) {
                    var dealerLocation = [dealer.lat, dealer.lon];

                    // Voeg de dealer toe aan de lijst aan de rechterkant
                    var dealerItem = document.createElement('li');
                    dealerItem.innerHTML = `
                        <strong>${dealer.naamorg}</strong><br>
                        ${dealer.adres} ${dealer.huisnummer}<br>
                        ${dealer.postcode} ${dealer.stad}<br>
                        <strong>Afstand:</strong> ${dealer.distance} km
                        <br><a href="https://www.google.com/maps/dir/?api=1&origin=${userMarker.getLatLng().lat},${userMarker.getLatLng().lng}&destination=${dealer.lat},${dealer.lon}" target="_blank">📍 Route via Google Maps</a>
                    `;
                    document.getElementById('dealer-list').appendChild(dealerItem);

                    // Voeg dealermarker toe op de kaart
                    var marker = L.marker(dealerLocation).addTo(map)
                        .bindPopup(`
                            <strong>${dealer.naamorg}</strong><br>
                            ${dealer.adres} ${dealer.huisnummer}<br>
                            ${dealer.postcode} ${dealer.stad}<br>
                            <strong>Afstand:</strong> ${dealer.distance} km<br>
                            <a href="${dealer.url}" target="_blank">Website</a><br>
                            <a href="https://www.google.com/maps/dir/?api=1&origin=${userMarker.getLatLng().lat},${userMarker.getLatLng().lng}&destination=${dealer.lat},${dealer.lon}" target="_blank" style="color:blue; font-weight:bold;">📍 Route via Google Maps</a>
                        `);

                    markers.push(marker);  // Voeg marker toe aan de markers array
                }
            }
        }

        // Verwijder alle markers van de kaart
        function clearMarkers() {
            markers.forEach(function(marker) {
                marker.remove();  // Verwijder elke marker van de kaart
            });
            markers = [];  // Reset de markers array
        }

        // Initialize the map when the page is loaded
        window.onload = initMap;
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('dealer_locator', 'dealer_locator_shortcode');

?>
