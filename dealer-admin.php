<?php
// Voeg een admin pagina toe in het WordPress dashboard
function dealer_admin_page() {
    // Controleer of de gebruiker een beheerder is
    if ( ! current_user_can( 'administrator' ) ) {
        wp_die( 'Je hebt geen toegang tot deze pagina.' ); // Toon een foutmelding als de gebruiker geen beheerder is
    }

    global $wpdb;

    // Gebruik de juiste tabelnaam zonder de prefix
    $table_name = 'wp_dealers'; // Directe naam zonder prefix

    // Zoekopdracht afhandelen
    $search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $search_query = '';
    if ($search_term) {
        $search_query = "WHERE naamorg LIKE '%$search_term%' OR postcode LIKE '%$search_term%' OR stad LIKE '%$search_term%'";
    }

    // Haal dealers op, met een zoekfilter als nodig
    $dealers = $wpdb->get_results("SELECT * FROM $table_name $search_query");

    // Success bericht na het toevoegen van een nieuwe dealer
if (isset($_GET['success']) && $_GET['success'] == 'true') {
    echo '<div class="dealer-admin-notification success"><p>Dealer succesvol toegevoegd!</p></div>';
}


    // Verwijder een dealer als de 'delete' parameter is gezet
    if (isset($_GET['delete'])) {
        $dealer_id = intval($_GET['delete']);
        $deleted = $wpdb->delete($table_name, array('ccdeb' => $dealer_id));

        // Succesbericht
        if ($deleted) {
            echo '<div class="dealer-admin-notification success"><p>Dealer succesvol verwijderd!</p></div>';
        } else {
            echo '<div class="dealer-admin-notification error"><p>Er is een probleem met het verwijderen van de dealer!</p></div>';
        }

        // Herlaad de pagina na het verwijderen van de dealer
        echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
        exit;
    }

    // Voeg dealer toe als het formulier is ingediend
    if (isset($_POST['submit_dealer'])) {
        // Verzamelen van gegevens uit het formulier
        $dealer_name = sanitize_text_field($_POST['dealer_name']);
        $dealer_address = sanitize_text_field($_POST['dealer_address']);
        $dealer_postcode = sanitize_text_field($_POST['dealer_postcode']);
        $dealer_city = sanitize_text_field($_POST['dealer_city']);
        $dealer_url = sanitize_text_field($_POST['dealer_url']);

        // Haal het hoogste ccdeb nummer op en verhoog met 1
        $last_ccdeb = $wpdb->get_var("SELECT MAX(ccdeb) FROM $table_name");
        $new_ccdeb = $last_ccdeb ? $last_ccdeb + 1 : 300000; // Start bij 300000 als het de eerste dealer is

        // Toevoegen aan de wp_dealers tabel, met automatisch gegenereerd ccdeb nummer
        $wpdb->insert($table_name, array(
            'ccdeb' => $new_ccdeb,
            'naamorg' => $dealer_name,
            'adres' => $dealer_address,
            'huisnummer' => '', // Indien gewenst kun je huisnummer apart beheren
            'postcode' => $dealer_postcode,
            'stad' => $dealer_city,
            'url' => $dealer_url,
        ));

        // Success message en laat de lijst van dealers opnieuw zien
        echo '<div class="dealer-admin-notification success"><p>Dealer toegevoegd!</p></div>';

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
                echo '<div class="dealer-admin-notification error"><p>Ongeldig bestandstype. Alleen CSV-bestanden zijn toegestaan.</p></div>';
            } else {
                // Verwerk het CSV-bestand met puntkomma als scheidingsteken
                if (($handle = fopen($_FILES['csv_file']['tmp_name'], "r")) !== FALSE) {
                    $row = 0;
                    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) { // Aangepast naar puntkomma
                        if ($row > 0) { // Skip de header
                            // Verwerk elke rij en voeg de dealer toe aan de database
                            $wpdb->insert($table_name, array(
                                'ccdeb' => $wpdb->get_var("SELECT MAX(ccdeb) FROM $table_name") + 1,
                                'naamorg' => sanitize_text_field($data[1]),
                                'adres' => sanitize_text_field($data[3]),
                                'huisnummer' => sanitize_text_field($data[4]),
                                'postcode' => sanitize_text_field($data[5]),
                                'stad' => sanitize_text_field($data[6]),
                                'url' => !empty($data[2]) ? sanitize_text_field($data[2]) : '',
                            ));
                        }
                        $row++;
                    }
                    fclose($handle);

                    echo '<div class="dealer-admin-notification success"><p>CSV-bestand succesvol verwerkt!</p></div>';
                    // Herlaad de pagina na het verwerken van het bestand
                    echo '<meta http-equiv="refresh" content="0;URL=?page=dealer_admin">';
                    exit;
                }
            }
        } else {
            echo '<div class="dealer-admin-notification error"><p>Geen bestand geselecteerd.</p></div>';
        }
    }

    // Als de 'edit' parameter is gezet, haal dan de dealer op
    if (isset($_GET['edit'])) {
        $dealer_id = intval($_GET['edit']);
        $dealer_to_edit = $wpdb->get_row("SELECT * FROM $table_name WHERE ccdeb = $dealer_id");

        // Als de dealer bestaat
        if ($dealer_to_edit) {
            // Formulier voor het bewerken van de dealer
            if (isset($_POST['submit_dealer'])) {
                // Verzamelen van gegevens uit het formulier
                $dealer_name = sanitize_text_field($_POST['dealer_name']);
                $dealer_address = sanitize_text_field($_POST['dealer_address']);
                $dealer_postcode = sanitize_text_field($_POST['dealer_postcode']);
                $dealer_city = sanitize_text_field($_POST['dealer_city']);
                $dealer_url = sanitize_text_field($_POST['dealer_url']);

                // Update de dealer in de wp_dealers tabel
                $wpdb->update($table_name, array(
                    'naamorg' => $dealer_name,
                    'adres' => $dealer_address,
                    'postcode' => $dealer_postcode,
                    'stad' => $dealer_city,
                    'url' => $dealer_url,
                ), array('ccdeb' => $dealer_id));

                // Success message
                echo '<div class="dealer-admin-notification success"><p>Dealer bijgewerkt!</p></div>';
                echo '<a href="?page=dealer_admin" class="dealer-admin-button">Terug naar Dealer Admin</a>';
            }

            // De bewerkformulier met de bestaande gegevens van de dealer
            ?>
            <div class="dealer-admin-wrap">
                <h1>Dealer bewerken</h1>
                <form method="post" action="">
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
                        <input type="submit" name="submit_dealer" id="submit_dealer" class="button-primary" value="Dealer bijwerken">
                    </p>
                </form>
            </div>
            <?php
        } else {
            echo '<div class="dealer-admin-notification error"><p>Dealer niet gevonden!</p></div>';
        }
    } else {
        // Toon de bestaande dealers als er geen edit-parameter is
        ?>
        <div class="dealer-admin-wrap">
            <h1>Dealer Locator - Admin</h1>

            <!-- Dealer Toevoegen Formulier -->
            <h2>Voeg een nieuwe dealer toe</h2>
            <form method="post" action="">
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

            <!-- Zoekformulier voor dealers -->
            <h2>Zoek naar dealers</h2>
            <form method="get" action="">
                <input type="text" name="search" placeholder="Zoek op naam, postcode of stad" value="<?php echo esc_attr($search_term); ?>" class="input-field">
                <button type="submit" class="button-primary">Zoeken</button>
            </form>

            <!-- CSV-upload formulier onder de dealer toevoegen knop -->
            <h2>Upload CSV-bestand</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="csv_file" accept=".csv">
                <input type="submit" name="upload_csv" value="Upload CSV">
            </form>

            <?php
            // Tonen van de bestaande dealers
            if (!empty($dealers)) {
                ?>
                <h2>Alle dealers</h2>
                <table class="dealer-admin-table">
                    <thead>
                        <tr>
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
                                    <a href="?page=dealer_admin&edit=<?php echo esc_attr($dealer->ccdeb); ?>">Bewerken</a> | 
                                    <a href="?page=dealer_admin&delete=<?php echo esc_attr($dealer->ccdeb); ?>" onclick="return confirm('Weet je zeker dat je deze dealer wilt verwijderen?');">Verwijderen</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
            } else {
                echo '<p>Er zijn nog geen dealers toegevoegd.</p>';
            }
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}

// Registreer de shortcode
add_shortcode('dealer_admin', 'dealer_admin_page');

?>
