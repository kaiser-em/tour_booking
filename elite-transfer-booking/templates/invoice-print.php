<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Contrôle de sécurité
if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( 'Accès non autorisé.' );
}

$booking_id = absint( $_GET['booking_id'] ?? 0 );
check_admin_referer( 'etb_print_invoice_' . $booking_id );

if ( ! $booking_id || get_post_type( $booking_id ) !== 'tour_booking' ) {
    wp_die( 'Réservation introuvable.' );
}

// 2. Récupération des données de l'entreprise
$company_name = get_bloginfo( 'name' );
$admin_email  = get_option( 'admin_email' );
$gen_settings = get_option( 'etb_general_settings', array() );
$currency     = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';

// 3. Récupération des données de la réservation
$customer_name  = get_post_meta( $booking_id, '_etb_customer_name', true ) ?: 'Client';
$customer_email = get_post_meta( $booking_id, '_etb_customer_email', true ) ?: 'Non renseigné';
$customer_phone = get_post_meta( $booking_id, '_etb_customer_phone', true ) ?: 'Non renseigné';
$booking_date   = get_post_meta( $booking_id, '_etb_booking_date', true );
$booking_time   = get_post_meta( $booking_id, '_etb_booking_time', true );
$pickup_address = get_post_meta( $booking_id, '_etb_pickup_address', true ) ?: 'Non renseigné';
$dropoff_info   = get_post_meta( $booking_id, '_etb_dropoff_info', true ) ?: $pickup_address;
$circuit_id     = get_post_meta( $booking_id, '_etb_circuit_id', true );
$option_id      = get_post_meta( $booking_id, '_etb_circuit_option_id', true );
$duration_hours = floatval( get_post_meta( $booking_id, '_etb_duration_hours', true ) ?: 1 );
$adults         = absint( get_post_meta( $booking_id, '_etb_adults', true ) );
$children       = absint( get_post_meta( $booking_id, '_etb_children', true ) );
$luggage        = absint( get_post_meta( $booking_id, '_etb_luggage', true ) );

$vehicles       = get_post_meta( $booking_id, '_etb_vehicles', true ) ?: array();
$extras         = get_post_meta( $booking_id, '_etb_extras', true ) ?: array();
$pricing        = get_post_meta( $booking_id, '_etb_pricing_details', true ) ?: array();
$grand_total    = floatval( get_post_meta( $booking_id, '_etb_total_price', true ) );
$promo_code     = get_post_meta( $booking_id, '_etb_promo_code', true );
$discount_amt   = floatval( get_post_meta( $booking_id, '_etb_discount_amount', true ) );
$status         = get_post_meta( $booking_id, '_etb_status', true ) ?: 'pending';
$limo_id        = get_post_meta( $booking_id, '_etb_limo_booking_id', true );

$prestation_label = ! empty( $option_id )
    ? ETB_Pricing_Engine::get_circuit_option_label( $option_id, $circuit_id )
    : 'Transfert privé';

