<?php
// Voeg een admin pagina toe in het WordPress dashboard
function dealer_admin_page() {
    ob_start();

    // Laad de CSS voor de admin pagina
    echo '<link rel="stylesheet" href="' . plugin_dir_url(__FILE__) . 'dealer-admin-style.css?ver=1.0.0">';

    // Controleer of de gebruiker administrator is
    if ( ! current_user_can( 'administrator' ) ) {
        // Bouw de admin pagina URL voor redirect
        $redirect_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        // Toon foutmelding met inlog-link en redirect na succesvolle login
        wp_die(
            'Je hebt geen toegang tot deze pagina. <a href="' . esc_url( wp_login_url( $redirect_url ) ) . '">Inloggen</a>'
        );
    }

    global $wpdb;
    $table_name = 'wp_dealers'; // Tabelnaam voor dealers

    // Zoekopdracht afhandelen
    $search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $search_query = '';
    if ($search_term) {
        // Als het zoekterm een nummer is, zoek dan ook op ccdeb exact
        if (is_numeric($search_term)) {
            $search_query = $wpdb->prepare(
                "WHERE naamorg LIKE %s OR postcode LIKE %s OR stad LIKE %s OR ccdeb = %d",
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                intval($search_term)
            );
        } else {
            $search_query = $wpdb->prepare(
                "WHERE naamorg LIKE %s OR postcode LIKE %s OR stad LIKE %s",
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%'
            );
        }
    }

    // Haal alle dealers op, eventueel gefilterd op zoekopdracht
    $dealers = $wpdb->get_results("SELECT * FROM $table_name $search_query");

    // Succesmelding na toevoegen dealer
    if (isset($_GET['success']) && $_GET['success'] == 'true') {
        echo '<div class="dealer-admin-notification success"><p>Dealer succesvol toegevoegd!</p></div>';
    }

    // Afhandelen van verwijderen van dealer
    if (isset($_GET['delete'])) {
        $dealer_id = intval($_GET['delete']);
        $deleted = $wpdb->delete($table_name, array('ccdeb' => $dealer_id));
        if ($deleted) {
            echo '<div class="dealer-admin-notification success"><p>Dealer succesvol verwijderd!</p></div>';
        } else {
            echo '<div class="dealer-admin-notification error"><p>Er is een probleem met het verwijderen van de dealer!</p></div>';
        }
        echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
        exit;
    }

    // Afhandelen bewerken van een dealer
    if (isset($_GET['edit'])) {
        $dealer_id = intval($_GET['edit']);
        $dealer_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE ccdeb = %d", $dealer_id));
        if ($dealer_to_edit) {
            if (isset($_POST['update_dealer'])) {
                $dealer_name = sanitize_text_field($_POST['dealer_name']);
                $dealer_address = sanitize_text_field($_POST['dealer_address']);
                $dealer_postcode = sanitize_text_field($_POST['dealer_postcode']);
                $dealer_city = sanitize_text_field($_POST['dealer_city']);
                $dealer_url = sanitize_text_field($_POST['dealer_url']);

                // Update dealer gegevens
                $wpdb->update($table_name, array(
                    'naamorg' => $dealer_name,
                    'adres' => $dealer_address,
                    'postcode' => $dealer_postcode,
                    'stad' => $dealer_city,
                    'url' => $dealer_url,
                ), array('ccdeb' => $dealer_id));

                echo '<div class="dealer-admin-notification success"><p>Dealer bijgewerkt!</p></div>';
                echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
                exit;
            }
            ?>
            <div class="dealer-admin-wrap">
                <h1 class="page-title">Dealer bewerken</h1>
                <form method="post" action="" class="dealer-admin-form">
                    <div class="dealer-admin-card">
                        <label for="dealer_ccdeb">ccdeb (uniek nummer)</label>
                        <input type="text" name="dealer_ccdeb" id="dealer_ccdeb" class="input-field" value="<?php echo esc_attr($dealer_to_edit->ccdeb); ?>" readonly>
                    </div>
                    <div class="dealer-admin-card">
                        <label for="dealer_name">Naam van de dealer</label>
                        <input type="text" name="dealer_name" id="dealer_name" class="input-field" value="<?php echo esc_attr(stripslashes($dealer_to_edit->naamorg)); ?>" required>
                    </div>
                    <div class="dealer-admin-card">
                        <label for="dealer_address">Adres</label>
                        <input type="text" name="dealer_address" id="dealer_address" class="input-field" value="<?php echo esc_attr(stripslashes($dealer_to_edit->adres)); ?>" required>
                    </div>
                    <div class="dealer-admin-card">
                        <label for="dealer_postcode">Postcode</label>
                        <input type="text" name="dealer_postcode" id="dealer_postcode" class="input-field" value="<?php echo esc_attr($dealer_to_edit->postcode); ?>" required>
                    </div>
                    <div class="dealer-admin-card">
                        <label for="dealer_city">Stad</label>
                        <input type="text" name="dealer_city" id="dealer_city" class="input-field" value="<?php echo esc_attr($dealer_to_edit->stad); ?>" required>
                    </div>
                    <div class="dealer-admin-card">
                        <label for="dealer_url">Website URL</label>
                        <input type="url" name="dealer_url" id="dealer_url" class="input-field" value="<?php echo esc_attr($dealer_to_edit->url); ?>">
                    </div>
                    <p class="submit">
                        <input type="submit" name="update_dealer" id="update_dealer" class="button-primary" value="Dealer bijwerken">
                    </p>
                </form>
            </div>
            <?php
            return ob_get_clean();
        } else {
            // Dealer niet gevonden bij bewerken
            echo '<div class="dealer-admin-notification error"><p>Dealer niet gevonden!</p></div>';
        }
    }

    // Afhandelen toevoegen van een dealer
    if (isset($_POST['submit_dealer'])) {
        $dealer_name = sanitize_text_field($_POST['dealer_name']);
        $dealer_address = sanitize_text_field($_POST['dealer_address']);
        $dealer_postcode = sanitize_text_field($_POST['dealer_postcode']);
        $dealer_city = sanitize_text_field($_POST['dealer_city']);
        $dealer_url = sanitize_text_field($_POST['dealer_url']);

        // Genereer nieuwe ccdeb
        $last_ccdeb = $wpdb->get_var("SELECT MAX(ccdeb) FROM $table_name");
        $new_ccdeb = $last_ccdeb ? $last_ccdeb + 1 : 300000;

        // Automatisch lat/lon ophalen
        $latlon = dealer_admin_geocode($dealer_address, $dealer_postcode, $dealer_city);
        $insert_data = array(
            'ccdeb' => $new_ccdeb,
            'naamorg' => $dealer_name,
            'adres' => $dealer_address,
            'huisnummer' => '',
            'postcode' => $dealer_postcode,
            'stad' => $dealer_city,
            'url' => $dealer_url,
        );
        if ($latlon['lat']) $insert_data['lat'] = $latlon['lat'];
        if ($latlon['lon']) $insert_data['lon'] = $latlon['lon'];

        // Voeg dealer toe aan database
        $wpdb->insert($table_name, $insert_data);

        echo '<div class="dealer-admin-notification success"><p>Dealer toegevoegd!</p></div>';
        echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
        exit;
    }

    // CSV upload voor dealers
    if (isset($_POST['upload_csv'])) {
        if (!empty($_FILES['csv_file']['name'])) {
            $allowed_extensions = ['csv'];
            $file_info = pathinfo($_FILES['csv_file']['name']);
            $file_extension = strtolower($file_info['extension']);

            if (!in_array($file_extension, $allowed_extensions)) {
                echo '<div class="dealer-admin-notification error"><p>Ongeldig bestandstype. Alleen CSV-bestanden zijn toegestaan.</p></div>';
            } else {
                if (($handle = fopen($_FILES['csv_file']['tmp_name'], "r")) !== FALSE) {
                    $row = 0;
                    $addedDealers = 0;
                    $duplicatesFound = false;
                    $validDealerFound = false;

                    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                        if ($row > 0) {
                            $dealerName = sanitize_text_field($data[1]);
                            $postcode = sanitize_text_field($data[5]);
                            $adres = sanitize_text_field($data[3]);
                            $huisnummer = sanitize_text_field($data[4]);
                            $stad = sanitize_text_field($data[6]);
                            $url = !empty($data[2]) ? sanitize_text_field($data[2]) : '';

                            // Controleer op bestaande dealer (naam + postcode)
                            $dealer_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE naamorg = %s AND postcode = %s", $dealerName, $postcode));
                            if ($dealer_exists == 0) {
                                // Automatisch lat/lon ophalen
                                $latlon = dealer_admin_geocode($adres, $postcode, $stad);
                                $insert_data = array(
                                    'ccdeb' => $wpdb->get_var("SELECT MAX(ccdeb) FROM wp_dealers") + 1,
                                    'naamorg' => $dealerName,
                                    'adres' => $adres,
                                    'huisnummer' => $huisnummer,
                                    'postcode' => $postcode,
                                    'stad' => $stad,
                                    'url' => $url,
                                );
                                if ($latlon['lat']) $insert_data['lat'] = $latlon['lat'];
                                if ($latlon['lon']) $insert_data['lon'] = $latlon['lon'];
                                $wpdb->insert($table_name, $insert_data);
                                $addedDealers++;
                                $validDealerFound = true;
                            } else {
                                $duplicatesFound = true;
                            }
                        }
                        $row++;
                    }
                    fclose($handle);
                    if ($duplicatesFound) {
                        echo '<div class="dealer-admin-notification error"><p>Er zijn duplicaten gevonden, deze dealers zijn niet toegevoegd!</p></div>';
                    }
                    if ($addedDealers > 0) {
                        echo '<div class="dealer-admin-notification success"><p>' . $addedDealers . ' dealers succesvol toegevoegd!</p></div>';
                    } else {
                        echo '<div class="dealer-admin-notification error"><p>Geen nieuwe dealers toegevoegd, mogelijk duplicaten gevonden.</p></div>';
                    }
                    echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
                    exit;
                }
            }
        } else {
            echo '<div class="dealer-admin-notification error"><p>Geen bestand geselecteerd.</p></div>';
        }
    }

    // ================= REST VAN DE HTML EN JAVASCRIPT =======================
    ?>
    <script>
    jQuery(document).ready(function($) {
        var hasDuplicate = false;
        var validDealerFound = false;
        // Live check voor bestaande dealernaam bij handmatig toevoegen
        $('#dealer_name').on('keyup', function() {
            var dealerName = $(this).val();
            if (dealerName.length > 2) {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'check_dealer_exists',
                        dealer_name: dealerName
                    },
                    success: function(response) {
                        if (response == 'exists') {
                            $('#dealer-name-feedback').text('Deze dealernaam bestaat al.').css('color', 'red');
                            $('#submit_dealer').prop('disabled', true);
                        } else {
                            $('#dealer-name-feedback').text('');
                            $('#submit_dealer').prop('disabled', false);
                        }
                    }
                });
            } else {
                $('#dealer-name-feedback').text('');
                $('#submit_dealer').prop('disabled', false);
            }
        });

        // CSV preview met ccdeb
        $('input[type="file"]').change(function(e) {
            var file = e.target.files[0];
            if (file && file.type === 'text/csv') {
                var reader = new FileReader();
                reader.onload = function(event) {
                    var content = event.target.result;
                    var rows = content.split("\n");
                    var ccdeb_base = <?php
                        $last_ccdeb = $wpdb->get_var("SELECT MAX(ccdeb) FROM $table_name");
                        echo ($last_ccdeb ? $last_ccdeb : 300000);
                    ?>;
                    var html = "<table class='dealer-admin-csv-preview'><thead><tr><th>ccdeb</th><th>Naam</th><th>Adres</th><th>Postcode</th><th>Stad</th><th>URL</th><th>Status</th><th>Actie</th></tr></thead><tbody>";

                    hasDuplicate = false;
                    validDealerFound = false;

                    rows.forEach(function(row, index) {
                        if (index > 0 && row.trim() !== "") {
                            var cols = row.split(';');
                            var ccdeb = ccdeb_base + index;
                            html += "<tr class='csv-row' data-ccdeb='" + ccdeb + "' data-dealer-name='" + cols[1] + "' data-postcode='" + cols[5] + "' data-adres='" + cols[3] + "' data-huisnummer='" + (cols[4]||'') + "' data-stad='" + (cols[6]||'') + "' data-url='" + (cols[2]||'') + "'>";
                            html += "<td>" + ccdeb + "</td>";
                            html += "<td>" + cols[1] + "</td>";
                            html += "<td>" + cols[3] + "</td>";
                            html += "<td>" + cols[5] + "</td>";
                            html += "<td>" + (cols[6]||'') + "</td>";
                            html += "<td>" + (cols[2] ? cols[2] : 'Geen URL') + "</td>";
                            html += "<td class='status'>Checking...</td>";
                            html += "<td class='actie'></td>";
                            html += "</tr>";
                        }
                    });
                    html += "</tbody></table>";
                    $('#csv-preview').html(html);

                    // Check per regel op duplicaat
                    $('.csv-row').each(function() {
                        var dealerName = $(this).data('dealer-name');
                        var postcode = $(this).data('postcode');
                        var row = $(this);

                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            method: 'POST',
                            data: {
                                action: 'check_dealer_exists',
                                dealer_name: dealerName,
                                dealer_postcode: postcode
                            },
                            success: function(response) {
                                var statusCell = row.find('.status');
                                var actieCell = row.find('.actie');
                                if (response === 'exists') {
                                    row.css('background-color', '#ffebee');
                                    statusCell.text('Duplicate found').css('color', 'red');
                                    actieCell.html('<button class="add-single-dealer" disabled>Al toegevoegd</button>');
                                    hasDuplicate = true;
                                } else {
                                    statusCell.text('Valid').css('color', 'green');
                                    actieCell.html('<button class="add-single-dealer dealer-btn-green">Voeg toe</button>');
                                    validDealerFound = true;
                                }
                                if (hasDuplicate) {
                                    $('input[type="submit"][name="upload_csv"]').prop('disabled', !validDealerFound);
                                } else {
                                    $('input[type="submit"][name="upload_csv"]').prop('disabled', false);
                                }
                            }
                        });
                    });
                };
                reader.readAsText(file);
            } else {
                alert('Selecteer een geldig CSV-bestand.');
            }
        });

        // Toevoegen enkele dealer vanuit preview
        $('#csv-preview').on('click', '.add-single-dealer:not(:disabled)', function(e) {
            e.preventDefault();
            var row = $(this).closest('.csv-row');
            var dealerName = row.data('dealer-name');
            var postcode = row.data('postcode');
            var adres = row.data('adres');
            var huisnummer = row.data('huisnummer');
            var stad = row.data('stad');
            var url = row.data('url');
            var btn = $(this);
            btn.prop('disabled', true).text('Toevoegen...');
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                data: {
                    action: 'add_single_dealer',
                    dealer_name: dealerName,
                    dealer_address: adres,
                    dealer_postcode: postcode,
                    dealer_city: stad,
                    dealer_url: url,
                    dealer_huisnummer: huisnummer
                },
                success: function(response) {
                    if (response === 'success') {
                        window.location.reload();
                    } else if(response === 'duplicate') {
                        row.css('background-color', '#ffebee');
                        row.find('.status').text('Duplicate found').css('color', 'red');
                        btn.text('Al toegevoegd').prop('disabled', true);
                    } else {
                        btn.text('Fout! Probeer opnieuw');
                        row.find('.status').text('Fout bij toevoegen').css('color', 'orange');
                    }
                }
            });
        });
    });
    </script>
    <div id="dealer-name-feedback"></div>
    <div id="csv-preview" style="overflow-x:auto;"></div>
    <div class="dealer-admin-wrap">
        <h1 class="page-title">Dealer Locator <span class="admin-badge">Admin</span></h1>
        <div class="dealer-flex-2col">
            <section class="dealer-section">
                <h2>Voeg een nieuwe dealer toe</h2>
                <form method="post" action="" class="dealer-admin-form">
                    <div class="dealer-admin-card">
                        <label for="dealer_ccdeb">ccdeb (uniek nummer)</label>
                        <input type="text" name="dealer_ccdeb" id="dealer_ccdeb" class="input-field" value="<?php
                            $last_ccdeb = $wpdb->get_var("SELECT MAX(ccdeb) FROM $table_name");
                            $new_ccdeb = $last_ccdeb ? $last_ccdeb + 1 : 300000;
                            echo esc_attr($new_ccdeb);
                        ?>" readonly>
                    </div>
                    <div class="dealer-admin-card">
                        <label for="dealer_name">Naam van de dealer</label>
                        <input type="text" name="dealer_name" id="dealer_name" class="input-field" required>
                    </div>
                    <div class="dealer-admin-card">
                        <label for="dealer_address">Adres</label>
                        <input type="text" name="dealer_address" id="dealer_address" class="input-field" required>
                    </div>
                    <div class="dealer-admin-card">
                        <label for="dealer_postcode">Postcode</label>
                        <input type="text" name="dealer_postcode" id="dealer_postcode" class="input-field" required>
                    </div>
                    <div class="dealer-admin-card">
                        <label for="dealer_city">Stad</label>
                        <input type="text" name="dealer_city" id="dealer_city" class="input-field" required>
                    </div>
                    <div class="dealer-admin-card">
                        <label for="dealer_url">Website URL</label>
                        <input type="url" name="dealer_url" id="dealer_url" class="input-field">
                    </div>
                    <p class="submit">
                        <input type="submit" name="submit_dealer" id="submit_dealer" class="button-primary" value="Dealer toevoegen">
                    </p>
                </form>
            </section>
            <section class="dealer-section">
                <h2>Zoek naar dealers</h2>
                <form method="get" action="" class="dealer-search-form">
                    <input type="text" name="search" placeholder="Zoek op naam, postcode, stad of ccdeb" value="<?php echo esc_attr(stripslashes($search_term)); ?>" class="input-field">
                    <button type="submit" class="button-primary">Zoeken</button>
                    <?php if (!empty($search_term)) : ?>
                        <a href="?page=dealer_admin" class="button-secondary dealer-reset-btn">Reset</a>
                    <?php endif; ?>
                </form>
                <h2>Upload CSV-bestand</h2>
                <form method="post" enctype="multipart/form-data" class="dealer-csv-form">
                    <input type="file" name="csv_file" accept=".csv">
                    <input type="submit" name="upload_csv" value="Upload CSV" class="button-primary">
                </form>
            </section>
        </div>
        <?php
        if (!empty($dealers)) {
            ?>
            <h2>Alle dealers</h2>
            <div class="dealer-table-wrap">
                <table class="dealer-admin-table">
                    <thead>
                        <tr>
                            <th>ccdeb</th>
                            <th>Naam</th>
                            <th>Adres</th>
                            <th>Postcode</th>
                            <th>Stad</th>
                            <th>Website</th>
                            <th>Aanpassen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dealers as $dealer): ?>
                            <tr>
                                <td><?php echo esc_html($dealer->ccdeb); ?></td>
                                <td><?php echo esc_html(stripslashes($dealer->naamorg)); ?></td>
                                <td><?php echo esc_html($dealer->adres . ' ' . $dealer->huisnummer); ?></td>
                                <td><?php echo esc_html($dealer->postcode); ?></td>
                                <td><?php echo esc_html($dealer->stad); ?></td>
                                <td>
                                    <?php if (!empty($dealer->url)): ?>
                                        <a href="<?php echo esc_url($dealer->url); ?>" target="_blank">Website</a>
                                    <?php else: ?>
                                        Geen website
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=dealer_admin&edit=<?php echo esc_attr($dealer->ccdeb); ?>" class="dealer-btn-edit">Bewerken</a>
                                    <a href="?page=dealer_admin&delete=<?php echo esc_attr($dealer->ccdeb); ?>" class="dealer-btn-delete" onclick="return confirm('Weet je zeker dat je deze dealer wilt verwijderen?');">Verwijderen</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        } else {
            echo '<p>Er zijn nog geen dealers toegevoegd.</p>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('dealer_admin', 'dealer_admin_page');

// Geocode functie: haalt lat/lon op via Nominatim (OpenStreetMap)
function dealer_admin_geocode($adres, $postcode, $stad) {
    // Bouw query voor Nominatim search
    $query = urlencode(trim($adres . ' ' . $postcode . ' ' . $stad . ' Nederland'));
    $url = "https://nominatim.openstreetmap.org/search?q=$query&format=json&limit=1";
    $response = wp_remote_get($url, array(
        'headers' => array('User-Agent' => 'WordPress Dealer Admin Plugin')
    ));
    if (is_wp_error($response)) {
        return array('lat' => '', 'lon' => '');
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
        return array('lat' => $data[0]['lat'], 'lon' => $data[0]['lon']);
    }
    return array('lat' => '', 'lon' => '');
}

// AJAX: check of dealer bestaat (voor validatie)
function check_dealer_exists() {
    global $wpdb;
    $table_name = 'wp_dealers';
    if (isset($_POST['dealer_name']) && isset($_POST['dealer_postcode'])) {
        $dealer_name = sanitize_text_field($_POST['dealer_name']);
        $dealer_postcode = sanitize_text_field($_POST['dealer_postcode']);
        $dealer_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE naamorg = %s AND postcode = %s", $dealer_name, $dealer_postcode));
        echo ($dealer_exists > 0) ? 'exists' : 'not_exists';
        wp_die();
    }
    if (isset($_POST['dealer_name'])) {
        $dealer_name = sanitize_text_field($_POST['dealer_name']);
        $dealer_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE naamorg = %s", $dealer_name));
        echo ($dealer_exists > 0) ? 'exists' : 'not_exists';
        wp_die();
    }
    wp_die();
}
add_action('wp_ajax_check_dealer_exists', 'check_dealer_exists');
add_action('wp_ajax_nopriv_check_dealer_exists', 'check_dealer_exists');

// AJAX: enkele dealer toevoegen vanuit preview
function add_single_dealer() {
    global $wpdb;
    $table_name = 'wp_dealers';

    $dealer_name = sanitize_text_field($_POST['dealer_name']);
    $dealer_address = sanitize_text_field($_POST['dealer_address']);
    $dealer_postcode = sanitize_text_field($_POST['dealer_postcode']);
    $dealer_city = sanitize_text_field($_POST['dealer_city']);
    $dealer_url = sanitize_text_field($_POST['dealer_url']);
    $dealer_huisnummer = sanitize_text_field($_POST['dealer_huisnummer']);

    // Controle op dubbele dealer
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE naamorg = %s AND postcode = %s", $dealer_name, $dealer_postcode));
    if ($exists > 0) {
        echo 'duplicate';
        wp_die();
    }

    $last_ccdeb = $wpdb->get_var("SELECT MAX(ccdeb) FROM $table_name");
    $new_ccdeb = $last_ccdeb ? $last_ccdeb + 1 : 300000;

    // Automatisch lat/lon ophalen
    $latlon = dealer_admin_geocode($dealer_address, $dealer_postcode, $dealer_city);

    $data = array(
        'ccdeb' => $new_ccdeb,
        'naamorg' => $dealer_name,
        'adres' => $dealer_address,
        'huisnummer' => $dealer_huisnummer,
        'postcode' => $dealer_postcode,
        'stad' => $dealer_city,
        'url' => $dealer_url,
    );
    if($latlon['lat']) $data['lat'] = $latlon['lat'];
    if($latlon['lon']) $data['lon'] = $latlon['lon'];

    $result = $wpdb->insert($table_name, $data);

    if ($result !== false) {
        echo 'success';
    } else {
        echo 'error';
    }
    wp_die();
}
add_action('wp_ajax_add_single_dealer', 'add_single_dealer');
?>