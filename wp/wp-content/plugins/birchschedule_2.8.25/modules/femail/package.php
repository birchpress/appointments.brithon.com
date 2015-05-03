<?php

birch_ns( 'birchschedule.femail', function( $ns ) {

		global $birchschedule;

		birch_defn( $ns, 'init', function() use ( $ns ) {
				add_action( 'init', array( $ns, 'wp_init' ), 8 );
				add_action( 'admin_init', array( $ns, 'wp_admin_init' ) );
			} );

		birch_defn( $ns, 'wp_admin_init', function() {} );

		birch_defn( $ns, 'wp_init', function() use ( $ns ) {
				if ( $ns->enable_fake_email() ) {
					add_filter( 'birchschedule_fbuilder_is_login_disabled',
						array( $ns, 'disable_login' ), 20 );

					add_filter( 'birchschedule_view_bookingform_validate_booking_info',
						array( $ns, 'eliminate_no_email_error' ), 50 );

					add_filter( 'birchschedule_view_appointments_new_validate_client_info',
						array( $ns, 'eliminate_no_email_error' ), 50 );

					add_filter( 'birchschedule_view_appointments_edit_clientlist_edit_validate_client_info',
						array( $ns, 'eliminate_no_email_error' ), 50 );

					add_action( 'birchschedule_view_clients_validate_data_before',
						array( $ns, 'create_fake_email' ), 20 );
				}
			} );

		birch_defn( $ns, 'generate_fake_email', function() {
				return uniqid() . '@fake.mail';
			} );

		birch_defn( $ns, 'eliminate_no_email_error', function( $errors ) use ( $ns ) {
				if ( isset( $errors['birs_client_email'] ) ) {
					$_POST['birs_client_email'] = $ns->generate_fake_email();
					unset( $errors['birs_client_email'] );
				}
				return $errors;
			} );

		birch_defn( $ns, 'create_fake_email', function() use ( $ns ) {
				if ( !is_email( $_POST['birs_client_email'] ) ) {
					$_POST['birs_client_email'] = $ns->generate_fake_email();
				}
			} );

		birch_defn( $ns, 'enable_fake_email', function() {
				return false;
			} );

		birch_defn( $ns, 'disable_login', function() {
				return true;
			} );

	} );
