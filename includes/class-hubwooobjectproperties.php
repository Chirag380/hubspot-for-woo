<?php
/**
 * All api GET/POST functionalities.
 *
 * @link       https://makewebbetter.com/
 * @since      1.0.0
 *
 * @package    makewebbetter-hubspot-for-woocommerce
 * @subpackage makewebbetter-hubspot-for-woocommerce/includes
 */

/**
 * Handles all hubspot api reqests/response related functionalities of the plugin.
 *
 * Provide a list of functions to manage all the requests
 * that needs in our integration to get/fetch data
 * from/to hubspot.
 *
 * @package    makewebbetter-hubspot-for-woocommerce
 * @subpackage makewebbetter-hubspot-for-woocommerce/includes
 */
class HubwooObjectProperties {

	/**
	 * The single instance of the class.
	 *
	 * @since   1.0.0
	 * @var HubwooObjectProperties  The single instance of the HubwooObjectProperties
	 */
	protected static $instance = null;
	/**
	 * Main HubwooObjectProperties Instance.
	 *
	 * Ensures only one instance of HubwooObjectProperties is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @return HubwooObjectProperties - Main instance.
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {

			self::$instance = new self();
		}

		return self::$instance;
	}
	/**
	 * Create/update contact and associate with a deal.
	 *
	 * @since 1.0.0
	 * @param int $user_id - User Id of the contact.
	 * @static
	 * @return  void.
	 */
	public static function hubwoo_ecomm_contacts_with_id( $user_id ) {

		$object_type           = 'CONTACT';
		$contact               = array();
		$user_info             = json_decode( json_encode( get_userdata( $user_id ) ), true );
		$user_email            = $user_info['data']['user_email'];
		$hubwoo_ecomm_customer = new HubwooEcommObject( $user_id, $object_type );
		$contact_properties    = $hubwoo_ecomm_customer->get_object_properties();
		$contact_properties    = apply_filters( 'hubwoo_map_ecomm_' . $object_type . '_properties', $contact_properties, $user_id );
		$user_vid              = get_user_meta( $user_id, 'hubwoo_user_vid', true );

		$contact = $contact_properties;
		$contact['email'] = $user_email;
		$contact = array(
			'properties' => $contact,
		);

		if ( count( $contact ) ) {

			$flag = true;

			if ( Hubwoo::is_access_token_expired() ) {

				$hapikey = HUBWOO_CLIENT_ID;
				$hseckey = HUBWOO_SECRET_ID;
				$status  = HubWooConnectionMananager::get_instance()->hubwoo_refresh_token( $hapikey, $hseckey );

				if ( ! $status ) {

					$flag = false;
				}
			}

			if ( $flag ) {

				if ( ! empty( $user_vid ) ) {
					$response = HubWooConnectionMananager::get_instance()->update_object_record( 'contacts', $user_vid, $contact );
				} else {
					$response = HubWooConnectionMananager::get_instance()->create_object_record( 'contacts', $contact );

					if ( 201 == $response['status_code'] ) {
						$contact_vid = json_decode( $response['body'] );
						update_user_meta( $user_id, 'hubwoo_user_vid', $contact_vid->id );
						update_user_meta( $user_id, 'hubwoo_pro_user_data_change', 'synced' );

					} else if ( 409 == $response['status_code'] ) {
						$contact_vid = json_decode( $response['body'] );
						$hs_id = explode( 'ID: ', $contact_vid->message );
						update_user_meta( $user_id, 'hubwoo_user_vid', $hs_id[1] );
						update_user_meta( $user_id, 'hubwoo_pro_user_data_change', 'synced' );
					} else if ( 400 == $response['status_code'] ) {
						update_user_meta( $user_id, 'hubwoo_invalid_contact', 'yes' );
						update_user_meta( $user_id, 'hubwoo_pro_user_data_change', 'synced' );
					}
				}
			}
		}
	}

