<?php

// Blokkeer directe toegang tot het bestand als WordPress debugmodus niet aanstaat.
// Dit voorkomt dat het script buiten WordPress om wordt aangeroepen.
if ( ! defined( 'WP_DEBUG' ) ) {
    die( 'Directe toegang verboden.' );
}

// Voeg het CSS-bestand van het parent theme toe aan de frontend.
// Dit zorgt ervoor dat de styling van het hoofdthema behouden blijft.
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
});

/**
 * Haal latitude en longitude op van een adres via de OpenStreetMap Nominatim API.
 *
 * @param string $address Het adres waarvan de coördinaten opgehaald moeten worden.
 * @return array ['lat' => latitude, 'lon' => longitude] - coördinaten van het adres.
 */
function get_lat_lon_from_address($address) {
    // Stel de URL samen voor de Nominatim API, inclusief adres en landcode NL.
    $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode($address . ', Netherlands') . '&countrycodes=NL';
    // Voer HTTP GET request uit naar de API.
    $response = wp_remote_get($url);
    // Haal het body-gedeelte (JSON) uit het antwoord.
    $data = wp_remote_retrieve_body($response);
    // Decodeer het JSON-resultaat naar een array.
    $json = json_decode($data, true);
    // Controleer of er resultaten zijn gevonden.
    if (isset($json[0])) {
        $lat = $json[0]['lat'];
        $lon = $json[0]['lon'];
        // Log het resultaat voor debugging.
        error_log("Geocoding voor adres '$address' gaf lat: $lat, lon: $lon");
        // Geef de gevonden coördinaten terug.
        return array('lat' => $lat, 'lon' => $lon);
    }
    // Als geen resultaat, log foutmelding en geef standaard coördinaten van Amsterdam terug.
    error_log("Geocoding mislukt voor adres '$address'. Geen lat/lon beschikbaar.");
    return array('lat' => 52.379189, 'lon' => 4.90093); // Standaard: Amsterdam
}

// === Handler voor toevoegen van één enkele dealer via formulier POST ===
if (isset($_POST['submit_dealer'])) {
    global $wpdb;
    // Haal en filter de inputvelden van het formulier op.
    $dealer_name = sanitize_text_field($_POST['dealer_name']);
    $dealer_address = sanitize_text_field($_POST['dealer_address']);
    $dealer_postcode = sanitize_text_field($_POST['dealer_postcode']);
    $dealer_city = sanitize_text_field($_POST['dealer_city']);
    $dealer_url = sanitize_text_field($_POST['dealer_url']);
    // Zet het volledige adres in elkaar voor geocoding.
    $full_address = $dealer_address . ' ' . $dealer_postcode . ' ' . $dealer_city;
    // Haal de coördinaten op.
    $geolocation = get_lat_lon_from_address($full_address);
    $lat = $geolocation['lat'];
    $lon = $geolocation['lon'];
    // Bepaal het nieuwe, unieke ccdeb-nummer.
    $last_ccdeb = $wpdb->get_var("SELECT MAX(ccdeb) FROM wp_dealers");
    $new_ccdeb = $last_ccdeb ? $last_ccdeb + 1 : 300000;
    // Voeg de nieuwe dealer toe aan de database.
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
    // Geef een bevestigingsmelding en refresh de pagina.
    echo '<div class="updated"><p>Dealer toegevoegd!</p></div>';
    echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
    exit;
}