// Numéro officiel de facture et date
$invoice_number = sprintf( 'INV-%s-%04d', date( 'Y' ), $booking_id );
$invoice_date   = get_the_date( 'd/m/Y', $booking_id );
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture <?php echo esc_html( $invoice_number ); ?> — <?php echo esc_html( $customer_name ); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        body { background: #f8fafc; color: #1e293b; padding: 40px 20px; }
        .invoice-wrapper { max-width: 850px; margin: 0 auto; background: #ffffff; padding: 45px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e2e8f0; padding-bottom: 25px; margin-bottom: 30px; }
        .company-info h1 { font-size: 24px; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
        .company-info p { font-size: 13px; color: #64748b; line-height: 1.4; }
        .invoice-meta { text-align: right; }
        .invoice-meta h2 { font-size: 28px; color: #e65a15; margin-bottom: 6px; letter-spacing: -0.5px; }
        .invoice-meta p { font-size: 13px; color: #475569; }

        .invoice-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 35px; }
        .party-card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 18px; border-radius: 6px; }
        .party-card h3 { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 10px; letter-spacing: 0.5px; }
        .party-card strong { font-size: 16px; color: #0f172a; display: block; margin-bottom: 6px; }
        .party-card p { font-size: 13px; color: #334155; line-height: 1.5; }

        .service-summary { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 14px 18px; border-radius: 4px; margin-bottom: 30px; font-size: 13px; color: #1e3a8a; line-height: 1.6; }

        .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .invoice-table th { background: #0f172a; color: #ffffff; text-align: left; padding: 12px 14px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .invoice-table td { padding: 14px; border-bottom: 1px solid #e2e8f0; font-size: 13px; color: #1e293b; }
        .invoice-table td.qty, .invoice-table th.qty { text-align: center; width: 80px; }
        .invoice-table td.price, .invoice-table th.price { text-align: right; width: 120px; }
        .invoice-table td.total, .invoice-table th.total { text-align: right; width: 130px; font-weight: 700; }

        .invoice-totals { display: flex; justify-content: flex-end; margin-bottom: 35px; }
        .totals-table { width: 320px; }
        .totals-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 13px; color: #475569; border-bottom: 1px solid #f1f5f9; }
        .totals-row.discount { color: #16a34a; font-weight: 600; }
        .totals-row.grand-total { border-top: 2px solid #0f172a; border-bottom: none; padding-top: 14px; margin-top: 6px; font-size: 20px; font-weight: 800; color: #0f172a; }
        .totals-row.grand-total span:last-child { color: #e65a15; }

        .invoice-footer { border-top: 1px solid #e2e8f0; padding-top: 20px; text-align: center; font-size: 12px; color: #94a3b8; line-height: 1.5; }
        
        /* Boutons d'action */
        .action-bar { max-width: 850px; margin: 0 auto 20px auto; display: flex; justify-content: space-between; align-items: center; }
        .btn-print { background: #e65a15; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-print:hover { background: #d1541f; }
        .btn-close { background: #64748b; color: #fff; border: none; padding: 10px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; text-decoration: none; }

        @media print {
            body { background: #fff; padding: 0; }
            .action-bar { display: none !important; }
            .invoice-wrapper { box-shadow: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <a href="javascript:window.close();" class="btn-close">✕ Fermer</a>
        <button onclick="window.print();" class="btn-print">🖨️ Imprimer / Enregistrer en PDF</button>
    </div>

    <div class="invoice-wrapper">
        
        <!-- En-tête -->
        <div class="invoice-header">
            <div class="company-info">
                <h1><?php echo esc_html( $company_name ); ?></h1>
                <p>Services de Transfert VIP & Excursions Privées</p>
                <p>E-mail : <?php echo esc_html( $admin_email ); ?></p>
            </div>
            <div class="invoice-meta">
                <h2>FACTURE</h2>
                <p><strong>N° :</strong> <?php echo esc_html( $invoice_number ); ?></p>
                <p><strong>Date :</strong> <?php echo esc_html( $invoice_date ); ?></p>
                <p><strong>Dossier :</strong> #<?php echo esc_html( $booking_id ); ?></p>
                <?php if ( ! empty( $limo_id ) ) : ?>
                    <p><strong>Course LimoExpress :</strong> #<?php echo esc_html( $limo_id ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Coordonnées Client & Trajet -->
        <div class="invoice-parties">
            <div class="party-card">
                <h3>Facturé à</h3>
                <strong><?php echo esc_html( $customer_name ); ?></strong>
                <p>📞 <?php echo esc_html( $customer_phone ); ?></p>
                <p>✉️ <?php echo esc_html( $customer_email ); ?></p>
            </div>
            <div class="party-card">
                <h3>Détails de la mission</h3>
                <strong><?php echo esc_html( $prestation_label ); ?></strong>
                <p>📅 <strong>Date :</strong> <?php echo esc_html( $booking_date ); ?> à <?php echo esc_html( $booking_time ); ?></p>
                <p>📍 <strong>Prise en charge :</strong> <?php echo esc_html( $pickup_address ); ?></p>
                <p>🏁 <strong>Dépose :</strong> <?php echo esc_html( $dropoff_info ); ?></p>
                <p>👥 <strong>Passagers :</strong> <?php echo esc_html( $adults + $children ); ?> (<?php echo esc_html( $adults ); ?> ad., <?php echo esc_html( $children ); ?> enf.) | 🧳 <?php echo esc_html( $luggage ); ?> bagage(s)</p>
            </div>
        </div>

        <!-- Tableau détaillé ligne par ligne -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Article / Prestation</th>
                    <th class="qty">Qté</th>
                    <th class="price">Prix Unit.</th>
                    <th class="total">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // 1. Ligne(s) Véhicule(s)
                foreach ( $vehicles as $v_id => $qty ) :
                    if ( $qty <= 0 ) continue;
                    $v_title = get_the_title( $v_id );
                    $hourly_rate = floatval( get_post_meta( $v_id, '_etb_hourly_rate', true ) );
                    if ( $hourly_rate <= 0 ) {
                        $hourly_rate = floatval( get_post_meta( $v_id, '_etb_base_price', true ) );
                    }
                    $line_total = $hourly_rate * $duration_hours * $qty;
                ?>
                    <tr>
                        <td>
                            <strong>Véhicule : <?php echo esc_html( $v_title ); ?></strong><br>
                            <small style="color: #64748b;">Mise à disposition avec chauffeur privé (<?php echo esc_html( $duration_hours ); ?>h)</small>
                        </td>
                        <td class="qty"><?php echo esc_html( $qty ); ?></td>
                        <td class="price"><?php echo number_format_i18n( $hourly_rate * $duration_hours, 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                        <td class="total"><?php echo number_format_i18n( $line_total, 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php
                // 2. Supplément départ circuit (si applicable)
                if ( ! empty( $pricing['circuit_additional_price'] ) && $pricing['circuit_additional_price'] > 0 ) :
                ?>
                    <tr>
                        <td>
                            <strong>Supplément ville de départ</strong><br>
                            <small style="color: #64748b;">Frais de liaison kilométrique pour l'itinéraire</small>
                        </td>
                        <td class="qty">1</td>
                        <td class="price"><?php echo number_format_i18n( $pricing['circuit_additional_price'], 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                        <td class="total"><?php echo number_format_i18n( $pricing['circuit_additional_price'], 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                    </tr>
                <?php endif; ?>

                <?php
                // 3. Supplément prise en charge personnalisée (si applicable)
                if ( ! empty( $pricing['pickup_surcharge'] ) && $pricing['pickup_surcharge'] > 0 ) :
                ?>
                    <tr>
                        <td>
                            <strong>Supplément lieu de prise en charge</strong><br>
                            <small style="color: #64748b;">Prise en charge hors zone standard</small>
                        </td>
                        <td class="qty">1</td>
                        <td class="price"><?php echo number_format_i18n( $pricing['pickup_surcharge'], 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                        <td class="total"><?php echo number_format_i18n( $pricing['pickup_surcharge'], 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                    </tr>
                <?php endif; ?>

                <?php
                // 4. Ligne(s) Extras / Options
                foreach ( $extras as $e_id => $qty ) :
                    if ( $qty <= 0 ) continue;
                    $e_title    = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $price_type = get_post_meta( $e_id, '_etb_price_type', true );
                    $line_total = ( $price_type === 'fixed' ) ? $e_price : ( $e_price * $qty );
                ?>
                    <tr>
                        <td>
                            <strong>Option : <?php echo esc_html( $e_title ); ?></strong><br>
                            <small style="color: #64748b;">Service additionnel</small>
                        </td>
                        <td class="qty"><?php echo esc_html( $qty ); ?></td>
                        <td class="price"><?php echo number_format_i18n( $e_price, 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                        <td class="total"><?php echo number_format_i18n( $line_total, 2 ); ?> <?php echo esc_html( $currency ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totaux financiers -->
        <div class="invoice-totals">
            <div class="totals-table">
                <?php if ( $discount_amt > 0 && ! empty( $promo_code ) ) : ?>
                    <div class="totals-row discount">
                        <span>Remise Code Promo (<?php echo esc_html( $promo_code ); ?>)</span>
                        <span>- <?php echo number_format_i18n( $discount_amt, 2 ); ?> <?php echo esc_html( $currency ); ?></span>
                    </div>
                <?php endif; ?>
                <div class="totals-row grand-total">
                    <span>TOTAL NET</span>
                    <span><?php echo number_format_i18n( $grand_total, 2 ); ?> <?php echo esc_html( $currency ); ?></span>
                </div>
            </div>
        </div>

        <!-- Pied de page -->
        <div class="invoice-footer">
            <p>Merci pour votre confiance. Prestation effectuée avec chauffeur professionnel VTC.</p>
            <p><?php echo esc_html( $company_name ); ?> — Document officiel généré le <?php echo esc_html( date( 'd/m/Y H:i' ) ); ?></p>
        </div>

    </div>

</body>
</html>