	/**
	 * Create/update a guest user and associate with a deal.
	 *
	 * @since 1.0.0
	 * @param int $order_id - order id of the contact.
	 * @static
	 * @return  void.
	 */
	public static function hubwoo_ecomm_guest_user( $order_id ) {

		global $hubwoo;
		$guest_email = get_post_meta( $order_id, '_billing_email', true );

		$guest_updates = array();

		if ( ! empty( $guest_email ) ) {

			$object_type                                = 'CONTACT';
			$guest_user_info                            = array();
			$guest_order_callback                       = new HubwooGuestOrdersManager( $order_id );
			$guest_user_properties                      = $guest_order_callback->get_order_related_properties( $order_id, $guest_email );
			$guest_user_properties                      = $hubwoo->hubwoo_filter_contact_properties( $guest_user_properties );

			foreach ( $guest_user_properties as $key => $value ) {
				$guest_user_info[ $value['property'] ] = $value['value'];
			}

			$guest_user_info['email']                   = $guest_email;
			$guest_user_info['firstname']               = get_post_meta( $order_id, '_billing_first_name', true );
			$guest_user_info['lastname']                = get_post_meta( $order_id, '_billing_last_name', true );
			$guest_user_info['phone']                   = get_post_meta( $order_id, '_billing_phone', true );
			$guest_user_info['billing_address_line_1']  = get_post_meta( $order_id, '_billing_address_1', true );
			$guest_user_info['billing_address_line_2']  = get_post_meta( $order_id, '_billing_address_2', true );
			$guest_user_info['billing_city']            = get_post_meta( $order_id, '_billing_city', true );
			$guest_user_info['billing_state']           = get_post_meta( $order_id, '_billing_state', true );
			$guest_user_info['billing_country']         = get_post_meta( $order_id, '_billing_country', true );
			$guest_user_info['billing_postal_code']     = get_post_meta( $order_id, '_billing_postcode', true );
			$guest_user_info['lifecyclestage']          = 'customer';
			$guest_user_info['customer_source_store']   = get_bloginfo( 'name' );
			$guest_user_info['hs_language']             = get_post_meta( $order_id, 'hubwoo_preferred_language', true );
			$guest_contact_properties                   = apply_filters( 'hubwoo_map_ecomm_guest_' . $object_type . '_properties', $guest_user_info, $order_id );
			$user_vid                                   = get_post_meta( $order_id, 'hubwoo_user_vid', true );
			$contact = array(
				'properties' => $guest_contact_properties,
			);
		}
		if ( count( $contact ) ) {

			$flag = true;

			if ( Hubwoo::is_access_token_expired() ) {

				$hapikey = HUBWOO_CLIENT_ID;
				$hseckey = HUBWOO_SECRET_ID;
				$status  = HubWooConnectionMananager::get_instance()->hubwoo_refresh_token( $hapikey, $hseckey );

				if ( ! $status ) {

					$flag = false;
				}
			}

			if ( $flag ) {

				if ( ! empty( $user_vid ) ) {
					$response = HubWooConnectionMananager::get_instance()->update_object_record( 'contacts', $user_vid, $contact );
				} else {
					$response = HubWooConnectionMananager::get_instance()->create_object_record( 'contacts', $contact );

					if ( 201 == $response['status_code'] ) {
						$contact_vid = json_decode( $response['body'] );
						update_post_meta( $order_id, 'hubwoo_user_vid', $contact_vid->id );
						update_post_meta( $order_id, 'hubwoo_pro_guest_order', 'synced' );

					} else if ( 409 == $response['status_code'] ) {
						$contact_vid = json_decode( $response['body'] );
						$hs_id = explode( 'ID: ', $contact_vid->message );
						update_post_meta( $order_id, 'hubwoo_user_vid', $hs_id[1] );
						update_post_meta( $order_id, 'hubwoo_pro_guest_order', 'synced' );
					} else if ( 400 == $response['status_code'] ) {
						update_post_meta( $order_id, 'hubwoo_invalid_contact', 'yes' );
						update_user_meta( $order_id, 'hubwoo_pro_user_data_change', 'synced' );
					}
				}
			}
		}
	}