// === Handler voor uploaden van een CSV-bestand met meerdere dealers ===
if (isset($_POST['upload_csv'])) {
    // Controleer of er een bestand is geüpload.
    if (!empty($_FILES['csv_file']['name'])) {
        $allowed_extensions = ['csv'];
        $file_info = pathinfo($_FILES['csv_file']['name']);
        $file_extension = strtolower($file_info['extension']);
        // Controleer of het bestand een CSV is.
        if (!in_array($file_extension, $allowed_extensions)) {
            echo '<div class="error"><p>Ongeldig bestandstype. Alleen CSV-bestanden zijn toegestaan.</p></div>';
        } else {
            // Open het CSV-bestand en lees regel voor regel.
            if (($handle = fopen($_FILES['csv_file']['tmp_name'], "r")) !== FALSE) {
                $row = 0;
                while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    if ($row > 0) { // Sla de headerregel over.
                        // Zet het volledige adres in elkaar voor geocoding.
                        $full_address = $data[3] . ' ' . $data[5] . ' ' . $data[6];
                        $geolocation = get_lat_lon_from_address($full_address);
                        $lat = $geolocation['lat'];
                        $lon = $geolocation['lon'];
                        // Voeg de dealer toe aan de database.
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
                // Geef een bevestigingsmelding en refresh de pagina.
                echo '<div class="updated"><p>CSV-bestand succesvol verwerkt!</p></div>';
                echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
                exit;
            }
        }
    } else {
        echo '<div class="error"><p>Geen bestand geselecteerd.</p></div>';
    }
}

/**
 * Shortcode functie voor de Dealer Locator (kaart + lijst).
 * Gebruik: [dealer_locator]
 * Hiermee kun je dealers zoeken en tonen op een kaart, inclusief route-optie.
 */
function dealer_locator_shortcode() {
    global $wpdb;
    $table_name = 'wp_dealers';
    // Haal alle dealers op uit de database.
    $sql = "SELECT ccdeb, naamorg, adres, huisnummer, postcode, stad, url, lat, lon FROM $table_name";
    $dealers = $wpdb->get_results($sql, ARRAY_A);

    ob_start();
    ?>
    <!-- Dealer Locator Container -->
    <div id="dealer-locator-container" class="dealer-locator-container">
        <!-- Titel -->
        <h1 class="dealer-locator-title">Vind een verkooppunt</h1>
        <!-- Zoekveld voor postcode/plaats -->
        <div class="dealer-locator-search-container">
            <input type="text" id="postcode" class="dealer-locator-input-field" placeholder="Voer postcode en plaats in, bijv. 8388 Oosterstreek">
            <button onclick="zoekDealer()" class="dealer-locator-search-button">Zoeken</button>
        </div>
        <!-- Flex container voor kaart en dealer lijst -->
        <div class="dealer-locator-flex-container">
            <!-- Kaart -->
            <div id="map" class="dealer-locator-map-container"></div>
            <!-- Dealer lijst aan de rechterkant -->
            <div id="dealer-details" class="dealer-locator-details-container">
                <h3>Dealers:</h3>
                <ul id="dealer-list"></ul>
            </div>
        </div>
    </div>
    <!-- Voeg Leaflet CSS/JS en LocateControl plugin toe voor de kaartfunctionaliteit -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.locatecontrol@0.71.1/dist/L.Control.Locate.min.js"></script>
    <script>
        // ===== JavaScript voor Dealer Locator frontend =====

        // Globale variabelen voor de kaart en dealers
        var map;
        var markers = [];
        var userMarker = null;
        var userCoords = null;
        var dealers = <?php echo json_encode($dealers); ?>;

        // Helper: voeg https:// toe aan URL indien het ontbreekt
        function formatUrl(url) {
            if (!url) return "#";
            if (!/^https?:\/\//i.test(url)) {
                return "https://" + url;
            }
            return url;
        }

        // Initialiseer Leaflet kaart en voeg OpenStreetMap tiles toe
        function initMap() {
            map = L.map('map').setView([52.379189, 4.90093], 6); // Startpositie (Nederland)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            // Voeg LocateControl toe voor het bepalen van de gebruikerslocatie
            L.control.locate({
                position: 'topleft',
                locateOptions: {
                    enableHighAccuracy: true,
                    maxZoom: 16
                }
            }).addTo(map);
        }

        // Geocode een adres naar lat/lon met Nominatim API
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
                        // Fallback positie (Amsterdam) als geocoding mislukt
                        callback(52.379189, 4.90093);
                    }
                });
        }

        // Bereken afstand (in kilometer) tussen twee geografische punten
        function calculateDistance(lat1, lon1, lat2, lon2) {
            var R = 6371; // Aarde straal in km
            var dLat = (lat2 - lat1) * Math.PI / 180;
            var dLon = (lon2 - lon1) * Math.PI / 180;
            var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLon / 2) * Math.sin(dLon / 2);
            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            var distance = R * c;
            return distance.toFixed(2);
        }

        // Zoek dealers op basis van postcode/plaats en toon op kaart/lijst
        function zoekDealer() {
            var postcodePlaats = document.getElementById('postcode').value;
            geocodeAddress(postcodePlaats, function(lat, lon) {
                if (lat && lon) {
                    // Verplaats kaart naar gezochte locatie
                    map.setView([lat, lon], 14);
                    // Verwijder bestaande user marker (indien aanwezig)
                    if (userMarker) userMarker.remove();
                    // Voeg blauwe marker toe voor de gebruiker
                    userMarker = L.marker([lat, lon], {
                        icon: L.icon({
                            iconUrl: 'https://maps.gstatic.com/mapfiles/ms2/micons/blue.png',
                            iconSize: [40, 40]
                        })
                    }).addTo(map).bindPopup('Jouw locatie').openPopup();
                    userCoords = {lat: parseFloat(lat), lon: parseFloat(lon)};
                    // Filter dealers op afstand tot gebruiker
                    filterDealers([lat, lon]);
                } else {
                    alert('Geocoding failed for postcode: ' + postcodePlaats);
                }
            });
        }

        // Filter dealers op afstand tot gebruiker, update markers en lijst
        function filterDealers(userLocation) {
            var maxDistance = 50; // Max. afstand in km (optioneel filter)
            clearMarkers();
            // Bereken afstand van elke dealer tot gebruiker en sorteer op afstand
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

            // Maak de lijst leeg en toon maximaal 5 dichtstbijzijnde dealers
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
                        <div class="afstand-row">
                            <span class="afstand-label"><strong>Afstand:</strong></span>
                            <span>${dealer.distance} km</span>
                        </div>
                        <button type="button" class="google-maps-route-btn" onclick="openRoute(${dealer.lat},${dealer.lon})">
                            📍 Start route naar deze dealer
                        </button>
                    `;
                    document.getElementById('dealer-list').appendChild(dealerItem);

                    // Voeg marker met popup toe aan de kaart
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

        // Verwijder alle huidige markers van de kaart
        function clearMarkers() {
            markers.forEach(function(marker) {
                marker.remove();
            });
            markers = [];
        }

        // Open Google Maps met route vanaf actuele GPS locatie naar dealer
        function openRoute(destLat, destLon) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        var originLat = position.coords.latitude;
                        var originLon = position.coords.longitude;
                        window.open(
                            `https://www.google.com/maps/dir/?api=1&origin=${originLat},${originLon}&destination=${destLat},${destLon}`,
                            '_blank'
                        );
                    },
                    function(error) {
                        alert('Kan jouw actuele locatie niet ophalen. Zet locatievoorzieningen aan en probeer opnieuw.');
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 0
                    }
                );
            } else {
                alert('Je browser ondersteunt geen locatiebepaling.');
            }
        }

        // Initialiseer de kaart bij het laden van de pagina
        window.onload = initMap;
    </script>
    <?php
    // Geef de inhoud van de output buffer terug als shortcode resultaat.
    return ob_get_clean();
}

// Registreer de shortcode [dealer_locator] zodat je deze in de WordPress editor kunt gebruiken.
add_shortcode('dealer_locator', 'dealer_locator_shortcode');
?>