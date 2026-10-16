<?php
/**
 * Section « Autres circuits » affichée sous la vue circuit.
 * Variables attendues : $circuit_id (circuit courant), $related_count (int).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$related_count = isset( $related_count ) ? absint( $related_count ) : 3;
if ( $related_count < 1 ) {
    return;
}

// Catégorie passée par le shortcode (résolue en amont). Vide = pas de filtre.
$related_category = isset( $related_category ) ? sanitize_title( $related_category ) : '';

$related_args = array(
    'post_type'     => 'circuit',
    'post_status'   => 'publish',
    'has_password'  => false,
    'post__not_in'  => array( absint( $circuit_id ) ),
    'numberposts'   => $related_count,
    'fields'        => 'ids',
    'orderby'       => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
    'no_found_rows' => true,
);

// Filtrage par catégorie si une catégorie a été déterminée (shortcode ou défaut).
if ( ! empty( $related_category ) ) {
    $related_args['tax_query'] = array(
        array(
            'taxonomy' => 'circuit_category',
            'field'    => 'slug',
            'terms'    => $related_category,
        ),
    );
}

$related_ids = get_posts( $related_args );

// Préparation des cartes : on ignore les circuits sans option de départ.
$related_cards = array();
foreach ( $related_ids as $rid ) {
    $r_options = get_post_meta( $rid, '_circuit_options_data', true );
    if ( empty( $r_options ) || ! is_array( $r_options ) ) {
        continue;
    }
    $r_first = reset( $r_options );

    // Image : image mise en avant, sinon 1ʳᵉ photo de la galerie.
    $r_thumb_id = get_post_thumbnail_id( $rid );
    if ( ! $r_thumb_id ) {
        $r_gallery  = array_filter( array_map( 'absint', explode( ',', (string) ( $r_first['gallery_image_ids'] ?? '' ) ) ) );
        $r_thumb_id = $r_gallery ? reset( $r_gallery ) : 0;
    }

    // Villes de départ (3 maximum).
    $r_cities = array();
    foreach ( $r_options as $r_opt ) {
        if ( ! empty( $r_opt['city_name'] ) ) {
            $r_cities[] = sanitize_text_field( $r_opt['city_name'] );
        }
    }
    $r_cities = array_slice( array_unique( $r_cities ), 0, 3 );

    $related_cards[] = array(
        'id'       => $rid,
        'thumb_id' => $r_thumb_id,
        'cities'   => $r_cities,
        'duration' => floatval( $r_first['duration_hours'] ?? 0 ),
    );
}

if ( empty( $related_cards ) ) {
    return;
}

// Titre et sous-titre personnalisables depuis la metabox circuit.
// metadata_exists() distingue « méta absente » (fallback) de « méta présente mais vide » (masquage volontaire).
$circuit_id_int = absint( $circuit_id );

$related_title = metadata_exists( 'post', $circuit_id_int, '_circuit_related_title' )
    ? (string) get_post_meta( $circuit_id_int, '_circuit_related_title', true )
    : __( 'Découvrez nos autres circuits', 'elite-transfer-booking' );

$related_subtitle = metadata_exists( 'post', $circuit_id_int, '_circuit_related_subtitle' )
    ? (string) get_post_meta( $circuit_id_int, '_circuit_related_subtitle', true )
    : __( 'Poursuivez l’aventure avec une autre expérience sur mesure.', 'elite-transfer-booking' );

$related_heading_id = wp_unique_id( 'etb-related-title-' );
?>
<?php $has_title = ! empty( $related_title ); ?>
<section class="etb-related"<?php echo $has_title ? ' aria-labelledby="' . esc_attr( $related_heading_id ) . '"' : ''; ?>>
    <header class="etb-related__header">
        <?php if ( $has_title ) : ?>
            <h2 class="etb-related__title" id="<?php echo esc_attr( $related_heading_id ); ?>">
                <?php echo esc_html( $related_title ); ?>
            </h2>
        <?php endif; ?>
        <?php if ( ! empty( $related_subtitle ) ) : ?>
            <p class="etb-related__subtitle">
                <?php echo esc_html( $related_subtitle ); ?>
            </p>
        <?php endif; ?>
    </header>

    <ul class="etb-related__grid">
        <?php foreach ( $related_cards as $card ) : ?>
            <li class="etb-related__card">
                <div class="etb-related__media">
                    <?php
                    if ( $card['thumb_id'] ) {
                        // alt vide : la carte est déjà nommée par le titre-lien (image décorative).
                        echo wp_get_attachment_image( $card['thumb_id'], 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy' ) );
                    }
                    ?>
                </div>
                <div class="etb-related__body">
                    <?php if ( ! empty( $card['cities'] ) ) : ?>
                        <span class="etb-related__cities">
                            <?php echo esc_html( implode( ' · ', $card['cities'] ) ); ?>
                        </span>
                    <?php endif; ?>

                    <h3 class="etb-related__name">
                        <a class="etb-related__link" href="<?php echo esc_url( get_permalink( $card['id'] ) ); ?>">
                            <?php echo esc_html( get_the_title( $card['id'] ) ); ?>
                        </a>
                    </h3>

                    <?php if ( $card['duration'] > 0 ) : ?>
                        <span class="etb-related__meta">
                            <?php
                            /* translators: %s: durée du circuit en heures */
                            echo esc_html( sprintf( __( 'Duration : %s h', 'elite-transfer-booking' ), number_format_i18n( $card['duration'], 0 ) ) );
                            ?>
                        </span>
                    <?php endif; ?>

                    <span class="etb-related__cta" aria-hidden="true">
                        <?php esc_html_e( 'See tour', 'elite-transfer-booking' ); ?> →
                    </span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>