	/**
	 * Create/update an ecommerce deal.
	 *
	 * @since 1.0.0
	 * @param int $order_id - order id.
	 * @param int $source - register or guest.
	 * @param int $customer_id - user id.
	 * @static
	 * @return  array sync response from HubSpot.
	 */
	public static function hubwoo_ecomm_sync_deal( $order_id, $source, $customer_id ) {
		$object_type                 = 'DEAL';
		$deal_updates                = array();
		$assc_deal_cmpy              = get_option( 'hubwoo_assoc_deal_cmpy_enable', 'yes' );
		$pipeline_id                 = get_option( 'hubwoo_ecomm_pipeline_id', false );
		$hubwoo_ecomm_deal           = new HubwooEcommObject( $order_id, $object_type );
		$deal_properties             = $hubwoo_ecomm_deal->get_object_properties();
		$deal_properties             = apply_filters( 'hubwoo_map_ecomm_' . $object_type . '_properties', $deal_properties, $order_id );

		if ( 'yes' == get_option( 'hubwoo_deal_multi_currency_enable', 'no' ) ) {
			$currency = get_post_meta( $order_id, '_order_currency', true );
			if ( ! empty( $currency ) ) {
				$deal_properties['deal_currency_code'] = $currency;
			}
		}

		if ( empty( $pipeline_id ) ) {
			Hubwoo::get_all_deal_stages();
			$pipeline_id = get_option( 'hubwoo_ecomm_pipeline_id', false );
		}

		$deal_properties['pipeline'] = $pipeline_id;

		$deal_updates   = array(
			'properties' => $deal_properties,
		);
		$response          = '';

		if ( 'user' == $source ) {
			$user_info  = json_decode( wp_json_encode( get_userdata( $customer_id ) ), true );
			$user_email = $user_info['data']['user_email'];
			$contact    = $user_email;
			if ( empty( $contact ) ) {
				$contact = $customer_id;
			}
			$contact_vid = get_user_meta( $customer_id, 'hubwoo_user_vid', true );
			$invalid_contact = get_user_meta( $customer_id, 'hubwoo_invalid_contact', true );
		} else {
			$contact_vid = get_post_meta( $order_id, 'hubwoo_user_vid', true );
			$contact = get_post_meta( $order_id, '_billing_email', true );
			$invalid_contact = get_post_meta( $order_id, 'hubwoo_invalid_contact', true );
		}

		if ( count( $deal_updates ) ) {

			$flag = true;
			if ( Hubwoo::is_access_token_expired() ) {

				$hapikey = HUBWOO_CLIENT_ID;
				$hseckey = HUBWOO_SECRET_ID;
				$status  = HubWooConnectionMananager::get_instance()->hubwoo_refresh_token( $hapikey, $hseckey );

				if ( ! $status ) {

					$flag = false;
				}
			}

			if ( $flag ) {

				$filtergps = array(
					'filterGroups' => array(
						array(
							'filters' => array(
								array(
									'value' => $order_id,
									'propertyName' => 'order_number',
									'operator' => 'EQ',
								),
							),
						),
						array(
							'filters' => array(
								array(
									'value' => $order_id,
									'propertyName' => 'ip__ecomm_bridge__order_number',
									'operator' => 'EQ',
								),
							),
						),
					),
				);

				$response = HubWooConnectionMananager::get_instance()->search_object_record( 'deals', $filtergps );

				if ( 200 == $response['status_code'] ) {
					$responce_body = json_decode( $response['body'] );
					$result = $responce_body->results;
					if ( ! empty( $result ) ) {
						foreach ( $result as $key => $value ) {
							update_post_meta( $order_id, 'hubwoo_ecomm_deal_id', $value->id );
						}
					}
				}

				$hubwoo_ecomm_deal_id = get_post_meta( $order_id, 'hubwoo_ecomm_deal_id', true );

				if ( empty( $hubwoo_ecomm_deal_id ) ) {
					$response = HubWooConnectionMananager::get_instance()->create_object_record( 'deals', $deal_updates );
					if ( 201 == $response['status_code'] ) {
						$response_body = json_decode( $response['body'] );
						$hubwoo_ecomm_deal_id = $response_body->id;
						update_post_meta( $order_id, 'hubwoo_ecomm_deal_id', $hubwoo_ecomm_deal_id );
					}
				} else {
					$response = HubWooConnectionMananager::get_instance()->update_object_record( 'deals', $hubwoo_ecomm_deal_id, $deal_updates );
				}

				HubWooConnectionMananager::get_instance()->associate_object( 'deal', $hubwoo_ecomm_deal_id, 'contact', $contact_vid, 3 );

				do_action( 'hubwoo_ecomm_deal_created', $order_id );

				if ( 'yes' == $assc_deal_cmpy ) {
					if ( ! empty( $contact ) && empty( $invalid_contact ) ) {
						Hubwoo::hubwoo_associate_deal_company( $contact, $hubwoo_ecomm_deal_id );
					}
				}

				$response = self::hubwoo_ecomm_sync_line_items( $order_id );

				return $response;
			}
		}
	}


