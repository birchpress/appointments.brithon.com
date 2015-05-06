<?php

birch_ns( 'birchschedule.pcalendar', function( $ns ) {

		global $birchschedule;

		$_ns_data = new stdClass();

		birch_defn( $ns, 'init', function() use( $ns, $_ns_data ) {

				$_ns_data->SC_PUBLIC_CALENDAR = 'bpscheduler_public_calendar';

				$_ns_data->SC_PUBLIC_CALENDAR_LEGACY = 'bp-scheduler-public-calendar';

				add_action( 'init', array( $ns, 'wp_init' ) );

				add_action( 'birchschedule_view_get_shortcodes', array( $ns, 'add_shortcode' ), 20 );
			} );

		birch_defn( $ns, 'wp_init', function() use( $ns, $_ns_data, $birchschedule ) {

				$product_version = $birchschedule->get_product_version();

				add_shortcode( $_ns_data->SC_PUBLIC_CALENDAR, array( $ns, 'get_shortcode' ) );

				add_shortcode( $_ns_data->SC_PUBLIC_CALENDAR_LEGACY, array( $ns, 'get_shortcode' ) );

				add_action( 'wp_ajax_birchschedule_pcalendar_query_appointments',
					array( $ns, 'ajax_query_appointments' ) );

				add_action( 'wp_ajax_nopriv_birchschedule_pcalendar_query_appointments',
					array( $ns, 'ajax_query_appointments' ) );

				wp_register_script( 'birchschedule_pcalendar', $birchschedule->plugin_url() .
					'/modules/pcalendar/assets/js/public-calendar.js',
					array( 'birchschedule_view', 'fullcalendar_birchpress',
						'select2', 'moment' ), $product_version );

				wp_register_style( 'birchschedule_pcalendar', $birchschedule->plugin_url() .
					'/modules/pcalendar/assets/css/public-calendar.css',
					array( 'fullcalendar_birchpress', 'select2' ), $product_version );

				$birchschedule->view->register_script_data_fn(
					'birchschedule_pcalendar', 'birchschedule_pcalendar',
					array( $ns, 'get_script_data_fn_pcalendar' ) );
			} );

		birch_defn( $ns, 'get_script_data_fn_pcalendar', function() use ( $ns, $birchschedule ) {
				global $birchpress;

				return array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'location_map' => $ns->get_locations_map(),
					'location_staff_map' => $ns->get_locations_staff_map(),
					'staff_order' => $ns->get_staff_listing_order(),
					'location_order' => $ns->get_locations_listing_order(),
					'gmt_offset' =>$birchpress->util->get_gmt_offset(),
					'datepicker_i18n_options' => $birchpress->util->get_datepicker_i18n_params()
				);
			} );

		birch_defn( $ns, 'enqueue_scripts', function() use( $ns, $birchschedule ) {

				$birchschedule->view->register_3rd_scripts();
				$birchschedule->view->register_3rd_styles();
				$birchschedule->view->enqueue_styles( 'birchschedule_pcalendar' );
				$birchschedule->view->enqueue_scripts(
					array(
						'birchschedule_pcalendar'
					)
				);
			} );

		birch_defn( $ns, 'add_shortcode', function( $shortcodes ) use( $ns, $_ns_data ) {
				$shortcodes[] = $_ns_data->SC_PUBLIC_CALENDAR;
				return $shortcodes;
			} );

		birch_defn( $ns, 'get_locations_map', function() use( $ns, $birchschedule ) {

				return $birchschedule->view->calendar->get_locations_map();
			} );

		birch_defn( $ns, 'get_locations_staff_map', function() use( $ns, $birchschedule ) {

				$i18n_msgs = $birchschedule->view->get_frontend_i18n_messages();
				$map = $birchschedule->model->get_locations_staff_map();
				$allstaff = $birchschedule->model->query(
					array(
						'post_type' => 'birs_staff'
					),
					array(
						'meta_keys' => array(),
						'base_keys' => array( 'post_title' )
					)
				);
				$new_allstaff = array(
					'-1' => $i18n_msgs['All Providers']
				);
				foreach ( $allstaff as $staff_id => $staff ) {
					$new_allstaff[$staff_id] = $staff['post_title'];
				}
				$map[-1] = $new_allstaff;
				return $map;
			} );

		birch_defn( $ns, 'get_locations_listing_order', function() use( $birchschedule ) {

				return $birchschedule->view->calendar->get_locations_listing_order();
			} );

		birch_defn( $ns, 'get_staff_listing_order', function() use( $birchschedule ) {

				return $birchschedule->view->calendar->get_staff_listing_order();
			} );

		birch_defn( $ns, 'ajax_query_appointments', function() use( $ns, $birchschedule ) {
				global $birchpress;

				$start = $_GET['birs_time_start'];
				$start = $birchpress->util->get_wp_datetime( $start )->format( 'U' );
				$end = $_GET['birs_time_end'];
				$end = $birchpress->util->get_wp_datetime( $end )->format( 'U' );
				$location_id = $_GET['birs_location_id'];
				$staff_id = $_GET['birs_staff_id'];
				$title_template = stripslashes( $_GET['title_template'] );

				remove_filter( 'birchschedule_view_calendar_query_appointments',
					array( $birchschedule->cintegration, 'add_imported_events' ), 20 );
				$fn_get_template = function ( $template ) use ( $title_template ) {
					return $title_template;
				};
				add_filter( 'birchschedule_eadmin_get_calendar_appointment_title_template', $fn_get_template, 20 );
				$appointments =
				$birchschedule->view->calendar->query_appointments( $start, $end, $location_id, $staff_id );
?>
        <div id="birs_response">
            <?php
				echo json_encode( $appointments );
?>
        </div>
        <?php
				exit;
			} );

		birch_defn( $ns, 'get_shortcode', function( $atts, $content = null ) use( $ns, $birchschedule, $_ns_data ) {

				$a = shortcode_atts( array(
						'title' => '{service_name} - {client_name}',
						'default_view' => 'month'
					), $atts );
				$title_template = $a['title'];
				if ( $a['default_view'] == 'week' ) {
					$a['default_view'] = 'agendaWeek';
				} else
					if ( $a['default_view'] == 'day' ) {
					$a['default_view'] = 'agendaDay';
				} else {
					$a['default_view'] = 'month';
				}
				$ns->enqueue_scripts();
				wp_localize_script( 'birchschedule_pcalendar', 'birchschedule_pcalendar_sc_attrs', $a );
				$labels = $birchschedule->view->bookingform->get_fields_labels();
				ob_start();
?>
        <style type="text/css">
        <?php
				echo $birchschedule->view->get_custom_code_css( $_ns_data->SC_PUBLIC_CALENDAR );
?>
        </style>
        <div class="birchschedule wrap">
            <div id="birs_calendar_toolbar">
                <div id="birs_calendar_filter">
                    <label><?php echo $labels['location']; ?></label>
                    <select id="birs_calendar_location">
                    </select>
                    <label><?php echo $labels['service_provider']; ?></label>
                    <select id="birs_calendar_staff">
                    </select>
                    <input type="hidden" name="birs_appointment_title_template" id="birs_appointment_title_template" value="<?php echo esc_attr( $title_template ); ?>" />
                </div>
                <div class="clear"></div>
            </div>
            <div  id="birs_calendar"></div>
        </div>
        <?php
				return ob_get_clean();
			} );

	} );
