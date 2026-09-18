<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $post;
if ( ! $post || 'group' !== get_post_type( $post ) ) return;
    $group_id      = get_the_ID();
    $organiser_id  = (int) get_post_field( 'post_author', $group_id );
    $organiser     = get_userdata( $organiser_id );
    $category      = bubbahub_group_category( $group_id );
    $booking_sessions = function_exists( 'bubbahub_booking_get_available_sessions' ) ? bubbahub_booking_get_available_sessions( $group_id ) : array();
    $term_time     = bubbahub_group_term_time( $group_id );
    $venue_id      = bubbahub_group_venue_id( $group_id );
    $address       = bubbahub_group_address( $group_id, $venue_id );
    $venue_url     = bubbahub_group_venue_url( $venue_id );
    $age           = bubbahub_group_format_value( bubbahub_group_get_field( $group_id, 'age_range', '' ) );
    $price         = bubbahub_group_format_value( bubbahub_group_get_field( $group_id, 'price', '' ) );
    $session       = bubbahub_group_format_value( bubbahub_group_get_field( $group_id, 'session_length', '' ) );
    $booking       = bubbahub_group_format_value( bubbahub_group_get_field( $group_id, 'booking_required', '' ) );
    $schedule      = bubbahub_group_format_value( bubbahub_group_get_field( $group_id, 'business_hours', '' ) );
    $schedule_note = bubbahub_group_format_value( bubbahub_group_get_field( $group_id, 'schedule_notes', '' ) );
    $map           = bubbahub_group_normalise_map( bubbahub_group_get_field( $group_id, 'map', '' ) );
    if ( ! $map && $venue_id ) $map = bubbahub_group_normalise_map( bubbahub_group_get_field( $venue_id, 'map', '' ) );
    $venues        = bubbahub_group_get_organiser_venues( $organiser_id );
    $related       = bubbahub_group_related_query( $group_id, $organiser_id, $venue_id );
    $tags          = bubbahub_group_tags( $group_id );
    $contact_email  = bubbahub_group_format_value( bubbahub_group_get_field( $group_id, 'email', '' ) );
    $contact_website = bubbahub_group_format_value( bubbahub_group_get_field( $group_id, 'website', '' ) );
    $contact_facebook = bubbahub_group_format_value( bubbahub_group_get_field( $group_id, 'facebook', '' ) );
    $contact_instagram = bubbahub_group_format_value( bubbahub_group_get_field( $group_id, 'instagram', '' ) );
    $contact_form_id = 0;
    if ( function_exists( 'Ninja_Forms' ) ) {
        foreach ( Ninja_Forms()->form()->get_forms() as $bh_contact_form ) {
            $bh_form_title = strtolower( trim( (string) $bh_contact_form->get_setting( 'title' ) ) );
            if ( false !== strpos( $bh_form_title, 'contact organiser' ) || false !== strpos( $bh_form_title, 'contact organizer' ) ) {
                $contact_form_id = absint( $bh_contact_form->get_id() );
                break;
            }
        }
    }
    ?>
    <main class="bhg-single" data-post-id="<?php echo esc_attr( $group_id ); ?>" data-lat="<?php echo $map ? esc_attr( $map['lat'] ) : ''; ?>" data-lng="<?php echo $map ? esc_attr( $map['lng'] ) : ''; ?>">
        <div class="bhg-shell">
            <div class="bhg-top-actions">
                <a class="bhg-button bhg-button-light" href="<?php echo esc_url( function_exists( 'wp_get_referer' ) && wp_get_referer() ? wp_get_referer() : home_url( '/directory/' ) ); ?>">← Back to Directory</a>
                <?php if ( bubbahub_group_can_edit( $group_id ) ) : ?><a class="bhg-button bhg-button-accent" href="<?php echo esc_url( get_edit_post_link( $group_id ) ); ?>">Edit Listing</a><?php endif; ?>
            </div>
            <header class="bhg-hero">
                <div class="bhg-hero-content">
                    <?php if ( $category ) : ?><div class="bhg-category"><?php echo esc_html( $category ); ?></div><?php endif; ?>
                    <h1><?php echo esc_html( get_the_title() ); ?></h1>
                    <div class="bhg-badges" aria-label="Listing actions">
                        <button type="button" class="bhg-badge" data-badge-action="favourite" aria-pressed="false"><span>♡</span> Fav</button>
                        <button type="button" class="bhg-badge" data-badge-action="compare" aria-pressed="false"><span>＋</span> Compare</button>
                        <button type="button" class="bhg-badge" data-badge-action="visited" aria-pressed="false"><span>✓</span> Visited</button>
                        <?php if ( $term_time ) : ?><span class="bhg-badge bhg-badge-term"><span>▣</span> Term Time</span><?php endif; ?>
                    </div>
                    <?php if ( $address ) : ?><div class="bhg-address"><span aria-hidden="true">⌖</span><?php if ( $venue_url ) : ?><a href="<?php echo esc_url( $venue_url ); ?>"><?php echo esc_html( $address ); ?></a><?php else : echo esc_html( $address ); endif; ?></div><?php endif; ?>
                    <button type="button" class="bhg-share" data-share><span aria-hidden="true">↗</span> Share now</button>
                </div>
            </header>
            <div class="bhg-layout">
                <div class="bhg-main-column">
                    <section class="bhg-section bhg-quick-details"><h2>Quick Details</h2><div class="bhg-detail-grid"><?php foreach ( array( 'Age range' => $age, 'Price' => $price, 'Session length' => $session, 'Booking required' => $booking ) as $label => $value ) : ?><div class="bhg-detail-card"><span><?php echo esc_html( $label ); ?></span><strong><?php echo $value !== '' ? esc_html( $value ) : 'Not specified'; ?></strong></div><?php endforeach; ?></div></section>
                    <section class="bhg-section bhg-description"><h2>About this group</h2><div class="bhg-richtext"><?php echo wpautop( do_shortcode( get_the_content() ) ); ?></div></section>
                    <section class="bhg-section bhg-map-section">
                        <div class="bhg-section-heading"><div><h2>Location</h2><p>Find this class and explore other venues from the same organiser.</p></div></div>
                        <?php if ( count( $venues ) > 1 ) : ?><label class="bhg-venue-select-label" for="bhg-venue-select">Show classes at venue</label><select id="bhg-venue-select" class="bhg-venue-select" data-group-id="<?php echo esc_attr( $group_id ); ?>"><option value="">Current venue</option><?php foreach ( $venues as $venue ) : ?><option value="<?php echo esc_attr( $venue->ID ); ?>" <?php selected( $venue->ID, $venue_id ); ?>><?php echo esc_html( $venue->post_title ); ?></option><?php endforeach; ?></select><?php endif; ?>
                        <?php if ( $map ) : ?><div id="bhg-map" class="bhg-map" data-lat="<?php echo esc_attr( $map['lat'] ); ?>" data-lng="<?php echo esc_attr( $map['lng'] ); ?>" aria-label="Map showing the venue location"></div><?php else : ?><div class="bhg-no-map">No map location has been added to this listing yet.</div><?php endif; ?>
                    </section>
                    <section class="bhg-section bhg-related"><div class="bhg-section-heading"><div><h2>Other Classes by this Organiser</h2><p><?php echo $organiser ? esc_html( $organiser->display_name ) : 'This organiser'; ?></p></div><div class="bhg-carousel-controls"><button type="button" data-carousel-prev aria-label="Previous">←</button><button type="button" data-carousel-next aria-label="Next">→</button></div></div><div class="bhg-related-track" data-related-track><?php echo bubbahub_group_related_markup( $related ); ?></div></section>
                </div>
                <aside class="bhg-sidebar">
                    <section class="bhg-sidebar-card"><h2>Tags</h2><?php if ( $tags ) : ?><div class="bhg-tags"><?php foreach ( $tags as $tag ) : ?><a href="<?php echo esc_url( get_term_link( $tag ) ); ?>"><?php echo esc_html( $tag->name ); ?></a><?php endforeach; ?></div><?php else : ?><p class="bhg-muted">No tags added.</p><?php endif; ?></section>
                    <section class="bhg-sidebar-card"><h2>Schedule</h2><div class="bhg-schedule"><?php echo $schedule !== '' ? wp_kses_post( nl2br( esc_html( $schedule ) ) ) : '<span class="bhg-muted">Schedule not added yet.</span>'; ?></div></section>
                    <section class="bhg-sidebar-card"><h2>Schedule notes</h2><div class="bhg-schedule-notes"><?php echo $schedule_note !== '' ? wp_kses_post( nl2br( esc_html( $schedule_note ) ) ) : '<span class="bhg-muted">No additional notes.</span>'; ?></div></section>
                    <section class="bhg-sidebar-card bhg-contact-card">
                        <h2>Contact</h2>
                        <div class="bhg-contact-links">
                            <?php if ( $contact_website && filter_var( $contact_website, FILTER_VALIDATE_URL ) ) : ?><a href="<?php echo esc_url( $contact_website ); ?>" target="_blank" rel="noopener"><span>↗</span> Website</a><?php endif; ?>
                            <?php if ( $contact_email && is_email( $contact_email ) ) : ?><a href="mailto:<?php echo esc_attr( antispambot( $contact_email ) ); ?>"><span>✉</span> Email</a><?php endif; ?>
                            <?php if ( $contact_facebook && filter_var( $contact_facebook, FILTER_VALIDATE_URL ) ) : ?><a href="<?php echo esc_url( $contact_facebook ); ?>" target="_blank" rel="noopener"><span>f</span> Facebook</a><?php endif; ?>
                            <?php if ( $contact_instagram && filter_var( $contact_instagram, FILTER_VALIDATE_URL ) ) : ?><a href="<?php echo esc_url( $contact_instagram ); ?>" target="_blank" rel="noopener"><span>◎</span> Instagram</a><?php endif; ?>
                        </div>
                        <?php if ( $contact_form_id ) : ?><button type="button" class="bhg-contact-button" data-contact-open>Contact Organiser <span>→</span></button><?php elseif ( $contact_email && is_email( $contact_email ) ) : ?><a class="bhg-contact-button" href="mailto:<?php echo esc_attr( antispambot( $contact_email ) ); ?>">Contact Organiser <span>→</span></a><?php endif; ?>
                    </section>
                    <section class="bhg-sidebar-card bhg-ready"><?php if ( $booking_sessions ) : ?><div class="bhg-ready-icon" aria-hidden="true">✓</div><h2>Ready to Join?</h2><p>Secure your spot for this group right away.</p><a href="#bh-booking" class="bhg-book-button" data-booking-open>Book My Space Now <span>→</span></a><?php else : ?><div class="bhg-ready-icon" aria-hidden="true">ℹ</div><h2>No Bookings Available</h2><p>Sorry, no bookings are currently available. Please check the organiser's website for more information.</p><?php endif; ?></section>
                </aside>
            </div>
            <?php if ( $contact_form_id ) : ?>
            <div id="bh-contact-modal" class="bh-contact-modal" hidden aria-hidden="true">
                <div class="bh-contact-modal-backdrop" data-contact-close></div>
                <div class="bh-contact-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bh-contact-title">
                    <button type="button" class="bh-contact-modal-close" data-contact-close aria-label="Close contact form">×</button>
                    <div class="bh-contact-modal-heading"><span class="bh-contact-eyebrow">CONTACT ORGANISER</span><h2 id="bh-contact-title">Contact <?php echo $organiser ? esc_html( $organiser->display_name ) : 'the organiser'; ?></h2><p>Your message will be sent directly to the organiser's listing email.</p></div>
                    <div class="bh-contact-form"><?php echo do_shortcode( '[ninja_form id="' . absint( $contact_form_id ) . '"]' ); ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ( function_exists( 'bubbahub_booking_render_group_widget' ) ) : ?><?php bubbahub_booking_render_group_widget( $group_id ); ?><?php endif; ?>
        </div>
    </main>
    <?php