	/**
	 * Create and Associate Line Items for an order.
	 *
	 * @since 1.0.0
	 * @param int $order_id - order id.
	 * @static
	 * @return  array sync response from HubSpot.
	 */
	public static function hubwoo_ecomm_sync_line_items( $order_id ) {

		if ( ! empty( $order_id ) ) {

			$order             = wc_get_order( $order_id );
			$line_updates      = array();
			$order_items       = $order->get_items();
			$object_ids        = array();
			$response          = array( 'status_code' => 206 );
			$no_products_found = false;

			if ( is_array( $order_items ) && count( $order_items ) ) {

				foreach ( $order_items as $item_key => $single_item ) :

					$product_id = $single_item->get_variation_id();
					if ( 0 === $product_id ) {
						$product_id = $single_item->get_product_id();
						if ( 0 === $product_id ) {
							$no_products_found = true;
						}
					}
					if ( get_post_status( $product_id ) == 'trash' || get_post_status( $product_id ) == false ) {
						continue;
					}
					$item_sku = get_post_meta( $product_id, '_sku', true );
					if ( empty( $item_sku ) ) {
						$item_sku = $product_id;
					}

					$line_item_hs_id = wc_get_order_item_meta( $item_key, 'hubwoo_ecomm_line_item_id', true );

					if ( ! empty( $line_item_hs_id ) ) {
						continue;
					}

					$quantity        = ! empty( $single_item->get_quantity() ) ? $single_item->get_quantity() : 0;
					$item_total      = ! empty( $single_item->get_total() ) ? $single_item->get_total() : 0;
					$item_sub_total  = ! empty( $single_item->get_subtotal() ) ? $single_item->get_subtotal() : 0;
					$product         = $single_item->get_product();
					$name            = self::hubwoo_ecomm_product_name( $product );
					$discount_amount = abs( $item_total - $item_sub_total );
					$discount_amount = $discount_amount / $quantity;
					$item_sub_total  = $item_sub_total / $quantity;
					$hs_product_id   = get_post_meta( $product_id, 'hubwoo_ecomm_pro_id', true );
					$object_ids[]    = $item_key;

					$properties = array(
						'quantity'        => $quantity,
						'price'           => $item_sub_total,
						'amount'          => $item_total,
						'name'            => $name,
						'discount_amount' => $discount_amount,
						'sku'             => $item_sku,
						'tax_amount'      => $single_item->get_total_tax(),
					);

					if ( 'yes' != get_option( 'hubwoo_product_scope_needed', 'no' ) ) {
						$properties['hs_product_id'] = $hs_product_id;
					}

					$properties = apply_filters( 'hubwoo_line_item_properties', $properties, $product_id );

					$line_updates[] = array(
						'properties'       => $properties,
					);
				endforeach;
			}

			if ( count( $line_updates ) ) {

				$line_updates = array(
					'inputs' => $line_updates,
				);

				$flag = true;
				if ( Hubwoo::is_access_token_expired() ) {
					$hapikey = HUBWOO_CLIENT_ID;
					$hseckey = HUBWOO_SECRET_ID;
					$status  = HubWooConnectionMananager::get_instance()->hubwoo_refresh_token( $hapikey, $hseckey );
					if ( ! $status ) {
						$flag = false;
					}
				}
				if ( $flag ) {
					$response = HubWooConnectionMananager::get_instance()->create_batch_object_record( 'line_items', $line_updates );
				}
			}

			if ( 201 == $response['status_code'] || 206 == $response['status_code'] || empty( $object_ids ) ) {

				update_post_meta( $order_id, 'hubwoo_ecomm_deal_created', 'yes' );

				$deal_id = get_post_meta( $order_id, 'hubwoo_ecomm_deal_id', true );
				if ( isset( $response['body'] ) && ! empty( $response['body'] ) ) {
					$response_body = json_decode( $response['body'] );
					foreach ( $order_items as $item_key => $single_item ) :

						$product_id = $single_item->get_variation_id();
						if ( 0 === $product_id ) {
							$product_id = $single_item->get_product_id();
							if ( 0 === $product_id ) {
								$no_products_found = true;
							}
						}
						if ( get_post_status( $product_id ) == 'trash' || get_post_status( $product_id ) == false ) {
							continue;
						}

						$product         = $single_item->get_product();
						$name            = self::get_instance()->hubwoo_ecomm_product_name( $product );

						if ( isset( $response_body ) && ! empty( $response_body ) ) {

							foreach ( $response_body->results as $key => $value ) {

								if ( $value->properties->name == $name ) {

									$line_item_hs_id = $value->id;
									wc_update_order_item_meta( $item_key, 'hubwoo_ecomm_line_item_id', $line_item_hs_id );
									$response = HubWooConnectionMananager::get_instance()->associate_object( 'deal', $deal_id, 'line_item', $line_item_hs_id, 19 );
								}
							}
						}
					endforeach;

					if ( 1 == get_option( 'hubwoo_deals_sync_running', 0 ) ) {

						$current_count = get_option( 'hubwoo_deals_current_sync_count', 0 );
						update_option( 'hubwoo_deals_current_sync_count', ++$current_count );
					}
				}
			}

			return $response;
		}
	}


	/**
	 * Start syncing an ecommerce deal
	 *
	 * @since 1.0.0
	 * @param int $order_id - order id.
	 * @return  array sync response from HubSpot.
	 */
	public function hubwoo_ecomm_deals_sync( $order_id ) {

		if ( ! empty( $order_id ) ) {
			$hubwoo_ecomm_order = wc_get_order( $order_id );
			if ( $hubwoo_ecomm_order instanceof WC_Order ) {
				$customer_id = $hubwoo_ecomm_order->get_customer_id();

				if ( ! empty( $customer_id ) ) {
					$source = 'user';
					self::hubwoo_ecomm_contacts_with_id( $customer_id );
				} else {
					$source = 'guest';
					self::hubwoo_ecomm_guest_user( $order_id );
				}

				$response = self::hubwoo_ecomm_sync_deal( $order_id, $source, $customer_id );
				update_option( 'hubwoo_last_sync_date', time() );
				return $response;
			}
		}
	}
	/**
	 * Create a formatted name of the product.
	 *
	 * @since 1.0.0
	 * @param int $product product object.
	 * @return string formatted name of the product.
	 */
	public static function hubwoo_ecomm_product_name( $product ) {

		if ( $product->get_sku() ) {
			$identifier = $product->get_sku();
		} else {
			$identifier = '#' . $product->get_id();
		}
		return sprintf( '%2$s (%1$s)', $identifier, $product->get_name() );
	}


	/**
	 * Return formatted time for HubSpot
	 *
	 * @param  int $unix_timestamp current timestamp.
	 * @return string formatted time.
	 * @since 1.0.0
	 */
	public static function hubwoo_set_utc_midnight( $unix_timestamp ) {

		$string       = gmdate( 'Y-m-d H:i:s', $unix_timestamp );
		$date         = new DateTime( $string );
		$wp_time_zone = get_option( 'timezone_string', '' );
		if ( empty( $wp_time_zone ) ) {
			$wp_time_zone = 'UTC';
		}
		$time_zone = new DateTimeZone( $wp_time_zone );
		$date->setTimezone( $time_zone );
		return $date->getTimestamp() * 1000; // in miliseconds.
	}
}
