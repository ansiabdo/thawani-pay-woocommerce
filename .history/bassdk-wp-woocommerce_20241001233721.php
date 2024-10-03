<?php
/*
Plugin Name: BASSuperApp SDK v0.1
Description: BAS SuperApp SDK for WooCommerce
Version: 0.1.0
Author: Abdullah AlAnsi
Tags: BAS SuperApp WooCommerce SDK for Login and payment, online payment, woocommerce,payment gateway
Text Domain: woocommerce-extension
Requires at least: 4.0.0
Tested up to: 5.8.0
Requires PHP: 5.6
Stable tag: 5.6.2
WC requires at least: 4.0.0
WC tested up to: 5.5.2
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

defined('ABSPATH') or wp_die('No script kiddies please!');

/**
 * Initialize the plugin and its modules.
 */
function init()
{
  if (! function_exists('is_plugin_active')) {
    require_once ABSPATH . '/wp-admin/includes/plugin.php';
  }
  if (! is_plugin_active('woocommerce/woocommerce.php')) {
    add_action(
      'admin_notices',
      function () {
        echo '<div class="error"><p><strong>' . sprintf(esc_html__('BASSuperApp SDK v0.1 requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-paypal-payments'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
      }
    );

    return;
  }
  if (version_compare(PHP_VERSION, '7.1', '<')) {
    add_action(
      'admin_notices',
      function () {
        echo '<div class="error"><p>' . esc_html__('BASSuperApp SDK v0.1 requires PHP 7.1 or above.', 'woocommerce-paypal-payments'), '</p></div>';
      }
    );

    return;
  }
}

add_action(
  'plugins_loaded',
  function () {
    init();
  }
);

/**
 * Check if WooCommerce is active
 **/
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

  function plugin_scripts()
  {
    $plugin_url = plugin_dir_url(__FILE__);
    if (isset($_GET['page']) && $_GET['page'] == 'bas-sdk-v1') {
      // Enqueue Core Admin Styles
      wp_enqueue_style('style',  $plugin_url . "/css/style.css");
    }
  }

  add_action('admin_print_styles', 'plugin_scripts');

  add_filter('woocommerce_payment_gateways', 'thawani_add_gateway_class');
  function thawani_add_gateway_class($gateways)
  {
    $gateways[] = 'WC_Thawani_Gateway';
    return $gateways;
  }

  add_action('plugins_loaded', 'thawani_init_gateway_class');
  function thawani_init_gateway_class()
  {

    class WC_Thawani_Gateway extends WC_Payment_Gateway
    {
      public function __construct()
      {
        $plugin_dir = plugin_dir_url(__FILE__);
        $this->id = 'thawani';
        $this->icon = "";
        //un-comment below line to use payment icon behind the payment title on the checkout page
        // $this->icon = $plugin_dir . "/img/logo.png";
        $this->has_fields = false;
        $this->method_title = 'BasSDK';
        $this->method_description = 'Accepts payments with the <b>BASSuperApp SDK v0.1</b> Gateway for WooCommerce <img src="https://i.postimg.cc/tJcqVHGC/Thawani-logo.png" width="100px" />';
        $this->supports = array('products');
        $this->init_form_fields();
        $this->init_settings();
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->secret_key = $this->get_option('secret_key');
        $this->publishable_key = $this->get_option('publishable_key');
        $this->cancel_url = $this->get_option('cancel_url');
        $this->success_url = $this->get_option('success_url');
        $this->client_prefix = $this->get_option('client_prefix');
        $this->environment = $this->get_option('environment');
        if ($this->get_option('environment') == 'yes') {
          //UAT
          $this->posturl = 'https://uatcheckout.thawani.om';
          $this->paymentmode = 0;
        } else {
          //production
          $this->posturl = 'https://checkout.thawani.om';
          $this->paymentmode = 1;
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      }

      //init plugin required fields
      public function init_form_fields()
      {
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        $domain_parts = explode('.', $domain);
        $website_name = $domain_parts[0];
        $success_url_api = wc_get_checkout_url() . "?success";
        $cancel_url_api = wc_get_checkout_url() . "?cancel";
        $admin_email = get_option('admin_email');
        $this->form_fields = array(
          'enabled' => array(
            'title'       => 'Enable/Disable',
            'label'       => 'Enable Thawani Pay payments',
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'no',
            'desc_tip'    => true
          ),
          'title' => array(
            'title'       => 'Title',
            'type'        => 'text',
            'description' => 'Controls the name of this payment method as displayed to the customer during checkout.',
            'default'     => 'Thawani Pay',
            'desc_tip'    => true
          ),
          'description' => array(
            'title'       => 'Description',
            'type'        => 'textarea',
            'description' => 'This controls the description which the user sees during checkout.',
            'default'     => 'Payment Methods Accepted: VisaCard, Credit Card/Debit Card'
          ),
          'secret_key' => array(
            'title'       => 'Secret Key',
            'type'        => 'password',
            'description'       => 'Enter your Thawani Secret Key',
            'desc_tip'    => true
          ),
          'publishable_key' => array(
            'title'       => 'Publishable key',
            'type'        => 'password',
            'description'       => 'Enter your Thawani Publishable key',
            'desc_tip'    => true
          ),
          'cancel_url' => array(
            'title'       => 'Cancel url',
            'type'       => 'select',
            'options'     => $this->thawani_get_pages('Select Cancel Page'),
            'description'       => 'Select Thawani cancel page. Default url <span style="background: #e2e2e2;padding: 1.5px;">' . $cancel_url_api . '</span>',
            'desc_tip'    => false
          ),
          'success_url' => array(
            'title'       => 'Success url',
            'type'       => 'select',
            'options'     => $this->thawani_get_pages('Select Success Page'),
            'description'       => 'Select Thawani success page. Default url <span style="background: #e2e2e2;padding: 1.5px;">' . $success_url_api . '</span>',
            'desc_tip'    => false
          ),
          'client_prefix' => array(
            'title'       => 'Client Reference Prefix',
            'type'        => 'text',
            'default'     => $website_name,
            'description'       => 'Plugin will use this prefix with order id <strong>[prefix][orderid]</strong> as client_reference_id . ex: mahmoud123321',
            'desc_tip'    => false
          ),
          'success_status' => array(
            'title'       => 'Order status on payment Success',
            'type'       => 'select',
            'options'     => array(
              'Processing' => 'Processing',
              'On hold' => 'On hold',
              'Processing' => 'Processing',
              'Completed' => 'Completed',
              'Cancelled' => 'Cancelled',
              'Failed' => 'Failed',
              'Pending payment' => 'Pending payment'
            ),
            'description'       => 'Select order status to be used on payment success. default status <code>Processing</code>',
            'desc_tip'    => false
          ),
          'Cancel_status' => array(
            'title'       => 'Cancel status',
            'type'       => 'select',
            'options'     => array(
              'Pending payment' => 'Pending payment',
              'Processing' => 'Processing',
              'On hold' => 'On hold',
              'Processing' => 'Processing',
              'Completed' => 'Completed',
              'Cancelled' => 'Cancelled',
              'Failed' => 'Failed'
            ),
            'description'       => 'Select order status to be used on payment cancel. default status <code>Pending payment</code>',
            'desc_tip'    => false
          ),
          'environment' => array(
            'title' => 'Environment',
            'label'    => 'Enable UAT Mode',
            'type' => 'checkbox',
            'description' => 'If Test mode is enabled you should use the UAT Secret and Publishable Keys available on <a href="https://developer.thawani.om/#product1category1" target="_blank">Thawani Checkout API Documentation</a>',
            'desc_tip'    => false,
            'default'  => 'no',
          ),
          'developer_log' => array(
            'title'       => 'Error Notification',
            'type'        => 'text',
            'default'     => $admin_email,
            'description' => 'Enter your email to receive notification when something happens with the payment api. This is more for developer to track Thawani Api response when the payment failed like <code>Unauthorized</code> requests.',
            'desc_tip'    => false
          ),
        );
      }

      //Process Thawani Payment
      public function process_payment($order_id)
      {
        global $woocommerce;
        $order = wc_get_order($order_id);

        //access Thawani settings
        $secret_key  = $this->get_option('secret_key');
        $publishable_key  = $this->get_option('publishable_key');
        $cancel_url = get_permalink($this->success_url) . '?mode=' . $this->paymentmode . '&oid=' . $order_id . '&status=cancel';
        $success_url = get_permalink($this->success_url) . '?mode=' . $this->paymentmode . '&oid=' . $order_id . '&status=success';
        $client_prefix = $this->get_option('client_prefix');
        $client_reference_id = $client_prefix . '' . $order_id;
        $thawani_api = $this->posturl;
        $developer_email  = $this->get_option('developer_log');

        //access customer order data
        $amount = $order->get_total();
        $order_items = $order->get_items();
        $shipping_total = $order->get_shipping_total();
        $discount_total = $order->get_discount_total();
        $products_list = array();

        // Get and Loop Over Order Items and add it as array to be used on "products": [] with the Thawani checkout api
        foreach ($order_items as $item_id => $item) {
          $product_id = $item->get_product_id();
          $product_name = $item->get_name();
          $quantity = $item->get_quantity();
          $subtotal = $item->get_subtotal();
          $total_price = $item->get_total();
          $price_excl_tax = $total_price / $quantity;
          // $subtotal_tax = $item->get_subtotal_tax(); // Line subtotal tax
          // $price_incl_tax = ( $subtotal + $subtotal_tax ) / $quantity;

          array_push($products_list, array('name' => substr($product_name, 0, 39), 'unit_amount' => $price_excl_tax * 1000, 'quantity' => $quantity));
        }

        //check for shipping cost to be added as product item on Thawani checkout api.
        if ($shipping_total != 0) {
          array_push($products_list, array('name' => 'Shipping Fee', 'unit_amount' => $shipping_total * 1000, 'quantity' => 1));
        }

        $billing_email = $order->get_billing_email();
        $billing_phone = $order->get_billing_phone();
        $billing_email = preg_replace("/[+]/", "00", $billing_email);
        $fname = $order->get_billing_first_name();
        $lname = $order->get_billing_last_name();
        $full_name = $fname . ' ' . $lname;
        $user_id = $order->get_user_id();
        $products = [array()];

        $payment_json = array(
          'client_reference_id' => $client_reference_id,
          'products' => $products_list,
          'success_url' => $success_url,
          'cancel_url' => $cancel_url,
          'metadata' =>
          array(
            'customer_name' => $full_name,
            'customer_id' => $user_id,
            'order_id' => $order_id,
            'customer_email' => $billing_email,
            'customer_phone' => $billing_phone
          ),
        );

        $data = json_encode($payment_json);

        try {
          //Send Post request to get payment session details
          $curl = curl_init();
          curl_setopt_array($curl, [
            CURLOPT_URL => $thawani_api . '/api/v1/checkout/session',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
              "Content-Type: application/json",
              "Thawani-Api-Key: " . $secret_key
            ],
          ]);
          $result = curl_exec($curl);
          $err = curl_error($curl);
          curl_close($curl);
          if ($err) {
            wc_add_notice("Error: " . $err, 'error');
            //Send email to admin with error message
            if (empty($developer_email)) {
              wc_mail(get_option('admin_email'), "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />" . $err, $headers = "Content-Type: text/htmlrn", $attachments = "");
            } else {
              wc_mail($developer_email, "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />" . $err, $headers = "Content-Type: text/htmlrn", $attachments = "");
            }
          } else {
            $response = json_decode($result, true);
            $code = $response['code'];
            if ($code == 2004 || $code == '2004') {
              $session_id = $response['data']['session_id'];
              $invoice_id = $response['data']['invoice'];
              update_post_meta($order_id, 'session_id', $session_id);
              update_post_meta($order_id, 'invoice_id', $invoice_id);

              $redirect_url = $thawani_api . "/pay/" . $session_id . '?key=' . $publishable_key;
              return array(
                'result'   => 'success',
                'redirect' => $redirect_url
              );
            } else {
              wc_add_notice($code . ": " . $response['description'], 'error');
              if (empty($developer_email)) {
                wc_mail(get_option('admin_email'), "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />" . $result, $headers = "Content-Type: text/htmlrn", $attachments = "");
              } else {
                wc_mail($developer_email, "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />" . $result, $headers = "Content-Type: text/htmlrn", $attachments = "");
              }
              return;
            }
          }
        } catch (\Throwable $th) {
          if (empty($developer_email)) {
            wc_mail(get_option('admin_email'), "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />" . $th, $headers = "Content-Type: text/htmlrn", $attachments = "");
          } else {
            wc_mail($developer_email, "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />" . $th, $headers = "Content-Type: text/htmlrn", $attachments = "");
          }
          throw $th;
        }
      }

      // get website all pages
      function thawani_get_pages($title = false, $indent = true)
      {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
          $prefix = '';
          // show indented child pages?
          if ($indent) {
            $has_parent = $page->post_parent;
            while ($has_parent) {
              $prefix .=  ' - ';
              $next_page = get_post($has_parent);
              $has_parent = $next_page->post_parent;
            }
          }
          // add to page list array array
          $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
      }
    }
  }

  //Payment callback to check transaction status
  add_action('init', 'woocommerce_process_thawani_payment');
  function woocommerce_process_thawani_payment()
  {
    global $woocommerce;
    //check for paramter passed on the url. This will access the success and cancel payment callback
    if (isset($_GET['mode']) && isset($_GET['oid']) && isset($_GET['status'])) {
      $mode = $_GET['mode'];
      $order_id = $_GET['oid'];
      $order_status = $_GET['status'];
      $session_id = get_post_meta($order_id, "session_id", true);
      $options = get_option('woocommerce_thawani_settings');
      $secret_key = str_replace('"', '', json_encode($options['secret_key']));
      $developer_email = str_replace('"', '', json_encode($options['developer_log']));

      $order = wc_get_order($order_id);

      if ($mode == 0) {
        //UAT
        $posturl = 'https://uatcheckout.thawani.om/api/v1/checkout/session/' . $session_id;
      } else {
        //production
        $posturl = 'https://checkout.thawani.om/api/v1/checkout/session/' . $session_id;
      }

      if (empty($secret_key)) {
        wc_add_notice("Error: No key found", 'error');
        //Send email to admin with error message
        if (empty($developer_email)) {
          wc_mail(get_option('admin_email'), "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />Secret Key not found on the Thawany Payment settings, please make sure to add your Thawani api secret key.", $headers = "Content-Type: text/htmlrn", $attachments = "");
        } else {
          wc_mail($developer_email, "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />Secret Key not found on the Thawany Payment settings, please make sure to add your Thawani api secret key.", $headers = "Content-Type: text/htmlrn", $attachments = "");
        }
      } else {
        //Send GET request to get payment session details
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $posturl,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_HTTPHEADER => array(
            'Thawani-Api-Key: ' . $secret_key
          ),
        ));

        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
          wc_add_notice("Error: " . $err, 'error');
          //Send email to admin with error message
          if (empty($developer_email)) {
            wc_mail(get_option('admin_email'), "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />" . $err, $headers = "Content-Type: text/htmlrn", $attachments = "");
          } else {
            wc_mail($developer_email, "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />" . $err, $headers = "Content-Type: text/htmlrn", $attachments = "");
          }
        } else {
          $response = json_decode($result, true);
          $code = $response['code'];
          $success = $response['success'];
          $description = $response['description'];
          //Check if call success
          if ($code == 2000 && $success == true) {
            $payment_status = $response['data']['payment_status'];
            //Check payment status
            if ($payment_status == 'paid') {
              $order->payment_complete();
              $order->add_order_note('Thawani payment successful.');
              $woocommerce->cart->empty_cart();
              wc_add_notice(__('Thank you for shopping with us.', 'woothemes') . "order placed successfully", 'success');
            } else if ($payment_status == 'unpaid') {
              $order->add_order_note('The Thawani transaction has been declined.');
              wc_add_notice(__('Thank you for shopping with us.', 'woothemes') . "However, the transaction has been declined.", 'error');
            } else {
              wc_add_notice(__('Thank you for shopping with us.', 'woothemes') . "However, the transaction has been declined.", 'error');
            }
          } else {
            //Return error if payment request not success and notify admin via email
            wc_add_notice($code . ": " . $response['description'], 'error');
            if (empty($developer_email)) {
              wc_mail(get_option('admin_email'), "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />" . $result, $headers = "Content-Type: text/htmlrn", $attachments = "");
            } else {
              wc_mail($developer_email, "[ERROR] Thawani Pay - Order #" . $order_id, "Howdy!<br />We caught an error with payment requests.<br /><br />Error Details<br />===========<br />" . $result, $headers = "Content-Type: text/htmlrn", $attachments = "");
            }
          }
        }
      }
    }
  }


  //welcome page
  register_activation_hook(__FILE__, 'thawani_welcome_activate');
  function thawani_welcome_activate()
  {
    set_transient('_thawani_welcome_activation_redirect', true, 30);
  }

  add_action('admin_init', 'thawani_welcome_do_activation_redirect');
  function thawani_welcome_do_activation_redirect()
  {
    // Bail if no activation redirect
    if (! get_transient('_thawani_welcome_activation_redirect')) {
      return;
    }

    // Delete the redirect transient
    delete_transient('_thawani_welcome_activation_redirect');

    // Bail if activating from network, or bulk
    if (is_network_admin() || isset($_GET['activate-multi'])) {
      return;
    }

    // Redirect to plugin about page
    wp_safe_redirect(add_query_arg(array('page' => 'bas-sdk-v1'), admin_url('index.php')));
  }

  add_action('admin_menu', 'thawani_welcome_pages');

  function thawani_welcome_pages()
  {
    add_menu_page(
      'Thawani v2',
      'Thawani v2',
      'read',
      'bas-sdk-v1',
      'thawani_welcome_content',
      plugins_url('thawani-pay-woocommerce/img/thawani-logo.svg'),
      100
    );
  }


  add_action('admin_menu', 'wpdocs_register_my_custom_submenu_page');

  function wpdocs_register_my_custom_submenu_page()
  {
    add_submenu_page(
      'bas-sdk-v1',
      'payment-settings',
      'Settings',
      'manage_options',
      'payment-settings',
      'thawani_payment_settings_callback'
    );
  }

  function thawani_payment_settings_callback()
  {
    wp_redirect(home_url() . "/wp-admin/admin.php?page=wc-settings&tab=checkout&section=thawani");
    exit;
  }



  function thawani_welcome_content()
  {
?>
    <div class="wrap">

      <section class="jumbotron text-center bg-light">
        <div class="container">
          <svg width="280" class="animated" id="freepik_stories-payment-information" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.com/svgjs">
            <style>
              svg#freepik_stories-payment-information:not(.animated) .animable {
                opacity: 0;
              }

              svg#freepik_stories-payment-information.animated #freepik--Floor--inject-95 {
                animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideUp;
                animation-delay: 0s;
              }

              svg#freepik_stories-payment-information.animated #freepik--Shadows--inject-95 {
                animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideDown;
                animation-delay: 0s;
              }

              svg#freepik_stories-payment-information.animated #freepik--Pictures--inject-95 {
                animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideUp;
                animation-delay: 0s;
              }

              svg#freepik_stories-payment-information.animated #freepik--Table--inject-95 {
                animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideDown;
                animation-delay: 0s;
              }

              svg#freepik_stories-payment-information.animated #freepik--Plant--inject-95 {
                animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideLeft;
                animation-delay: 0s;
              }

              svg#freepik_stories-payment-information.animated #freepik--Device--inject-95 {
                animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideRight;
                animation-delay: 0s;
              }

              svg#freepik_stories-payment-information.animated #freepik--window-2--inject-95 {
                animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideDown;
                animation-delay: 0s;
              }

              svg#freepik_stories-payment-information.animated #freepik--window-1--inject-95 {
                animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) zoomIn;
                animation-delay: 0s;
              }

              svg#freepik_stories-payment-information.animated #freepik--Puff--inject-95 {
                animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) zoomIn;
                animation-delay: 0s;
              }

              svg#freepik_stories-payment-information.animated #freepik--Character--inject-95 {
                animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) fadeIn;
                animation-delay: 0s;
              }

              @keyframes slideUp {
                0% {
                  opacity: 0;
                  transform: translateY(30px);
                }

                100% {
                  opacity: 1;
                  transform: inherit;
                }
              }

              @keyframes slideDown {
                0% {
                  opacity: 0;
                  transform: translateY(-30px);
                }

                100% {
                  opacity: 1;
                  transform: translateY(0);
                }
              }

              @keyframes slideLeft {
                0% {
                  opacity: 0;
                  transform: translateX(-30px);
                }

                100% {
                  opacity: 1;
                  transform: translateX(0);
                }
              }

              @keyframes slideRight {
                0% {
                  opacity: 0;
                  transform: translateX(30px);
                }

                100% {
                  opacity: 1;
                  transform: translateX(0);
                }
              }

              @keyframes zoomIn {
                0% {
                  opacity: 0;
                  transform: scale(0.5);
                }

                100% {
                  opacity: 1;
                  transform: scale(1);
                }
              }

              @keyframes fadeIn {
                0% {
                  opacity: 0;
                }

                100% {
                  opacity: 1;
                }
              }
            </style>
            <g id="freepik--Floor--inject-95" class="animable" style="transform-origin: 250px 342.311px;">
              <path id="freepik--floor--inject-95" d="M77.67,441.82c-95.18-54.95-95.18-144,0-199s249.48-55,344.66,0,95.18,144,0,199S172.85,496.77,77.67,441.82Z" style="fill: rgb(245, 245, 245); transform-origin: 250px 342.311px;" class="animable"></path>
            </g>
            <g id="freepik--Shadows--inject-95" class="animable" style="transform-origin: 256.153px 322.233px;">
              <path id="freepik--Shadow--inject-95" d="M70.7,359.43c10.72,6.19,10.72,16.23,0,22.42s-28.11,6.19-38.84,0-10.72-16.23,0-22.42S60,353.23,70.7,359.43Z" style="fill: rgb(224, 224, 224); transform-origin: 51.2781px 370.638px;" class="animable"></path>
              <path id="freepik--shadow--inject-95" d="M194.32,390.86l6.29,3.63a7.92,7.92,0,0,0,7.19,0l118.38-68.1c2-1.14,2-3,0-4.15l-6.29-3.63a7.92,7.92,0,0,0-7.19,0l-118.38,68.1C192.33,387.86,192.33,389.71,194.32,390.86Z" style="fill: rgb(224, 224, 224); transform-origin: 260.254px 356.55px;" class="animable"></path>
              <path id="freepik--shadow--inject-95" d="M318.62,251.15c-15.54,9-15.54,23.51,0,32.48s40.72,9,56.26,0,15.53-23.51,0-32.48S334.15,242.18,318.62,251.15Z" style="fill: rgb(230, 230, 230); transform-origin: 346.748px 267.396px;" class="animable"></path>
              <path id="freepik--shadow--inject-95" d="M66.19,388.68l6.29,3.64a8,8,0,0,0,7.19,0l138.73-80.1c2-1.14,2-3,0-4.15l-6.3-3.63a7.9,7.9,0,0,0-7.18,0L66.19,384.53C64.2,385.68,64.2,387.54,66.19,388.68Z" style="fill: rgb(224, 224, 224); transform-origin: 142.299px 348.375px;" class="animable"></path>
              <path id="freepik--shadow--inject-95" d="M105,395.55l6.3,3.63a7.9,7.9,0,0,0,7.18,0l118.39-68.1c2-1.14,2-3,0-4.15l-6.3-3.63a7.9,7.9,0,0,0-7.18,0L105,391.4C103,392.55,103,394.4,105,395.55Z" style="fill: rgb(224, 224, 224); transform-origin: 170.935px 361.24px;" class="animable"></path>
              <ellipse id="freepik--shadow--inject-95" cx="405.84" cy="339.69" rx="82.65" ry="47.72" style="fill: rgb(230, 230, 230); transform-origin: 405.84px 339.69px;" class="animable"></ellipse>
            </g>
            <g id="freepik--Pictures--inject-95" class="animable" style="transform-origin: 414.47px 103.505px;">
              <g id="freepik--Picture--inject-95" class="animable" style="transform-origin: 414.47px 76.395px;">
                <g id="freepik--Frame--inject-95" class="animable" style="transform-origin: 414.47px 76.395px;">
                  <polygon points="432.33 103.69 430.67 104.68 396.58 85 398.24 84.01 432.33 103.69" style="fill: rgb(250, 250, 250); transform-origin: 414.455px 94.345px;" id="elhmcqbpcp0lo" class="animable"></polygon>
                  <polygon points="398.24 84.01 430.67 102.73 430.67 68.77 398.24 50.06 398.24 84.01" style="fill: rgb(255, 255, 255); transform-origin: 414.455px 76.395px;" id="el9bt4aad1rf8" class="animable"></polygon>
                  <path d="M393.44,86.81l40.37,23.3V67L393.44,43.65Zm3.14-37.73,34.09,19.69v35.91L396.58,85Z" style="fill: rgb(240, 240, 240); transform-origin: 413.625px 76.88px;" id="elb6mxfm4pk19" class="animable"></path>
                  <polygon points="396.58 85 398.24 84.01 398.24 50.06 396.58 49.08 396.58 85" style="fill: rgb(224, 224, 224); transform-origin: 397.41px 67.04px;" id="eln8bsm6492yn" class="animable"></polygon>
                  <polygon points="393.44 43.65 395.13 42.68 435.5 65.99 433.81 66.95 393.44 43.65" style="fill: rgb(250, 250, 250); transform-origin: 414.47px 54.815px;" id="el2yf8k7zkkc7" class="animable"></polygon>
                  <polygon points="435.5 65.99 433.81 66.95 433.81 110.11 435.5 109.15 435.5 65.99" style="fill: rgb(224, 224, 224); transform-origin: 434.655px 88.05px;" id="el9z0grsv11pm" class="animable"></polygon>
                </g>
                <g id="freepik--Land--inject-95" class="animable" style="transform-origin: 414.46px 82.045px;">
                  <polygon points="399.51 82.99 429.41 100.26 429.41 91.62 421.93 74.36 417.27 78.68 418.95 85.91 408.62 63.83 399.51 74.36 399.51 82.99" style="fill: rgb(240, 240, 240); transform-origin: 414.46px 82.045px;" id="elgzxs4d8j5pl" class="animable"></polygon>
                  <path d="M417.56,66a4.88,4.88,0,0,1,2.21,3.83c0,1.41-1,2-2.21,1.27a4.87,4.87,0,0,1-2.22-3.83C415.34,65.89,416.33,65.32,417.56,66Z" style="fill: rgb(240, 240, 240); transform-origin: 417.555px 68.5636px;" id="el1d62ij1jnu6" class="animable"></path>
                </g>
              </g>
              <g id="freepik--picture--inject-95" class="animable" style="transform-origin: 414.47px 130.615px;">
                <g id="freepik--frame--inject-95" class="animable" style="transform-origin: 414.47px 130.615px;">
                  <polygon points="432.33 157.91 430.67 158.9 396.58 139.22 398.24 138.23 432.33 157.91" style="fill: rgb(250, 250, 250); transform-origin: 414.455px 148.565px;" id="el5ttsdnz0ce6" class="animable"></polygon>
                  <polygon points="398.24 138.23 430.67 156.95 430.67 122.98 398.24 104.28 398.24 138.23" style="fill: rgb(255, 255, 255); transform-origin: 414.455px 130.615px;" id="eleczt5sy3c5n" class="animable"></polygon>
                  <path d="M393.44,141l40.37,23.3V121.18L393.44,97.87Zm3.14-37.73L430.67,123V158.9l-34.09-19.68Z" style="fill: rgb(240, 240, 240); transform-origin: 413.625px 131.085px;" id="elp72evs0adj" class="animable"></path>
                  <polygon points="396.58 139.22 398.24 138.23 398.24 104.28 396.58 103.3 396.58 139.22" style="fill: rgb(224, 224, 224); transform-origin: 397.41px 121.26px;" id="eljbstqrwu8br" class="animable"></polygon>
                  <polygon points="393.44 97.87 395.13 96.9 435.5 120.21 433.81 121.17 393.44 97.87" style="fill: rgb(250, 250, 250); transform-origin: 414.47px 109.035px;" id="elvnfhibdo7r" class="animable"></polygon>
                  <polygon points="435.5 120.21 433.81 121.17 433.81 164.33 435.5 163.37 435.5 120.21" style="fill: rgb(224, 224, 224); transform-origin: 434.655px 142.27px;" id="el4voeg9em7w4" class="animable"></polygon>
                </g>
                <g id="freepik--land--inject-95" class="animable" style="transform-origin: 414.46px 136.265px;">
                  <polygon points="399.51 137.21 429.41 154.48 429.41 145.84 421.93 128.58 417.27 132.9 418.95 140.13 408.62 118.05 399.51 128.58 399.51 137.21" style="fill: rgb(240, 240, 240); transform-origin: 414.46px 136.265px;" id="ele65q8tczjw7" class="animable"></polygon>
                  <path d="M417.56,120.25a4.88,4.88,0,0,1,2.21,3.83c0,1.41-1,2-2.21,1.27a4.87,4.87,0,0,1-2.22-3.83C415.34,120.11,416.33,119.54,417.56,120.25Z" style="fill: rgb(240, 240, 240); transform-origin: 417.555px 122.804px;" id="eltwp7rarystn" class="animable"></path>
                </g>
              </g>
            </g>
            <g id="freepik--Table--inject-95" class="animable" style="transform-origin: 346.805px 227.307px;">
              <g id="freepik--table--inject-95" class="animable" style="transform-origin: 346.805px 227.307px;">
                <g id="freepik--table--inject-95" class="animable" style="transform-origin: 346.805px 233.434px;">
                  <path d="M318.56,223.54a1,1,0,0,1,.6-.83,3.18,3.18,0,0,1,2.88,0,1,1,0,0,1,.59.83l-7.24,42.77h0a.75.75,0,0,1-.42.58,2.29,2.29,0,0,1-2,0,.75.75,0,0,1-.42-.58h0Z" style="fill: rgb(69, 90, 100); transform-origin: 317.59px 244.743px;" id="elfzxr7rle3pd" class="animable"></path>
                  <path d="M374.44,223.54a1,1,0,0,0-.6-.83,3.18,3.18,0,0,0-2.88,0,1,1,0,0,0-.59.83l7.24,42.77h0a.75.75,0,0,0,.42.58,2.29,2.29,0,0,0,2,0,.75.75,0,0,0,.42-.58h0Z" style="fill: rgb(69, 90, 100); transform-origin: 375.41px 244.743px;" id="elfkbklhnuxe7" class="animable"></path>
                  <path d="M348.78,226.78a1,1,0,0,0-.59-.83,3.18,3.18,0,0,0-2.88,0,1,1,0,0,0-.6.83l.6,50.89h0a.75.75,0,0,0,.42.57,2.27,2.27,0,0,0,2,0,.71.71,0,0,0,.42-.57h0Z" style="fill: rgb(69, 90, 100); transform-origin: 346.745px 252.039px;" id="elpqnx9hftwk" class="animable"></path>
                  <path d="M383.22,209.45h0c0-5.39-3.52-10.78-10.65-14.89-14.24-8.22-37.33-8.22-51.57,0-7.12,4.11-10.65,9.5-10.65,14.89h0v2.89h0c.08,5.32,3.65,10.62,10.68,14.68,14.24,8.22,37.33,8.22,51.57,0,7-4.06,10.6-9.36,10.69-14.68h0Z" style="fill: rgb(146, 227, 169); transform-origin: 346.82px 210.79px;" id="elsyu0r7klwia" class="animable"></path>
                  <g id="elmvlgatlzbzp">
                    <path d="M383.22,209.45h0c0-5.39-3.52-10.78-10.65-14.89-14.24-8.22-37.33-8.22-51.57,0-7.12,4.11-10.65,9.5-10.65,14.89h0v2.89h0c.08,5.32,3.65,10.62,10.68,14.68,14.24,8.22,37.33,8.22,51.57,0,7-4.06,10.6-9.36,10.69-14.68h0Z" style="fill: rgb(255, 255, 255); opacity: 0.4; transform-origin: 346.82px 210.79px;" class="animable"></path>
                  </g>
                  <path d="M321,194.56c-14.24,8.22-14.24,21.56,0,29.78s37.33,8.22,51.57,0,14.25-21.56,0-29.78S335.2,186.34,321,194.56Z" style="fill: rgb(146, 227, 169); transform-origin: 346.787px 209.45px;" id="elk7oh1fpnlh" class="animable"></path>
                  <g id="elur2r8lhpexd">
                    <path d="M321,194.56c-14.24,8.22-14.24,21.56,0,29.78s37.33,8.22,51.57,0,14.25-21.56,0-29.78S335.2,186.34,321,194.56Z" style="fill: rgb(255, 255, 255); opacity: 0.6; transform-origin: 346.787px 209.45px;" class="animable"></path>
                  </g>
                </g>
                <g id="freepik--Pot--inject-95" class="animable" style="transform-origin: 346.668px 195.049px;">
                  <g id="elxyx2awvdv9">
                    <g style="opacity: 0.05; transform-origin: 346.508px 207.238px;" class="animable">
                      <path d="M354.74,202.49c4.54,2.63,4.54,6.88,0,9.5s-11.91,2.62-16.46,0-4.54-6.87,0-9.5S350.19,199.87,354.74,202.49Z" id="el8p4qj4g6l9y" class="animable" style="transform-origin: 346.508px 207.238px;"></path>
                    </g>
                  </g>
                  <path d="M339.27,207.83c-3.95-4-6.28-14.12-2.42-17.32h19.64c3.86,3.2,1.53,13.27-2.42,17.31l-.11.12-.13.13-.36.32-.1.07-.29.23a4,4,0,0,1-.52.32,13,13,0,0,1-11.78,0h0a4.13,4.13,0,0,1-.52-.33,2.78,2.78,0,0,1-.27-.21l-.12-.09c-.12-.1-.24-.2-.35-.31l-.15-.15Z" style="fill: rgb(235, 235, 235); transform-origin: 346.67px 200.465px;" id="elh332fg3ct8" class="animable"></path>
                  <path d="M354.78,189.22c4.48,2.59,4.48,6.78,0,9.37s-11.74,2.58-16.22,0-4.48-6.78,0-9.37S350.3,186.63,354.78,189.22Z" style="fill: rgb(250, 250, 250); transform-origin: 346.67px 193.903px;" id="el79uznule40l" class="animable"></path>
                  <path d="M351.61,191.05c2.72,1.58,2.72,4.13,0,5.7a10.87,10.87,0,0,1-9.87,0c-2.73-1.57-2.73-4.12,0-5.7A10.93,10.93,0,0,1,351.61,191.05Z" style="fill: rgb(235, 235, 235); transform-origin: 346.671px 193.904px;" id="elmrzxzygqtwb" class="animable"></path>
                  <path d="M341.74,193.76a10.87,10.87,0,0,1,9.87,0,4.58,4.58,0,0,1,1.63,1.49,4.6,4.6,0,0,1-1.63,1.5,10.87,10.87,0,0,1-9.87,0,4.56,4.56,0,0,1-1.64-1.5A4.54,4.54,0,0,1,341.74,193.76Z" style="fill: rgb(224, 224, 224); transform-origin: 346.67px 195.255px;" id="elhmt83xmr2vb" class="animable"></path>
                  <path d="M351,195.25a22.47,22.47,0,0,0,3.57-4.74c1.64-2.92,1.95-6.82,1.1-7.3s-4.33,3.41-4.33,3.41a21.34,21.34,0,0,0,.37-5c-.15-2.59-.78-4.95-1.52-5.43s-2.41,2.8-3.12,5.17-1.14,4.33-1.14,4.33a37,37,0,0,0-1.28-5.46c-.87-2.47-1.65-3-2.26-2.78s-1,1.39-1.19,4.67a33.07,33.07,0,0,0,.15,4.7s-1.23-1.34-2.58-2.65-2.13-1.63-2.56-1.35-.54,2.5,1.77,6.68a23.07,23.07,0,0,0,4.36,5.73S345.54,197.32,351,195.25Z" style="fill: rgb(146, 227, 169); transform-origin: 346.047px 186.154px;" id="el5iphnjkkzmb" class="animable"></path>
                </g>
              </g>
            </g>
            <g id="freepik--Plant--inject-95" class="animable" style="transform-origin: 47.334px 325.539px;">
              <g id="freepik--pot--inject-95" class="animable" style="transform-origin: 47.334px 325.539px;">
                <g id="freepik--pot--inject-95" class="animable" style="transform-origin: 51.2738px 346.141px;">
                  <g id="freepik--pot--inject-95" class="animable" style="transform-origin: 51.2738px 346.141px;">
                    <path d="M67.37,372.86c8.6-8.88,13.66-45,5.26-52.07H29.92c-8.4,7-3.35,43.18,5.26,52.06a2.73,2.73,0,0,0,.23.25l.3.29c.24.24.5.47.78.7a1.56,1.56,0,0,0,.2.16c.21.17.42.34.65.5s.74.51,1.11.72c7.08,4.13,18.56,4.13,25.64,0h0a11.86,11.86,0,0,0,1.12-.73c.21-.15.4-.3.6-.46l.25-.2c.27-.22.52-.45.75-.67l.35-.34Z" style="fill: rgb(55, 71, 79); transform-origin: 51.2738px 349.679px;" id="ela7rw01idnhq" class="animable"></path>
                    <path d="M33.63,318c-9.75,5.69-9.75,14.92,0,20.61s25.55,5.69,35.29,0,9.75-14.92,0-20.61S43.37,312.26,33.63,318Z" style="fill: rgb(69, 90, 100); transform-origin: 51.2731px 328.296px;" id="elvxwuyxamq3" class="animable"></path>
                    <path d="M38.31,320.69c-7.15,4.18-7.15,10.95,0,15.13s18.77,4.18,25.92,0,7.16-10.95,0-15.13S45.47,316.51,38.31,320.69Z" style="fill: rgb(38, 50, 56); transform-origin: 51.2719px 328.255px;" id="ellj1t5ho2fte" class="animable"></path>
                    <path d="M64.23,327.87c-7.15-4.18-18.76-4.18-25.92,0a12,12,0,0,0-4.28,4,11.93,11.93,0,0,0,4.28,4c7.16,4.18,18.77,4.18,25.92,0a11.89,11.89,0,0,0,4.29-4A11.91,11.91,0,0,0,64.23,327.87Z" style="fill: rgb(250, 250, 250); transform-origin: 51.275px 331.87px;" id="elndy4gywd8j" class="animable"></path>
                  </g>
                </g>
                <g id="freepik--Plants--inject-95" class="animable" style="transform-origin: 47.334px 303.4px;">
                  <g id="freepik--plants--inject-95" class="animable" style="transform-origin: 38.4513px 301.629px;">
                    <path d="M48.08,330.37c-.3.11-2.56.35-2.87.37-3.59.15-8-1.63-11.94-3.37-2.6-1.15-6.11-2.72-7.78-5.41a4.38,4.38,0,0,1-.38-4.39c.83-1.31,2.71-1.68,3.08-3.26-2.76-1.83-6.3-1.93-8.65-4.4a13.64,13.64,0,0,1-3.23-6.81c-.32-1.51-.32-3.56.47-4.73.93-1.38,2.85-1.26,4.48-1.09a2.72,2.72,0,0,0,1.27-.07,1.07,1.07,0,0,0,.7-1,1.92,1.92,0,0,0-1-1.33c-1-.65-2.09-1.17-3.07-1.89a6.1,6.1,0,0,1-2.31-2.87,8,8,0,0,1,.11-4.37,63.88,63.88,0,0,1,2-7.05,52.28,52.28,0,0,0,1.43-6.19c5.71.15,11.59,2.25,16.91,5,3,1.52,7.19,4.28,8.05,8.12a7.32,7.32,0,0,1-.1,3.23,12.84,12.84,0,0,1-.5,1.35c-.11.28-.54,1.36.19,1a1.09,1.09,0,0,0,.38-.39l2.83-4.11a2.42,2.42,0,0,1,1-1c1-.35,3.2.73,5.35,3.5a12,12,0,0,1,2.2,6.61c.22,3.71-.84,7.22-1.89,10.62l2.55-2.61c3-3.06,3.53,4.13,3.42,5.63-.28,3.93-1.85,9.72-3.83,12.84C54.64,325.9,51.93,328.93,48.08,330.37Z" style="fill: rgb(146, 227, 169); transform-origin: 38.4513px 301.629px;" id="elvcmgin3um4" class="animable"></path>
                    <g id="el0yuk1di3bhe">
                      <path d="M48.08,330.37c-.3.11-2.56.35-2.87.37-3.59.15-8-1.63-11.94-3.37-2.6-1.15-6.11-2.72-7.78-5.41a4.38,4.38,0,0,1-.38-4.39c.83-1.31,2.71-1.68,3.08-3.26-2.76-1.83-6.3-1.93-8.65-4.4a13.64,13.64,0,0,1-3.23-6.81c-.32-1.51-.32-3.56.47-4.73.93-1.38,2.85-1.26,4.48-1.09a2.72,2.72,0,0,0,1.27-.07,1.07,1.07,0,0,0,.7-1,1.92,1.92,0,0,0-1-1.33c-1-.65-2.09-1.17-3.07-1.89a6.1,6.1,0,0,1-2.31-2.87,8,8,0,0,1,.11-4.37,63.88,63.88,0,0,1,2-7.05,52.28,52.28,0,0,0,1.43-6.19c5.71.15,11.59,2.25,16.91,5,3,1.52,7.19,4.28,8.05,8.12a7.32,7.32,0,0,1-.1,3.23,12.84,12.84,0,0,1-.5,1.35c-.11.28-.54,1.36.19,1a1.09,1.09,0,0,0,.38-.39l2.83-4.11a2.42,2.42,0,0,1,1-1c1-.35,3.2.73,5.35,3.5a12,12,0,0,1,2.2,6.61c.22,3.71-.84,7.22-1.89,10.62l2.55-2.61c3-3.06,3.53,4.13,3.42,5.63-.28,3.93-1.85,9.72-3.83,12.84C54.64,325.9,51.93,328.93,48.08,330.37Z" style="fill: rgb(255, 255, 255); opacity: 0.3; transform-origin: 38.4513px 301.629px;" class="animable"></path>
                    </g>
                    <path d="M44,311.11a39.79,39.79,0,0,1,6.59-16.06l.07-.11a.51.51,0,0,0-.12-.66.38.38,0,0,0-.59.07l-.07.11a41.21,41.21,0,0,0-6.45,15c-5-14-13.1-28.32-17.83-32.75a.42.42,0,0,0-.63,0,.55.55,0,0,0,.08.72c4.66,4.37,12.7,18.47,17.57,32.32A25.63,25.63,0,0,0,26,304.05a.42.42,0,0,0-.34.51A.53.53,0,0,0,26,305a.52.52,0,0,0,.21,0c.09,0,8.71-.73,17,6.33,2.2,6.57,3.63,13,3.58,18.21A.59.59,0,0,0,47,330a.68.68,0,0,0,.2.07.44.44,0,0,0,.46-.46C47.72,324.33,46.26,317.79,44,311.11Z" style="fill: rgb(255, 255, 255); transform-origin: 37.8017px 303.319px;" id="elqd02v011j0q" class="animable"></path>
                  </g>
                  <g id="freepik--plants--inject-95" class="animable" style="transform-origin: 58.4979px 311.498px;">
                    <path d="M50,333.7c.26.11,2.29.47,2.57.51,3.22.39,7.15-.73,10.68-1.86,2.33-.74,5.47-1.76,7-3.82a3.2,3.2,0,0,0,.31-3.54c-.75-1.13-2.44-1.56-2.78-2.88,2.46-1.28,5.64-1.1,7.73-2.93a9.58,9.58,0,0,0,2.86-5.29,5.19,5.19,0,0,0-.45-3.88c-.84-1.19-2.56-1.24-4-1.22a2.92,2.92,0,0,1-1.14-.15,1,1,0,0,1-.63-.88c0-.48.48-.79.9-1,.91-.45,1.87-.8,2.75-1.31a4.57,4.57,0,0,0,2.05-2.16,6,6,0,0,0-.13-3.56,50.36,50.36,0,0,0-1.81-5.87,40.31,40.31,0,0,1-1.32-5.14,37,37,0,0,0-15.13,2.79c-2.66,1-6.44,2.94-7.18,6a5.45,5.45,0,0,0,.11,2.63,9.22,9.22,0,0,0,.46,1.13c.09.24.49,1.15-.17.81a1.17,1.17,0,0,1-.35-.35l-2.55-3.55a2.48,2.48,0,0,0-.93-.89c-.86-.36-2.87.35-4.78,2.45A8.45,8.45,0,0,0,42,304.94c-.17,3,.8,5.93,1.76,8.77l-2.3-2.31c-2.69-2.7-3.14,3.1-3,4.32A26.67,26.67,0,0,0,42,326.44C44.06,329.57,46.51,332.24,50,333.7Z" style="fill: rgb(146, 227, 169); transform-origin: 58.4979px 311.498px;" id="elg3vr6wqfroq" class="animable"></path>
                    <g id="elmectca5xweg">
                      <path d="M50,333.7c.26.11,2.29.47,2.57.51,3.22.39,7.15-.73,10.68-1.86,2.33-.74,5.47-1.76,7-3.82a3.2,3.2,0,0,0,.31-3.54c-.75-1.13-2.44-1.56-2.78-2.88,2.46-1.28,5.64-1.1,7.73-2.93a9.58,9.58,0,0,0,2.86-5.29,5.19,5.19,0,0,0-.45-3.88c-.84-1.19-2.56-1.24-4-1.22a2.92,2.92,0,0,1-1.14-.15,1,1,0,0,1-.63-.88c0-.48.48-.79.9-1,.91-.45,1.87-.8,2.75-1.31a4.57,4.57,0,0,0,2.05-2.16,6,6,0,0,0-.13-3.56,50.36,50.36,0,0,0-1.81-5.87,40.31,40.31,0,0,1-1.32-5.14,37,37,0,0,0-15.13,2.79c-2.66,1-6.44,2.94-7.18,6a5.45,5.45,0,0,0,.11,2.63,9.22,9.22,0,0,0,.46,1.13c.09.24.49,1.15-.17.81a1.17,1.17,0,0,1-.35-.35l-2.55-3.55a2.48,2.48,0,0,0-.93-.89c-.86-.36-2.87.35-4.78,2.45A8.45,8.45,0,0,0,42,304.94c-.17,3,.8,5.93,1.76,8.77l-2.3-2.31c-2.69-2.7-3.14,3.1-3,4.32A26.67,26.67,0,0,0,42,326.44C44.06,329.57,46.51,332.24,50,333.7Z" style="fill: rgb(255, 255, 255); opacity: 0.5; transform-origin: 58.4979px 311.498px;" class="animable"></path>
                    </g>
                    <path d="M53.5,318.34a33.93,33.93,0,0,0-6-13.53l-.07-.1a.38.38,0,0,1,.63-.43l.07.1A35.49,35.49,0,0,1,54,317c4.36-11,11.58-22,15.79-25.29a.41.41,0,0,1,.57.07.41.41,0,0,1-.07.58c-4.15,3.2-11.28,14.07-15.57,25a23.47,23.47,0,0,1,14.86-3.43.38.38,0,0,1,.06.74.4.4,0,0,1-.19,0,22.49,22.49,0,0,0-15.19,3.89c-1.94,5.17-3.18,10.29-3.11,14.52a.43.43,0,0,1-.22.38.58.58,0,0,1-.18,0,.4.4,0,0,1-.41-.4C50.26,328.81,51.53,323.6,53.5,318.34Z" style="fill: rgb(255, 255, 255); transform-origin: 58.9069px 312.546px;" id="elsswqpevs4fd" class="animable"></path>
                  </g>
                </g>
              </g>
            </g>
            <g id="freepik--Device--inject-95" class="animable" style="transform-origin: 138.705px 204.694px;">
              <g id="freepik--Mobile--inject-95" class="animable" style="transform-origin: 138.705px 204.694px;">
                <path d="M191.27,25.77,74.77,93a11.72,11.72,0,0,0-5.86,9.72V373.64c0,4,2.46,8.63,5.49,10.38s8.29,1.56,11.74-.43l116.11-67a13.86,13.86,0,0,0,6.24-10.82v-270c0-4-2.46-8.63-5.49-10.38S194.72,23.77,191.27,25.77Z" style="fill: rgb(146, 227, 169); transform-origin: 138.7px 204.692px;" id="el8ibwuxr74z4" class="animable"></path>
                <g id="eltbxaatk8tmd">
                  <path d="M191.27,25.77,74.77,93a11.72,11.72,0,0,0-5.86,9.72V373.64c0,4,2.46,8.63,5.49,10.38s8.29,1.56,11.74-.43l116.11-67a13.86,13.86,0,0,0,6.24-10.82v-270c0-4-2.46-8.63-5.49-10.38S194.72,23.77,191.27,25.77Z" style="opacity: 0.05; transform-origin: 138.7px 204.692px;" class="animable"></path>
                </g>
                <path d="M84.65,384.3c-3.34,1.3-7.62,1.24-10.24-.28-3-1.75-5.49-6.4-5.49-10.39V102.76a11.06,11.06,0,0,1,1.58-5.36l10.08,5.72a12.21,12.21,0,0,0-2.91,7.28V378.7C77.66,383.5,80.69,385.77,84.65,384.3Z" style="fill: rgb(146, 227, 169); transform-origin: 76.785px 241.311px;" id="el013d6l1dhvkm" class="animable"></path>
                <g id="el8j0v64imi6o">
                  <path d="M84.65,384.3c-3.34,1.3-7.62,1.24-10.24-.28-3-1.75-5.49-6.4-5.49-10.39V102.76a11.06,11.06,0,0,1,1.58-5.36l10.08,5.72a12.21,12.21,0,0,0-2.91,7.28V378.7C77.66,383.5,80.69,385.77,84.65,384.3Z" style="opacity: 0.3; transform-origin: 76.785px 241.311px;" class="animable"></path>
                </g>
                <path d="M83.17,100.86a12.23,12.23,0,0,0-5.51,9.54V378.71c0,5.38,3.81,7.58,8.47,4.89l116.13-67.05a13.86,13.86,0,0,0,6.24-10.82v-270c0-4-2.81-5.58-6.25-3.6Z" style="fill: rgb(146, 227, 169); transform-origin: 143.08px 208.013px;" id="ell9bd7uggk5" class="animable"></path>
                <g id="elrpjcexv1pwm">
                  <path d="M83.17,100.86a12.23,12.23,0,0,0-5.51,9.54V378.71c0,5.38,3.81,7.58,8.47,4.89l116.13-67.05a13.86,13.86,0,0,0,6.24-10.82v-270c0-4-2.81-5.58-6.25-3.6Z" style="opacity: 0.15; transform-origin: 143.08px 208.013px;" class="animable"></path>
                </g>
                <path d="M87.45,117a13.11,13.11,0,0,0-5.94,10.28V368.13c0,3.78,2.66,5.32,5.94,3.43l109.69-63.34a13.19,13.19,0,0,0,5.94-10.28V57.1c0-3.77-2.67-5.32-5.93-3.43Z" style="fill: rgb(38, 50, 56); transform-origin: 142.295px 212.616px;" id="elo6v3kc7nue" class="animable"></path>
                <path d="M194,291.35V55.48L87.45,117a13.11,13.11,0,0,0-5.94,10.28V363.15l106.55-61.52A13.16,13.16,0,0,0,194,291.35Z" style="fill: rgb(55, 71, 79); transform-origin: 137.755px 209.315px;" id="eldnig9idbzha" class="animable"></path>
                <path d="M133.26,79.56l18.07-10.43c1.08-.63,1.95-.18,1.95,1a4.2,4.2,0,0,1-1.95,3.25L133.26,83.79c-1.08.62-1.95.18-1.95-1A4.17,4.17,0,0,1,133.26,79.56Z" style="fill: rgb(38, 50, 56); transform-origin: 142.295px 76.4578px;" id="eliwqgb3jslzg" class="animable"></path>
                <path d="M164.26,61.63c1-.58,1.83-.11,1.83,1.06a4,4,0,0,1-1.83,3.17c-1,.58-1.83.11-1.83-1.06A4.07,4.07,0,0,1,164.26,61.63Z" style="fill: rgb(38, 50, 56); transform-origin: 164.26px 63.745px;" id="elx6zrrjj8bt" class="animable"></path>
                <path d="M171.59,57.41c1-.59,1.83-.11,1.83,1.05a4,4,0,0,1-1.83,3.17c-1,.59-1.83.11-1.83-1.05A4,4,0,0,1,171.59,57.41Z" style="fill: rgb(38, 50, 56); transform-origin: 171.59px 59.52px;" id="el68uq4nsz08a" class="animable"></path>
                <path d="M113,91.24c1-.59,1.83-.11,1.83,1.05A4.07,4.07,0,0,1,113,95.47c-1,.58-1.83.11-1.83-1.06A4,4,0,0,1,113,91.24Z" style="fill: rgb(38, 50, 56); transform-origin: 113px 93.3527px;" id="el5fae1ezlhwx" class="animable"></path>
                <path d="M120.32,87c1-.58,1.83-.11,1.83,1.06a4,4,0,0,1-1.83,3.17c-1,.58-1.83.11-1.83-1.06A4,4,0,0,1,120.32,87Z" style="fill: rgb(38, 50, 56); transform-origin: 120.32px 89.115px;" id="elw215v3k7ev" class="animable"></path>
              </g>
            </g>
            <g id="freepik--window-2--inject-95" class="animable" style="transform-origin: 165px 221.648px;">
              <g id="freepik--Window--inject-95" class="animable" style="transform-origin: 165px 221.648px;">
                <g id="freepik--window--inject-95" class="animable" style="transform-origin: 165px 221.648px;">
                  <path d="M216.91,62.56,106.57,126.18a5.5,5.5,0,0,0-2.49,4.3v245.1a5.48,5.48,0,0,0,2.49,4.29l1.55.89a5.56,5.56,0,0,0,5,0L223.44,317a5.51,5.51,0,0,0,2.48-4.31v-245a5.44,5.44,0,0,0-2.49-4.29l-1.55-.89A5.51,5.51,0,0,0,216.91,62.56Z" style="fill: rgb(224, 224, 224); transform-origin: 165px 221.648px;" id="eljoayt68tcw9" class="animable"></path>
                  <path d="M225.92,312.9c0,2.19-1.53,3.58-3.43,4.67L114.05,380.19c-1.89,1.09-3.44.21-3.44-2V135.29h0a6.86,6.86,0,0,1,3.42-5.92L222.49,66.73a2.28,2.28,0,0,1,3.43,2h0Z" style="fill: rgb(250, 250, 250); transform-origin: 168.265px 223.538px;" id="elvzfqzx5vki" class="animable"></path>
                  <path d="M223.63,67.37h0A1.34,1.34,0,0,1,225,68.71V312.9c0,1.48-.91,2.67-3,3.84L113.57,379.36a2.25,2.25,0,0,1-1,.34c-.83,0-1-.94-1-1.5V135.29a5.91,5.91,0,0,1,2.94-5.09L223,67.55a1.35,1.35,0,0,1,.67-.18m0-1a2.2,2.2,0,0,0-1.14.31L114,129.37a6.86,6.86,0,0,0-3.42,5.92V378.2c0,1.56.78,2.45,1.91,2.45a3.17,3.17,0,0,0,1.53-.46l108.44-62.62c1.9-1.09,3.43-2.48,3.43-4.67V68.71a2.28,2.28,0,0,0-2.29-2.29Z" style="fill: rgb(224, 224, 224); transform-origin: 168.235px 223.51px;" id="el7b8unycj7z" class="animable"></path>
                  <path d="M225.92,67.73v1a2.29,2.29,0,0,0-3.44-2L114,129.38a6.61,6.61,0,0,0-2.49,2.49.3.3,0,0,0,0,.08l-6.72-3.87a5.14,5.14,0,0,1,1.79-1.9L216.91,62.56a5.48,5.48,0,0,1,5,0l1.55.89A5.43,5.43,0,0,1,225.92,67.73Z" style="fill: rgb(240, 240, 240); transform-origin: 165.355px 96.9533px;" id="elf3db7eo9oou" class="animable"></path>
                  <path d="M131.8,132.84s0-.08,0-.16v-.8l-1.25.72c-.07,0-.1,0-.1-.11v-.29a.76.76,0,0,1,0-.16s0-.09,0-.15l1.33-3.63a1.13,1.13,0,0,1,.07-.15s0-.05.08-.07l.18-.1s.09,0,.11,0a.26.26,0,0,1,0,.18v2.79l.27-.16c.06,0,.09,0,.11,0a.25.25,0,0,1,0,.17v.19a.41.41,0,0,1,0,.21.25.25,0,0,1-.11.12l-.27.16v.8a.4.4,0,0,1,0,.2.25.25,0,0,1-.11.12l-.21.12C131.85,132.86,131.82,132.87,131.8,132.84Zm0-1.69v-2l-.9,2.52Z" style="fill: rgb(69, 90, 100); transform-origin: 131.53px 130.399px;" id="elpyie6cit6d" class="animable"></path>
                  <path d="M133.28,131.5a2.68,2.68,0,0,1-.37-1.64,5.29,5.29,0,0,1,.37-2.05,2.53,2.53,0,0,1,1-1.25,1.3,1.3,0,0,1,.5-.19.55.55,0,0,1,.39,0,.1.1,0,0,1,0,.07.44.44,0,0,1,0,.1l-.11.41a.28.28,0,0,1-.05.1h0a.74.74,0,0,0-.64.1,1.76,1.76,0,0,0-.71.86,4,4,0,0,0-.26,1.53,2.14,2.14,0,0,0,.25,1.23c.17.2.4.22.69.05a1.91,1.91,0,0,0,.3-.22,1.54,1.54,0,0,0,.25-.27v-1.3l-.52.3c-.06,0-.09,0-.11,0s0-.08,0-.17V129a.41.41,0,0,1,0-.21.25.25,0,0,1,.11-.12l.83-.48c.06,0,.1,0,.12,0a.31.31,0,0,1,0,.2v1.86a.71.71,0,0,1,0,.16.43.43,0,0,1-.05.13,2.63,2.63,0,0,1-1,1C133.87,131.8,133.53,131.79,133.28,131.5Z" style="fill: rgb(69, 90, 100); transform-origin: 134.117px 129.03px;" id="elycb7kz75vkc" class="animable"></path>
                  <path d="M143.83,125.11a1.91,1.91,0,0,1-.72,1.54c-.4.23-.73-.08-.73-.7a1.92,1.92,0,0,1,.73-1.54C143.51,124.18,143.83,124.49,143.83,125.11Z" style="fill: rgb(69, 90, 100); transform-origin: 143.105px 125.53px;" id="elaj3uk0x5d8k" class="animable"></path>
                  <path d="M143.11,122.58a4.36,4.36,0,0,0-1.69,2.54.49.49,0,0,0,.07.51h0c.13.08.34-.09.42-.38a3,3,0,0,1,1.19-1.79.77.77,0,0,1,1.19.42c.08.19.28.13.42-.11h0a.75.75,0,0,0,.08-.59A1.09,1.09,0,0,0,143.11,122.58Z" style="fill: rgb(69, 90, 100); transform-origin: 143.098px 124.015px;" id="el92ndgypcm6s" class="animable"></path>
                  <path d="M143.11,120.79a6.82,6.82,0,0,0-2.6,3.69c-.08.24,0,.49.06.56h0c.13.09.32-.08.41-.35a5.54,5.54,0,0,1,2.12-3,1.4,1.4,0,0,1,2.12.57c.09.17.28.11.41-.12h0a.72.72,0,0,0,.06-.63A1.72,1.72,0,0,0,143.11,120.79Z" style="fill: rgb(69, 90, 100); transform-origin: 143.106px 122.767px;" id="elzmkudidlgdr" class="animable"></path>
                  <path d="M167.72,109a.06.06,0,0,1,.09,0,.16.16,0,0,1,0,.11v.73a.47.47,0,0,1,0,.16.26.26,0,0,1-.09.11l-.45.26s-.07,0-.09,0a.16.16,0,0,1,0-.12v-.73a.32.32,0,0,1,0-.16.2.2,0,0,1,.09-.11Zm0,2.59a.06.06,0,0,1,.09,0,.16.16,0,0,1,0,.11v.73a.47.47,0,0,1,0,.16.26.26,0,0,1-.09.11l-.45.26s-.07,0-.09,0a.16.16,0,0,1,0-.12v-.73a.32.32,0,0,1,0-.16.2.2,0,0,1,.09-.11Z" style="fill: rgb(69, 90, 100); transform-origin: 167.494px 110.97px;" id="eldn7s73l7thp" class="animable"></path>
                  <path d="M170.28,111.26a.07.07,0,0,1-.09,0,.21.21,0,0,1,0-.12v-.9l-1.49.86a.08.08,0,0,1-.09,0,.21.21,0,0,1,0-.12v-.53a.69.69,0,0,1,0-.2c0-.06.05-.13.08-.21l1.47-3.67a.57.57,0,0,1,.2-.3l.36-.21s.07,0,.09,0a.16.16,0,0,1,0,.12v3l.42-.24a.08.08,0,0,1,.09,0,.17.17,0,0,1,0,.12v.52a.51.51,0,0,1,0,.17.35.35,0,0,1-.09.11l-.42.24v.89a.35.35,0,0,1,0,.17.2.2,0,0,1-.09.11Zm-.13-1.94v-1.59l-.82,2.06Z" style="fill: rgb(69, 90, 100); transform-origin: 169.966px 108.568px;" id="els73za5r6u3" class="animable"></path>
                  <path d="M165.35,114.09s-.07,0-.09,0a.16.16,0,0,1,0-.12v-.9l-1.49.86s-.07,0-.09,0a.16.16,0,0,1,0-.12v-.53a.69.69,0,0,1,0-.2c0-.06,0-.13.07-.21l1.47-3.67a.64.64,0,0,1,.2-.3l.37-.21a.08.08,0,0,1,.09,0,.21.21,0,0,1,0,.12v3l.41-.24a.07.07,0,0,1,.09,0,.15.15,0,0,1,0,.12v.52a.35.35,0,0,1,0,.17.25.25,0,0,1-.09.11l-.41.24v.89a.53.53,0,0,1,0,.17.35.35,0,0,1-.09.11Zm-.13-1.94v-1.59l-.82,2.06Z" style="fill: rgb(69, 90, 100); transform-origin: 165.03px 111.383px;" id="elu3e94oredoe" class="animable"></path>
                  <path d="M172.21,105.17a.53.53,0,0,1,0-.17.35.35,0,0,1,.09-.11l1.81-1s.07,0,.09,0a.16.16,0,0,1,0,.12v.53a.32.32,0,0,1,0,.16.17.17,0,0,1-.09.11l-1.35.78-.05,1a1,1,0,0,1,.26-.24l.2-.14a1.45,1.45,0,0,1,.5-.18.59.59,0,0,1,.4.1.78.78,0,0,1,.27.39,2.09,2.09,0,0,1,.09.67,3.88,3.88,0,0,1-.09.87,3.49,3.49,0,0,1-.27.71,2.32,2.32,0,0,1-.41.55,2,2,0,0,1-.49.38,1.34,1.34,0,0,1-.57.21.51.51,0,0,1-.39-.09.73.73,0,0,1-.23-.33,1.31,1.31,0,0,1-.09-.5.34.34,0,0,1,0-.17.36.36,0,0,1,.1-.11l.39-.22a.06.06,0,0,1,.08,0s0,0,.05.1a.38.38,0,0,0,.1.22.24.24,0,0,0,.14.09.39.39,0,0,0,.18,0l.21-.1a1.19,1.19,0,0,0,.45-.49,1.55,1.55,0,0,0,.16-.72.55.55,0,0,0-.16-.46.41.41,0,0,0-.45,0,1,1,0,0,0-.22.16.94.94,0,0,0-.14.16l-.1.13a.36.36,0,0,1-.11.1l-.44.25s-.07,0-.09,0a.16.16,0,0,1,0-.12Z" style="fill: rgb(69, 90, 100); transform-origin: 173.155px 106.904px;" id="elqmk7a87dtna" class="animable"></path>
                  <polygon points="117.97 141.42 117.97 139.88 119.3 139.11 119.3 140.65 117.97 141.42" style="fill: rgb(69, 90, 100); transform-origin: 118.635px 140.265px;" id="elgtv719ad1t4" class="animable"></polygon>
                  <polygon points="119.97 140.26 119.97 137.18 121.31 136.41 121.31 139.49 119.97 140.26" style="fill: rgb(69, 90, 100); transform-origin: 120.64px 138.335px;" id="elpnampcqvxz" class="animable"></polygon>
                  <polygon points="121.97 139.11 121.97 134.48 123.31 133.71 123.31 138.34 121.97 139.11" style="fill: rgb(69, 90, 100); transform-origin: 122.64px 136.41px;" id="elft45mpsj2a7" class="animable"></polygon>
                  <polygon points="123.98 137.95 123.98 131.78 125.31 131.01 125.31 137.18 123.98 137.95" style="fill: rgb(69, 90, 100); transform-origin: 124.645px 134.48px;" id="elms8jn4lg47" class="animable"></polygon>
                  <path d="M217.81,78.77a.36.36,0,0,0-.5-.16l-5,2.87a1.9,1.9,0,0,0-.57,1.51V85.8a1.63,1.63,0,0,0,.21.89.54.54,0,0,0,.21.19.31.31,0,0,0,.29,0l5-2.87a1.92,1.92,0,0,0,.58-1.51V79.66A1.62,1.62,0,0,0,217.81,78.77Zm-5.71,7.56a.79.79,0,0,1-.08-.41v-2.8a1.51,1.51,0,0,1,.44-1.16l5-2.87a.19.19,0,0,1,.16,0,.17.17,0,0,1,.06.06.79.79,0,0,1,.08.41v2.8a1.51,1.51,0,0,1-.44,1.16l-5,2.87A.15.15,0,0,1,212.1,86.33Z" style="fill: rgb(69, 90, 100); transform-origin: 214.884px 82.7403px;" id="elhk7nuydcxhj" class="animable"></path>
                  <path d="M213.41,81.9v3.32l-.79.45a.1.1,0,0,1-.15-.09V83.12a1.11,1.11,0,0,1,.33-.86Z" style="fill: rgb(69, 90, 100); transform-origin: 212.939px 83.7917px;" id="elnkqpq3rd9j" class="animable"></path>
                  <polygon points="213.77 81.7 214.71 81.16 214.71 84.47 213.77 85.01 213.77 81.7" style="fill: rgb(69, 90, 100); transform-origin: 214.24px 83.085px;" id="el7fzdwlk8d9o" class="animable"></polygon>
                  <polygon points="215.07 80.95 216.01 80.41 216.01 83.74 215.07 84.27 215.07 80.95" style="fill: rgb(69, 90, 100); transform-origin: 215.54px 82.34px;" id="elwroho1e43vj" class="animable"></polygon>
                  <path d="M217.31,79.88v2.39a1.26,1.26,0,0,1-.62,1.08l-.32.18V80.21l.75-.43A.13.13,0,0,1,217.31,79.88Z" style="fill: rgb(69, 90, 100); transform-origin: 216.84px 81.6474px;" id="el7vnhloucfph" class="animable"></path>
                  <path d="M218.21,79.57c.14-.08.25.32.25.9V81a2.07,2.07,0,0,1-.25,1.21l-.25.17V79.72Z" style="fill: rgb(69, 90, 100); transform-origin: 218.215px 80.9699px;" id="elfuhgmc6t9vs" class="animable"></path>
                  <polygon points="204.17 88.05 209.02 83.75 207.53 90.06 206.82 87.43 204.17 88.05" style="fill: rgb(69, 90, 100); transform-origin: 206.595px 86.905px;" id="el472n3f1qy8l" class="animable"></polygon>
                </g>
                <path d="M127.64,288.16l81.24-46.9a4.29,4.29,0,0,0,2-3.37V106.46c0-1.24-.87-1.75-2-1.13l-81.24,46.8a4.32,4.32,0,0,0-2,3.38V287C125.69,288.28,126.57,288.79,127.64,288.16Z" style="fill: rgb(146, 227, 169); transform-origin: 168.26px 196.748px;" id="elb70b46vdqen" class="animable"></path>
                <g id="elmdt6mxae2hg">
                  <path d="M127.64,288.16l81.24-46.9a4.29,4.29,0,0,0,2-3.37V106.46c0-1.24-.87-1.75-2-1.13l-81.24,46.8a4.32,4.32,0,0,0-2,3.38V287C125.69,288.28,126.57,288.79,127.64,288.16Z" style="fill: rgb(255, 255, 255); opacity: 0.5; transform-origin: 168.26px 196.748px;" class="animable"></path>
                </g>
                <path d="M125.4,304a7.74,7.74,0,0,1,3.62-6l33.46-19.32c2-1.15,3.61-.33,3.61,1.83a7.76,7.76,0,0,1-3.61,6L129,305.86C127,307,125.4,306.2,125.4,304Z" style="fill: rgb(146, 227, 169); transform-origin: 145.745px 292.268px;" id="elq99m7fufcq9" class="animable"></path>
                <g id="elwvl91p7wudo">
                  <path d="M125.4,304a7.74,7.74,0,0,1,3.62-6l33.46-19.32c2-1.15,3.61-.33,3.61,1.83a7.76,7.76,0,0,1-3.61,6L129,305.86C127,307,125.4,306.2,125.4,304Z" style="fill: rgb(255, 255, 255); opacity: 0.8; transform-origin: 145.745px 292.268px;" class="animable"></path>
                </g>
                <path d="M173.44,295.82c0,1.08.81,1.49,1.81.92l3.17-1.83a3.88,3.88,0,0,0,1.81-3c0-1.08-.81-1.49-1.81-.92l-3.17,1.83A3.88,3.88,0,0,0,173.44,295.82Z" style="fill: rgb(55, 71, 79); transform-origin: 176.835px 293.865px;" id="elonqv1gv1xig" class="animable"></path>
                <path d="M183.62,290c0,1.08.81,1.49,1.8.91l3.17-1.82a3.88,3.88,0,0,0,1.81-3c0-1.08-.81-1.49-1.81-.91L185.42,287A3.89,3.89,0,0,0,183.62,290Z" style="fill: rgb(55, 71, 79); transform-origin: 187.01px 288.045px;" id="elg55wcelus6d" class="animable"></path>
                <path d="M193.79,284.08c0,1.08.81,1.49,1.81.91l3.16-1.83a3.86,3.86,0,0,0,1.81-3c0-1.08-.81-1.49-1.81-.91l-3.16,1.83A3.86,3.86,0,0,0,193.79,284.08Z" style="fill: rgb(55, 71, 79); transform-origin: 197.18px 282.12px;" id="elfppo23418k" class="animable"></path>
                <path d="M204,278.2c0,1.09.81,1.49,1.81.92l3.17-1.83a3.89,3.89,0,0,0,1.8-3c0-1.08-.81-1.49-1.8-.92l-3.17,1.83A3.88,3.88,0,0,0,204,278.2Z" style="fill: rgb(55, 71, 79); transform-origin: 207.39px 276.246px;" id="elppwo07q70gg" class="animable"></path>
                <g id="freepik--add-to-bag-box--inject-95" class="animable" style="transform-origin: 157.595px 323.694px;">
                  <path d="M127.21,339,188,303.87c1-.58,1.81-.11,1.81,1V316.4a4,4,0,0,1-1.81,3.13l-60.81,35.11c-1,.57-1.81.11-1.81-1.05V342.11A4,4,0,0,1,127.21,339Z" style="fill: rgb(146, 227, 169); transform-origin: 157.595px 329.252px;" id="elgykoyvosqav" class="animable"></path>
                  <path d="M130.69,314.85a1.72,1.72,0,0,1,.94-.06,1.39,1.39,0,0,1,.69.36,1.74,1.74,0,0,1,.42.65,2.58,2.58,0,0,1,.16.78.69.69,0,0,1-.08.37.55.55,0,0,1-.22.25l-.77.45c-.12.07-.22.09-.28.05a.44.44,0,0,1-.17-.2.87.87,0,0,0-.5-.44,1.29,1.29,0,0,0-1,.26,3.35,3.35,0,0,0-1,.94,3.14,3.14,0,0,0-.3.61,2,2,0,0,0-.11.64,1,1,0,0,0,.12.56.47.47,0,0,0,.39.2,2.35,2.35,0,0,0,.7-.07l1-.24a3.7,3.7,0,0,1,1.12-.13,1.31,1.31,0,0,1,.75.28,1.41,1.41,0,0,1,.41.72,4.29,4.29,0,0,1,.13,1.17,5.56,5.56,0,0,1-.16,1.33,6.46,6.46,0,0,1-.48,1.31,6.69,6.69,0,0,1-.77,1.21,6.5,6.5,0,0,1-1,1V328a.87.87,0,0,1-.09.37.63.63,0,0,1-.21.26l-.89.51a.16.16,0,0,1-.21,0,.38.38,0,0,1-.09-.28v-1.15a2.42,2.42,0,0,1-.93.14,1.46,1.46,0,0,1-.76-.25A1.55,1.55,0,0,1,127,327a2.82,2.82,0,0,1-.22-1.05.73.73,0,0,1,.09-.38.6.6,0,0,1,.21-.25l.77-.45c.13-.07.22-.09.29-.06a.33.33,0,0,1,.16.21,2.59,2.59,0,0,0,.19.33.52.52,0,0,0,.3.22.81.81,0,0,0,.47,0,2.31,2.31,0,0,0,.7-.29,5.31,5.31,0,0,0,.64-.45,3.58,3.58,0,0,0,.54-.56,3.08,3.08,0,0,0,.37-.66,1.84,1.84,0,0,0,.14-.74.72.72,0,0,0-.15-.52.6.6,0,0,0-.47-.13,3.81,3.81,0,0,0-.78.12c-.3.08-.66.15-1.07.23a3,3,0,0,1-1,.07,1.14,1.14,0,0,1-.68-.29,1.47,1.47,0,0,1-.4-.73,4.6,4.6,0,0,1-.14-1.24,5.29,5.29,0,0,1,.17-1.31,6.76,6.76,0,0,1,.46-1.28,5.89,5.89,0,0,1,.7-1.15,5.68,5.68,0,0,1,.89-.94v-1.16a.84.84,0,0,1,.09-.37.57.57,0,0,1,.21-.26l.89-.51a.16.16,0,0,1,.21,0,.37.37,0,0,1,.09.27Z" style="fill: rgb(55, 71, 79); transform-origin: 129.936px 321.27px;" id="elgacp1bb47pj" class="animable"></path>
                  <path d="M140.36,321.19a.16.16,0,0,1-.21,0,.37.37,0,0,1-.09-.27v-2.06l-3.41,2a.15.15,0,0,1-.21,0,.37.37,0,0,1-.09-.27v-1.21a1.37,1.37,0,0,1,.07-.45l.17-.48L140,310a1.37,1.37,0,0,1,.46-.69l.83-.48a.15.15,0,0,1,.21,0,.34.34,0,0,1,.09.27v6.78l.95-.54a.15.15,0,0,1,.21,0,.37.37,0,0,1,.09.27v1.21a.84.84,0,0,1-.09.37.63.63,0,0,1-.21.26l-.95.54v2.06a.87.87,0,0,1-.09.37.63.63,0,0,1-.21.26Zm-.3-4.46V313.1l-1.88,4.72Z" style="fill: rgb(55, 71, 79); transform-origin: 139.595px 315.008px;" id="elh3f7vcblrof" class="animable"></path>
                  <path d="M147.49,317.08a.17.17,0,0,1-.21,0,.37.37,0,0,1-.09-.27v-2.05l-3.41,2a.15.15,0,0,1-.21,0,.34.34,0,0,1-.09-.27v-1.2a1.43,1.43,0,0,1,.07-.46l.17-.48,3.37-8.43a1.38,1.38,0,0,1,.46-.68l.83-.49a.17.17,0,0,1,.21,0,.37.37,0,0,1,.09.27v6.79l.95-.55a.16.16,0,0,1,.21,0,.38.38,0,0,1,.09.28v1.2a.84.84,0,0,1-.09.37.57.57,0,0,1-.21.26l-.95.55v2.05a.84.84,0,0,1-.09.37.57.57,0,0,1-.21.26Zm-.3-4.47V309l-1.88,4.71Z" style="fill: rgb(55, 71, 79); transform-origin: 146.705px 310.915px;" id="elmfqzmliwvt" class="animable"></path>
                  <path d="M151.89,314.08a.66.66,0,0,1,0-.15,1,1,0,0,1,0-.1l1.46-4.05a2.32,2.32,0,0,1-1.15.36,1.36,1.36,0,0,1-.91-.29,2,2,0,0,1-.61-.93,4.31,4.31,0,0,1-.23-1.54,6.93,6.93,0,0,1,.23-1.82,7.14,7.14,0,0,1,.62-1.64,6.35,6.35,0,0,1,.93-1.37,4.9,4.9,0,0,1,1.16-1,2.72,2.72,0,0,1,1.16-.38,1.36,1.36,0,0,1,.93.29,1.92,1.92,0,0,1,.63.93,4.59,4.59,0,0,1,.22,1.55,6,6,0,0,1-.07,1,9.18,9.18,0,0,1-.19.91c-.07.29-.16.58-.25.86s-.18.54-.28.8l-2.07,5.75a2.93,2.93,0,0,1-.12.28.49.49,0,0,1-.22.25l-1,.57c-.09.05-.16.05-.21,0A.37.37,0,0,1,151.89,314.08Zm.11-7.56c0,.63.13,1,.41,1.21a.93.93,0,0,0,1-.1,2.77,2.77,0,0,0,1-1.1,3.48,3.48,0,0,0,.41-1.68,1.43,1.43,0,0,0-.41-1.22,1,1,0,0,0-1,.08,2.81,2.81,0,0,0-1,1.12A3.38,3.38,0,0,0,152,306.52Z" style="fill: rgb(55, 71, 79); transform-origin: 153.39px 307.782px;" id="ellmlw0ha4n0k" class="animable"></path>
                  <path d="M158.11,308.36c.09,0,.15,0,.21,0a.34.34,0,0,1,.09.27v1.66a.81.81,0,0,1-.09.37.48.48,0,0,1-.21.26l-1,.59a.15.15,0,0,1-.21,0,.37.37,0,0,1-.09-.27v-1.67a.87.87,0,0,1,.09-.37.63.63,0,0,1,.21-.26Z" style="fill: rgb(55, 71, 79); transform-origin: 157.611px 309.956px;" id="elwwuo46rddm" class="animable"></path>
                  <path d="M160.32,309.21a.61.61,0,0,1,0-.14s0-.08,0-.11l1.46-4a2.45,2.45,0,0,1-1.15.37,1.42,1.42,0,0,1-.91-.3,2,2,0,0,1-.61-.92,4.37,4.37,0,0,1-.23-1.55,6.86,6.86,0,0,1,.23-1.81,7.4,7.4,0,0,1,.62-1.65,6.35,6.35,0,0,1,.93-1.37,4.84,4.84,0,0,1,1.16-.95,2.47,2.47,0,0,1,1.16-.38,1.36,1.36,0,0,1,.93.28,1.92,1.92,0,0,1,.63.93,4.69,4.69,0,0,1,.22,1.56,6,6,0,0,1-.07.95,8.12,8.12,0,0,1-.19.91c-.07.29-.16.58-.25.86s-.18.55-.28.81L162,308.39c0,.08-.07.18-.12.29a.59.59,0,0,1-.22.25l-1,.57a.15.15,0,0,1-.2,0A.34.34,0,0,1,160.32,309.21Zm.11-7.56c0,.63.13,1,.4,1.21a1,1,0,0,0,1-.09,2.87,2.87,0,0,0,1-1.11,3.48,3.48,0,0,0,.41-1.68,1.41,1.41,0,0,0-.41-1.21.93.93,0,0,0-1,.08,2.8,2.8,0,0,0-1,1.11A3.54,3.54,0,0,0,160.43,301.65Z" style="fill: rgb(55, 71, 79); transform-origin: 161.82px 302.967px;" id="eltrssele42k" class="animable"></path>
                  <path d="M167,305.37a.61.61,0,0,1,0-.15.3.3,0,0,1,0-.1l1.45-4.05a2.29,2.29,0,0,1-1.14.36,1.39,1.39,0,0,1-.92-.29,2,2,0,0,1-.61-.93,4.54,4.54,0,0,1-.23-1.54,7.38,7.38,0,0,1,.23-1.82,7.63,7.63,0,0,1,.62-1.64,6.77,6.77,0,0,1,.94-1.37,4.62,4.62,0,0,1,1.16-.95,2.44,2.44,0,0,1,1.15-.38,1.33,1.33,0,0,1,.94.28,1.91,1.91,0,0,1,.62.93,4.57,4.57,0,0,1,.23,1.55,6,6,0,0,1-.07,1,9.18,9.18,0,0,1-.19.91c-.08.29-.16.58-.25.86s-.19.54-.28.8l-2.08,5.75a2.66,2.66,0,0,1-.11.28.51.51,0,0,1-.23.25l-1,.58a.17.17,0,0,1-.21,0A.37.37,0,0,1,167,305.37Zm.11-7.56c0,.63.14,1,.41,1.21a.94.94,0,0,0,1-.1,2.75,2.75,0,0,0,1-1.1,3.48,3.48,0,0,0,.41-1.68c0-.62-.14-1-.41-1.21a.9.9,0,0,0-1,.08,2.73,2.73,0,0,0-1,1.11A3.49,3.49,0,0,0,167.08,297.81Z" style="fill: rgb(55, 71, 79); transform-origin: 168.495px 299.12px;" id="eli6lcdqf0e7n" class="animable"></path>
                  <path d="M140.68,341a.7.7,0,0,1,.09-.3.55.55,0,0,1,.15-.18l.69-.39a.12.12,0,0,1,.16,0,.26.26,0,0,1,.07.22,6.68,6.68,0,0,1-.18,1.17,5.81,5.81,0,0,1-.45,1.22,5.38,5.38,0,0,1-.77,1.15,4.5,4.5,0,0,1-1.13.93,2,2,0,0,1-1,.32,1.06,1.06,0,0,1-.76-.3A2,2,0,0,1,137,344a6,6,0,0,1-.21-1.32c0-.33,0-.69,0-1.09s0-.77,0-1.1a10.06,10.06,0,0,1,.21-1.57,7,7,0,0,1,.49-1.42,5.61,5.61,0,0,1,.76-1.19,4.25,4.25,0,0,1,1-.86,2.28,2.28,0,0,1,1.13-.37,1.21,1.21,0,0,1,.77.25,1.55,1.55,0,0,1,.45.71,3.87,3.87,0,0,1,.18,1,.62.62,0,0,1-.07.29.52.52,0,0,1-.16.19l-.69.4a.15.15,0,0,1-.16,0s-.08-.09-.09-.2a1.5,1.5,0,0,0-.12-.44.72.72,0,0,0-.25-.29.68.68,0,0,0-.41-.09,1.52,1.52,0,0,0-.58.21,2.06,2.06,0,0,0-.57.49,3,3,0,0,0-.41.65,3.76,3.76,0,0,0-.26.78,4.59,4.59,0,0,0-.1.83c0,.33,0,.7,0,1.1s0,.76,0,1.09a3,3,0,0,0,.1.71,1.05,1.05,0,0,0,.26.47.6.6,0,0,0,.41.18,1.1,1.1,0,0,0,.57-.17,2.4,2.4,0,0,0,.58-.46,3.09,3.09,0,0,0,.41-.56,2.79,2.79,0,0,0,.26-.59A3.15,3.15,0,0,0,140.68,341Z" style="fill: rgb(255, 255, 255); transform-origin: 139.316px 340.11px;" id="el3iv3wep0rje" class="animable"></path>
                  <path d="M147.3,339.73a.75.75,0,0,1-.06.29.4.4,0,0,1-.17.19l-.64.38a.12.12,0,0,1-.16,0,.28.28,0,0,1-.07-.2v-3.32a2,2,0,0,0-.23-1.11c-.16-.22-.4-.24-.73-.05a1.73,1.73,0,0,0-.71.88,3.64,3.64,0,0,0-.26,1.4v3.31a.75.75,0,0,1-.06.29.4.4,0,0,1-.17.19l-.64.38a.12.12,0,0,1-.16,0,.28.28,0,0,1-.07-.2v-8.55a.73.73,0,0,1,.07-.29.48.48,0,0,1,.16-.2l.64-.37a.13.13,0,0,1,.17,0,.29.29,0,0,1,.06.21v2.78a4.92,4.92,0,0,1,.52-.86,2.46,2.46,0,0,1,.7-.63,1.44,1.44,0,0,1,.87-.25.79.79,0,0,1,.55.34,1.72,1.72,0,0,1,.3.8,6.32,6.32,0,0,1,.09,1.11Z" style="fill: rgb(255, 255, 255); transform-origin: 145.235px 337.554px;" id="el4uaz4qvj057" class="animable"></path>
                  <path d="M148.63,335.93a7.16,7.16,0,0,1,.15-1.46,6.34,6.34,0,0,1,.41-1.36,5,5,0,0,1,.66-1.13,3,3,0,0,1,.86-.77,1.42,1.42,0,0,1,.86-.23,1,1,0,0,1,.65.34,2,2,0,0,1,.42.81,4.35,4.35,0,0,1,.15,1.19v.58a.59.59,0,0,1-.07.29.42.42,0,0,1-.16.2L149.73,336a1.08,1.08,0,0,0,.09.48.51.51,0,0,0,.21.26.54.54,0,0,0,.31.06,1,1,0,0,0,.37-.14,1.71,1.71,0,0,0,.44-.34,2.4,2.4,0,0,0,.28-.37,1.53,1.53,0,0,1,.15-.23.56.56,0,0,1,.16-.14l.68-.39a.13.13,0,0,1,.16,0,.27.27,0,0,1,.07.21,2.43,2.43,0,0,1-.13.55,5.31,5.31,0,0,1-.37.77,5.61,5.61,0,0,1-.61.82,3.63,3.63,0,0,1-.83.68,1.53,1.53,0,0,1-.86.24.94.94,0,0,1-.66-.35,2,2,0,0,1-.41-.87A5.27,5.27,0,0,1,148.63,335.93Zm2.08-3.16a1.48,1.48,0,0,0-.42.36,1.82,1.82,0,0,0-.29.45,2.3,2.3,0,0,0-.18.5,3,3,0,0,0-.09.45l1.92-1.1a2.41,2.41,0,0,0-.07-.37.69.69,0,0,0-.15-.31.43.43,0,0,0-.28-.12A.84.84,0,0,0,150.71,332.77Z" style="fill: rgb(255, 255, 255); transform-origin: 150.71px 334.719px;" id="elgl7g7rmp14c" class="animable"></path>
                  <path d="M155,331.78c0,.14,0,.29,0,.47s0,.32,0,.44a2.19,2.19,0,0,0,.12.62.62.62,0,0,0,.24.33.41.41,0,0,0,.3.08.68.68,0,0,0,.35-.11,2.09,2.09,0,0,0,.36-.26,1.68,1.68,0,0,0,.24-.29,2,2,0,0,0,.18-.32l.14-.36c0-.1.07-.2.11-.31a.39.39,0,0,1,.17-.2l.64-.38a.13.13,0,0,1,.16,0,.25.25,0,0,1,.07.21,3.85,3.85,0,0,1-.13.74,4.6,4.6,0,0,1-.37,1,4.84,4.84,0,0,1-.64,1,3.33,3.33,0,0,1-.93.78,1.57,1.57,0,0,1-.84.25,1,1,0,0,1-.64-.28,1.68,1.68,0,0,1-.44-.7,4.39,4.39,0,0,1-.19-1c0-.15,0-.32,0-.52s0-.37,0-.54a6.11,6.11,0,0,1,.19-1.25,6.66,6.66,0,0,1,.43-1.21,5.14,5.14,0,0,1,.65-1,3,3,0,0,1,.84-.72,1.68,1.68,0,0,1,.93-.29,1,1,0,0,1,.64.23,1.28,1.28,0,0,1,.37.54,2,2,0,0,1,.13.59.58.58,0,0,1-.07.28.37.37,0,0,1-.16.2l-.64.38a.14.14,0,0,1-.17,0l-.11-.16-.14-.2a.46.46,0,0,0-.18-.13.49.49,0,0,0-.24,0,1.26,1.26,0,0,0-.36.15,1.72,1.72,0,0,0-.35.28,2.43,2.43,0,0,0-.3.44,2.35,2.35,0,0,0-.24.6A4.17,4.17,0,0,0,155,331.78Z" style="fill: rgb(255, 255, 255); transform-origin: 155.991px 331.715px;" id="ele1xos50w6m" class="animable"></path>
                  <path d="M160.54,329.84v2.25a.71.71,0,0,1-.06.28.48.48,0,0,1-.16.2l-.65.37a.12.12,0,0,1-.16,0,.29.29,0,0,1-.07-.21v-8.55a.72.72,0,0,1,.07-.28.43.43,0,0,1,.16-.2l.65-.37a.12.12,0,0,1,.16,0,.29.29,0,0,1,.06.21v4.1l1.42-2.57a1,1,0,0,1,.13-.22.55.55,0,0,1,.2-.2l.86-.49a.1.1,0,0,1,.14,0,.25.25,0,0,1,.06.19.57.57,0,0,1,0,.13.55.55,0,0,1-.08.19l-2,3.58,2.16,1.95a.24.24,0,0,1,.06.09.2.2,0,0,1,0,.11.62.62,0,0,1-.06.26.35.35,0,0,1-.14.17l-.82.47a.28.28,0,0,1-.22.05.32.32,0,0,1-.13-.07Z" style="fill: rgb(255, 255, 255); transform-origin: 161.468px 328.135px;" id="elpjc6r1edut" class="animable"></path>
                  <path d="M166.27,322.22a1.62,1.62,0,0,1,.85-.26,1,1,0,0,1,.65.23,1.7,1.7,0,0,1,.44.66,3.3,3.3,0,0,1,.19,1,2.63,2.63,0,0,1,0,.3v.72a2.63,2.63,0,0,1,0,.3,5.84,5.84,0,0,1-.2,1.23,5.54,5.54,0,0,1-.43,1.16,4.77,4.77,0,0,1-.65,1,3.35,3.35,0,0,1-.85.71,1.71,1.71,0,0,1-.84.27,1,1,0,0,1-.65-.23,1.63,1.63,0,0,1-.44-.66,3.56,3.56,0,0,1-.19-1,2.27,2.27,0,0,1,0-.28c0-.12,0-.24,0-.36s0-.24,0-.37a2.63,2.63,0,0,1,0-.3,5.82,5.82,0,0,1,.19-1.23,6.28,6.28,0,0,1,.44-1.17,4.58,4.58,0,0,1,.65-1A3.31,3.31,0,0,1,166.27,322.22Zm1,2.34a2,2,0,0,0-.12-.6.62.62,0,0,0-.24-.28.5.5,0,0,0-.32,0,1.48,1.48,0,0,0-.35.15,1.54,1.54,0,0,0-.34.26,1.59,1.59,0,0,0-.32.39,3.25,3.25,0,0,0-.24.56,3.92,3.92,0,0,0-.12.75,2,2,0,0,0,0,.26c0,.11,0,.22,0,.33s0,.22,0,.33a1.7,1.7,0,0,0,0,.25,2,2,0,0,0,.12.6.62.62,0,0,0,.24.28.5.5,0,0,0,.32,0,1.63,1.63,0,0,0,.34-.14,2.11,2.11,0,0,0,.35-.26,2.24,2.24,0,0,0,.32-.4,3.25,3.25,0,0,0,.24-.56,3.81,3.81,0,0,0,.12-.74c0-.07,0-.15,0-.26v-.66C167.31,324.72,167.3,324.63,167.3,324.56Z" style="fill: rgb(255, 255, 255); transform-origin: 166.275px 325.75px;" id="el51k36hvb0i4" class="animable"></path>
                  <path d="M169.75,320.7a.73.73,0,0,1,.07-.29.48.48,0,0,1,.16-.2l.65-.37a.12.12,0,0,1,.16,0,.31.31,0,0,1,.06.21v3.31a2.26,2.26,0,0,0,.22,1.13c.14.23.38.25.71.06a1.66,1.66,0,0,0,.68-.87,3.71,3.71,0,0,0,.24-1.39V319a.58.58,0,0,1,.07-.28.42.42,0,0,1,.16-.2l.64-.37a.12.12,0,0,1,.16,0,.25.25,0,0,1,.07.21v6.07a.62.62,0,0,1-.07.29.42.42,0,0,1-.16.2l-.64.37a.13.13,0,0,1-.16,0,.28.28,0,0,1-.07-.21v-.3a5.82,5.82,0,0,1-.48.82,2.36,2.36,0,0,1-.7.64,1.4,1.4,0,0,1-.85.25.78.78,0,0,1-.54-.36,1.83,1.83,0,0,1-.29-.8,6.43,6.43,0,0,1-.09-1.12Z" style="fill: rgb(255, 255, 255); transform-origin: 171.776px 322.305px;" id="el4om9m89zqzc" class="animable"></path>
                  <path d="M176.87,320.56a.91.91,0,0,0,.14.57c.1.1.27.08.52-.07l.52-.29a.12.12,0,0,1,.16,0,.29.29,0,0,1,.07.21v.86a.72.72,0,0,1-.07.28.43.43,0,0,1-.16.2l-.63.36c-.53.31-.94.35-1.22.12s-.43-.76-.43-1.58v-2.8l-.56.33a.12.12,0,0,1-.16,0,.28.28,0,0,1-.07-.2v-.86a.73.73,0,0,1,.07-.29.48.48,0,0,1,.16-.2l.56-.32v-2.13a.58.58,0,0,1,.07-.28.42.42,0,0,1,.16-.2l.64-.37a.12.12,0,0,1,.16,0,.25.25,0,0,1,.07.21v2.12l1.09-.63a.15.15,0,0,1,.17,0,.33.33,0,0,1,.06.21v.86a.72.72,0,0,1-.06.29.43.43,0,0,1-.17.2l-1.09.63Z" style="fill: rgb(255, 255, 255); transform-origin: 176.63px 318.408px;" id="el2s4hkgx0f4" class="animable"></path>
                </g>
                <path d="M176,273.39v7.28c0,.69.49,1,1.09.63l32.55-18.8a2.37,2.37,0,0,0,1.09-1.88v-7.28c0-.7-.49-1-1.09-.63L177.1,271.5A2.41,2.41,0,0,0,176,273.39Z" style="fill: rgb(55, 71, 79); transform-origin: 193.365px 267.004px;" id="elmvzqysw0lgh" class="animable"></path>
                <g id="freepik--stars--inject-95" class="animable" style="transform-origin: 193.377px 267.371px;">
                  <path d="M181.55,271l.53,1.13a.12.12,0,0,0,.17.07l1.19-.46c.19-.07.26.2.13.45l-.86,1.62a.52.52,0,0,0-.07.31l.2,1.46c0,.23-.16.54-.33.52l-1.06-.14a.29.29,0,0,0-.22.13l-1.06,1.36c-.17.21-.37.14-.33-.13l.2-1.7c0-.11,0-.19-.07-.23l-.86-.62c-.13-.1-.06-.46.13-.6l1.19-.92a.57.57,0,0,0,.17-.27l.53-1.74C181.22,271,181.46,270.85,181.55,271Z" style="fill: rgb(146, 227, 169); transform-origin: 181.34px 274.256px;" id="elk169erbk0i9" class="animable"></path>
                  <path d="M187.57,267.59l.53,1.13a.12.12,0,0,0,.17.06l1.19-.45c.19-.08.26.19.13.45l-.86,1.61a.52.52,0,0,0-.07.31l.2,1.46c0,.24-.16.54-.33.52l-1.06-.13a.28.28,0,0,0-.22.12L186.19,274c-.17.22-.37.14-.34-.13l.21-1.7A.24.24,0,0,0,186,272l-.86-.62c-.14-.1-.06-.45.13-.6l1.19-.91a.57.57,0,0,0,.17-.27l.53-1.75C187.24,267.55,187.48,267.41,187.57,267.59Z" style="fill: rgb(146, 227, 169); transform-origin: 187.363px 270.819px;" id="el0ngyc7xmusz" class="animable"></path>
                  <path d="M193.58,264.14l.54,1.13a.12.12,0,0,0,.17.07l1.19-.46c.19-.07.26.19.13.45l-.86,1.62a.51.51,0,0,0-.07.31l.2,1.46c0,.23-.16.53-.33.51l-1.07-.13c-.06,0-.14,0-.21.13l-1.06,1.36c-.17.21-.37.13-.34-.14l.21-1.69a.25.25,0,0,0-.07-.23l-.86-.63c-.14-.09-.06-.45.13-.59l1.19-.92a.57.57,0,0,0,.17-.27l.53-1.74C193.26,264.1,193.5,264,193.58,264.14Z" style="fill: rgb(146, 227, 169); transform-origin: 193.378px 267.393px;" id="elxp49xgj6xn" class="animable"></path>
                  <path d="M199.6,260.69l.53,1.13a.14.14,0,0,0,.18.07l1.19-.46c.19-.07.26.2.12.45l-.86,1.62a.51.51,0,0,0-.06.31l.2,1.46c0,.23-.16.54-.33.52l-1.07-.13a.25.25,0,0,0-.21.12l-1.06,1.36c-.17.22-.37.14-.34-.13l.2-1.7a.24.24,0,0,0-.06-.23l-.86-.62c-.14-.1-.06-.45.13-.6l1.18-.92a.51.51,0,0,0,.18-.26l.53-1.75C199.27,260.65,199.52,260.51,199.6,260.69Z" style="fill: rgb(146, 227, 169); transform-origin: 199.396px 263.938px;" id="elx1grvlhqjwi" class="animable"></path>
                  <path d="M205.62,257.25l.53,1.13a.13.13,0,0,0,.18.06l1.19-.45c.18-.07.26.19.12.45l-.86,1.61a.52.52,0,0,0-.06.31l.2,1.47c0,.23-.17.53-.33.51l-1.07-.13a.25.25,0,0,0-.21.12l-1.07,1.36c-.16.22-.36.14-.33-.13l.2-1.69a.25.25,0,0,0-.06-.24l-.86-.62c-.14-.1-.06-.45.12-.6l1.19-.91a.59.59,0,0,0,.18-.27l.53-1.74C205.29,257.21,205.54,257.07,205.62,257.25Z" style="fill: rgb(146, 227, 169); transform-origin: 205.415px 260.493px;" id="eljecgavavegk" class="animable"></path>
                </g>
                <g id="freepik--heart-box--inject-95" class="animable" style="transform-origin: 203.82px 302.061px;">
                  <path d="M198.75,297l10.15-5.86c1-.59,1.84-.11,1.84,1.07v11.72a4,4,0,0,1-1.84,3.19l-10.15,5.86c-1,.59-1.85.12-1.85-1.06V300.15A4.09,4.09,0,0,1,198.75,297Z" style="fill: rgb(255, 255, 255); transform-origin: 203.82px 302.061px;" id="elge2fc4auhod" class="animable"></path>
                </g>
                <path d="M199.11,303.38a7.12,7.12,0,0,1,.59-2.76h0v0a4.75,4.75,0,0,1,1.75-2.24c1.2-.71,2.21.73,2.36,2.42.15-1.87,1.13-4.47,2.33-5.18a1.31,1.31,0,0,1,1.77.18h0a3.06,3.06,0,0,1,.61,2c0,2.87-1.71,5.47-3.15,8.2l-1.56,2.64-1.5-.84C200.86,306.81,199.13,306.24,199.11,303.38Z" style="fill: rgb(146, 227, 169); transform-origin: 203.817px 301.998px;" id="elt1whv6ksraj" class="animable"></path>
                <path d="M134.09,249.87c1.12-.35.53-9.44.42-19.45s.49-24.76.49-24.76-2.28,8.29-3.51,29.2C130.93,244.51,132.54,247.85,134.09,249.87Z" style="fill: rgb(55, 71, 79); transform-origin: 133.188px 227.765px;" id="el1kzzgfk088i" class="animable"></path>
                <path d="M132.86,205.71c-1.8,8.19-4.3,38.36-2.85,45l4.08-.84s-1.58-1.92-1.56-12.72a184.4,184.4,0,0,1,2.6-31.93Z" style="fill: rgb(69, 90, 100); transform-origin: 132.353px 227.965px;" id="el2lwq8karfxj" class="animable"></path>
                <path d="M134.62,206.08l-1.66.14c3.35-15.84,9.17-21.8,9.45-22.27l1.13,1.16C143.47,185.22,137.28,192.51,134.62,206.08Z" style="fill: rgb(38, 50, 56); transform-origin: 138.25px 195.085px;" id="el9dqs5t5s39v" class="animable"></path>
                <path d="M134.55,207.8l-2.08-.11c-.36,0-.52-.52-.35-1.11l.72-3.07c.17-.59.61-1.06,1-1l2.07.11c.37,0,.53.52.35,1.11l-.71,3.07C135.35,207.35,134.92,207.82,134.55,207.8Z" style="fill: rgb(146, 227, 169); transform-origin: 134.193px 205.153px;" id="el4drfgt2gjij" class="animable"></path>
                <g id="elnhyira831y9">
                  <path d="M134.55,207.8l-2.08-.11c-.36,0-.52-.52-.35-1.11l.72-3.07c.17-.59.61-1.06,1-1l2.07.11c.37,0,.53.52.35,1.11l-.71,3.07C135.35,207.35,134.92,207.82,134.55,207.8Z" style="opacity: 0.15; transform-origin: 134.193px 205.153px;" class="animable"></path>
                </g>
                <path d="M204.11,157.41v45c0,4.45-2.71,9.63-6.05,11.56l-43.35,25V177.87l43.35-25C201.4,150.91,204.11,153,204.11,157.41Z" style="fill: rgb(69, 90, 100); transform-origin: 179.41px 195.551px;" id="elw0ha318xsnc" class="animable"></path>
                <path d="M189.21,154.27l5.94-3.42c.7-.41,1.28,0,1.28,1v2l-8.49,4.9v-2A3.08,3.08,0,0,1,189.21,154.27Z" style="fill: rgb(55, 71, 79); transform-origin: 192.185px 154.721px;" id="elufk8xqp4ia" class="animable"></path>
                <path d="M168.79,217.41,167.69,181c-.1-4.25-1.61-7.17-3.95-8.35l-9,5.22V239l7.76-4.48A27.15,27.15,0,0,0,168.79,217.41Z" style="fill: rgb(55, 71, 79); transform-origin: 161.765px 205.825px;" id="elojlcotykosm" class="animable"></path>
                <path d="M166.13,218.94c.19,7.94-4.65,17.41-10.7,20.9L146.51,245c-6,3.49-10.9-.37-10.7-8.54l1.09-37.68c.18-7.8,4.92-16.67,10.71-20l6.72-3.88c5.79-3.34,10.53.05,10.71,7.65Z" style="fill: rgb(69, 90, 100); transform-origin: 150.97px 209.951px;" id="elbx1zseoymtd" class="animable"></path>
                <path d="M164.89,226.81c-1.8,5.54-5.36,10.66-9.46,13L146.51,245c-6,3.49-10.9-.37-10.7-8.54l1.09-37.68c.18-7.8,4.92-16.67,10.71-20l1-.57c-6.57,3.8-6.83,17.78-6.87,40,0,7.23-.7,15.83,2.56,19.49,2.59,2.9,7.64.94,11.93-1.42A19.14,19.14,0,0,0,164.89,226.81Z" style="fill: rgb(55, 71, 79); transform-origin: 150.347px 212.248px;" id="elw4tkzeandm" class="animable"></path>
                <path d="M144.61,201.25l-2.7,1.56c-.7.4-1.27,0-1.27-1v-9.24a3.05,3.05,0,0,1,1.27-2.43l2.7-1.56c.7-.4,1.27,0,1.27,1v9.24A3.05,3.05,0,0,1,144.61,201.25Z" style="fill: rgb(224, 224, 224); transform-origin: 143.26px 195.695px;" id="elsa0wdckfbn9" class="animable"></path>
                <path d="M143.79,198l-1.71,1c-.44.25-.8,0-.8-.61V192.5a1.91,1.91,0,0,1,.8-1.53l1.71-1c.44-.25.8,0,.8.61v5.83A1.91,1.91,0,0,1,143.79,198Z" style="fill: rgb(245, 245, 245); transform-origin: 142.935px 194.485px;" id="elljad01s2xqc" class="animable"></path>
                <path d="M142.16,204.92l2.2-1.27c.84-.49,1.52,0,1.52,1.15h0a3.66,3.66,0,0,1-1.52,2.91l-2.2,1.27c-.84.48-1.52,0-1.52-1.15h0A3.63,3.63,0,0,1,142.16,204.92Z" style="fill: rgb(146, 227, 169); transform-origin: 143.26px 206.313px;" id="el8g4etug2afp" class="animable"></path>
                <g id="elxdnd52uccx">
                  <ellipse cx="185.42" cy="193" rx="28.43" ry="16.08" style="fill: rgb(38, 50, 56); transform-origin: 185.42px 193px; transform: rotate(-66.95deg);" class="animable"></ellipse>
                </g>
                <g id="elnqiwdmdc5y">
                  <ellipse cx="185.42" cy="193" rx="26.86" ry="15.19" style="fill: rgb(69, 90, 100); transform-origin: 185.42px 193px; transform: rotate(-66.95deg);" class="animable"></ellipse>
                </g>
                <g id="el163gzsns115">
                  <ellipse cx="185.42" cy="192.13" rx="16.07" ry="9.09" style="fill: rgb(38, 50, 56); transform-origin: 185.42px 192.13px; transform: rotate(-66.95deg);" class="animable"></ellipse>
                </g>
                <g id="elvptzp84ucr">
                  <ellipse cx="185.42" cy="192.13" rx="12.78" ry="7.23" style="fill: rgb(55, 71, 79); transform-origin: 185.42px 192.13px; transform: rotate(-66.95deg);" class="animable"></ellipse>
                </g>
                <path d="M191.18,184.53a5.68,5.68,0,0,1-2.37,4.53c-1.31.75-2.37-.05-2.37-1.79a5.68,5.68,0,0,1,2.37-4.53C190.12,182,191.18,182.79,191.18,184.53Z" style="fill: rgb(224, 224, 224); transform-origin: 188.81px 185.902px;" id="eljv3ecopo4ro" class="animable"></path>
                <path d="M192.18,189.1a2.42,2.42,0,0,1-1,1.92c-.56.32-1,0-1-.76a2.43,2.43,0,0,1,1-1.93C191.73,188,192.18,188.35,192.18,189.1Z" style="fill: rgb(224, 224, 224); transform-origin: 191.18px 189.674px;" id="elthnj73tcf88" class="animable"></path>
                <path d="M199.75,161l-3.65,2.1c-.93.54-1.68,0-1.68-1.27h0a4,4,0,0,1,1.68-3.22l3.65-2.11c.93-.54,1.69,0,1.69,1.27h0A4.05,4.05,0,0,1,199.75,161Z" style="fill: rgb(250, 250, 250); transform-origin: 197.93px 159.8px;" id="el6ikqz5jqdh4" class="animable"></path>
                <g id="elt7tgb5ucamo">
                  <path d="M200.78,185.87c1.67-5.85-.11-11.56-2.45-14.19a7.27,7.27,0,0,0-5.35-2.5c-.64,0-5.9.57-4.92,3.34a.82.82,0,0,0,.64.49,25.93,25.93,0,0,1,3.15.93c3.53,1.51,5.79,5.91,6,11.67a7.35,7.35,0,0,0,.26,2.23.85.85,0,0,0,1.4.45A4.84,4.84,0,0,0,200.78,185.87Z" style="fill: rgb(255, 255, 255); opacity: 0.1; transform-origin: 194.687px 178.844px;" class="animable"></path>
                </g>
                <g id="elfg2eq7zx7ti">
                  <path d="M196.43,193.93a4.19,4.19,0,0,1,1.74-3.34c1-.56,1.75,0,1.75,1.32a4.18,4.18,0,0,1-1.75,3.34C197.21,195.81,196.43,195.21,196.43,193.93Z" style="fill: rgb(255, 255, 255); opacity: 0.1; transform-origin: 198.175px 192.917px;" class="animable"></path>
                </g>
              </g>
            </g>
            <g id="freepik--window-1--inject-95" class="animable" style="transform-origin: 254.38px 216.959px;">
              <g id="freepik--window--inject-95" class="animable" style="transform-origin: 254.38px 216.959px;">
                <g id="freepik--window--inject-95" class="animable" style="transform-origin: 254.38px 216.959px;">
                  <path d="M306.27,57.87,195.93,121.49a5.49,5.49,0,0,0-2.48,4.3v245.1a5.46,5.46,0,0,0,2.49,4.29l1.55.89a5.54,5.54,0,0,0,5,0L312.8,312.34a5.49,5.49,0,0,0,2.49-4.31V63a5.45,5.45,0,0,0-2.49-4.29l-1.55-.89A5.53,5.53,0,0,0,306.27,57.87Z" style="fill: rgb(224, 224, 224); transform-origin: 254.37px 216.959px;" id="elbyfjd7tbnxj" class="animable"></path>
                  <path d="M315.29,308.21c0,2.19-1.54,3.58-3.44,4.67L203.41,375.49c-1.89,1.1-3.43.21-3.43-2V130.6h0a6.83,6.83,0,0,1,3.42-5.92L311.85,62a2.29,2.29,0,0,1,3.44,2h0Z" style="fill: rgb(250, 250, 250); transform-origin: 257.635px 218.826px;" id="el8o5coie1vyk" class="animable"></path>
                  <path d="M313,62.68h0A1.34,1.34,0,0,1,314.33,64V308.21c0,1.48-.91,2.67-2.95,3.84L202.93,374.67a2.18,2.18,0,0,1-1.05.34c-.82,0-.94-.94-.94-1.5V130.6a5.89,5.89,0,0,1,2.94-5.09L312.33,62.86a1.31,1.31,0,0,1,.66-.18m0-1a2.2,2.2,0,0,0-1.14.31L203.4,124.68A6.83,6.83,0,0,0,200,130.6V373.51c0,1.56.78,2.45,1.9,2.45a3.09,3.09,0,0,0,1.53-.47l108.44-62.61c1.9-1.09,3.44-2.48,3.44-4.67V64a2.29,2.29,0,0,0-2.3-2.29Z" style="fill: rgb(224, 224, 224); transform-origin: 257.655px 218.82px;" id="elwzgn1remhrr" class="animable"></path>
                  <path d="M315.29,63v1a2.29,2.29,0,0,0-3.44-2L203.4,124.69a6.57,6.57,0,0,0-2.5,2.49.3.3,0,0,0,0,.08l-6.71-3.87a5.06,5.06,0,0,1,1.79-1.9L306.27,57.87a5.48,5.48,0,0,1,5,0l1.55.89A5.44,5.44,0,0,1,315.29,63Z" style="fill: rgb(240, 240, 240); transform-origin: 254.74px 92.2633px;" id="elxrbfw86q0np" class="animable"></path>
                  <path d="M221.16,128.15a.28.28,0,0,1,0-.16v-.8l-1.26.72c-.06,0-.1,0-.1-.11v-.29c0-.06,0-.12,0-.16l.05-.15,1.33-3.63a.57.57,0,0,1,.06-.15.22.22,0,0,1,.09-.07l.17-.1c.05,0,.09,0,.12,0a.36.36,0,0,1,0,.18v2.79l.28-.16c.05,0,.09,0,.11,0a.34.34,0,0,1,0,.17v.19a.49.49,0,0,1,0,.2.33.33,0,0,1-.11.13l-.28.16v.8a.56.56,0,0,1,0,.2.23.23,0,0,1-.12.12l-.21.12C221.22,128.17,221.18,128.18,221.16,128.15Zm.05-1.69v-2L220.3,127Z" style="fill: rgb(69, 90, 100); transform-origin: 220.91px 125.709px;" id="el8223ewwwtln" class="animable"></path>
                  <path d="M222.65,126.81a2.61,2.61,0,0,1-.37-1.64,5.11,5.11,0,0,1,.37-2,2.48,2.48,0,0,1,1-1.25,1.25,1.25,0,0,1,.5-.19.56.56,0,0,1,.39,0,.09.09,0,0,1,0,.07.38.38,0,0,1,0,.1l-.12.41s0,.09,0,.1h-.05a.74.74,0,0,0-.64.1,1.82,1.82,0,0,0-.71.86,4,4,0,0,0-.25,1.52,2.1,2.1,0,0,0,.25,1.24c.16.2.39.22.68,0A1.6,1.6,0,0,0,224,126a1.46,1.46,0,0,0,.24-.27v-1.3l-.52.3c-.05,0-.09,0-.11,0a.29.29,0,0,1,0-.17v-.18a.58.58,0,0,1,0-.21.31.31,0,0,1,.11-.12l.84-.48s.1,0,.12,0a.31.31,0,0,1,0,.2v1.86c0,.07,0,.12,0,.16a.49.49,0,0,1-.06.13,2.68,2.68,0,0,1-1,1C223.24,127.11,222.89,127.1,222.65,126.81Z" style="fill: rgb(69, 90, 100); transform-origin: 223.481px 124.371px;" id="elqgakaalcn8f" class="animable"></path>
                  <path d="M233.2,120.42a1.92,1.92,0,0,1-.73,1.54c-.4.23-.72-.08-.72-.7a1.91,1.91,0,0,1,.72-1.54C232.87,119.49,233.2,119.8,233.2,120.42Z" style="fill: rgb(69, 90, 100); transform-origin: 232.475px 120.84px;" id="elffupvz8863l" class="animable"></path>
                  <path d="M232.47,117.89a4.36,4.36,0,0,0-1.69,2.54c-.06.22,0,.44.08.51h0c.14.08.34-.09.42-.38a3.13,3.13,0,0,1,1.19-1.79.77.77,0,0,1,1.19.42c.08.19.29.13.43-.11h0a.71.71,0,0,0,.07-.59A1.09,1.09,0,0,0,232.47,117.89Z" style="fill: rgb(69, 90, 100); transform-origin: 232.475px 119.322px;" id="elult9k2yxyw" class="animable"></path>
                  <path d="M232.47,116.1a6.81,6.81,0,0,0-2.59,3.69c-.08.24-.06.49.06.56h0c.13.09.33-.08.42-.35a5.48,5.48,0,0,1,2.11-3,1.4,1.4,0,0,1,2.12.57c.09.17.28.11.42-.12h0a.72.72,0,0,0,.06-.63A1.73,1.73,0,0,0,232.47,116.1Z" style="fill: rgb(69, 90, 100); transform-origin: 232.474px 118.074px;" id="elu8lm460gja" class="animable"></path>
                  <path d="M257.08,104.34s.07,0,.09,0a.12.12,0,0,1,0,.11v.73a.32.32,0,0,1,0,.16.17.17,0,0,1-.09.11l-.45.26a.07.07,0,0,1-.09,0,.21.21,0,0,1,0-.12v-.73a.47.47,0,0,1,0-.16.35.35,0,0,1,.09-.11Zm0,2.59s.07,0,.09,0a.12.12,0,0,1,0,.11v.73a.32.32,0,0,1,0,.16.17.17,0,0,1-.09.11l-.45.26a.07.07,0,0,1-.09,0,.21.21,0,0,1,0-.12v-.73a.47.47,0,0,1,0-.16.35.35,0,0,1,.09-.11Z" style="fill: rgb(69, 90, 100); transform-origin: 256.857px 106.328px;" id="elswfqsew2vae" class="animable"></path>
                  <path d="M259.65,106.57s-.07,0-.09,0a.16.16,0,0,1,0-.12v-.9l-1.49.86s-.07,0-.09,0a.16.16,0,0,1,0-.12v-.53a.69.69,0,0,1,0-.2l.07-.21,1.47-3.67a.64.64,0,0,1,.2-.3l.37-.21a.07.07,0,0,1,.09,0,.21.21,0,0,1,0,.12v3l.41-.24a.06.06,0,0,1,.09,0s0,.06,0,.12v.52a.35.35,0,0,1,0,.17.25.25,0,0,1-.09.11l-.41.24v.89a.53.53,0,0,1,0,.17.35.35,0,0,1-.09.11Zm-.13-1.94V103l-.82,2.06Z" style="fill: rgb(69, 90, 100); transform-origin: 259.329px 103.862px;" id="ell36atiezw0q" class="animable"></path>
                  <path d="M254.71,109.4a.07.07,0,0,1-.09,0,.21.21,0,0,1,0-.12v-.9l-1.49.86a.07.07,0,0,1-.09,0,.21.21,0,0,1,0-.12v-.53a.69.69,0,0,1,0-.2c0-.06,0-.13.08-.21l1.47-3.67a.57.57,0,0,1,.2-.3l.36-.21s.07,0,.09,0a.16.16,0,0,1,0,.12v3l.42-.24a.08.08,0,0,1,.09,0,.17.17,0,0,1,0,.12v.52a.51.51,0,0,1,0,.17.35.35,0,0,1-.09.11l-.42.24v.89a.35.35,0,0,1,0,.17.2.2,0,0,1-.09.11Zm-.13-1.94v-1.59l-.82,2.06Z" style="fill: rgb(69, 90, 100); transform-origin: 254.396px 106.708px;" id="el1nmbtax6flg" class="animable"></path>
                  <path d="M261.58,100.48a.35.35,0,0,1,0-.17.2.2,0,0,1,.09-.11l1.81-1a.08.08,0,0,1,.09,0,.21.21,0,0,1,0,.12v.53a.47.47,0,0,1,0,.16.26.26,0,0,1-.09.11l-1.35.78-.06,1a1.14,1.14,0,0,1,.26-.24,1.52,1.52,0,0,1,.21-.14,1.45,1.45,0,0,1,.5-.18.6.6,0,0,1,.4.1.77.77,0,0,1,.26.39,1.82,1.82,0,0,1,.1.67,3.35,3.35,0,0,1-.1.87,3.49,3.49,0,0,1-.27.71,2.29,2.29,0,0,1-.4.55,2.16,2.16,0,0,1-.49.38,1.34,1.34,0,0,1-.57.21.5.5,0,0,1-.39-.09.68.68,0,0,1-.24-.33,1.86,1.86,0,0,1-.09-.5.35.35,0,0,1,0-.17.33.33,0,0,1,.09-.11l.39-.22a.07.07,0,0,1,.09,0,.19.19,0,0,1,.05.1.45.45,0,0,0,.09.22.24.24,0,0,0,.14.09.43.43,0,0,0,.19,0l.21-.1a1.17,1.17,0,0,0,.44-.49,1.56,1.56,0,0,0,.17-.72.55.55,0,0,0-.17-.46.38.38,0,0,0-.44,0,1.14,1.14,0,0,0-.23.16l-.14.16-.09.13a.52.52,0,0,1-.11.1l-.45.25a.08.08,0,0,1-.09,0,.21.21,0,0,1,0-.12Z" style="fill: rgb(69, 90, 100); transform-origin: 262.52px 102.207px;" id="elzbf9yf1bgz9" class="animable"></path>
                  <polygon points="207.33 136.73 207.33 135.19 208.67 134.42 208.67 135.96 207.33 136.73" style="fill: rgb(69, 90, 100); transform-origin: 208px 135.575px;" id="elnhnb6bnoj8e" class="animable"></polygon>
                  <polygon points="209.33 135.57 209.33 132.49 210.67 131.72 210.67 134.8 209.33 135.57" style="fill: rgb(69, 90, 100); transform-origin: 210px 133.645px;" id="elkf7w1u1wvdc" class="animable"></polygon>
                  <polygon points="211.34 134.42 211.34 129.79 212.67 129.02 212.67 133.65 211.34 134.42" style="fill: rgb(69, 90, 100); transform-origin: 212.005px 131.72px;" id="elgcfcyz5xcq8" class="animable"></polygon>
                  <polygon points="213.34 133.26 213.34 127.09 214.68 126.32 214.68 132.49 213.34 133.26" style="fill: rgb(69, 90, 100); transform-origin: 214.01px 129.79px;" id="elycerb4h4suf" class="animable"></polygon>
                  <path d="M307.18,74.08c-.15-.21-.32-.27-.5-.16l-5,2.87a1.9,1.9,0,0,0-.58,1.51v2.81a1.62,1.62,0,0,0,.22.89.48.48,0,0,0,.2.18.3.3,0,0,0,.3,0l5-2.87a1.91,1.91,0,0,0,.57-1.51V75A1.6,1.6,0,0,0,307.18,74.08Zm-5.71,7.56a.79.79,0,0,1-.08-.41v-2.8a1.51,1.51,0,0,1,.44-1.16l5-2.87a.17.17,0,0,1,.15,0,.13.13,0,0,1,.07.06.79.79,0,0,1,.08.41v2.8a1.51,1.51,0,0,1-.44,1.16l-5,2.87A.15.15,0,0,1,301.47,81.64Z" style="fill: rgb(69, 90, 100); transform-origin: 304.245px 78.0431px;" id="elwatz8qxugv" class="animable"></path>
                  <path d="M302.77,77.21v3.32L302,81a.1.1,0,0,1-.15-.09V78.43a1.15,1.15,0,0,1,.32-.86Z" style="fill: rgb(69, 90, 100); transform-origin: 302.309px 79.1117px;" id="el2prr68mpv17" class="animable"></path>
                  <polygon points="303.14 77.01 304.07 76.47 304.07 79.78 303.14 80.32 303.14 77.01" style="fill: rgb(69, 90, 100); transform-origin: 303.605px 78.395px;" id="elquzpxrajims" class="animable"></polygon>
                  <polygon points="304.44 76.27 305.37 75.72 305.37 79.05 304.44 79.58 304.44 76.27" style="fill: rgb(69, 90, 100); transform-origin: 304.905px 77.65px;" id="el8a2kf0gvr3r" class="animable"></polygon>
                  <path d="M306.67,75.19v2.39a1.26,1.26,0,0,1-.62,1.08l-.31.18V75.52l.74-.43A.12.12,0,0,1,306.67,75.19Z" style="fill: rgb(69, 90, 100); transform-origin: 306.205px 76.9537px;" id="el4va5cfmd9pe" class="animable"></path>
                  <path d="M307.58,74.88c.14-.08.25.32.25.9v.52a2.07,2.07,0,0,1-.25,1.21l-.26.17V75Z" style="fill: rgb(69, 90, 100); transform-origin: 307.58px 76.2749px;" id="el0xhsl0fiutic" class="animable"></path>
                  <polygon points="293.53 83.36 298.39 79.05 296.89 85.37 296.18 82.74 293.53 83.36" style="fill: rgb(69, 90, 100); transform-origin: 295.96px 82.21px;" id="el4b25wgfvmjx" class="animable"></polygon>
                </g>
                <path d="M240.19,160.75c.62-.36,1.1-.41,1.44-.16s.51.81.51,1.67a4.88,4.88,0,0,1-.51,2.28,3.68,3.68,0,0,1-1.44,1.51l-1.23.71v2.62a.58.58,0,0,1-.06.26.39.39,0,0,1-.14.18l-.62.36a.12.12,0,0,1-.15,0,.25.25,0,0,1-.06-.19v-7.61a.62.62,0,0,1,.06-.26.39.39,0,0,1,.15-.18ZM239,165.28l1.19-.68a2,2,0,0,0,.7-.67,2,2,0,0,0,.26-1.07c0-.44-.09-.7-.26-.77a.9.9,0,0,0-.7.15l-1.19.69Z" style="fill: rgb(69, 90, 100); transform-origin: 240.035px 165.32px;" id="elonvwzpz344i" class="animable"></path>
                <path d="M245.21,157.85c.08,0,.14,0,.18,0a.67.67,0,0,1,.11.24l1.87,6.37a.15.15,0,0,1,0,.07.58.58,0,0,1-.06.26.39.39,0,0,1-.14.18l-.56.32c-.1.06-.17.07-.21,0a.42.42,0,0,1-.09-.14L246,164l-2.42,1.4-.33,1.49a1.44,1.44,0,0,1-.08.25.58.58,0,0,1-.22.22l-.55.32a.12.12,0,0,1-.15,0,.25.25,0,0,1-.06-.19s0-.06,0-.09l1.88-8.52a1.73,1.73,0,0,1,.1-.37.37.37,0,0,1,.19-.2Zm.42,4.93-.84-2.87-.84,3.84Z" style="fill: rgb(69, 90, 100); transform-origin: 244.781px 162.778px;" id="eljlmqor9h2kj" class="animable"></path>
                <path d="M251.6,154.16a.11.11,0,0,1,.14,0,.27.27,0,0,1,.06.19.49.49,0,0,1,0,.12l-1.82,6V163a.65.65,0,0,1-.06.26.54.54,0,0,1-.14.18l-.61.35a.12.12,0,0,1-.15,0,.25.25,0,0,1-.06-.19V161l-1.81-3.87a.24.24,0,0,1,0-.09.58.58,0,0,1,.06-.26.35.35,0,0,1,.15-.18l.57-.33c.1-.06.18-.07.23,0a.31.31,0,0,1,.09.13l1.24,2.61L250.7,155a1.63,1.63,0,0,1,.09-.23.61.61,0,0,1,.23-.25Z" style="fill: rgb(69, 90, 100); transform-origin: 249.475px 158.976px;" id="eluq14wzv493" class="animable"></path>
                <path d="M255.14,156.32l1.45-4.72.11-.25a.52.52,0,0,1,.22-.26l.52-.3a.12.12,0,0,1,.15,0,.25.25,0,0,1,.06.19v7.61a.57.57,0,0,1-.06.25.35.35,0,0,1-.15.18l-.62.36a.1.1,0,0,1-.14,0,.25.25,0,0,1-.06-.19v-4.6l-1,3.22a1.72,1.72,0,0,1-.1.26.51.51,0,0,1-.18.2l-.4.23a.15.15,0,0,1-.18,0,.41.41,0,0,1-.1-.13l-1-2.08v4.61a.62.62,0,0,1-.06.26.54.54,0,0,1-.14.18l-.62.35a.11.11,0,0,1-.15,0,.25.25,0,0,1-.06-.19v-7.61a.57.57,0,0,1,.06-.25.35.35,0,0,1,.15-.18l.52-.3c.1-.06.17-.06.22,0l.11.12Z" style="fill: rgb(69, 90, 100); transform-origin: 255.14px 156.242px;" id="el8rex4xvk6l8" class="animable"></path>
                <path d="M262.45,154.67a.11.11,0,0,1,.14,0,.25.25,0,0,1,.06.19v.84a.65.65,0,0,1-.06.26.41.41,0,0,1-.14.17l-3.36,1.94a.11.11,0,0,1-.15,0,.24.24,0,0,1-.06-.18v-7.62a.57.57,0,0,1,.06-.25.35.35,0,0,1,.15-.18l3.3-1.91a.12.12,0,0,1,.14,0,.27.27,0,0,1,.06.19V149a.65.65,0,0,1-.06.26.54.54,0,0,1-.14.18l-2.48,1.42v1.88l2.31-1.33a.1.1,0,0,1,.14,0,.25.25,0,0,1,.06.19v.83a.58.58,0,0,1-.06.26.39.39,0,0,1-.14.18l-2.31,1.33v1.95Z" style="fill: rgb(69, 90, 100); transform-origin: 260.765px 153.003px;" id="elmc16kngtii" class="animable"></path>
                <path d="M267.11,153.45c-.08.05-.15.05-.19,0a.47.47,0,0,1-.1-.11l-2.15-3.66v4.85a.62.62,0,0,1-.06.26.39.39,0,0,1-.14.18l-.62.36a.12.12,0,0,1-.15,0,.27.27,0,0,1-.06-.19v-7.61a.58.58,0,0,1,.06-.26.35.35,0,0,1,.15-.18l.53-.31c.09-.05.16-.05.2,0l.1.11,2.14,3.66v-4.85a.62.62,0,0,1,.06-.26.46.46,0,0,1,.15-.18l.62-.36a.14.14,0,0,1,.15,0,.27.27,0,0,1,.06.19v7.61a.58.58,0,0,1-.06.26.4.4,0,0,1-.15.18Z" style="fill: rgb(69, 90, 100); transform-origin: 265.75px 150.117px;" id="elzx1gqz2ash" class="animable"></path>
                <path d="M272.65,142a.12.12,0,0,1,.15,0,.27.27,0,0,1,.06.19V143a.62.62,0,0,1-.06.26.39.39,0,0,1-.15.18l-1.37.79v6.46a.62.62,0,0,1-.06.26.45.45,0,0,1-.15.18l-.62.35a.1.1,0,0,1-.14,0,.21.21,0,0,1-.07-.18v-6.46l-1.37.79a.11.11,0,0,1-.15,0,.23.23,0,0,1-.06-.18v-.84a.62.62,0,0,1,.06-.26.35.35,0,0,1,.15-.18Z" style="fill: rgb(69, 90, 100); transform-origin: 270.76px 146.741px;" id="el8jxedo51bex" class="animable"></path>
                <path d="M275.46,140.27a2,2,0,0,1,.91-.31,1.12,1.12,0,0,1,.65.15,1,1,0,0,1,.39.48,1.71,1.71,0,0,1,.15.63.45.45,0,0,1-.06.27.36.36,0,0,1-.15.17l-.53.31a.23.23,0,0,1-.2,0,.58.58,0,0,1-.12-.14.58.58,0,0,0-.34-.31.89.89,0,0,0-.7.18,2.47,2.47,0,0,0-.38.28,2.17,2.17,0,0,0-.31.37,2.06,2.06,0,0,0-.21.42,1.42,1.42,0,0,0-.08.45.83.83,0,0,0,.08.4.34.34,0,0,0,.24.15,1.27,1.27,0,0,0,.46,0l.7-.16a2.7,2.7,0,0,1,.79-.1,1,1,0,0,1,.55.18,1,1,0,0,1,.31.49,2.6,2.6,0,0,1,.1.8,4,4,0,0,1-.15,1.07,4.44,4.44,0,0,1-.44,1,5,5,0,0,1-.69.91,4.34,4.34,0,0,1-.93.73,2.45,2.45,0,0,1-.82.31A1.3,1.3,0,0,1,274,149a1,1,0,0,1-.49-.44,1.81,1.81,0,0,1-.2-.83.4.4,0,0,1,.06-.26.41.41,0,0,1,.14-.17L274,147c.09-.05.15-.07.2-.05a.27.27,0,0,1,.11.15,1.71,1.71,0,0,0,.13.23.41.41,0,0,0,.21.15.71.71,0,0,0,.33,0,1.55,1.55,0,0,0,.48-.21,3,3,0,0,0,.45-.31,2.28,2.28,0,0,0,.37-.39,1.89,1.89,0,0,0,.26-.46,1.25,1.25,0,0,0,.1-.51.5.5,0,0,0-.11-.36.4.4,0,0,0-.32-.09,2.43,2.43,0,0,0-.54.08c-.22.06-.46.11-.74.16a2.33,2.33,0,0,1-.67,0,.8.8,0,0,1-.48-.2,1.13,1.13,0,0,1-.28-.51,3.27,3.27,0,0,1-.09-.86,3.93,3.93,0,0,1,.15-1.06,5.36,5.36,0,0,1,.42-1,5,5,0,0,1,.64-.87A3.63,3.63,0,0,1,275.46,140.27Z" style="fill: rgb(69, 90, 100); transform-origin: 275.509px 144.501px;" id="elms8og22ikmi" class="animable"></path>
                <path d="M206.8,149.47a.41.41,0,0,1,0-.19.76.76,0,0,1,.09-.18l1.51-2.43,0,0h0a0,0,0,0,1,.06,0,.12.12,0,0,1,0,.09V147a.49.49,0,0,1-.05.2,1.72,1.72,0,0,1-.09.19l-1.2,1.94,1.2.56.09.07a.25.25,0,0,1,.05.16v.31a.24.24,0,0,1,0,.12.18.18,0,0,1-.06.08h0s0,0,0,0l-1.51-.69a.18.18,0,0,1-.09-.08.18.18,0,0,1,0-.14Z" style="fill: rgb(69, 90, 100); transform-origin: 207.627px 148.65px;" id="el53xt0izo6" class="animable"></path>
                <path d="M211.64,144.37a1.29,1.29,0,0,1,.42-.16.52.52,0,0,1,.35.06.65.65,0,0,1,.23.31,1.67,1.67,0,0,1,.09.61,2.67,2.67,0,0,1-.18,1,2.58,2.58,0,0,1-.47.75l.68,1.44a.19.19,0,0,1,0,.07.27.27,0,0,1,0,.12.23.23,0,0,1-.07.09l-.2.11c-.06,0-.1,0-.13,0l-.06-.08-.66-1.41-.78.46v1.78a.42.42,0,0,1,0,.15.21.21,0,0,1-.09.11l-.18.11a.07.07,0,0,1-.09,0,.16.16,0,0,1,0-.11v-4.52a.47.47,0,0,1,0-.16.18.18,0,0,1,.09-.1Zm-.79,2.76.76-.45a1.41,1.41,0,0,0,.51-.49,1.44,1.44,0,0,0,.18-.75c0-.3-.06-.48-.18-.53s-.29,0-.51.11l-.76.44Z" style="fill: rgb(69, 90, 100); transform-origin: 211.629px 147.054px;" id="el32yn41bi7tu" class="animable"></path>
                <path d="M215.36,146.49a.06.06,0,0,1,.08,0,.12.12,0,0,1,0,.11v.25a.29.29,0,0,1,0,.16.24.24,0,0,1-.08.11l-1.84,1.06a.1.1,0,0,1-.09,0,.18.18,0,0,1,0-.11v-4.52a.41.41,0,0,1,0-.16.22.22,0,0,1,.09-.1l1.8-1a.08.08,0,0,1,.09,0,.18.18,0,0,1,0,.11v.26a.4.4,0,0,1,0,.15.35.35,0,0,1-.09.11l-1.49.86v1.47l1.39-.8a.06.06,0,0,1,.09,0,.16.16,0,0,1,0,.11v.25a.4.4,0,0,1,0,.15.26.26,0,0,1-.09.11l-1.39.8v1.54Z" style="fill: rgb(69, 90, 100); transform-origin: 214.437px 145.233px;" id="ellxrvdcz4ecd" class="animable"></path>
                <path d="M218.08,140.65a.08.08,0,0,1,.09,0,.16.16,0,0,1,0,.11V141a.45.45,0,0,1,0,.16.3.3,0,0,1-.09.1l-.85.49v4.08a.32.32,0,0,1,0,.16.21.21,0,0,1-.08.1l-.19.11a.08.08,0,0,1-.09,0,.21.21,0,0,1,0-.12V142l-.85.49s-.07,0-.09,0a.19.19,0,0,1,0-.12v-.25a.42.42,0,0,1,0-.15.2.2,0,0,1,.09-.11Z" style="fill: rgb(69, 90, 100); transform-origin: 217.05px 143.425px;" id="elqr4h2iddy4q" class="animable"></path>
                <path d="M221,139a.06.06,0,0,1,.08,0,.14.14,0,0,1,0,.11v2.81a4.57,4.57,0,0,1-.07.83,3.45,3.45,0,0,1-.22.75,2.21,2.21,0,0,1-.38.63,1.91,1.91,0,0,1-.55.47,1,1,0,0,1-.56.18.51.51,0,0,1-.38-.2,1.16,1.16,0,0,1-.22-.5,4,4,0,0,1-.07-.75v-2.8a.34.34,0,0,1,0-.16.18.18,0,0,1,.09-.1l.18-.11a.06.06,0,0,1,.09,0,.16.16,0,0,1,0,.11v2.83a1.33,1.33,0,0,0,.21.9c.14.14.34.14.59,0a1.54,1.54,0,0,0,.58-.67,2.76,2.76,0,0,0,.21-1.14v-2.84a.28.28,0,0,1,0-.15.24.24,0,0,1,.08-.11Z" style="fill: rgb(69, 90, 100); transform-origin: 219.856px 141.882px;" id="elw61hp3h8glo" class="animable"></path>
                <path d="M223.14,137.73a1.16,1.16,0,0,1,.42-.16.52.52,0,0,1,.35.06.6.6,0,0,1,.24.31,1.92,1.92,0,0,1,.09.61,2.67,2.67,0,0,1-.19,1,2.4,2.4,0,0,1-.47.74l.69,1.45a.15.15,0,0,1,0,.07.27.27,0,0,1,0,.12.17.17,0,0,1-.07.09l-.2.11c-.06,0-.1,0-.13,0l-.06-.08-.66-1.41-.78.45v1.79a.28.28,0,0,1,0,.15.24.24,0,0,1-.08.11l-.19.11a.08.08,0,0,1-.09,0,.16.16,0,0,1,0-.11v-4.52a.41.41,0,0,1,0-.16.3.3,0,0,1,.09-.1Zm-.79,2.75.77-.44a1.47,1.47,0,0,0,.5-.49,1.44,1.44,0,0,0,.18-.75c0-.3-.06-.48-.18-.53s-.29,0-.5.1l-.77.45Z" style="fill: rgb(69, 90, 100); transform-origin: 223.138px 140.413px;" id="elbbvbsx4xbxt" class="animable"></path>
                <path d="M227,140.41a.09.09,0,0,1-.11,0,.43.43,0,0,1-.07-.1l-1.48-2.57v3.45a.28.28,0,0,1,0,.15.24.24,0,0,1-.08.11l-.19.1a.06.06,0,0,1-.08,0,.14.14,0,0,1,0-.11V136.9a.28.28,0,0,1,0-.15.24.24,0,0,1,.08-.11l.16-.09a.09.09,0,0,1,.11,0,.26.26,0,0,1,.07.1l1.48,2.57v-3.45a.28.28,0,0,1,0-.15.24.24,0,0,1,.08-.11l.19-.1a.08.08,0,0,1,.09,0,.18.18,0,0,1,0,.11v4.52a.41.41,0,0,1,0,.16.35.35,0,0,1-.09.11Z" style="fill: rgb(69, 90, 100); transform-origin: 226.119px 138.481px;" id="elyvazd90ks7c" class="animable"></path>
                <path d="M251.19,137.45a.37.37,0,0,1-.21-.06.57.57,0,0,1-.25-.52v-6.71a1.23,1.23,0,0,1,.53-1l7.07-4.09a.51.51,0,0,1,.49,0,.57.57,0,0,1,.25.52v6.71a1.24,1.24,0,0,1-.54,1h0l-7.07,4.09A.56.56,0,0,1,251.19,137.45Zm7.42-12.06-.08,0-7.07,4.08a.84.84,0,0,0-.33.66v6.71a.25.25,0,0,0,.05.18.13.13,0,0,0,.08,0l7.07-4.08a.84.84,0,0,0,.34-.66v-6.71c0-.1,0-.16-.05-.17Z" style="fill: rgb(146, 227, 169); transform-origin: 254.9px 131.229px;" id="el2a3n5i48iei" class="animable"></path>
                <polygon points="258.87 128.23 250.93 132.81 250.93 131.65 258.87 127.07 258.87 128.23" style="fill: rgb(146, 227, 169); transform-origin: 254.9px 129.94px;" id="el41fad8p8jht" class="animable"></polygon>
                <path d="M253.74,141.47a.16.16,0,0,1-.09,0,.18.18,0,0,1-.11-.17v-4.71a.2.2,0,0,1,.2-.2.2.2,0,0,1,.21.2v4.36L264.14,135v-7.7l-4.69,2.71a.2.2,0,0,1-.2-.35l5-2.88a.18.18,0,0,1,.2,0,.21.21,0,0,1,.1.17v8.16a.19.19,0,0,1-.1.17l-10.6,6.12A.17.17,0,0,1,253.74,141.47Z" style="fill: rgb(146, 227, 169); transform-origin: 259.045px 134.113px;" id="elp8zkucuikxa" class="animable"></path>
                <path d="M255.35,139.55a.18.18,0,0,1-.1,0,.21.21,0,0,1-.1-.18.93.93,0,0,0-.29-.79.36.36,0,0,0-.37,0,.18.18,0,0,1-.2,0,.19.19,0,0,1-.1-.17v-2.24a.2.2,0,0,1,.4,0v1.95a.64.64,0,0,1,.47.08,1.05,1.05,0,0,1,.47.8l7-4.05a2.77,2.77,0,0,1,1-2v-2.82a.72.72,0,0,1-.48-.07,1.09,1.09,0,0,1-.46-.81l-3.11,1.8a.2.2,0,0,1-.27-.08.19.19,0,0,1,.07-.27l3.39-1.95a.17.17,0,0,1,.19,0,.18.18,0,0,1,.11.17.91.91,0,0,0,.28.79.36.36,0,0,0,.38,0,.23.23,0,0,1,.2,0,.21.21,0,0,1,.1.18v3.21a.19.19,0,0,1-.1.17,2.37,2.37,0,0,0-.86,1.87.18.18,0,0,1-.11.17l-7.38,4.27A.18.18,0,0,1,255.35,139.55Z" style="fill: rgb(146, 227, 169); transform-origin: 259.06px 134.152px;" id="elscq3pb581e7" class="animable"></path>
                <path d="M258.52,136.78a1,1,0,0,1-.47-.13,1.83,1.83,0,0,1-.69-1.66,5.62,5.62,0,0,1,.05-.7.21.21,0,0,1,.24-.16.2.2,0,0,1,.16.23,3.41,3.41,0,0,0-.05.63,1.48,1.48,0,0,0,.49,1.31.68.68,0,0,0,.69-.05,3.71,3.71,0,0,0,1.39-3,1.62,1.62,0,0,0-.43-1.27.56.56,0,0,0-.51-.09.2.2,0,0,1-.24-.14.2.2,0,0,1,.14-.25,1,1,0,0,1,.85.16,2,2,0,0,1,.59,1.59,4.07,4.07,0,0,1-1.59,3.32A1.17,1.17,0,0,1,258.52,136.78Z" style="fill: rgb(146, 227, 169); transform-origin: 259.042px 134.123px;" id="elum8npcjjfqe" class="animable"></path>
                <path d="M254.56,147a4.51,4.51,0,0,1-2.28-.61c-2.29-1.32-3.61-4.58-3.61-8.94,0-7.5,4-15.89,8.86-18.71,2-1.14,3.86-1.26,5.45-.34,2.3,1.32,3.61,4.58,3.61,8.94,0,7.5-4,15.89-8.85,18.71A6.34,6.34,0,0,1,254.56,147Zm6.15-28.8a5.94,5.94,0,0,0-3,.9c-4.77,2.75-8.66,11-8.66,18.35,0,4.15,1.28,7.36,3.41,8.59a4.9,4.9,0,0,0,5-.34c4.77-2.76,8.65-11,8.65-18.36,0-4.14-1.27-7.35-3.4-8.59A4.19,4.19,0,0,0,260.71,118.17Z" style="fill: rgb(146, 227, 169); transform-origin: 257.63px 132.394px;" id="elvqa5alp65jk" class="animable"></path>
                <path d="M299.79,95.22a.24.24,0,0,1-.21-.12.25.25,0,0,1,.09-.34l7.06-4.08a.25.25,0,1,1,.25.43l-7.06,4.08A.31.31,0,0,1,299.79,95.22Z" style="fill: rgb(69, 90, 100); transform-origin: 303.337px 92.9261px;" id="el8awlpyrgvgy" class="animable"></path>
                <path d="M299.79,97.57a.24.24,0,0,1-.21-.12.26.26,0,0,1,.09-.35L306.73,93a.25.25,0,1,1,.25.44l-7.06,4.08A.31.31,0,0,1,299.79,97.57Z" style="fill: rgb(69, 90, 100); transform-origin: 303.327px 95.2685px;" id="elk4uq07a33x8" class="animable"></path>
                <path d="M299.79,99.92a.25.25,0,0,1-.12-.47l7.06-4.08a.25.25,0,1,1,.25.43l-7.06,4.08A.33.33,0,0,1,299.79,99.92Z" style="fill: rgb(69, 90, 100); transform-origin: 303.333px 97.6211px;" id="el8ozs7x44z4t" class="animable"></path>
                <path d="M209.37,192.08a.25.25,0,0,1-.22-.12.27.27,0,0,1,.09-.35l97.49-56.28a.25.25,0,1,1,.25.43l-97.49,56.29A.24.24,0,0,1,209.37,192.08Z" style="fill: rgb(146, 227, 169); transform-origin: 258.123px 163.681px;" id="el28cmeci755b" class="animable"></path>
                <path d="M209.37,248.28a.26.26,0,0,1-.22-.13.26.26,0,0,1,.09-.34l97.49-56.29a.27.27,0,0,1,.35.1.26.26,0,0,1-.1.34l-97.49,56.28A.25.25,0,0,1,209.37,248.28Z" style="fill: rgb(146, 227, 169); transform-origin: 258.114px 219.886px;" id="eliau1dhgpni" class="animable"></path>
                <path d="M211.53,253.8,304.69,200c1.2-.69,2.17-.13,2.17,1.25v4.38a4.78,4.78,0,0,1-2.17,3.75l-93.16,53.79c-1.19.69-2.16.13-2.16-1.25v-4.38A4.8,4.8,0,0,1,211.53,253.8Z" style="fill: rgb(224, 224, 224); transform-origin: 258.115px 231.585px;" id="eldepyyzyiv9" class="animable"></path>
                <path d="M222.17,249.91v1.15l-7,4v-1.15a.8.8,0,0,1,.34-.64l6.29-3.64C222,249.54,222.17,249.66,222.17,249.91Z" style="fill: rgb(69, 90, 100); transform-origin: 218.67px 252.33px;" id="elrv96i9zi6bm" class="animable"></path>
                <path d="M215.2,256l7-4v3.14a.81.81,0,0,1-.34.64l-6.29,3.63c-.18.11-.34,0-.34-.25Z" style="fill: rgb(69, 90, 100); transform-origin: 218.7px 255.726px;" id="elnmwq1b6588g" class="animable"></path>
                <path d="M226.21,249.27c0,.21,0,.41,0,.59s0,.38,0,.59a1.17,1.17,0,0,0,.23.81c.14.15.35.15.62,0a1.31,1.31,0,0,0,.36-.3,1.45,1.45,0,0,0,.25-.36,1.43,1.43,0,0,0,.15-.39,3,3,0,0,0,.08-.39.29.29,0,0,1,0-.14.19.19,0,0,1,.07-.08l.21-.13a.06.06,0,0,1,.07,0,.12.12,0,0,1,0,.09,2.43,2.43,0,0,1-.08.55,3.24,3.24,0,0,1-.22.64,4.12,4.12,0,0,1-.39.63,2.14,2.14,0,0,1-.58.5,1,1,0,0,1-.53.16.59.59,0,0,1-.4-.17,1,1,0,0,1-.26-.45,2.74,2.74,0,0,1-.1-.7v-1.24a4.48,4.48,0,0,1,.1-.82,3.49,3.49,0,0,1,.26-.75,2.64,2.64,0,0,1,.4-.62,1.74,1.74,0,0,1,.53-.45,1,1,0,0,1,.58-.17.63.63,0,0,1,.39.17.93.93,0,0,1,.22.39,1.44,1.44,0,0,1,.08.46.27.27,0,0,1,0,.12.22.22,0,0,1-.07.09L228,248H228s0,0,0-.09a1.67,1.67,0,0,0-.08-.3.44.44,0,0,0-.15-.22.39.39,0,0,0-.25-.07.69.69,0,0,0-.36.13,1.43,1.43,0,0,0-.62.72A2.5,2.5,0,0,0,226.21,249.27Z" style="fill: rgb(69, 90, 100); transform-origin: 226.974px 249.355px;" id="elw4paoydp5fq" class="animable"></path>
                <path d="M228.73,249.93a1.88,1.88,0,0,1,.07-.53,2.76,2.76,0,0,1,.2-.47,3.08,3.08,0,0,1,.3-.41,3,3,0,0,1,.36-.35l.64-.53v-.09q0-.36-.15-.45a.36.36,0,0,0-.38,0,.93.93,0,0,0-.3.29,2.12,2.12,0,0,0-.18.37.24.24,0,0,1-.06.11l-.08.07-.14.08a.06.06,0,0,1-.09,0,.16.16,0,0,1,0-.11,1.31,1.31,0,0,1,.08-.36,2.09,2.09,0,0,1,.19-.4,2.4,2.4,0,0,1,.28-.37,1.4,1.4,0,0,1,.33-.27.91.91,0,0,1,.38-.13.44.44,0,0,1,.3.1.65.65,0,0,1,.2.32,2,2,0,0,1,.07.55v2.13a.42.42,0,0,1,0,.16.2.2,0,0,1-.09.11l-.16.09s-.07,0-.09,0a.18.18,0,0,1,0-.11v-.29a1.32,1.32,0,0,1-.12.31,2.83,2.83,0,0,1-.2.28,2.66,2.66,0,0,1-.23.24,1.5,1.5,0,0,1-.23.17,1,1,0,0,1-.32.11.39.39,0,0,1-.25-.07.38.38,0,0,1-.16-.22A.93.93,0,0,1,228.73,249.93Zm.84-.09a1.73,1.73,0,0,0,.3-.25,2.12,2.12,0,0,0,.23-.35,1.56,1.56,0,0,0,.15-.41,1.73,1.73,0,0,0,.05-.45v-.18l-.53.44a2.08,2.08,0,0,0-.46.49.91.91,0,0,0-.17.5.35.35,0,0,0,0,.14.23.23,0,0,0,.07.1.18.18,0,0,0,.13,0A.53.53,0,0,0,229.57,249.84Z" style="fill: rgb(69, 90, 100); transform-origin: 229.744px 248.465px;" id="ele5rysc7qlsn" class="animable"></path>
                <path d="M232.6,245s.07,0,.09,0a.18.18,0,0,1,0,.11v.23a.42.42,0,0,1,0,.15.2.2,0,0,1-.09.11l-.26.15a1.16,1.16,0,0,0-.52,1.11v2a.53.53,0,0,1,0,.16.18.18,0,0,1-.09.1l-.16.1s-.07,0-.09,0a.12.12,0,0,1,0-.11v-3.26a.34.34,0,0,1,0-.16.18.18,0,0,1,.09-.1l.16-.1s.07,0,.09,0a.16.16,0,0,1,0,.11v.22a1.8,1.8,0,0,1,.21-.44,1.21,1.21,0,0,1,.34-.29Z" style="fill: rgb(69, 90, 100); transform-origin: 232.083px 247.11px;" id="elc1rct0d16jd" class="animable"></path>
                <path d="M234,244.09a.6.6,0,0,1,.45-.1.42.42,0,0,1,.25.17v-1.54a.47.47,0,0,1,0-.16.18.18,0,0,1,.09-.1l.16-.1s.07,0,.09,0a.16.16,0,0,1,0,.12V247a.28.28,0,0,1,0,.15.17.17,0,0,1-.09.11l-.16.1a.07.07,0,0,1-.09,0,.16.16,0,0,1,0-.11V247a1.89,1.89,0,0,1-.26.48,1.31,1.31,0,0,1-.44.4.73.73,0,0,1-.4.12.5.5,0,0,1-.32-.15,1,1,0,0,1-.22-.4,2.66,2.66,0,0,1-.09-.64,1.48,1.48,0,0,1,0-.22,1.69,1.69,0,0,1,0-.23,4.52,4.52,0,0,1,.09-.75,3.81,3.81,0,0,1,.22-.65,2.6,2.6,0,0,1,.32-.51A1.62,1.62,0,0,1,234,244.09Zm.7,1.79a2.27,2.27,0,0,0,0-.28,1.94,1.94,0,0,0,0-.27.94.94,0,0,0-.19-.66.37.37,0,0,0-.47,0,1.18,1.18,0,0,0-.47.57,2.49,2.49,0,0,0-.19.91v.39a1.1,1.1,0,0,0,.19.68c.12.13.27.14.47,0a1.2,1.2,0,0,0,.47-.54A2,2,0,0,0,234.71,245.88Z" style="fill: rgb(69, 90, 100); transform-origin: 234.009px 245.13px;" id="ele6stbgxgmqw" class="animable"></path>
                <path d="M243.71,242.26s-.07,0-.09,0a.21.21,0,0,1,0-.12v-.84l-1.41.81a.05.05,0,0,1-.08,0,.12.12,0,0,1,0-.11v-.5a.57.57,0,0,1,0-.18c0-.06,0-.13.07-.2l1.39-3.48a.55.55,0,0,1,.19-.28l.34-.2s.07,0,.09,0a.16.16,0,0,1,0,.12v2.8l.39-.23a.05.05,0,0,1,.08,0,.12.12,0,0,1,0,.11v.5a.28.28,0,0,1,0,.15.24.24,0,0,1-.08.11l-.39.22v.85a.28.28,0,0,1,0,.15.2.2,0,0,1-.09.11Zm-.13-1.84v-1.5l-.77,2Z" style="fill: rgb(250, 250, 250); transform-origin: 243.405px 239.71px;" id="elf81fk35vwic" class="animable"></path>
                <path d="M246.67,240.55a.06.06,0,0,1-.09,0,.16.16,0,0,1,0-.11v-.85l-1.41.82a.06.06,0,0,1-.09,0,.16.16,0,0,1,0-.11v-.5a.63.63,0,0,1,0-.19.89.89,0,0,1,.07-.19l1.39-3.48a.55.55,0,0,1,.18-.28l.35-.2a.08.08,0,0,1,.09,0,.18.18,0,0,1,0,.11v2.8l.39-.22s.07,0,.09,0a.16.16,0,0,1,0,.11v.49a.42.42,0,0,1,0,.16.18.18,0,0,1-.09.1l-.39.23v.85a.4.4,0,0,1,0,.15.35.35,0,0,1-.09.11Zm-.12-1.84v-1.5l-.78,1.95Z" style="fill: rgb(250, 250, 250); transform-origin: 246.36px 238.008px;" id="elwbyckizytbg" class="animable"></path>
                <path d="M249.64,238.84s-.07,0-.09,0a.12.12,0,0,1,0-.11v-.85l-1.41.82a.06.06,0,0,1-.08,0,.12.12,0,0,1,0-.11v-.5a.63.63,0,0,1,0-.19l.07-.2,1.39-3.47a.58.58,0,0,1,.19-.29l.34-.2s.07,0,.09,0a.18.18,0,0,1,0,.11v2.8l.39-.22a.06.06,0,0,1,.08,0,.15.15,0,0,1,0,.12V237a.32.32,0,0,1,0,.16.21.21,0,0,1-.08.1l-.39.23v.85a.42.42,0,0,1,0,.15.2.2,0,0,1-.09.11Zm-.13-1.84v-1.5l-.77,1.95Z" style="fill: rgb(250, 250, 250); transform-origin: 249.335px 236.29px;" id="ely2d0i55j0xc" class="animable"></path>
                <path d="M252.6,237.13a.08.08,0,0,1-.09,0,.16.16,0,0,1,0-.11v-.85l-1.41.81s-.07,0-.09,0a.16.16,0,0,1,0-.11v-.5a.63.63,0,0,1,0-.19c0-.06.05-.12.07-.2l1.4-3.47a.6.6,0,0,1,.18-.29l.35-.2a.06.06,0,0,1,.08,0,.12.12,0,0,1,0,.11v2.8l.39-.22a.07.07,0,0,1,.09,0,.18.18,0,0,1,0,.11v.5a.5.5,0,0,1,0,.16.3.3,0,0,1-.09.1l-.39.23v.84a.29.29,0,0,1,0,.16.21.21,0,0,1-.08.1Zm-.12-1.84v-1.5l-.78,1.94Z" style="fill: rgb(250, 250, 250); transform-origin: 252.289px 234.574px;" id="el43z3i9z3s3j" class="animable"></path>
                <path d="M260,229.81v1.25a7,7,0,0,1-.08.8,4.27,4.27,0,0,1-.21.72,2.4,2.4,0,0,1-.38.61,2,2,0,0,1-.56.47,1,1,0,0,1-.56.18.47.47,0,0,1-.37-.18,1.06,1.06,0,0,1-.21-.48,4.65,4.65,0,0,1-.08-.71c0-.17,0-.37,0-.6s0-.43,0-.61a7.17,7.17,0,0,1,.08-.81,2.93,2.93,0,0,1,.21-.73,2.43,2.43,0,0,1,.37-.62,2,2,0,0,1,.56-.48,1,1,0,0,1,.56-.18.53.53,0,0,1,.38.18,1.31,1.31,0,0,1,.21.49A4.51,4.51,0,0,1,260,229.81Zm-1.84,2.27a1.44,1.44,0,0,0,.16.69c.09.13.24.14.45,0a1,1,0,0,0,.45-.54,2.7,2.7,0,0,0,.16-.87c0-.18,0-.38,0-.6s0-.4,0-.58a1.34,1.34,0,0,0-.16-.69c-.08-.13-.24-.14-.45,0a1,1,0,0,0-.45.53,3,3,0,0,0-.16.87c0,.19,0,.38,0,.6S258.11,231.91,258.11,232.08Z" style="fill: rgb(250, 250, 250); transform-origin: 258.775px 231.14px;" id="elw13jq60oytm" class="animable"></path>
                <path d="M262.93,228.09a2.53,2.53,0,0,1,0,.29V229a2.71,2.71,0,0,1,0,.29,5,5,0,0,1-.08.8,2.74,2.74,0,0,1-.21.72,2.27,2.27,0,0,1-.37.62,2.15,2.15,0,0,1-.56.47,1,1,0,0,1-.56.18.47.47,0,0,1-.37-.19,1,1,0,0,1-.21-.48,3.55,3.55,0,0,1-.08-.7c0-.18,0-.38,0-.61s0-.42,0-.6a5.29,5.29,0,0,1,.08-.81,2.81,2.81,0,0,1,.21-.73,2.22,2.22,0,0,1,.37-.63,2,2,0,0,1,.56-.47,1,1,0,0,1,.56-.18.47.47,0,0,1,.37.18,1.06,1.06,0,0,1,.21.48A3.66,3.66,0,0,1,262.93,228.09Zm-1.83,2.27a1.51,1.51,0,0,0,.16.69c.09.13.24.13.45,0a1,1,0,0,0,.45-.54,2.87,2.87,0,0,0,.16-.87c0-.18,0-.38,0-.59s0-.41,0-.58a1.44,1.44,0,0,0-.16-.69c-.09-.14-.24-.14-.45,0a1,1,0,0,0-.45.54,3,3,0,0,0-.16.87c0,.18,0,.38,0,.59S261.09,230.18,261.1,230.36Z" style="fill: rgb(250, 250, 250); transform-origin: 261.712px 229.38px;" id="elkbedsks28r" class="animable"></path>
                <path d="M265.92,226.36v1.25a6.77,6.77,0,0,1-.08.8,3,3,0,0,1-.21.72,2.21,2.21,0,0,1-.37.61,2,2,0,0,1-.56.47,1.09,1.09,0,0,1-.57.18.49.49,0,0,1-.37-.18,1.34,1.34,0,0,1-.21-.48,4.65,4.65,0,0,1-.08-.71v-1.21a7.17,7.17,0,0,1,.08-.81,4.12,4.12,0,0,1,.21-.73,2.75,2.75,0,0,1,.37-.62,1.92,1.92,0,0,1,.57-.48,1.13,1.13,0,0,1,.56-.18.48.48,0,0,1,.37.19,1,1,0,0,1,.21.48A4.51,4.51,0,0,1,265.92,226.36Zm-1.83,2.27a1.47,1.47,0,0,0,.15.69c.09.13.25.14.46,0a1,1,0,0,0,.45-.54,3,3,0,0,0,.16-.87c0-.18,0-.38,0-.6s0-.4,0-.58a1.51,1.51,0,0,0-.16-.69c-.09-.13-.24-.13-.45,0a1,1,0,0,0-.46.53,3,3,0,0,0-.15.88c0,.18,0,.37,0,.59S264.08,228.46,264.09,228.63Z" style="fill: rgb(250, 250, 250); transform-origin: 264.695px 227.69px;" id="el6fla735tcjn" class="animable"></path>
                <path d="M268.91,224.64v1.24a6.56,6.56,0,0,1-.08.8,4.12,4.12,0,0,1-.21.73,2.68,2.68,0,0,1-.37.61,2.2,2.2,0,0,1-.57.47,1,1,0,0,1-.56.18.5.5,0,0,1-.37-.19,1,1,0,0,1-.21-.48,4.51,4.51,0,0,1-.08-.7c0-.18,0-.38,0-.6s0-.43,0-.61a6.94,6.94,0,0,1,.08-.81,3,3,0,0,1,.58-1.36,2.15,2.15,0,0,1,.56-.47,1.06,1.06,0,0,1,.57-.18.49.49,0,0,1,.37.18,1.34,1.34,0,0,1,.21.48A4.65,4.65,0,0,1,268.91,224.64Zm-1.83,2.27a1.38,1.38,0,0,0,.15.69c.09.13.24.13.45,0a1,1,0,0,0,.46-.53,2.73,2.73,0,0,0,.15-.88c0-.18,0-.38,0-.59s0-.41,0-.58a1.35,1.35,0,0,0-.15-.69c-.09-.13-.24-.14-.46,0a1,1,0,0,0-.45.54,2.72,2.72,0,0,0-.15.87c0,.18,0,.38,0,.59S267.07,226.73,267.08,226.91Z" style="fill: rgb(250, 250, 250); transform-origin: 267.685px 225.97px;" id="ell10z76nz97" class="animable"></path>
                <path d="M272.74,224a2.69,2.69,0,0,1,.1-.7,2.53,2.53,0,0,1,.29-.65,1,1,0,0,1-.27-.8,2.28,2.28,0,0,1,.09-.63,2.57,2.57,0,0,1,.23-.6,2.25,2.25,0,0,1,.37-.49,2,2,0,0,1,.45-.37,1.17,1.17,0,0,1,.45-.16.58.58,0,0,1,.37.08.61.61,0,0,1,.23.32,1.38,1.38,0,0,1,.09.54,2.17,2.17,0,0,1-.07.57,2.41,2.41,0,0,1-.2.53.65.65,0,0,1,.29.32,1.63,1.63,0,0,1,.09.59,2.51,2.51,0,0,1-.08.67,2.7,2.7,0,0,1-.26.63,2.66,2.66,0,0,1-.39.54,2.54,2.54,0,0,1-.52.41,1.38,1.38,0,0,1-.52.19.59.59,0,0,1-.65-.42A1.55,1.55,0,0,1,272.74,224Zm.62-.35a.58.58,0,0,0,.05.25.29.29,0,0,0,.15.12.34.34,0,0,0,.2,0A.78.78,0,0,0,274,224a1.88,1.88,0,0,0,.24-.18,2.12,2.12,0,0,0,.2-.24,1.59,1.59,0,0,0,.15-.29,1.12,1.12,0,0,0,.05-.31.55.55,0,0,0-.05-.25.29.29,0,0,0-.15-.12.34.34,0,0,0-.2,0,.59.59,0,0,0-.24.09,1.88,1.88,0,0,0-.24.18,1.16,1.16,0,0,0-.2.24,1.51,1.51,0,0,0-.15.28A1.13,1.13,0,0,0,273.36,223.69Zm.11-2.15a.45.45,0,0,0,0,.22.2.2,0,0,0,.11.11.32.32,0,0,0,.17,0,1,1,0,0,0,.2-.08,1.3,1.3,0,0,0,.2-.15,1.2,1.2,0,0,0,.17-.21.66.66,0,0,0,.11-.24.81.81,0,0,0,0-.27.45.45,0,0,0,0-.22.2.2,0,0,0-.11-.11h-.17a.56.56,0,0,0-.2.08,1,1,0,0,0-.2.15,1.12,1.12,0,0,0-.17.2.66.66,0,0,0-.11.24A.86.86,0,0,0,273.47,221.54Z" style="fill: rgb(250, 250, 250); transform-origin: 273.995px 222.296px;" id="el9vq8ksqs2cd" class="animable"></path>
                <path d="M275.72,222.32a2.32,2.32,0,0,1,.1-.7,2.66,2.66,0,0,1,.28-.65,1,1,0,0,1-.26-.79,2.74,2.74,0,0,1,.08-.64,2.81,2.81,0,0,1,.24-.59,2.06,2.06,0,0,1,.36-.5,2,2,0,0,1,.46-.37,1.11,1.11,0,0,1,.45-.16.6.6,0,0,1,.36.08.73.73,0,0,1,.24.32,1.42,1.42,0,0,1,.09.54,2.69,2.69,0,0,1-.07.57,2.49,2.49,0,0,1-.2.54.57.57,0,0,1,.28.31,1.42,1.42,0,0,1,.1.59,2.59,2.59,0,0,1-.09.68,2.78,2.78,0,0,1-.25.62,2.36,2.36,0,0,1-.4.54,2,2,0,0,1-.51.41,1.28,1.28,0,0,1-.52.19.57.57,0,0,1-.65-.42A1.55,1.55,0,0,1,275.72,222.32Zm.62-.35a.47.47,0,0,0,.05.25.27.27,0,0,0,.14.12.37.37,0,0,0,.21,0,.65.65,0,0,0,.24-.09l.24-.18.2-.24a1.11,1.11,0,0,0,.14-.29.85.85,0,0,0,.06-.31.44.44,0,0,0-.06-.25.24.24,0,0,0-.14-.12.34.34,0,0,0-.2,0,.78.78,0,0,0-.24.09,1.37,1.37,0,0,0-.24.18,1.57,1.57,0,0,0-.21.24,1.47,1.47,0,0,0-.14.28A.89.89,0,0,0,276.34,222Zm.11-2.15a.43.43,0,0,0,0,.22.21.21,0,0,0,.12.11.32.32,0,0,0,.17,0,1,1,0,0,0,.2-.08,1.25,1.25,0,0,0,.19-.15,1.06,1.06,0,0,0,.17-.2,1.15,1.15,0,0,0,.12-.25.77.77,0,0,0,0-.27.48.48,0,0,0,0-.22.19.19,0,0,0-.12-.1.31.31,0,0,0-.17,0,.52.52,0,0,0-.19.08,1,1,0,0,0-.2.15,1.12,1.12,0,0,0-.17.2,1.34,1.34,0,0,0-.12.24A.84.84,0,0,0,276.45,219.82Z" style="fill: rgb(250, 250, 250); transform-origin: 276.975px 220.618px;" id="el744zkx1qlha" class="animable"></path>
                <path d="M278.7,220.6a2.37,2.37,0,0,1,.1-.7,2.66,2.66,0,0,1,.28-.65,1,1,0,0,1-.27-.79,2.3,2.3,0,0,1,.09-.64,2.81,2.81,0,0,1,.24-.59,2.28,2.28,0,0,1,.36-.5,2.21,2.21,0,0,1,.45-.37,1.41,1.41,0,0,1,.46-.16.6.6,0,0,1,.36.08.73.73,0,0,1,.24.32,1.42,1.42,0,0,1,.09.54,2.62,2.62,0,0,1-.07.57,3.16,3.16,0,0,1-.2.54.61.61,0,0,1,.28.31,1.42,1.42,0,0,1,.1.59,2.7,2.7,0,0,1-.34,1.3,2.36,2.36,0,0,1-.4.54,1.89,1.89,0,0,1-.52.41,1.24,1.24,0,0,1-.51.19.58.58,0,0,1-.4-.08.65.65,0,0,1-.25-.34A1.55,1.55,0,0,1,278.7,220.6Zm.61-.35a.46.46,0,0,0,.06.25.27.27,0,0,0,.14.12.37.37,0,0,0,.21,0,.61.61,0,0,0,.23-.09,1.08,1.08,0,0,0,.24-.18,1.21,1.21,0,0,0,.21-.24,1.55,1.55,0,0,0,.14-.29.85.85,0,0,0,.06-.31.44.44,0,0,0-.06-.25.27.27,0,0,0-.14-.12.37.37,0,0,0-.21,0,1,1,0,0,0-.24.09,1.3,1.3,0,0,0-.23.18,1.57,1.57,0,0,0-.21.24,1.55,1.55,0,0,0-.14.29A.81.81,0,0,0,279.31,220.25Zm.12-2.15a.43.43,0,0,0,0,.22.21.21,0,0,0,.12.11.32.32,0,0,0,.17,0,.52.52,0,0,0,.19-.08,1,1,0,0,0,.2-.15,1.63,1.63,0,0,0,.17-.2,1.15,1.15,0,0,0,.12-.25.77.77,0,0,0,0-.27.48.48,0,0,0,0-.22.19.19,0,0,0-.12-.1.32.32,0,0,0-.17,0,.64.64,0,0,0-.2.08,1.25,1.25,0,0,0-.19.15,1.12,1.12,0,0,0-.17.2,1.34,1.34,0,0,0-.12.24A.84.84,0,0,0,279.43,218.1Z" style="fill: rgb(250, 250, 250); transform-origin: 279.955px 218.898px;" id="elmvegyh1yl4" class="animable"></path>
                <path d="M281.68,218.88a2.37,2.37,0,0,1,.1-.7,2.44,2.44,0,0,1,.28-.64,1,1,0,0,1-.27-.8,2.3,2.3,0,0,1,.09-.64,2.36,2.36,0,0,1,.24-.59,2.28,2.28,0,0,1,.36-.5,2.21,2.21,0,0,1,.45-.37,1.41,1.41,0,0,1,.46-.16.6.6,0,0,1,.36.08.73.73,0,0,1,.24.32,1.71,1.71,0,0,1,.08.54,2.12,2.12,0,0,1-.07.57,2.47,2.47,0,0,1-.19.54.61.61,0,0,1,.28.31,1.61,1.61,0,0,1,.1.59,2.7,2.7,0,0,1-.34,1.3,2.36,2.36,0,0,1-.4.54,2,2,0,0,1-.52.41,1.11,1.11,0,0,1-.52.19.57.57,0,0,1-.39-.08.65.65,0,0,1-.25-.34A1.55,1.55,0,0,1,281.68,218.88Zm.61-.35a.44.44,0,0,0,.06.25.24.24,0,0,0,.14.12.34.34,0,0,0,.2,0,.59.59,0,0,0,.24-.09l.24-.18a1.21,1.21,0,0,0,.21-.24,2,2,0,0,0,.14-.29.84.84,0,0,0,.05-.31.44.44,0,0,0-.05-.25.31.31,0,0,0-.14-.12.37.37,0,0,0-.21,0,.78.78,0,0,0-.24.09l-.24.18-.2.24a1.11,1.11,0,0,0-.14.29A.81.81,0,0,0,282.29,218.53Zm.12-2.15a.43.43,0,0,0,0,.22.21.21,0,0,0,.12.11.29.29,0,0,0,.16,0,.56.56,0,0,0,.2-.08,1.3,1.3,0,0,0,.2-.15,1.63,1.63,0,0,0,.17-.2,1.15,1.15,0,0,0,.12-.25.77.77,0,0,0,0-.27.48.48,0,0,0,0-.22.23.23,0,0,0-.12-.1.32.32,0,0,0-.17,0,.84.84,0,0,0-.2.08,1.3,1.3,0,0,0-.2.15,1.08,1.08,0,0,0-.16.2,1.34,1.34,0,0,0-.12.24A.84.84,0,0,0,282.41,216.38Z" style="fill: rgb(250, 250, 250); transform-origin: 282.935px 217.177px;" id="elu3bze579yns" class="animable"></path>
                <path d="M288,215a3.39,3.39,0,0,1,.1-.74q.09-.33.21-.66l.86-2.37.05-.12a.22.22,0,0,1,.09-.1l.41-.24a.07.07,0,0,1,.09,0,.16.16,0,0,1,0,.11s0,0,0,.06,0,0,0,0l-.56,1.58a1.17,1.17,0,0,1,.49-.16.59.59,0,0,1,.39.13.84.84,0,0,1,.25.4,2,2,0,0,1,.09.64,3.14,3.14,0,0,1-.09.75,3.07,3.07,0,0,1-.65,1.28,1.92,1.92,0,0,1-.5.41,1,1,0,0,1-.5.16.59.59,0,0,1-.39-.12.89.89,0,0,1-.25-.4A1.83,1.83,0,0,1,288,215Zm1.86-1.07a.65.65,0,0,0-.16-.5c-.11-.1-.26-.09-.46,0a1.09,1.09,0,0,0-.46.51,1.66,1.66,0,0,0-.16.69.71.71,0,0,0,.16.51c.11.1.26.1.46,0a1.17,1.17,0,0,0,.46-.52A1.56,1.56,0,0,0,289.88,213.94Z" style="fill: rgb(250, 250, 250); transform-origin: 289.235px 213.442px;" id="ell1yi3u4t5pi" class="animable"></path>
                <path d="M290.87,213.37a2.89,2.89,0,0,1,.09-.74q.09-.33.21-.66l.86-2.37.05-.12a.19.19,0,0,1,.1-.1l.4-.24a.07.07,0,0,1,.09,0,.12.12,0,0,1,0,.11s0,0,0,.06a.05.05,0,0,1,0,0l-.56,1.58a1.17,1.17,0,0,1,.49-.16.59.59,0,0,1,.39.13.85.85,0,0,1,.25.39,2,2,0,0,1,.09.65,3.14,3.14,0,0,1-.09.75,3.25,3.25,0,0,1-.26.7,2.87,2.87,0,0,1-.39.57,1.78,1.78,0,0,1-.5.42,1.08,1.08,0,0,1-.5.16.59.59,0,0,1-.39-.12.89.89,0,0,1-.25-.4A2.1,2.1,0,0,1,290.87,213.37Zm1.85-1.07a.64.64,0,0,0-.16-.5c-.11-.1-.26-.09-.46,0a1.08,1.08,0,0,0-.45.51,1.53,1.53,0,0,0-.17.69.66.66,0,0,0,.17.51c.1.1.26.1.45,0a1.09,1.09,0,0,0,.46-.51A1.56,1.56,0,0,0,292.72,212.3Z" style="fill: rgb(250, 250, 250); transform-origin: 292.1px 211.812px;" id="eltp409k4ywj" class="animable"></path>
                <path d="M293.71,211.73a2.89,2.89,0,0,1,.09-.74q.09-.33.21-.66l.87-2.37a.56.56,0,0,1,0-.12.19.19,0,0,1,.1-.1l.41-.24a.05.05,0,0,1,.08,0,.12.12,0,0,1,0,.11.13.13,0,0,1,0,.06s0,0,0,0L295,209.3a1.08,1.08,0,0,1,.5-.16.57.57,0,0,1,.38.13.85.85,0,0,1,.25.39,2,2,0,0,1,.09.65,3.36,3.36,0,0,1-.34,1.45,2.54,2.54,0,0,1-.4.57,1.81,1.81,0,0,1-.5.42,1,1,0,0,1-.5.16.54.54,0,0,1-.39-.13.8.8,0,0,1-.25-.39A2.1,2.1,0,0,1,293.71,211.73Zm1.85-1.07a.64.64,0,0,0-.16-.5c-.11-.1-.26-.09-.46,0a1.16,1.16,0,0,0-.45.5,1.59,1.59,0,0,0-.16.7.66.66,0,0,0,.16.51c.11.1.26.1.45,0a1.09,1.09,0,0,0,.46-.51A1.56,1.56,0,0,0,295.56,210.66Z" style="fill: rgb(250, 250, 250); transform-origin: 294.965px 210.196px;" id="elwm3ocek4vzr" class="animable"></path>
                <path d="M296.55,210.09a2.83,2.83,0,0,1,.09-.74q.09-.33.21-.66l.87-2.37a.31.31,0,0,1,0-.12.15.15,0,0,1,.09-.1l.41-.24a.05.05,0,0,1,.08,0,.12.12,0,0,1,0,.11.13.13,0,0,1,0,.06s0,0,0,0l-.57,1.57a1.05,1.05,0,0,1,.5-.15.55.55,0,0,1,.38.13.85.85,0,0,1,.25.39,2,2,0,0,1,.09.65,3.2,3.2,0,0,1-.09.75,3,3,0,0,1-.25.69,2.68,2.68,0,0,1-.39.58,2.15,2.15,0,0,1-.5.42,1.11,1.11,0,0,1-.51.16.54.54,0,0,1-.39-.13.92.92,0,0,1-.25-.4A2,2,0,0,1,296.55,210.09ZM298.4,209a.64.64,0,0,0-.16-.5c-.1-.1-.26-.09-.45,0a1.12,1.12,0,0,0-.46.5,1.59,1.59,0,0,0-.16.7.68.68,0,0,0,.16.51c.11.1.26.1.46,0a1.08,1.08,0,0,0,.45-.51A1.59,1.59,0,0,0,298.4,209Z" style="fill: rgb(250, 250, 250); transform-origin: 297.743px 208.531px;" id="eljee57ia4cgq" class="animable"></path>
                <path d="M211.77,201.48a.08.08,0,0,1,.09,0,.21.21,0,0,1,0,.12v.25a.4.4,0,0,1,0,.15.35.35,0,0,1-.09.11l-1.84,1.06a.05.05,0,0,1-.08,0,.12.12,0,0,1,0-.11v-4.52a.28.28,0,0,1,0-.15.2.2,0,0,1,.08-.11l1.8-1s.07,0,.09,0a.16.16,0,0,1,0,.11v.25a.42.42,0,0,1,0,.15.2.2,0,0,1-.09.11l-1.49.86v1.48l1.39-.81s.07,0,.09,0a.18.18,0,0,1,0,.11v.25a.42.42,0,0,1,0,.16.15.15,0,0,1-.09.1l-1.39.81v1.53Z" style="fill: rgb(69, 90, 100); transform-origin: 210.853px 200.235px;" id="elqd57cwbndvs" class="animable"></path>
                <path d="M212.89,198.23,213,198a1.59,1.59,0,0,1,.13-.22,1.12,1.12,0,0,1,.17-.21,1,1,0,0,1,.22-.17.62.62,0,0,1,.46-.11.47.47,0,0,1,.28.29,2.88,2.88,0,0,1,.13-.31,2,2,0,0,1,.15-.29,1.59,1.59,0,0,1,.2-.24,1.46,1.46,0,0,1,.27-.2c.3-.17.52-.16.66,0a1.9,1.9,0,0,1,.21,1v1.94a.4.4,0,0,1,0,.15.35.35,0,0,1-.09.11l-.17.09a.06.06,0,0,1-.08,0,.14.14,0,0,1,0-.11v-1.87a1.3,1.3,0,0,0-.14-.72c-.09-.12-.22-.12-.4,0a1,1,0,0,0-.39.47,2,2,0,0,0-.15.84v1.92a.4.4,0,0,1,0,.15.2.2,0,0,1-.09.11l-.17.09a.06.06,0,0,1-.08,0,.15.15,0,0,1,0-.12v-1.86a1.21,1.21,0,0,0-.14-.72q-.13-.18-.39,0a1,1,0,0,0-.39.47,2.12,2.12,0,0,0-.15.85v1.91a.29.29,0,0,1,0,.16.21.21,0,0,1-.08.1l-.17.1a.07.07,0,0,1-.09,0,.21.21,0,0,1,0-.12v-3.26a.42.42,0,0,1,0-.15s.05-.09.09-.11l.17-.1a.06.06,0,0,1,.08,0,.12.12,0,0,1,0,.11Z" style="fill: rgb(69, 90, 100); transform-origin: 214.294px 199.016px;" id="elmp8upges4ie" class="animable"></path>
                <path d="M216.38,198.53a1.88,1.88,0,0,1,.07-.53,2,2,0,0,1,.2-.47,1.73,1.73,0,0,1,.3-.41,3,3,0,0,1,.36-.35l.64-.53v-.09q0-.36-.15-.45a.36.36,0,0,0-.38,0,.7.7,0,0,0-.3.29,2,2,0,0,0-.18.36.67.67,0,0,1-.06.12l-.09.07-.13.07a.07.07,0,0,1-.09,0,.12.12,0,0,1,0-.11,1.35,1.35,0,0,1,.09-.36,1.62,1.62,0,0,1,.19-.4,2.51,2.51,0,0,1,.27-.37,1.68,1.68,0,0,1,.34-.27.86.86,0,0,1,.38-.13.41.41,0,0,1,.3.09.67.67,0,0,1,.19.32,1.78,1.78,0,0,1,.07.56v2.13a.45.45,0,0,1,0,.16.3.3,0,0,1-.09.1l-.17.1a.05.05,0,0,1-.08,0,.12.12,0,0,1,0-.11v-.28a1.3,1.3,0,0,1-.13.3,1.87,1.87,0,0,1-.2.29,1.75,1.75,0,0,1-.23.24,1.34,1.34,0,0,1-.22.16.68.68,0,0,1-.33.11.35.35,0,0,1-.25-.06.47.47,0,0,1-.16-.22A1.25,1.25,0,0,1,216.38,198.53Zm.83-.1a1,1,0,0,0,.3-.25,2.08,2.08,0,0,0,.24-.34,2.6,2.6,0,0,0,.15-.42,2.14,2.14,0,0,0,0-.45v-.17l-.54.44a2.25,2.25,0,0,0-.45.49.88.88,0,0,0-.17.49.59.59,0,0,0,0,.14.23.23,0,0,0,.08.11.17.17,0,0,0,.13,0A.55.55,0,0,0,217.21,198.43Z" style="fill: rgb(69, 90, 100); transform-origin: 217.394px 197.056px;" id="elr3x1apledt" class="animable"></path>
                <path d="M219.51,193.26a.28.28,0,0,1,0,.15.24.24,0,0,1-.08.11l-.26.15a.07.07,0,0,1-.09,0,.16.16,0,0,1,0-.11v-.4a.42.42,0,0,1,0-.15.2.2,0,0,1,.09-.11l.26-.15a.05.05,0,0,1,.08,0,.12.12,0,0,1,0,.11Zm0,4.22a.4.4,0,0,1,0,.15.35.35,0,0,1-.09.11l-.17.1a.08.08,0,0,1-.08,0,.12.12,0,0,1,0-.11v-3.27a.28.28,0,0,1,0-.15.17.17,0,0,1,.08-.1l.17-.1a.07.07,0,0,1,.09,0,.21.21,0,0,1,0,.12Z" style="fill: rgb(69, 90, 100); transform-origin: 219.297px 195.29px;" id="elfdt0q2gmrmf" class="animable"></path>
                <path d="M220.58,196.83a.5.5,0,0,1,0,.16.3.3,0,0,1-.09.1l-.17.1a.06.06,0,0,1-.09,0,.16.16,0,0,1,0-.11v-4.59a.4.4,0,0,1,0-.15.26.26,0,0,1,.09-.11l.17-.1a.06.06,0,0,1,.09,0,.16.16,0,0,1,0,.11Z" style="fill: rgb(69, 90, 100); transform-origin: 220.405px 194.66px;" id="elvmqw76c090c" class="animable"></path>
                <path d="M211.53,206.91l93.16-53.79c1.2-.69,2.17-.13,2.17,1.25v4.38a4.78,4.78,0,0,1-2.17,3.75l-93.16,53.79c-1.19.69-2.16.13-2.16-1.25v-4.38A4.8,4.8,0,0,1,211.53,206.91Z" style="fill: rgb(224, 224, 224); transform-origin: 258.115px 184.705px;" id="elm0gd604jxf" class="animable"></path>
                <path d="M211.08,221.06a1.16,1.16,0,0,1,.42-.16.58.58,0,0,1,.35.07.66.66,0,0,1,.24.33,1.7,1.7,0,0,1,.08.61,2.74,2.74,0,0,1-.08.72,2.77,2.77,0,0,1-.24.61,2.2,2.2,0,0,1-.35.48,1.9,1.9,0,0,1-.42.34l-.84.48v1.71a.45.45,0,0,1,0,.16.3.3,0,0,1-.09.1l-.19.11a.06.06,0,0,1-.08,0,.16.16,0,0,1,0-.12V222a.28.28,0,0,1,0-.15.24.24,0,0,1,.08-.11Zm-.84,2.85.82-.47a1.44,1.44,0,0,0,.5-.51,1.54,1.54,0,0,0,.18-.77c0-.3-.06-.48-.18-.55a.55.55,0,0,0-.5.09l-.82.47Z" style="fill: rgb(69, 90, 100); transform-origin: 211.02px 223.766px;" id="elr4dpr1j8ygb" class="animable"></path>
                <path d="M212.48,224.23a1.88,1.88,0,0,1,.07-.53,2.46,2.46,0,0,1,.2-.47,3.08,3.08,0,0,1,.3-.41,3,3,0,0,1,.36-.35l.64-.53v-.09q0-.36-.15-.45a.36.36,0,0,0-.38,0,.93.93,0,0,0-.3.29,2.12,2.12,0,0,0-.18.37l-.06.11-.08.07-.14.08a.06.06,0,0,1-.09,0,.16.16,0,0,1,0-.11,1,1,0,0,1,.08-.36,1.62,1.62,0,0,1,.19-.4,2.4,2.4,0,0,1,.28-.37,1.4,1.4,0,0,1,.33-.27.91.91,0,0,1,.38-.13.49.49,0,0,1,.3.09.74.74,0,0,1,.2.33,2,2,0,0,1,.07.55v2.13a.42.42,0,0,1,0,.16.2.2,0,0,1-.09.11l-.16.09s-.07,0-.09,0a.15.15,0,0,1,0-.12v-.28a1.51,1.51,0,0,1-.12.31,2.83,2.83,0,0,1-.2.28,2.66,2.66,0,0,1-.23.24l-.23.16a.85.85,0,0,1-.32.12.39.39,0,0,1-.25-.07.38.38,0,0,1-.16-.22A1,1,0,0,1,212.48,224.23Zm.84-.09a1.73,1.73,0,0,0,.3-.25,2.12,2.12,0,0,0,.23-.35,2.86,2.86,0,0,0,.15-.41,2.3,2.3,0,0,0,.05-.45v-.18l-.53.44a2.08,2.08,0,0,0-.46.49.91.91,0,0,0-.17.5.35.35,0,0,0,0,.14.17.17,0,0,0,.07.1.18.18,0,0,0,.13,0A.53.53,0,0,0,213.32,224.14Z" style="fill: rgb(69, 90, 100); transform-origin: 213.494px 222.765px;" id="elkfrr22bp1c" class="animable"></path>
                <path d="M216.5,221.82a.31.31,0,0,0-.05-.19.21.21,0,0,0-.14-.07,1.19,1.19,0,0,0-.25,0l-.34.08c-.24.06-.42,0-.52-.1a.94.94,0,0,1-.14-.56,1.63,1.63,0,0,1,.05-.43,1.92,1.92,0,0,1,.17-.43,1.66,1.66,0,0,1,.66-.7,1.09,1.09,0,0,1,.39-.14.51.51,0,0,1,.28,0,.44.44,0,0,1,.17.16.4.4,0,0,1,.06.22.24.24,0,0,1,0,.16.3.3,0,0,1-.09.1l-.15.09-.06,0a.1.1,0,0,1-.08,0,.2.2,0,0,0-.17-.1.57.57,0,0,0-.32.11,1,1,0,0,0-.34.31.68.68,0,0,0-.13.4.35.35,0,0,0,0,.2.14.14,0,0,0,.11.07.6.6,0,0,0,.22,0l.34-.07a.89.89,0,0,1,.35,0,.4.4,0,0,1,.22.12.52.52,0,0,1,.13.23,1.51,1.51,0,0,1,0,.32,1.86,1.86,0,0,1-.06.45,2.88,2.88,0,0,1-.19.45,2,2,0,0,1-.3.41,1.52,1.52,0,0,1-.4.32,1.14,1.14,0,0,1-.39.15.68.68,0,0,1-.3,0,.42.42,0,0,1-.19-.16.45.45,0,0,1-.07-.23.4.4,0,0,1,0-.15.35.35,0,0,1,.09-.11l.17-.09.06,0a.08.08,0,0,1,.07,0,.25.25,0,0,0,.2.13.67.67,0,0,0,.33-.11,1.3,1.3,0,0,0,.2-.15,1.63,1.63,0,0,0,.17-.2.94.94,0,0,0,.12-.22A.59.59,0,0,0,216.5,221.82Z" style="fill: rgb(69, 90, 100); transform-origin: 215.921px 221.329px;" id="eljc9wc1mrw6i" class="animable"></path>
                <path d="M218.82,220.48a.26.26,0,0,0,0-.19.21.21,0,0,0-.14-.07,1.15,1.15,0,0,0-.24,0l-.35.08c-.24.06-.41,0-.51-.1a.87.87,0,0,1-.15-.56,1.65,1.65,0,0,1,.06-.43,1.72,1.72,0,0,1,.16-.43,1.66,1.66,0,0,1,.66-.7,1.09,1.09,0,0,1,.39-.14.42.42,0,0,1,.45.18.62.62,0,0,1,.07.23.28.28,0,0,1,0,.15.17.17,0,0,1-.09.11l-.15.09-.06,0a.08.08,0,0,1-.07,0,.24.24,0,0,0-.18-.1.66.66,0,0,0-.32.1,1.16,1.16,0,0,0-.34.32.76.76,0,0,0-.13.4.46.46,0,0,0,0,.2.15.15,0,0,0,.12.07.56.56,0,0,0,.21,0l.34-.07a.73.73,0,0,1,.35,0,.46.46,0,0,1,.23.12.55.55,0,0,1,.12.23,1.47,1.47,0,0,1,0,.32,1.9,1.9,0,0,1-.07.45,2.16,2.16,0,0,1-.19.45,2.26,2.26,0,0,1-.3.41,1.57,1.57,0,0,1-.4.32.88.88,0,0,1-.39.14.71.71,0,0,1-.3,0,.4.4,0,0,1-.18-.16.49.49,0,0,1-.08-.23.28.28,0,0,1,0-.15.2.2,0,0,1,.09-.11l.16-.09.06,0s0,0,.07,0a.27.27,0,0,0,.2.13.64.64,0,0,0,.33-.11,1,1,0,0,0,.2-.15,1.12,1.12,0,0,0,.18-.2,1.55,1.55,0,0,0,.12-.22A.6.6,0,0,0,218.82,220.48Z" style="fill: rgb(69, 90, 100); transform-origin: 218.294px 220.002px;" id="ellvwcx31a2j" class="animable"></path>
                <path d="M222,218.55l.51-2.64a.48.48,0,0,1,.05-.14.22.22,0,0,1,.1-.11l.15-.09a.06.06,0,0,1,.08,0,.15.15,0,0,1,0,.1v.06l-.7,3.62a.69.69,0,0,1-.05.17.2.2,0,0,1-.09.11l-.13.08s-.07,0-.09,0a.54.54,0,0,1-.06-.11l-.54-2-.54,2.64a.84.84,0,0,1-.05.17.24.24,0,0,1-.09.11l-.14.08s-.07,0-.09,0a.38.38,0,0,1-.05-.11l-.69-2.82a.07.07,0,0,1,0,0,.35.35,0,0,1,0-.14.36.36,0,0,1,.08-.09l.15-.09c.05,0,.08,0,.11,0a.22.22,0,0,1,0,.08l.51,2,.54-2.65a.65.65,0,0,1,0-.13.29.29,0,0,1,.1-.12l.1-.05s.08,0,.1,0a.36.36,0,0,1,0,.09Z" style="fill: rgb(69, 90, 100); transform-origin: 221.236px 218.077px;" id="el8dvui7jig1d" class="animable"></path>
                <path d="M224.31,214.61a.86.86,0,0,1,.44-.14.54.54,0,0,1,.33.13.88.88,0,0,1,.21.37,2,2,0,0,1,.1.58s0,.09,0,.15v.36c0,.06,0,.11,0,.15a5,5,0,0,1-.1.69,2.74,2.74,0,0,1-.21.61,2.23,2.23,0,0,1-.33.51,1.55,1.55,0,0,1-.44.37.93.93,0,0,1-.43.14.54.54,0,0,1-.33-.13,1,1,0,0,1-.22-.37,2.28,2.28,0,0,1-.09-.58.57.57,0,0,1,0-.14V217a.81.81,0,0,1,0-.16,4.08,4.08,0,0,1,.09-.69,3.77,3.77,0,0,1,.22-.62,2.41,2.41,0,0,1,.33-.5A1.51,1.51,0,0,1,224.31,214.61Zm.67,1.8c0-.08,0-.18,0-.3s0-.21,0-.29a1.7,1.7,0,0,0-.06-.35.65.65,0,0,0-.13-.25.34.34,0,0,0-.2-.09.48.48,0,0,0-.28.08,1.23,1.23,0,0,0-.28.24,1.69,1.69,0,0,0-.19.32,1.63,1.63,0,0,0-.13.39,3,3,0,0,0-.06.43,2.81,2.81,0,0,0,0,.3,2.44,2.44,0,0,0,0,.29,1.93,1.93,0,0,0,.06.36.55.55,0,0,0,.13.24.28.28,0,0,0,.19.09.45.45,0,0,0,.28-.08.92.92,0,0,0,.28-.24,1.38,1.38,0,0,0,.2-.33,2.12,2.12,0,0,0,.13-.38A3,3,0,0,0,225,216.41Z" style="fill: rgb(69, 90, 100); transform-origin: 224.313px 216.5px;" id="el0s39sglxwyyr" class="animable"></path>
                <path d="M227.19,213a.08.08,0,0,1,.09,0,.19.19,0,0,1,0,.12v.22a.4.4,0,0,1,0,.15.35.35,0,0,1-.09.11l-.26.15a1.17,1.17,0,0,0-.53,1.11v2a.45.45,0,0,1,0,.16.22.22,0,0,1-.09.1l-.17.1a.05.05,0,0,1-.08,0,.12.12,0,0,1,0-.11V213.9a.28.28,0,0,1,0-.15.2.2,0,0,1,.08-.11l.17-.1a.07.07,0,0,1,.09,0,.16.16,0,0,1,0,.11v.22a1.8,1.8,0,0,1,.22-.44,1.32,1.32,0,0,1,.33-.29Z" style="fill: rgb(69, 90, 100); transform-origin: 226.668px 215.113px;" id="elv97f4cw8m2m" class="animable"></path>
                <path d="M228.6,212.14a.56.56,0,0,1,.45-.11.42.42,0,0,1,.25.18v-1.55a.32.32,0,0,1,0-.16.21.21,0,0,1,.08-.1l.17-.1s.06,0,.09,0a.16.16,0,0,1,0,.11V215a.5.5,0,0,1,0,.16.22.22,0,0,1-.09.1l-.17.1a.05.05,0,0,1-.08,0,.12.12,0,0,1,0-.11V215a2.33,2.33,0,0,1-.26.49,1.42,1.42,0,0,1-.44.4.74.74,0,0,1-.4.11.5.5,0,0,1-.32-.15.89.89,0,0,1-.22-.4,2.6,2.6,0,0,1-.1-.63v-.46a4.52,4.52,0,0,1,.1-.75,3,3,0,0,1,.22-.64,2.41,2.41,0,0,1,.32-.52A1.27,1.27,0,0,1,228.6,212.14Zm.7,1.78c0-.07,0-.16,0-.28s0-.2,0-.27a.94.94,0,0,0-.19-.66.37.37,0,0,0-.47,0,1.17,1.17,0,0,0-.47.57,2.3,2.3,0,0,0-.19.91,2.54,2.54,0,0,0,0,.39,1.07,1.07,0,0,0,.19.69c.11.13.27.13.47,0a1.27,1.27,0,0,0,.47-.54A2,2,0,0,0,229.3,213.92Z" style="fill: rgb(69, 90, 100); transform-origin: 228.605px 213.15px;" id="elivpkmi0u8ur" class="animable"></path>
                <path d="M211.53,230.36l93.16-53.79c1.2-.69,2.17-.13,2.17,1.25v4.38a4.76,4.76,0,0,1-2.17,3.75l-93.16,53.79c-1.19.69-2.16.13-2.16-1.25v-4.38A4.8,4.8,0,0,1,211.53,230.36Z" style="fill: rgb(224, 224, 224); transform-origin: 258.115px 208.155px;" id="elsx3i49h240e" class="animable"></path>
                <path d="M214.51,212.05l-.9-2.68v-.05a.28.28,0,0,1,0-.15.24.24,0,0,1,.08-.11l.34-.19s.07,0,.1,0a.11.11,0,0,1,.05.08l.61,1.81.6-2.51a.77.77,0,0,1,.06-.15.25.25,0,0,1,.09-.11l.34-.19s.07,0,.09,0a.19.19,0,0,1,0,.12.43.43,0,0,0,0,.05l-1.28,5.3a.48.48,0,0,1,0,.14.22.22,0,0,1-.1.11l-.34.2a.05.05,0,0,1-.08,0,.12.12,0,0,1,0-.11.09.09,0,0,1,0,0Z" style="fill: rgb(250, 250, 250); transform-origin: 214.79px 210.77px;" id="el593cotcj8bq" class="animable"></path>
                <path d="M217.44,206.85a.94.94,0,0,1,.45-.14.56.56,0,0,1,.35.13.84.84,0,0,1,.24.35,2,2,0,0,1,.1.54v.71a3,3,0,0,1-.11.66,2.68,2.68,0,0,1-.58,1.15,1.75,1.75,0,0,1-.45.38.92.92,0,0,1-.46.15.63.63,0,0,1-.35-.13.93.93,0,0,1-.23-.35,1.68,1.68,0,0,1-.1-.54.66.66,0,0,1,0-.15v-.39a.86.86,0,0,1,0-.17,2.51,2.51,0,0,1,.1-.66,4.29,4.29,0,0,1,.23-.63,3.24,3.24,0,0,1,.35-.53A2.07,2.07,0,0,1,217.44,206.85Zm.55,1.26a.8.8,0,0,0-.07-.32.25.25,0,0,0-.13-.15.2.2,0,0,0-.17,0,.67.67,0,0,0-.18.07,1.33,1.33,0,0,0-.19.14,1,1,0,0,0-.17.22,1.42,1.42,0,0,0-.13.3,2.12,2.12,0,0,0-.06.4.53.53,0,0,0,0,.14v.35a.57.57,0,0,0,0,.14,1.22,1.22,0,0,0,.06.32.33.33,0,0,0,.13.15.25.25,0,0,0,.17,0,.6.6,0,0,0,.19-.08.73.73,0,0,0,.18-.14.74.74,0,0,0,.17-.21,1,1,0,0,0,.13-.3,1.59,1.59,0,0,0,.07-.4v-.63Z" style="fill: rgb(250, 250, 250); transform-origin: 217.438px 208.745px;" id="elth92i8y1ak8" class="animable"></path>
                <path d="M219.1,206.15a.28.28,0,0,1,0-.15.24.24,0,0,1,.08-.11l.35-.2a.08.08,0,0,1,.09,0,.16.16,0,0,1,0,.11v1.78a1.12,1.12,0,0,0,.12.6c.08.13.2.14.38,0a.94.94,0,0,0,.37-.47,2.13,2.13,0,0,0,.12-.74v-1.78a.53.53,0,0,1,0-.16.18.18,0,0,1,.09-.1l.34-.2a.07.07,0,0,1,.09,0,.19.19,0,0,1,0,.12v3.26a.42.42,0,0,1,0,.15c0,.05-.05.09-.09.11l-.34.2s-.07,0-.09,0a.16.16,0,0,1,0-.11v-.16a2.53,2.53,0,0,1-.26.44,1.09,1.09,0,0,1-.37.34.79.79,0,0,1-.46.14.44.44,0,0,1-.29-.19,1.13,1.13,0,0,1-.15-.44,3.64,3.64,0,0,1-.05-.6Z" style="fill: rgb(250, 250, 250); transform-origin: 220.085px 206.967px;" id="elh27bb4bizau" class="animable"></path>
                <path d="M223,204.53a1,1,0,0,0-.39.42,1.58,1.58,0,0,0-.12.67v1.82a.53.53,0,0,1,0,.16.18.18,0,0,1-.09.1l-.34.2a.07.07,0,0,1-.09,0,.21.21,0,0,1,0-.12v-3.26a.4.4,0,0,1,0-.15.35.35,0,0,1,.09-.11l.34-.2s.07,0,.09,0a.16.16,0,0,1,0,.11v.16a2.12,2.12,0,0,1,.25-.4,1.29,1.29,0,0,1,.35-.3l.21-.12a.08.08,0,0,1,.08,0,.12.12,0,0,1,0,.11v.46a.29.29,0,0,1,0,.16.21.21,0,0,1-.08.1Z" style="fill: rgb(250, 250, 250); transform-origin: 222.677px 205.708px;" id="elka5ixhvos9l" class="animable"></path>
                <path d="M223.69,205.13a4.13,4.13,0,0,1,.08-.78,3.89,3.89,0,0,1,.22-.73,2.8,2.8,0,0,1,.36-.61,1.48,1.48,0,0,1,.46-.41.79.79,0,0,1,.46-.13.53.53,0,0,1,.35.19,1.09,1.09,0,0,1,.23.43,2.49,2.49,0,0,1,.08.64V204a.42.42,0,0,1,0,.16.15.15,0,0,1-.09.1l-1.52.88a.63.63,0,0,0,.05.26.33.33,0,0,0,.11.14.25.25,0,0,0,.17,0,.53.53,0,0,0,.2-.07,1.15,1.15,0,0,0,.23-.18.91.91,0,0,0,.15-.21l.09-.12a.27.27,0,0,1,.08-.07l.37-.21a.07.07,0,0,1,.09,0,.19.19,0,0,1,0,.12,1.28,1.28,0,0,1-.07.29,2.2,2.2,0,0,1-.2.41,3.28,3.28,0,0,1-.32.44,1.78,1.78,0,0,1-.45.37.81.81,0,0,1-.46.13.54.54,0,0,1-.36-.19,1.21,1.21,0,0,1-.22-.47A3,3,0,0,1,223.69,205.13Zm1.12-1.69a.83.83,0,0,0-.23.19,1,1,0,0,0-.15.24,1,1,0,0,0-.1.27c0,.09,0,.17-.05.24l1-.59a1.38,1.38,0,0,0,0-.2.34.34,0,0,0-.08-.16.21.21,0,0,0-.15-.07A.45.45,0,0,0,224.81,203.44Z" style="fill: rgb(250, 250, 250); transform-origin: 224.814px 204.455px;" id="el9ygkb3vwku6" class="animable"></path>
                <path d="M227,201.73a2.23,2.23,0,0,1,.27-.46,1.27,1.27,0,0,1,.32-.3.59.59,0,0,1,.47-.1.52.52,0,0,1,.29.26,1.92,1.92,0,0,1,.14-.33,2.2,2.2,0,0,1,.19-.3,1.5,1.5,0,0,1,.2-.23,1.27,1.27,0,0,1,.2-.16.61.61,0,0,1,.43-.11.39.39,0,0,1,.28.19.88.88,0,0,1,.15.44,3.64,3.64,0,0,1,.05.6v1.88a.28.28,0,0,1,0,.15.17.17,0,0,1-.09.11l-.34.2a.06.06,0,0,1-.09,0,.16.16,0,0,1,0-.11V201.6a.93.93,0,0,0-.12-.59q-.12-.12-.33,0a.88.88,0,0,0-.32.36,1.71,1.71,0,0,0-.13.72V204a.53.53,0,0,1,0,.16.18.18,0,0,1-.09.1l-.34.2a.07.07,0,0,1-.09,0,.21.21,0,0,1,0-.12v-1.84a1,1,0,0,0-.12-.6q-.12-.12-.33,0a.82.82,0,0,0-.32.38,1.63,1.63,0,0,0-.14.73v1.85a.5.5,0,0,1,0,.16.22.22,0,0,1-.09.1l-.34.2s-.07,0-.09,0a.19.19,0,0,1,0-.12v-3.26a.42.42,0,0,1,0-.15.2.2,0,0,1,.09-.11l.34-.2a.07.07,0,0,1,.09,0,.16.16,0,0,1,0,.11Z" style="fill: rgb(250, 250, 250); transform-origin: 228.305px 202.658px;" id="eljnom442hc7" class="animable"></path>
                <path d="M230.65,200.19a1.48,1.48,0,0,1,.08-.36,1.56,1.56,0,0,1,.19-.42,1.91,1.91,0,0,1,.29-.41,1.45,1.45,0,0,1,.41-.33,1,1,0,0,1,.42-.15.45.45,0,0,1,.32.09.66.66,0,0,1,.2.34,2,2,0,0,1,.08.59v2.06a.42.42,0,0,1,0,.15.2.2,0,0,1-.09.11l-.34.2s-.06,0-.09,0a.16.16,0,0,1,0-.11v-.23a2.26,2.26,0,0,1-.28.5,1.4,1.4,0,0,1-.46.41.67.67,0,0,1-.35.12.38.38,0,0,1-.26-.07.61.61,0,0,1-.16-.26,1.18,1.18,0,0,1,0-.4,1.81,1.81,0,0,1,.22-.89,2.66,2.66,0,0,1,.59-.71l.75-.63c0-.19-.05-.3-.13-.33a.36.36,0,0,0-.3.06.45.45,0,0,0-.18.15,1.17,1.17,0,0,0-.12.2.41.41,0,0,1-.08.12l-.07.07-.43.24s-.05,0-.07,0S230.64,200.24,230.65,200.19Zm.75,1.54a1,1,0,0,0,.28-.23,1.37,1.37,0,0,0,.2-.29,1,1,0,0,0,.12-.32,1.24,1.24,0,0,0,.05-.31v-.07l-.62.52a1,1,0,0,0-.27.3.52.52,0,0,0-.08.3c0,.1,0,.15.09.16A.35.35,0,0,0,231.4,201.73Z" style="fill: rgb(250, 250, 250); transform-origin: 231.62px 200.635px;" id="elqwn0krpguv" class="animable"></path>
                <path d="M233.76,196.17a.06.06,0,0,1,.08,0,.12.12,0,0,1,0,.11v.55a.28.28,0,0,1,0,.15.24.24,0,0,1-.08.11l-.4.22a.06.06,0,0,1-.08,0,.15.15,0,0,1,0-.12v-.54a.32.32,0,0,1,0-.16.21.21,0,0,1,.08-.1Zm.1,4.72a.42.42,0,0,1,0,.16s0,.09-.09.11l-.34.19s-.07,0-.09,0a.16.16,0,0,1,0-.11V198a.42.42,0,0,1,0-.15.15.15,0,0,1,.09-.1l.34-.21a.09.09,0,0,1,.09,0,.16.16,0,0,1,0,.11Z" style="fill: rgb(250, 250, 250); transform-origin: 233.569px 198.752px;" id="eljo2v63nkhdi" class="animable"></path>
                <path d="M235.1,200.18a.42.42,0,0,1,0,.15.2.2,0,0,1-.09.11l-.34.2a.06.06,0,0,1-.09,0,.16.16,0,0,1,0-.11v-4.59a.5.5,0,0,1,0-.16.3.3,0,0,1,.09-.1l.34-.2s.07,0,.09,0a.21.21,0,0,1,0,.12Z" style="fill: rgb(250, 250, 250); transform-origin: 234.84px 198.07px;" id="elps4a5sgb4ab" class="animable"></path>
                <path d="M239.1,196.42a2.09,2.09,0,0,1-.07.51,2.41,2.41,0,0,1-.16.43,1.72,1.72,0,0,1-.22.33,1,1,0,0,1-.25.22.51.51,0,0,1-.35.1.32.32,0,0,1-.19-.11l-.08.18a.72.72,0,0,1-.11.19,1.19,1.19,0,0,1-.15.18,1.46,1.46,0,0,1-.24.17.47.47,0,0,1-.31.08.39.39,0,0,1-.23-.16.8.8,0,0,1-.15-.36,3,3,0,0,1-.05-.56,3.08,3.08,0,0,1,.05-.61,2.74,2.74,0,0,1,.14-.53,2.48,2.48,0,0,1,.22-.43,1,1,0,0,1,.31-.28.36.36,0,0,1,.27-.07.33.33,0,0,1,.15.14v-.07a.42.42,0,0,1,0-.15.2.2,0,0,1,.09-.11l.23-.13s.07,0,.09,0a.19.19,0,0,1,0,.12V197c0,.11,0,.18.07.21a.12.12,0,0,0,.16,0,.48.48,0,0,0,.16-.18,1.09,1.09,0,0,0,.07-.36c0-.13,0-.27,0-.41s0-.26,0-.38a2,2,0,0,0-.11-.61.73.73,0,0,0-.24-.35.53.53,0,0,0-.38-.07,1.61,1.61,0,0,0-.54.22,1.79,1.79,0,0,0-.48.39,2.56,2.56,0,0,0-.35.53,2.5,2.5,0,0,0-.24.63,2.63,2.63,0,0,0-.12.69c0,.06,0,.15,0,.25v.63c0,.1,0,.18,0,.25a1.43,1.43,0,0,0,.12.54.71.71,0,0,0,.24.35.55.55,0,0,0,.35.12,1.18,1.18,0,0,0,.48-.16,3,3,0,0,0,.32-.22l.24-.21a1,1,0,0,0,.17-.21,2.43,2.43,0,0,0,.14-.2,1.05,1.05,0,0,1,.08-.13.46.46,0,0,1,.07-.07l.34-.19a.07.07,0,0,1,.09,0s0,.06,0,.13a.35.35,0,0,1,0,.12c-.06.13-.13.28-.21.42a3,3,0,0,1-.29.44,3.19,3.19,0,0,1-.4.43,4.11,4.11,0,0,1-.55.4,1.47,1.47,0,0,1-.7.22.82.82,0,0,1-.51-.19,1.4,1.4,0,0,1-.32-.52,2.85,2.85,0,0,1-.15-.76v-.26c0-.11,0-.22,0-.34s0-.23,0-.34v-.26a5.91,5.91,0,0,1,.14-.91,4.38,4.38,0,0,1,.33-.9,3.82,3.82,0,0,1,.51-.79,2.47,2.47,0,0,1,.7-.59,1.8,1.8,0,0,1,.78-.28.71.71,0,0,1,.53.17,1,1,0,0,1,.31.53,3.52,3.52,0,0,1,.13.79c0,.12,0,.26,0,.42S239.11,196.29,239.1,196.42Zm-2.08.93a.94.94,0,0,0,.08.48.17.17,0,0,0,.25,0,.66.66,0,0,0,.25-.33,1.68,1.68,0,0,0,.08-.59.94.94,0,0,0-.08-.48.16.16,0,0,0-.25,0,.58.58,0,0,0-.25.33A1.76,1.76,0,0,0,237,197.35Z" style="fill: rgb(250, 250, 250); transform-origin: 237.35px 197.148px;" id="elpd0ihhjw5en" class="animable"></path>
                <path d="M239.57,196a4.07,4.07,0,0,1,.3-1.51,2.8,2.8,0,0,1,.36-.61,1.6,1.6,0,0,1,.46-.41.71.71,0,0,1,.46-.12.48.48,0,0,1,.35.18,1.07,1.07,0,0,1,.23.44,2.35,2.35,0,0,1,.08.63v.32a.42.42,0,0,1,0,.15.24.24,0,0,1-.08.11l-1.52.87a.62.62,0,0,0,0,.26.33.33,0,0,0,.11.14.25.25,0,0,0,.17,0,.53.53,0,0,0,.2-.07,1.25,1.25,0,0,0,.24-.18,1.05,1.05,0,0,0,.14-.2l.09-.13a.27.27,0,0,1,.08-.07l.37-.21a.06.06,0,0,1,.09,0,.14.14,0,0,1,0,.11,1.28,1.28,0,0,1-.07.29,2,2,0,0,1-.2.42,3,3,0,0,1-.32.43,1.78,1.78,0,0,1-.45.37.79.79,0,0,1-.46.13.54.54,0,0,1-.36-.19,1.14,1.14,0,0,1-.22-.47A3,3,0,0,1,239.57,196Zm1.12-1.69a.83.83,0,0,0-.23.19,1,1,0,0,0-.15.24,1,1,0,0,0-.1.27,1.18,1.18,0,0,0,0,.25l1-.6a.69.69,0,0,0,0-.2.34.34,0,0,0-.08-.16.21.21,0,0,0-.15-.07A.48.48,0,0,0,240.69,194.27Z" style="fill: rgb(250, 250, 250); transform-origin: 240.692px 195.344px;" id="ely7syr1zoi0f" class="animable"></path>
                <path d="M242.92,192.56a1.84,1.84,0,0,1,.26-.46,1.27,1.27,0,0,1,.32-.3.59.59,0,0,1,.47-.1.52.52,0,0,1,.29.26c0-.11.09-.22.14-.33a2.2,2.2,0,0,1,.19-.3,1.5,1.5,0,0,1,.2-.23,1.27,1.27,0,0,1,.2-.16.62.62,0,0,1,.44-.11.41.41,0,0,1,.28.19,1.19,1.19,0,0,1,.15.44,5.32,5.32,0,0,1,0,.6v1.88a.34.34,0,0,1,0,.16.21.21,0,0,1-.08.1l-.35.2a.05.05,0,0,1-.08,0,.12.12,0,0,1,0-.11v-1.85a.93.93,0,0,0-.12-.59q-.12-.12-.33,0a.8.8,0,0,0-.31.36,1.56,1.56,0,0,0-.14.72v1.88a.34.34,0,0,1,0,.16.21.21,0,0,1-.08.1l-.35.2a.08.08,0,0,1-.09,0,.21.21,0,0,1,0-.12V193.3a1,1,0,0,0-.12-.6q-.12-.12-.33,0a.82.82,0,0,0-.32.38,1.66,1.66,0,0,0-.13.74v1.84a.32.32,0,0,1,0,.16.18.18,0,0,1-.09.1l-.34.2a.07.07,0,0,1-.09,0,.19.19,0,0,1,0-.12v-3.26a.4.4,0,0,1,0-.15.35.35,0,0,1,.09-.11l.34-.2a.07.07,0,0,1,.09,0,.12.12,0,0,1,0,.11Z" style="fill: rgb(250, 250, 250); transform-origin: 244.185px 193.481px;" id="el5hnds99qjcv" class="animable"></path>
                <path d="M246.53,191a2.18,2.18,0,0,1,.08-.36,2.6,2.6,0,0,1,.19-.42,2.34,2.34,0,0,1,.29-.41,1.86,1.86,0,0,1,.41-.33,1,1,0,0,1,.42-.15.45.45,0,0,1,.32.09.66.66,0,0,1,.21.34,2.38,2.38,0,0,1,.07.59v2.06a.28.28,0,0,1,0,.15.2.2,0,0,1-.08.11l-.35.2a.06.06,0,0,1-.09,0,.16.16,0,0,1,0-.11v-.23a2.58,2.58,0,0,1-.28.51,1.44,1.44,0,0,1-.45.4.7.7,0,0,1-.36.12.35.35,0,0,1-.25-.07.62.62,0,0,1-.17-.26,1.5,1.5,0,0,1,0-.4,1.81,1.81,0,0,1,.22-.89,2.67,2.67,0,0,1,.6-.71l.74-.63c0-.19,0-.3-.13-.33a.36.36,0,0,0-.3.06.54.54,0,0,0-.18.15,1.76,1.76,0,0,0-.12.2.41.41,0,0,1-.08.12.23.23,0,0,1-.07.07l-.42.24a.06.06,0,0,1-.08,0S246.52,191.07,246.53,191Zm.76,1.54a1,1,0,0,0,.27-.23,1.15,1.15,0,0,0,.2-.29,1.44,1.44,0,0,0,.13-.32,1.88,1.88,0,0,0,0-.31v-.07l-.62.52a1.43,1.43,0,0,0-.27.3.61.61,0,0,0-.08.3c0,.1,0,.15.1.16A.38.38,0,0,0,247.29,192.56Z" style="fill: rgb(250, 250, 250); transform-origin: 247.503px 191.445px;" id="elolefiev98oe" class="animable"></path>
                <path d="M249.64,187a.06.06,0,0,1,.08,0,.12.12,0,0,1,0,.11v.55a.28.28,0,0,1,0,.15.24.24,0,0,1-.08.11l-.4.22a.06.06,0,0,1-.08,0,.15.15,0,0,1,0-.12v-.54a.28.28,0,0,1,0-.15.2.2,0,0,1,.08-.11Zm.1,4.73a.42.42,0,0,1,0,.15.2.2,0,0,1-.09.11l-.34.2a.1.1,0,0,1-.09,0,.16.16,0,0,1,0-.11v-3.26a.45.45,0,0,1,0-.16.3.3,0,0,1,.09-.1l.34-.2s.07,0,.09,0a.18.18,0,0,1,0,.11Z" style="fill: rgb(250, 250, 250); transform-origin: 249.448px 189.593px;" id="el7be2ydbb71k" class="animable"></path>
                <path d="M251,191a.28.28,0,0,1,0,.15.2.2,0,0,1-.08.11l-.35.2a.06.06,0,0,1-.09,0,.16.16,0,0,1,0-.11v-4.59a.5.5,0,0,1,0-.16.3.3,0,0,1,.09-.1l.35-.2a.05.05,0,0,1,.08,0,.12.12,0,0,1,0,.11Z" style="fill: rgb(250, 250, 250); transform-origin: 250.742px 188.88px;" id="elo1s86hi8sib" class="animable"></path>
                <path d="M252.12,189.47a.1.1,0,0,1,.09,0,.18.18,0,0,1,0,.11v.69a.4.4,0,0,1,0,.15.26.26,0,0,1-.09.11l-.42.24s-.07,0-.09,0a.18.18,0,0,1,0-.11V190a.42.42,0,0,1,0-.15.17.17,0,0,1,.09-.11Z" style="fill: rgb(250, 250, 250); transform-origin: 251.91px 190.115px;" id="el0b7s48mkbar" class="animable"></path>
                <path d="M253.32,187.78a2,2,0,0,0,0,.25,1.63,1.63,0,0,0,0,.24,1,1,0,0,0,.06.34.32.32,0,0,0,.12.17.26.26,0,0,0,.17.05l.19-.06a1.56,1.56,0,0,0,.19-.14,1,1,0,0,0,.13-.16.56.56,0,0,0,.09-.17,2.07,2.07,0,0,0,.08-.2l.06-.16c0-.05.05-.09.09-.11l.34-.2s.07,0,.09,0a.16.16,0,0,1,0,.12,2.73,2.73,0,0,1-.07.39,2.41,2.41,0,0,1-.2.52,2.45,2.45,0,0,1-.34.52,1.87,1.87,0,0,1-.5.43.87.87,0,0,1-.45.13.56.56,0,0,1-.35-.15.84.84,0,0,1-.23-.38,2.23,2.23,0,0,1-.11-.55v-.57a4,4,0,0,1,.1-.67,3.35,3.35,0,0,1,.24-.65,2.32,2.32,0,0,1,.34-.55,1.52,1.52,0,0,1,.46-.39.93.93,0,0,1,.5-.16.55.55,0,0,1,.34.13.66.66,0,0,1,.2.29,1.35,1.35,0,0,1,.07.31.32.32,0,0,1,0,.16.15.15,0,0,1-.09.1l-.34.2s-.06,0-.09,0l-.06-.09a1,1,0,0,0-.08-.11.15.15,0,0,0-.09-.06.15.15,0,0,0-.13,0,.48.48,0,0,0-.19.08.66.66,0,0,0-.19.15,1.13,1.13,0,0,0-.17.24,1.19,1.19,0,0,0-.12.32A1.6,1.6,0,0,0,253.32,187.78Z" style="fill: rgb(250, 250, 250); transform-origin: 253.811px 187.705px;" id="el1h2ewaquoug" class="animable"></path>
                <path d="M256.53,184.29a.86.86,0,0,1,.45-.15.63.63,0,0,1,.35.13.74.74,0,0,1,.23.35,1.79,1.79,0,0,1,.11.55v.7a3.75,3.75,0,0,1-.11.67,3.29,3.29,0,0,1-.23.62,3.24,3.24,0,0,1-.35.53,2.27,2.27,0,0,1-.45.38,1,1,0,0,1-.46.14.55.55,0,0,1-.35-.12.86.86,0,0,1-.23-.36,2,2,0,0,1-.11-.54V187c0-.06,0-.13,0-.19s0-.13,0-.2v-.17a3.5,3.5,0,0,1,.34-1.28,2.56,2.56,0,0,1,.35-.53A1.64,1.64,0,0,1,256.53,184.29Zm.55,1.25a1.09,1.09,0,0,0-.07-.32.33.33,0,0,0-.13-.15.25.25,0,0,0-.17,0,.48.48,0,0,0-.18.08.8.8,0,0,0-.19.13,1,1,0,0,0-.17.22,1.42,1.42,0,0,0-.13.3,1.6,1.6,0,0,0-.06.4.61.61,0,0,0,0,.14v.36a.49.49,0,0,0,0,.13.82.82,0,0,0,.06.32.28.28,0,0,0,.13.15.2.2,0,0,0,.17,0,.89.89,0,0,0,.19-.07l.18-.14a1,1,0,0,0,.17-.22,1.42,1.42,0,0,0,.13-.3,2.06,2.06,0,0,0,.07-.4v-.63Z" style="fill: rgb(250, 250, 250); transform-origin: 256.525px 186.175px;" id="elhq5xps4uubn" class="animable"></path>
                <path d="M258.8,183.39a1.86,1.86,0,0,1,.27-.46,1.27,1.27,0,0,1,.32-.3.59.59,0,0,1,.47-.1.49.49,0,0,1,.29.26,1.63,1.63,0,0,1,.14-.34l.18-.29a1.6,1.6,0,0,1,.21-.23.94.94,0,0,1,.2-.16.67.67,0,0,1,.43-.12.44.44,0,0,1,.28.2,1.13,1.13,0,0,1,.15.44,3.64,3.64,0,0,1,.05.6v1.88a.42.42,0,0,1,0,.15.2.2,0,0,1-.09.11l-.34.2s-.06,0-.09,0a.16.16,0,0,1,0-.11v-1.85a.91.91,0,0,0-.12-.59c-.08-.08-.19-.08-.34,0a.87.87,0,0,0-.31.36,1.87,1.87,0,0,0-.14.72v1.88a.4.4,0,0,1,0,.15.26.26,0,0,1-.09.11l-.34.2s-.07,0-.09,0a.16.16,0,0,1,0-.11v-1.85a.93.93,0,0,0-.12-.59q-.1-.12-.33,0a.81.81,0,0,0-.31.38,1.63,1.63,0,0,0-.14.73v1.85a.5.5,0,0,1,0,.16.3.3,0,0,1-.09.1l-.35.2a.05.05,0,0,1-.08,0,.12.12,0,0,1,0-.11v-3.26a.28.28,0,0,1,0-.15.24.24,0,0,1,.08-.11l.35-.2a.06.06,0,0,1,.09,0,.16.16,0,0,1,0,.11Z" style="fill: rgb(250, 250, 250); transform-origin: 260.102px 184.319px;" id="els69jq6k961e" class="animable"></path>
                <path d="M213.92,233.9a2.48,2.48,0,0,1,.06-.54,3.2,3.2,0,0,1,.18-.52,2.25,2.25,0,0,1,.27-.43,1,1,0,0,1,.33-.29.55.55,0,0,1,.33-.09.47.47,0,0,1,.27.12.73.73,0,0,1,.18.31,1.61,1.61,0,0,1,.07.47,2.5,2.5,0,0,1-.07.54,2.28,2.28,0,0,1-.18.52,2.34,2.34,0,0,1-.27.44,1.28,1.28,0,0,1-.33.29.75.75,0,0,1-.33.09.43.43,0,0,1-.27-.13.77.77,0,0,1-.18-.31A1.63,1.63,0,0,1,213.92,233.9Z" style="fill: rgb(250, 250, 250); transform-origin: 214.765px 233.42px;" id="el9p42p4weiw7" class="animable"></path>
                <path d="M217.78,231.68a2.64,2.64,0,0,1,.06-.55,2.47,2.47,0,0,1,.18-.52,2.25,2.25,0,0,1,.27-.43,1.11,1.11,0,0,1,.33-.29.55.55,0,0,1,.33-.09.47.47,0,0,1,.27.12.85.85,0,0,1,.18.31,1.68,1.68,0,0,1,.06.47,2.48,2.48,0,0,1-.06.54,2.65,2.65,0,0,1-.18.52,2.06,2.06,0,0,1-.27.44,1.11,1.11,0,0,1-.33.29.55.55,0,0,1-.33.09.43.43,0,0,1-.27-.13.62.62,0,0,1-.18-.31A1.56,1.56,0,0,1,217.78,231.68Z" style="fill: rgb(250, 250, 250); transform-origin: 218.62px 231.19px;" id="eluqpfkkt6e4" class="animable"></path>
                <path d="M221.63,229.45a2.5,2.5,0,0,1,.07-.54,2.28,2.28,0,0,1,.18-.52,2.34,2.34,0,0,1,.27-.44,1.28,1.28,0,0,1,.33-.29.52.52,0,0,1,.33-.09.43.43,0,0,1,.27.13.91.91,0,0,1,.18.31,1.56,1.56,0,0,1,.06.46,2.57,2.57,0,0,1-.06.55,4.56,4.56,0,0,1-.18.52,2.25,2.25,0,0,1-.27.43,1.43,1.43,0,0,1-.33.29.55.55,0,0,1-.33.09.47.47,0,0,1-.27-.12.73.73,0,0,1-.18-.31A1.61,1.61,0,0,1,221.63,229.45Z" style="fill: rgb(250, 250, 250); transform-origin: 222.475px 228.96px;" id="el1ac692kzchci" class="animable"></path>
                <path d="M225.49,227.22a2,2,0,0,1,.07-.54,2.28,2.28,0,0,1,.18-.52,2.34,2.34,0,0,1,.27-.44,1.28,1.28,0,0,1,.33-.29.68.68,0,0,1,.32-.09.39.39,0,0,1,.27.13.78.78,0,0,1,.19.31,1.63,1.63,0,0,1,.06.47,2.48,2.48,0,0,1-.06.54,3.24,3.24,0,0,1-.19.52,1.79,1.79,0,0,1-.27.44,1.17,1.17,0,0,1-.32.28.55.55,0,0,1-.33.09.42.42,0,0,1-.27-.12.73.73,0,0,1-.18-.31A1.33,1.33,0,0,1,225.49,227.22Z" style="fill: rgb(250, 250, 250); transform-origin: 226.335px 226.73px;" id="el7yosuf5h6ea" class="animable"></path>
                <path d="M229.35,225a2.48,2.48,0,0,1,.06-.54,3.24,3.24,0,0,1,.19-.52,1.57,1.57,0,0,1,.27-.43,1.07,1.07,0,0,1,.32-.29.55.55,0,0,1,.33-.09.47.47,0,0,1,.27.12.73.73,0,0,1,.18.31,1.33,1.33,0,0,1,.07.47,2,2,0,0,1-.07.54,2.28,2.28,0,0,1-.18.52,2.34,2.34,0,0,1-.27.44,1.28,1.28,0,0,1-.33.29.68.68,0,0,1-.32.09.39.39,0,0,1-.27-.13.78.78,0,0,1-.19-.31A1.63,1.63,0,0,1,229.35,225Z" style="fill: rgb(250, 250, 250); transform-origin: 230.195px 224.52px;" id="elysdjl7p94ad" class="animable"></path>
                <path d="M233.21,222.77a2.64,2.64,0,0,1,.06-.55,4.56,4.56,0,0,1,.18-.52,2.25,2.25,0,0,1,.27-.43,1.43,1.43,0,0,1,.33-.29.55.55,0,0,1,.33-.09.47.47,0,0,1,.27.12.73.73,0,0,1,.18.31,1.66,1.66,0,0,1,.07.47,2.5,2.5,0,0,1-.07.54,2.13,2.13,0,0,1-.18.52,2.06,2.06,0,0,1-.27.44,1.11,1.11,0,0,1-.33.29.52.52,0,0,1-.33.09.43.43,0,0,1-.27-.13.82.82,0,0,1-.18-.31A1.56,1.56,0,0,1,233.21,222.77Z" style="fill: rgb(250, 250, 250); transform-origin: 234.055px 222.28px;" id="elhb5xu7ovvg6" class="animable"></path>
                <path d="M237.07,220.54a2.48,2.48,0,0,1,.06-.54,2.9,2.9,0,0,1,.18-.52,2.34,2.34,0,0,1,.27-.44,1.28,1.28,0,0,1,.33-.29.55.55,0,0,1,.33-.09.43.43,0,0,1,.27.13.67.67,0,0,1,.18.31,1.56,1.56,0,0,1,.06.46,2.57,2.57,0,0,1-.06.55,2.47,2.47,0,0,1-.18.52,2.25,2.25,0,0,1-.27.43,1.11,1.11,0,0,1-.33.29.55.55,0,0,1-.33.09.47.47,0,0,1-.27-.12.85.85,0,0,1-.18-.31A1.63,1.63,0,0,1,237.07,220.54Z" style="fill: rgb(250, 250, 250); transform-origin: 237.91px 220.05px;" id="elw0l0g9ce6t" class="animable"></path>
                <path d="M240.92,218.31a2.5,2.5,0,0,1,.07-.54,2.28,2.28,0,0,1,.18-.52,2.34,2.34,0,0,1,.27-.44,1.28,1.28,0,0,1,.33-.29.71.71,0,0,1,.33-.09.43.43,0,0,1,.27.13.91.91,0,0,1,.18.31,1.63,1.63,0,0,1,.06.47,2.48,2.48,0,0,1-.06.54,4.56,4.56,0,0,1-.18.52,2.72,2.72,0,0,1-.27.44,1.2,1.2,0,0,1-.33.28.55.55,0,0,1-.33.09.42.42,0,0,1-.27-.12.73.73,0,0,1-.18-.31A1.61,1.61,0,0,1,240.92,218.31Z" style="fill: rgb(250, 250, 250); transform-origin: 241.765px 217.82px;" id="el5jgr43tj5r6" class="animable"></path>
                <path d="M211.54,270.28,251.89,247c1.19-.69,2.16-.13,2.16,1.25v23.12a4.8,4.8,0,0,1-2.16,3.75l-40.35,23.3c-1.2.69-2.17.13-2.17-1.25V274A4.78,4.78,0,0,1,211.54,270.28Z" style="fill: rgb(224, 224, 224); transform-origin: 231.71px 272.71px;" id="el4zwa52hwfzn" class="animable"></path>
                <path d="M215.83,276.58a.07.07,0,0,1,.09,0,.16.16,0,0,1,0,.11v.25a.41.41,0,0,1,0,.16.22.22,0,0,1-.09.1L214,278.26a.06.06,0,0,1-.08,0,.12.12,0,0,1,0-.11v-4.52a.29.29,0,0,1,0-.16.24.24,0,0,1,.08-.11l1.8-1s.07,0,.09,0a.12.12,0,0,1,0,.11v.26a.28.28,0,0,1,0,.15.2.2,0,0,1-.09.11l-1.49.86v1.47l1.4-.8a.05.05,0,0,1,.08,0,.12.12,0,0,1,0,.11v.25a.28.28,0,0,1,0,.15.2.2,0,0,1-.08.11l-1.4.8v1.54Z" style="fill: rgb(69, 90, 100); transform-origin: 214.918px 275.318px;" id="el2xa03lx2avx" class="animable"></path>
                <path d="M217.43,274.91l-.64,1.61,0,.11a.18.18,0,0,1-.09.1l-.21.12a.06.06,0,0,1-.08,0,.12.12,0,0,1,0-.09.2.2,0,0,1,0-.11l.83-2.08-.79-1.07a.2.2,0,0,1,0-.09.35.35,0,0,1,0-.13.36.36,0,0,1,.08-.09l.22-.13s.07,0,.09,0l.06.05.6.82.6-1.51,0-.12a.32.32,0,0,1,.1-.1l.21-.12s.05,0,.07,0a.13.13,0,0,1,0,.1.45.45,0,0,1,0,.11l-.8,2,.83,1.12a.2.2,0,0,1,0,.09.24.24,0,0,1,0,.13.22.22,0,0,1-.07.09l-.22.13s-.07,0-.09,0l-.06-.05Z" style="fill: rgb(69, 90, 100); transform-origin: 217.46px 274.473px;" id="elsultcsq4tx7" class="animable"></path>
                <path d="M220.17,274.77a.57.57,0,0,1-.45.11.55.55,0,0,1-.26-.18v1.55a.4.4,0,0,1,0,.15c0,.05-.05.09-.09.11l-.17.09a.06.06,0,0,1-.08,0,.12.12,0,0,1,0-.11v-4.6a.28.28,0,0,1,0-.15.17.17,0,0,1,.08-.1l.17-.1a.07.07,0,0,1,.09,0,.19.19,0,0,1,0,.12v.21a2.83,2.83,0,0,1,.26-.48,1.46,1.46,0,0,1,.45-.4.7.7,0,0,1,.4-.11.45.45,0,0,1,.32.14,1.16,1.16,0,0,1,.22.4,2.81,2.81,0,0,1,.09.64c0,.07,0,.14,0,.23s0,.15,0,.22a4.28,4.28,0,0,1-.09.75,3.53,3.53,0,0,1-.22.65,2.68,2.68,0,0,1-.32.52A1.77,1.77,0,0,1,220.17,274.77Zm-.71-1.78v.55a.91.91,0,0,0,.2.65.36.36,0,0,0,.46,0,1.17,1.17,0,0,0,.48-.56,2.58,2.58,0,0,0,.19-.91v-.39a1.14,1.14,0,0,0-.19-.69c-.12-.13-.28-.14-.48,0a1.22,1.22,0,0,0-.46.53A2.08,2.08,0,0,0,219.46,273Z" style="fill: rgb(69, 90, 100); transform-origin: 220.153px 273.747px;" id="elrd60o24p0k" class="animable"></path>
                <path d="M222.25,269.12a.4.4,0,0,1,0,.15.35.35,0,0,1-.09.11l-.26.14a.07.07,0,0,1-.09,0,.16.16,0,0,1,0-.11V269a.5.5,0,0,1,0-.16.3.3,0,0,1,.09-.1l.26-.15a.08.08,0,0,1,.09,0,.21.21,0,0,1,0,.12Zm0,4.22a.42.42,0,0,1,0,.15.15.15,0,0,1-.09.1l-.17.1a.06.06,0,0,1-.08,0,.15.15,0,0,1,0-.12v-3.26a.28.28,0,0,1,0-.15.24.24,0,0,1,.08-.11l.17-.09s.07,0,.09,0a.16.16,0,0,1,0,.11Z" style="fill: rgb(69, 90, 100); transform-origin: 222.03px 271.141px;" id="elh95ugmwvyn7" class="animable"></path>
                <path d="M224.11,268.78a.07.07,0,0,1,.09,0,.16.16,0,0,1,0,.11v.23a.42.42,0,0,1,0,.15c0,.05,0,.09-.09.11l-.26.15a1.16,1.16,0,0,0-.53,1.1v2.05a.4.4,0,0,1,0,.15c0,.05,0,.09-.09.11l-.17.1a.06.06,0,0,1-.08,0,.12.12,0,0,1,0-.11v-3.26a.29.29,0,0,1,0-.16.21.21,0,0,1,.08-.1l.17-.1s.06,0,.09,0a.16.16,0,0,1,0,.11v.21a2.14,2.14,0,0,1,.22-.43,1.07,1.07,0,0,1,.34-.29Z" style="fill: rgb(69, 90, 100); transform-origin: 223.588px 270.909px;" id="eltas02w3c3ia" class="animable"></path>
                <path d="M224.45,271.31a1.88,1.88,0,0,1,.07-.53,2,2,0,0,1,.2-.47,2.55,2.55,0,0,1,.3-.41,3,3,0,0,1,.36-.35L226,269v-.09q0-.36-.15-.45a.36.36,0,0,0-.38,0,.76.76,0,0,0-.3.29,2,2,0,0,0-.18.36.67.67,0,0,1-.06.12l-.08.07-.14.07a.07.07,0,0,1-.09,0,.12.12,0,0,1,0-.11,1,1,0,0,1,.08-.36,1.62,1.62,0,0,1,.19-.4,3.18,3.18,0,0,1,.27-.37,1.68,1.68,0,0,1,.34-.27.91.91,0,0,1,.38-.13.45.45,0,0,1,.3.09.67.67,0,0,1,.19.32,1.78,1.78,0,0,1,.07.56v2.13a.45.45,0,0,1,0,.16.22.22,0,0,1-.09.1l-.17.1a.06.06,0,0,1-.08,0,.16.16,0,0,1,0-.12v-.28a1.3,1.3,0,0,1-.13.3,1.45,1.45,0,0,1-.19.29l-.24.24a1.34,1.34,0,0,1-.22.16.68.68,0,0,1-.33.11.36.36,0,0,1-.25-.06.55.55,0,0,1-.16-.22A1.25,1.25,0,0,1,224.45,271.31Zm.83-.09a1.22,1.22,0,0,0,.3-.26,1.39,1.39,0,0,0,.24-.34,2.6,2.6,0,0,0,.15-.42,2.14,2.14,0,0,0,.05-.45v-.17l-.54.44a2.25,2.25,0,0,0-.45.49.91.91,0,0,0-.17.49.68.68,0,0,0,0,.15.25.25,0,0,0,.08.1.17.17,0,0,0,.13,0A.76.76,0,0,0,225.28,271.22Z" style="fill: rgb(69, 90, 100); transform-origin: 225.449px 269.811px;" id="elg3bedf2xn36" class="animable"></path>
                <path d="M227.71,269.12a1.66,1.66,0,0,0,0,.24.58.58,0,0,0,.05.17.14.14,0,0,0,.12.06.34.34,0,0,0,.19-.07l.24-.14a.07.07,0,0,1,.09,0,.12.12,0,0,1,0,.11v.23a.28.28,0,0,1,0,.15.2.2,0,0,1-.09.11l-.28.16c-.27.15-.46.16-.57,0a1.32,1.32,0,0,1-.18-.81v-1.81l-.34.19a.05.05,0,0,1-.08,0,.12.12,0,0,1,0-.11v-.22a.34.34,0,0,1,0-.16.21.21,0,0,1,.08-.1l.34-.2V265.8a.4.4,0,0,1,0-.15.35.35,0,0,1,.09-.11l.17-.09a.07.07,0,0,1,.09,0,.18.18,0,0,1,0,.11v1.15l.58-.34a.06.06,0,0,1,.09,0,.16.16,0,0,1,0,.11v.22a.45.45,0,0,1,0,.16.3.3,0,0,1-.09.1l-.58.34Z" style="fill: rgb(69, 90, 100); transform-origin: 227.63px 267.845px;" id="el2tsiqggg2gu" class="animable"></path>
                <path d="M229.39,265a.5.5,0,0,1,0,.16.3.3,0,0,1-.09.1l-.26.15a.06.06,0,0,1-.08,0,.15.15,0,0,1,0-.12v-.4a.28.28,0,0,1,0-.15.24.24,0,0,1,.08-.11l.26-.14a.08.08,0,0,1,.09,0,.16.16,0,0,1,0,.11Zm0,4.22a.34.34,0,0,1,0,.16.21.21,0,0,1-.08.1l-.17.1s-.06,0-.09,0a.16.16,0,0,1,0-.11v-3.26a.53.53,0,0,1,0-.16.22.22,0,0,1,.09-.1l.17-.1a.05.05,0,0,1,.08,0,.12.12,0,0,1,0,.11Z" style="fill: rgb(69, 90, 100); transform-origin: 229.175px 267.028px;" id="elkr8sgkdxje" class="animable"></path>
                <path d="M231,264.72a.84.84,0,0,1,.44-.13.44.44,0,0,1,.33.13.77.77,0,0,1,.21.37,2.24,2.24,0,0,1,.1.57v.66a5.13,5.13,0,0,1-.1.7,2.92,2.92,0,0,1-.21.61,2.23,2.23,0,0,1-.33.51,1.73,1.73,0,0,1-.44.37.91.91,0,0,1-.43.13.49.49,0,0,1-.33-.13.89.89,0,0,1-.22-.36,2.9,2.9,0,0,1-.1-.58v-.66a5,5,0,0,1,.1-.69,3.47,3.47,0,0,1,.22-.62,2.23,2.23,0,0,1,.33-.51A1.69,1.69,0,0,1,231,264.72Zm.67,1.8c0-.08,0-.18,0-.29s0-.21,0-.29a1.68,1.68,0,0,0-.06-.36.6.6,0,0,0-.13-.24.3.3,0,0,0-.2-.1.5.5,0,0,0-.28.09,1.23,1.23,0,0,0-.28.23,2.63,2.63,0,0,0-.2.33,2.22,2.22,0,0,0-.12.39,3,3,0,0,0-.06.42,2.91,2.91,0,0,0,0,.31,2.27,2.27,0,0,0,0,.28,1.68,1.68,0,0,0,.06.36.67.67,0,0,0,.12.24.35.35,0,0,0,.2.1.57.57,0,0,0,.28-.09,1,1,0,0,0,.28-.23,1.38,1.38,0,0,0,.2-.33,2.23,2.23,0,0,0,.13-.39A3,3,0,0,0,231.69,266.52Z" style="fill: rgb(69, 90, 100); transform-origin: 231px 266.615px;" id="elvao5yg7cmrd" class="animable"></path>
                <path d="M234.8,266.06a.32.32,0,0,1,0,.16.21.21,0,0,1-.08.1l-.17.1a.08.08,0,0,1-.09,0,.21.21,0,0,1,0-.12v-1.85a1,1,0,0,0-.17-.7c-.11-.12-.27-.11-.47,0a1.21,1.21,0,0,0-.46.53,2.08,2.08,0,0,0-.18.91V267a.4.4,0,0,1,0,.15.35.35,0,0,1-.09.11l-.17.09a.06.06,0,0,1-.08,0,.14.14,0,0,1,0-.11V264a.28.28,0,0,1,0-.15.17.17,0,0,1,.08-.1l.17-.1a.07.07,0,0,1,.09,0,.19.19,0,0,1,0,.12V264a2.47,2.47,0,0,1,.26-.47,1.37,1.37,0,0,1,.45-.41.79.79,0,0,1,.39-.12.42.42,0,0,1,.32.13.9.9,0,0,1,.2.38,2.13,2.13,0,0,1,.07.61Z" style="fill: rgb(69, 90, 100); transform-origin: 233.85px 265.182px;" id="elj9lblhctm3f" class="animable"></path>
                <path d="M239.13,260.75c0,.21,0,.39,0,.55s0,.34,0,.55a3.81,3.81,0,0,1-.36,1.61,2.85,2.85,0,0,1-.4.6,2,2,0,0,1-.51.43l-1.07.62a.09.09,0,0,1-.09,0,.16.16,0,0,1,0-.11v-4.52a.42.42,0,0,1,0-.16c0-.05.05-.09.09-.1l1-.61a1.32,1.32,0,0,1,.52-.17.59.59,0,0,1,.41.14.87.87,0,0,1,.27.44A2.28,2.28,0,0,1,239.13,260.75Zm-.43.28a2.06,2.06,0,0,0,0-.45.72.72,0,0,0-.15-.33.4.4,0,0,0-.28-.14.8.8,0,0,0-.41.14l-.71.41v3.64l.73-.43a1.38,1.38,0,0,0,.41-.34,1.74,1.74,0,0,0,.27-.45,2.05,2.05,0,0,0,.14-.5,2.94,2.94,0,0,0,0-.51v-.28a1.93,1.93,0,0,0,0-.24,2,2,0,0,0,0-.24Z" style="fill: rgb(69, 90, 100); transform-origin: 237.91px 262.281px;" id="el8hsc9ftdvs" class="animable"></path>
                <path d="M239.6,262.57a1.9,1.9,0,0,1,.07-.54,2.76,2.76,0,0,1,.2-.47,3.08,3.08,0,0,1,.3-.41,4,4,0,0,1,.36-.35l.64-.53v-.09q0-.36-.15-.45a.38.38,0,0,0-.38,0,.93.93,0,0,0-.3.29,2.53,2.53,0,0,0-.18.37.54.54,0,0,1-.06.11.27.27,0,0,1-.08.07l-.14.08a.06.06,0,0,1-.09,0,.12.12,0,0,1,0-.11,1,1,0,0,1,.08-.36,1.81,1.81,0,0,1,.19-.4,3.18,3.18,0,0,1,.27-.37,1.68,1.68,0,0,1,.34-.27A.91.91,0,0,1,241,259a.44.44,0,0,1,.3.1.58.58,0,0,1,.19.32,1.71,1.71,0,0,1,.07.55v2.14a.4.4,0,0,1,0,.15c0,.05-.05.09-.09.11l-.17.09a.06.06,0,0,1-.08,0,.14.14,0,0,1,0-.11v-.29a1.37,1.37,0,0,1-.13.31,1.73,1.73,0,0,1-.19.28l-.24.24a1.46,1.46,0,0,1-.22.17.8.8,0,0,1-.33.11.43.43,0,0,1-.25-.07.51.51,0,0,1-.16-.21A1.25,1.25,0,0,1,239.6,262.57Zm.83-.1a1.18,1.18,0,0,0,.3-.25,1.67,1.67,0,0,0,.24-.35,2.86,2.86,0,0,0,.15-.41,2.3,2.3,0,0,0,0-.45v-.18l-.54.44a2.25,2.25,0,0,0-.45.49.91.91,0,0,0-.17.5.68.68,0,0,0,0,.14.2.2,0,0,0,.08.1.17.17,0,0,0,.13,0A.45.45,0,0,0,240.43,262.47Z" style="fill: rgb(69, 90, 100); transform-origin: 240.583px 261.085px;" id="elag6tvwyzos" class="animable"></path>
                <path d="M242.9,262.74a1,1,0,0,1,0,.14.18.18,0,0,1-.09.1l-.19.11a.06.06,0,0,1-.07,0,.18.18,0,0,1,0-.11.43.43,0,0,0,0-.05l.44-1.71-.9-2.56s0,0,0,0a.32.32,0,0,1,0-.14.15.15,0,0,1,.08-.09l.19-.11a.06.06,0,0,1,.08,0,.41.41,0,0,1,.05.09l.7,2,.71-2.79a.74.74,0,0,1,0-.14.2.2,0,0,1,.09-.11l.19-.1s.05,0,.07,0a.11.11,0,0,1,0,.1.43.43,0,0,1,0,.05Z" style="fill: rgb(69, 90, 100); transform-origin: 243.172px 260.186px;" id="elgeyntulhy3j" class="animable"></path>
                <path d="M216.19,285.6v1.25a6.77,6.77,0,0,1-.08.8,4,4,0,0,1-.21.72,2.48,2.48,0,0,1-.37.61,2,2,0,0,1-.57.47,1.16,1.16,0,0,1-.56.19.52.52,0,0,1-.37-.19,1.06,1.06,0,0,1-.21-.48,4.65,4.65,0,0,1-.08-.71c0-.17,0-.37,0-.6s0-.43,0-.61a7.4,7.4,0,0,1,.08-.81,2.93,2.93,0,0,1,.21-.73,2.43,2.43,0,0,1,.37-.62,2,2,0,0,1,.56-.48,1.13,1.13,0,0,1,.57-.18.5.5,0,0,1,.37.19,1.19,1.19,0,0,1,.21.48A4.51,4.51,0,0,1,216.19,285.6Zm-1.84,2.27a1.44,1.44,0,0,0,.16.69c.09.14.24.14.45,0a1,1,0,0,0,.45-.54,2.7,2.7,0,0,0,.16-.87c0-.18,0-.38,0-.59s0-.41,0-.59a1.39,1.39,0,0,0-.16-.69c-.08-.13-.24-.13-.45,0a1,1,0,0,0-.45.54,2.87,2.87,0,0,0-.16.87c0,.18,0,.37,0,.59S214.35,287.7,214.35,287.87Z" style="fill: rgb(250, 250, 250); transform-origin: 214.965px 286.935px;" id="elx7akrcjgf6" class="animable"></path>
                <path d="M218.26,287.48a.06.06,0,0,1-.09,0,.16.16,0,0,1,0-.11v-.85l-1.41.82s-.07,0-.09,0a.16.16,0,0,1,0-.11v-.5a.63.63,0,0,1,0-.19,2,2,0,0,0,.07-.19l1.4-3.48a.55.55,0,0,1,.18-.28l.35-.2a.06.06,0,0,1,.08,0,.14.14,0,0,1,0,.11v2.81l.39-.23a.07.07,0,0,1,.09,0,.21.21,0,0,1,0,.12v.5a.4.4,0,0,1,0,.15.22.22,0,0,1-.09.1l-.39.23V287a.28.28,0,0,1,0,.15.24.24,0,0,1-.08.11Zm-.12-1.84v-1.5l-.78,1.95Z" style="fill: rgb(250, 250, 250); transform-origin: 217.949px 284.938px;" id="elhqrbgzsr7q" class="animable"></path>
                <path d="M221,280.59c0-.06,0-.12.07-.19a.35.35,0,0,1,.13-.14l.34-.19s.05,0,.07,0a.12.12,0,0,1,0,.09.2.2,0,0,1,0,.07l-1.54,6.55a.76.76,0,0,1-.06.18.4.4,0,0,1-.14.15l-.32.18s0,0-.07,0a.12.12,0,0,1,0-.09.2.2,0,0,1,0-.07Z" style="fill: rgb(250, 250, 250); transform-origin: 220.545px 283.68px;" id="el7q51ozmjoni" class="animable"></path>
                <path d="M223.39,281.77a1.36,1.36,0,0,0,.17-.44,1.55,1.55,0,0,0,0-.34.71.71,0,0,0-.11-.43c-.08-.08-.2-.08-.38,0a.89.89,0,0,0-.37.4,2.46,2.46,0,0,0-.16.58.43.43,0,0,1-.06.17.15.15,0,0,1-.08.09l-.36.21a.07.07,0,0,1-.09,0,.17.17,0,0,1,0-.12,2.53,2.53,0,0,1,.11-.65,3.41,3.41,0,0,1,.24-.63,3,3,0,0,1,.35-.54,1.75,1.75,0,0,1,.45-.38.79.79,0,0,1,.49-.14.51.51,0,0,1,.35.17.9.9,0,0,1,.2.39,2.09,2.09,0,0,1,.07.51c0,.12,0,.23,0,.34a2.33,2.33,0,0,1-.06.35,3,3,0,0,1-.15.41c-.06.15-.15.33-.25.52l-.84,1.67,1.26-.73a.08.08,0,0,1,.09,0,.19.19,0,0,1,0,.12v.49a.5.5,0,0,1,0,.16.3.3,0,0,1-.09.1L222,285.3a.07.07,0,0,1-.09,0,.16.16,0,0,1,0-.11v-.43a.64.64,0,0,1,.05-.23,1.44,1.44,0,0,1,.08-.17Z" style="fill: rgb(250, 250, 250); transform-origin: 223.085px 282.433px;" id="elqmrvuyl8jyk" class="animable"></path>
                <path d="M226.31,282.83s-.07,0-.09,0a.18.18,0,0,1,0-.11v-.85l-1.41.81a.06.06,0,0,1-.08,0,.15.15,0,0,1,0-.12v-.49a.58.58,0,0,1,0-.19c0-.06,0-.13.07-.2l1.39-3.48a.61.61,0,0,1,.19-.28l.35-.2a.05.05,0,0,1,.08,0,.12.12,0,0,1,0,.11v2.8l.39-.23a.06.06,0,0,1,.09,0,.16.16,0,0,1,0,.11v.5a.4.4,0,0,1,0,.15.35.35,0,0,1-.09.11l-.39.22v.85a.28.28,0,0,1,0,.15.2.2,0,0,1-.08.11Zm-.13-1.84v-1.5l-.77,1.95Z" style="fill: rgb(250, 250, 250); transform-origin: 226.009px 280.265px;" id="elb3gjutavjo7" class="animable"></path>
                <path d="M209.37,306.91a.26.26,0,0,1-.22-.13.26.26,0,0,1,.09-.34l97.49-56.29a.25.25,0,1,1,.25.44l-97.49,56.28A.25.25,0,0,1,209.37,306.91Z" style="fill: rgb(146, 227, 169); transform-origin: 258.114px 278.513px;" id="elqq48td2tut" class="animable"></path>
                <path d="M264.34,239.79l40.35-23.3c1.2-.69,2.17-.13,2.17,1.25v23.12a4.78,4.78,0,0,1-2.17,3.75l-40.35,23.3c-1.19.69-2.16.13-2.16-1.25V243.54A4.8,4.8,0,0,1,264.34,239.79Z" style="fill: rgb(224, 224, 224); transform-origin: 284.52px 242.2px;" id="eldeivnnejyw" class="animable"></path>
                <path d="M266.4,256.37a.17.17,0,0,1,0-.13.42.42,0,0,1,0-.15l1-1.77.08-.12a.24.24,0,0,1,.08-.09l.35-.2s.07,0,.09,0a.18.18,0,0,1,0,.11v4.53a.42.42,0,0,1,0,.15.2.2,0,0,1-.09.11l-.36.21s-.07,0-.09,0a.16.16,0,0,1,0-.11v-3.46l-.64,1.15a.19.19,0,0,1-.09.09.08.08,0,0,1-.08,0Z" style="fill: rgb(250, 250, 250); transform-origin: 267.198px 256.465px;" id="elihr08186vy" class="animable"></path>
                <path d="M270.49,254.58a1.86,1.86,0,0,0,.18-.44,2.68,2.68,0,0,0,0-.35.62.62,0,0,0-.12-.42c-.07-.09-.2-.08-.37,0a.84.84,0,0,0-.37.4,2.33,2.33,0,0,0-.17.58.48.48,0,0,1-.05.17.36.36,0,0,1-.08.09l-.36.21s-.07,0-.09,0a.1.1,0,0,1,0-.11,2.51,2.51,0,0,1,.1-.66,3.41,3.41,0,0,1,.24-.63,2.54,2.54,0,0,1,.36-.54,1.67,1.67,0,0,1,.44-.37.91.91,0,0,1,.5-.15.54.54,0,0,1,.34.18.81.81,0,0,1,.2.38,1.7,1.7,0,0,1,.07.51c0,.12,0,.23,0,.34a1.75,1.75,0,0,1-.07.36,3.34,3.34,0,0,1-.14.41c-.07.15-.15.32-.26.51l-.84,1.67,1.27-.73a.05.05,0,0,1,.08,0,.12.12,0,0,1,0,.11v.5a.28.28,0,0,1,0,.15.24.24,0,0,1-.08.11l-2.13,1.22a.06.06,0,0,1-.08,0,.15.15,0,0,1,0-.12v-.42a.46.46,0,0,1,.05-.23,1.1,1.1,0,0,1,.07-.17Z" style="fill: rgb(250, 250, 250); transform-origin: 270.203px 255.228px;" id="elu5oa63s0os" class="animable"></path>
                <path d="M274.47,250.12a.07.07,0,0,1,.09,0,.12.12,0,0,1,0,.11v.5a.34.34,0,0,1,0,.16l-.06.16-.67,1.46a.87.87,0,0,1,.37-.06.48.48,0,0,1,.29.12.71.71,0,0,1,.18.32,1.88,1.88,0,0,1,.06.51,2.69,2.69,0,0,1-.09.7,2.53,2.53,0,0,1-.26.63,2.59,2.59,0,0,1-.39.53,2.27,2.27,0,0,1-.49.39,1.32,1.32,0,0,1-.55.2.67.67,0,0,1-.39-.07.51.51,0,0,1-.24-.28,1.24,1.24,0,0,1-.1-.4.28.28,0,0,1,0-.15.2.2,0,0,1,.09-.11l.36-.21a.06.06,0,0,1,.07,0,.35.35,0,0,1,.06.09.63.63,0,0,0,.07.12.31.31,0,0,0,.13.06.38.38,0,0,0,.18,0,1.33,1.33,0,0,0,.28-.13,1.24,1.24,0,0,0,.45-.43,1.09,1.09,0,0,0,.17-.58c0-.21-.06-.33-.17-.36a.75.75,0,0,0-.45.12l-.38.22a.06.06,0,0,1-.08,0,.15.15,0,0,1,0-.12v-.49a.38.38,0,0,1,0-.17l.06-.15.64-1.4-1.26.73a.05.05,0,0,1-.08,0,.12.12,0,0,1,0-.11v-.5a.28.28,0,0,1,0-.15.24.24,0,0,1,.08-.11Z" style="fill: rgb(250, 250, 250); transform-origin: 273.47px 252.979px;" id="el34txdqmdnvd" class="animable"></path>
                <path d="M267,244.63c0,.21,0,.41,0,.59s0,.38,0,.58a1.24,1.24,0,0,0,.23.81c.15.16.35.16.62,0a1.61,1.61,0,0,0,.37-.3,1.78,1.78,0,0,0,.25-.36,3.12,3.12,0,0,0,.15-.39c0-.14.06-.27.08-.39a.33.33,0,0,1,0-.15.3.3,0,0,1,.08-.08l.21-.12a.06.06,0,0,1,.07,0,.12.12,0,0,1,0,.09,3.17,3.17,0,0,1-.08.55,3.54,3.54,0,0,1-.23.64,3,3,0,0,1-.39.63,2,2,0,0,1-.58.5,1.06,1.06,0,0,1-.52.16.59.59,0,0,1-.4-.17,1,1,0,0,1-.26-.45,2.74,2.74,0,0,1-.1-.7c0-.41,0-.83,0-1.25a4.48,4.48,0,0,1,.1-.81,3.49,3.49,0,0,1,.26-.75,2.93,2.93,0,0,1,.4-.63,2,2,0,0,1,.52-.44,1.09,1.09,0,0,1,.58-.18.65.65,0,0,1,.39.18,1,1,0,0,1,.23.38,1.93,1.93,0,0,1,.08.46.28.28,0,0,1,0,.13.19.19,0,0,1-.07.08l-.21.13a.11.11,0,0,1-.08,0,.1.1,0,0,1,0-.09,2.17,2.17,0,0,0-.08-.3.61.61,0,0,0-.15-.22.41.41,0,0,0-.25-.07.75.75,0,0,0-.37.13,1.5,1.5,0,0,0-.62.71A2.94,2.94,0,0,0,267,244.63Z" style="fill: rgb(69, 90, 100); transform-origin: 267.784px 244.7px;" id="elb7p7eu3k0qk" class="animable"></path>
                <path d="M271.61,240.28a.88.88,0,0,1,.05-.15.33.33,0,0,1,.13-.13l.17-.1a.06.06,0,0,1,.07,0s0,.05,0,.09,0,.05,0,.07l-1,5a.92.92,0,0,1-.09.25.39.39,0,0,1-.15.16l-.19.11c-.06,0-.11,0-.15,0a.27.27,0,0,1-.08-.15l-1-3.83a.15.15,0,0,1,0-.07.27.27,0,0,1,0-.12.22.22,0,0,1,.07-.09l.17-.09c.06,0,.1,0,.13,0a.41.41,0,0,1,.05.09l.87,3.52Z" style="fill: rgb(69, 90, 100); transform-origin: 270.697px 242.734px;" id="el8478sl4iygu" class="animable"></path>
                <path d="M274.58,238.56a.48.48,0,0,1,.05-.14.25.25,0,0,1,.12-.13l.17-.1a.06.06,0,0,1,.07,0,.13.13,0,0,1,0,.1.2.2,0,0,1,0,.07l-1,5a1,1,0,0,1-.09.26.39.39,0,0,1-.15.16l-.18.11c-.06,0-.11,0-.15,0a.27.27,0,0,1-.09-.16l-1-3.82v-.07a.27.27,0,0,1,0-.12.16.16,0,0,1,.07-.09l.16-.1c.06,0,.11,0,.13,0a.41.41,0,0,1,.05.09l.88,3.52Z" style="fill: rgb(69, 90, 100); transform-origin: 273.662px 241.034px;" id="elcimyjolhjfc" class="animable"></path>
                <path d="M209.37,335a.25.25,0,0,1-.12-.47l97.48-56.29a.25.25,0,1,1,.25.44L209.5,335A.22.22,0,0,1,209.37,335Z" style="fill: rgb(146, 227, 169); transform-origin: 258.114px 306.608px;" id="eltwvi1f5ona9" class="animable"></path>
                <path d="M235.91,328.86l44.41-25.64c1.2-.7,2.17-.14,2.17,1.25v13.76a4.78,4.78,0,0,1-2.17,3.75l-44.41,25.64c-1.2.69-2.17.13-2.17-1.25V332.61A4.78,4.78,0,0,1,235.91,328.86Z" style="fill: rgb(146, 227, 169); transform-origin: 258.115px 325.417px;" id="el56rx5b2y6m3" class="animable"></path>
                <path d="M241.72,331.45c.53-.3.93-.34,1.22-.13s.44.68.44,1.42a4.13,4.13,0,0,1-.44,1.93,3.08,3.08,0,0,1-1.22,1.28l-1,.6v2.23a.42.42,0,0,1-.05.22.27.27,0,0,1-.12.15l-.53.31a.09.09,0,0,1-.12,0,.19.19,0,0,1-.05-.16v-6.46a.44.44,0,0,1,.05-.22.31.31,0,0,1,.12-.15Zm-1,3.85,1-.58a1.67,1.67,0,0,0,.59-.56,1.69,1.69,0,0,0,.22-.92c0-.37-.07-.59-.22-.64a.7.7,0,0,0-.59.12l-1,.58Z" style="fill: rgb(250, 250, 250); transform-origin: 241.614px 335.336px;" id="elqosyhuf1me" class="animable"></path>
                <path d="M244,333.24a2,2,0,0,1,.12-.52,3.76,3.76,0,0,1,.26-.6,3.41,3.41,0,0,1,.43-.59,2.25,2.25,0,0,1,.58-.47,1.36,1.36,0,0,1,.59-.21.66.66,0,0,1,.46.13.94.94,0,0,1,.3.49,2.84,2.84,0,0,1,.1.84v2.94a.59.59,0,0,1-.05.22.33.33,0,0,1-.13.15l-.49.29a.1.1,0,0,1-.12,0,.19.19,0,0,1,0-.16v-.33a2.88,2.88,0,0,1-.4.72,1.92,1.92,0,0,1-.65.58,1.17,1.17,0,0,1-.5.18.52.52,0,0,1-.37-.12.8.8,0,0,1-.23-.36,1.93,1.93,0,0,1-.08-.58,2.59,2.59,0,0,1,.32-1.26,3.56,3.56,0,0,1,.85-1l1.06-.89q0-.41-.18-.48a.48.48,0,0,0-.43.08.82.82,0,0,0-.26.22,1.58,1.58,0,0,0-.17.29l-.11.17a.31.31,0,0,1-.11.09l-.6.35a.09.09,0,0,1-.11,0A.17.17,0,0,1,244,333.24Zm1.08,2.2a1.76,1.76,0,0,0,.39-.32,1.71,1.71,0,0,0,.29-.43,2,2,0,0,0,.18-.46,1.72,1.72,0,0,0,.06-.44v-.1l-.89.75a2,2,0,0,0-.38.42.86.86,0,0,0-.12.44c0,.14.05.21.14.23A.55.55,0,0,0,245,335.44Z" style="fill: rgb(250, 250, 250); transform-origin: 245.331px 333.874px;" id="eluuxstdfqu1f" class="animable"></path>
                <path d="M248.59,334.21l-1.28-3.84a.15.15,0,0,1,0-.07.63.63,0,0,1,0-.22.39.39,0,0,1,.13-.15l.48-.28a.12.12,0,0,1,.14,0,.25.25,0,0,1,.08.12l.86,2.59.87-3.59a1.28,1.28,0,0,1,.07-.21.45.45,0,0,1,.14-.16l.49-.28a.1.1,0,0,1,.12,0,.21.21,0,0,1,.05.16v.08L249,335.94a.64.64,0,0,1-.08.2.36.36,0,0,1-.13.16l-.49.29a.12.12,0,0,1-.13,0,.25.25,0,0,1-.05-.16s0-.05,0-.08Z" style="fill: rgb(250, 250, 250); transform-origin: 249.021px 332.355px;" id="elcs3z2yx5k0p" class="animable"></path>
                <path d="M254.78,323.88a1.24,1.24,0,0,1,.56,0,.79.79,0,0,1,.4.22.9.9,0,0,1,.25.38,1.46,1.46,0,0,1,.1.46.42.42,0,0,1,0,.22.39.39,0,0,1-.13.15l-.45.26c-.08,0-.13.06-.17,0a.36.36,0,0,1-.1-.11.51.51,0,0,0-.29-.27.83.83,0,0,0-.59.16,1.41,1.41,0,0,0-.32.24,1.58,1.58,0,0,0-.27.31,2,2,0,0,0-.18.36,1.21,1.21,0,0,0-.06.38.58.58,0,0,0,.07.33.29.29,0,0,0,.23.12,1.69,1.69,0,0,0,.41,0l.61-.15a2.36,2.36,0,0,1,.65-.07.81.81,0,0,1,.45.16.79.79,0,0,1,.24.43,2.59,2.59,0,0,1,.08.68,3.06,3.06,0,0,1-.1.79,3.75,3.75,0,0,1-.28.77,4.05,4.05,0,0,1-.45.71,3.15,3.15,0,0,1-.61.6v.68a.42.42,0,0,1-.05.22.24.24,0,0,1-.12.15l-.53.31a.1.1,0,0,1-.12,0,.19.19,0,0,1-.05-.16v-.68a1.59,1.59,0,0,1-.55.08.86.86,0,0,1-.45-.15.88.88,0,0,1-.31-.37,1.58,1.58,0,0,1-.13-.62.48.48,0,0,1,.05-.23.3.3,0,0,1,.13-.14l.45-.27a.19.19,0,0,1,.17,0s.07.06.1.12a2,2,0,0,0,.11.2.37.37,0,0,0,.17.13.66.66,0,0,0,.28,0,1.43,1.43,0,0,0,.41-.17,2.4,2.4,0,0,0,.38-.27,1.67,1.67,0,0,0,.32-.33,1.87,1.87,0,0,0,.22-.39,1.22,1.22,0,0,0,.08-.43.43.43,0,0,0-.09-.31.38.38,0,0,0-.28-.08,2.39,2.39,0,0,0-.45.08l-.63.13a1.66,1.66,0,0,1-.57,0,.61.61,0,0,1-.4-.17.85.85,0,0,1-.24-.43,3.18,3.18,0,0,1,0-1.5,3.36,3.36,0,0,1,.27-.75,3.32,3.32,0,0,1,.41-.68,3.14,3.14,0,0,1,.53-.56v-.68a.44.44,0,0,1,.05-.22.24.24,0,0,1,.12-.15l.53-.31a.1.1,0,0,1,.12,0,.21.21,0,0,1,.05.16Z" style="fill: rgb(250, 250, 250); transform-origin: 254.395px 327.715px;" id="eln85hnmwhvl" class="animable"></path>
                <path d="M259,328.47a.1.1,0,0,1-.13,0,.25.25,0,0,1-.05-.16V327.1l-2,1.16a.1.1,0,0,1-.13,0,.23.23,0,0,1-.05-.16v-.71a1.26,1.26,0,0,1,0-.27c0-.09.07-.18.1-.28l2-5a.8.8,0,0,1,.27-.4l.49-.29a.1.1,0,0,1,.13,0,.23.23,0,0,1,0,.16v4l.56-.32a.08.08,0,0,1,.12,0,.22.22,0,0,1,.05.16v.71a.63.63,0,0,1-.05.22.38.38,0,0,1-.12.15l-.56.32v1.21a.59.59,0,0,1,0,.22.39.39,0,0,1-.13.15Zm-.18-2.62v-2.14l-1.11,2.78Z" style="fill: rgb(250, 250, 250); transform-origin: 258.497px 324.81px;" id="elxd5lksml8ds" class="animable"></path>
                <path d="M263.21,326.05a.1.1,0,0,1-.13,0,.23.23,0,0,1-.05-.16v-1.21l-2,1.16s-.09,0-.12,0a.18.18,0,0,1-.06-.16V325a.86.86,0,0,1,.05-.27,2.43,2.43,0,0,1,.1-.28l2-5a.85.85,0,0,1,.27-.4l.49-.29a.12.12,0,0,1,.13,0,.25.25,0,0,1,.05.16v4l.56-.32a.09.09,0,0,1,.12,0,.19.19,0,0,1,.05.16v.71a.44.44,0,0,1-.05.22.31.31,0,0,1-.12.15l-.56.32v1.21a.54.54,0,0,1-.05.22.28.28,0,0,1-.13.15Zm-.18-2.63v-2.14l-1.11,2.78Z" style="fill: rgb(250, 250, 250); transform-origin: 262.76px 322.407px;" id="elmukbqym8x8d" class="animable"></path>
                <path d="M265.8,324.28a.19.19,0,0,1,0-.08.64.64,0,0,1,0-.07l.86-2.38a1.37,1.37,0,0,1-.68.21.81.81,0,0,1-.54-.17,1.12,1.12,0,0,1-.35-.55,2.64,2.64,0,0,1-.14-.91,4.3,4.3,0,0,1,.14-1.07,4.5,4.5,0,0,1,.36-1,3.9,3.9,0,0,1,.55-.8,2.94,2.94,0,0,1,.68-.57,1.58,1.58,0,0,1,.69-.22.82.82,0,0,1,.55.17,1.16,1.16,0,0,1,.37.54,3,3,0,0,1,.13.92,3.56,3.56,0,0,1,0,.57c0,.18-.07.36-.11.53l-.15.51c-.06.16-.11.32-.17.47l-1.22,3.39-.07.17a.29.29,0,0,1-.13.14l-.59.34a.08.08,0,0,1-.12,0A.22.22,0,0,1,265.8,324.28Zm.07-4.46q0,.57.24.72a.54.54,0,0,0,.6-.06,1.62,1.62,0,0,0,.62-.65,2,2,0,0,0,.24-1,.83.83,0,0,0-.24-.71.57.57,0,0,0-.62,0,1.75,1.75,0,0,0-.6.66A2.07,2.07,0,0,0,265.87,319.82Z" style="fill: rgb(250, 250, 250); transform-origin: 266.69px 320.558px;" id="elndn21580xib" class="animable"></path>
                <path d="M269.47,320.91a.1.1,0,0,1,.12,0,.21.21,0,0,1,.05.16v1a.42.42,0,0,1-.05.22.24.24,0,0,1-.12.15l-.6.35a.09.09,0,0,1-.13,0,.22.22,0,0,1-.05-.16v-1a.63.63,0,0,1,.05-.22.39.39,0,0,1,.13-.15Z" style="fill: rgb(250, 250, 250); transform-origin: 269.165px 321.854px;" id="elfx113qrbsii" class="animable"></path>
                <path d="M270.77,321.41a.19.19,0,0,1,0-.08.14.14,0,0,1,0-.07l.86-2.38a1.37,1.37,0,0,1-.68.21.81.81,0,0,1-.54-.17,1.2,1.2,0,0,1-.36-.55,2.6,2.6,0,0,1-.13-.9,4.32,4.32,0,0,1,.13-1.08,4.52,4.52,0,0,1,.37-1,3.9,3.9,0,0,1,.55-.8,3,3,0,0,1,.68-.57,1.67,1.67,0,0,1,.69-.22.82.82,0,0,1,.55.17,1.13,1.13,0,0,1,.36.55,2.64,2.64,0,0,1,.14.91,5.1,5.1,0,0,1,0,.57c0,.18-.07.36-.11.53l-.15.51c-.06.16-.11.32-.17.47l-1.22,3.39c0,.05,0,.11-.07.17a.29.29,0,0,1-.13.14l-.59.34a.08.08,0,0,1-.12,0S270.77,321.49,270.77,321.41Zm.06-4.45c0,.37.08.61.24.71a.55.55,0,0,0,.61-.05,1.63,1.63,0,0,0,.61-.66,2,2,0,0,0,.24-1,.83.83,0,0,0-.24-.71.55.55,0,0,0-.61,0,1.78,1.78,0,0,0-.61.66A2.08,2.08,0,0,0,270.83,317Z" style="fill: rgb(250, 250, 250); transform-origin: 271.658px 317.688px;" id="ely0a06x1ib9" class="animable"></path>
                <path d="M274.69,319.15s0-.06,0-.09a.14.14,0,0,0,0-.06l.86-2.39a1.36,1.36,0,0,1-.67.22.83.83,0,0,1-.54-.17,1.18,1.18,0,0,1-.36-.55,2.68,2.68,0,0,1-.14-.91,4.24,4.24,0,0,1,.14-1.07,4.86,4.86,0,0,1,.36-1,3.89,3.89,0,0,1,.56-.81,2.94,2.94,0,0,1,.68-.56,1.5,1.5,0,0,1,.68-.22.8.8,0,0,1,.55.16,1.19,1.19,0,0,1,.37.55,2.72,2.72,0,0,1,.13.92,3.43,3.43,0,0,1,0,.56c0,.19-.07.36-.11.54l-.15.5c-.05.17-.11.33-.17.48l-1.22,3.38a.77.77,0,0,1-.07.17.27.27,0,0,1-.13.15l-.58.34a.12.12,0,0,1-.13,0A.23.23,0,0,1,274.69,319.15Zm.07-4.46q0,.56.24.72a.58.58,0,0,0,.61-.06,1.67,1.67,0,0,0,.61-.65,2,2,0,0,0,.24-1q0-.56-.24-.72a.55.55,0,0,0-.61.05,1.57,1.57,0,0,0-.61.66A2,2,0,0,0,274.76,314.69Z" style="fill: rgb(250, 250, 250); transform-origin: 275.58px 315.423px;" id="elexo6wwe495" class="animable"></path>
                <path d="M239.06,294.28a1.71,1.71,0,0,1,.61-.23.74.74,0,0,1,.49.08.82.82,0,0,1,.34.44,2.31,2.31,0,0,1,.13.87,3.81,3.81,0,0,1-.26,1.44,3.5,3.5,0,0,1-.68,1.07l1,2.06a.44.44,0,0,1,0,.1.41.41,0,0,1,0,.18.34.34,0,0,1-.1.13l-.28.16c-.08.05-.15.05-.19,0a.47.47,0,0,1-.09-.12l-.94-2-1.12.65v2.55a.47.47,0,0,1-.05.22.31.31,0,0,1-.12.15l-.27.15a.08.08,0,0,1-.12,0s-.05-.08-.05-.16v-6.46a.47.47,0,0,1,.05-.22.38.38,0,0,1,.12-.15Zm-1.13,3.93,1.1-.64a2,2,0,0,0,.73-.7,2.09,2.09,0,0,0,.25-1.07c0-.43-.08-.68-.25-.76a.83.83,0,0,0-.73.15l-1.1.64Z" style="fill: rgb(69, 90, 100); transform-origin: 239.03px 298.123px;" id="el42hgtrx5eus" class="animable"></path>
                <path d="M241.27,296.9a5,5,0,0,1,.15-.94,4.79,4.79,0,0,1,.3-.88,3.75,3.75,0,0,1,.45-.73,2.35,2.35,0,0,1,.59-.51,1.19,1.19,0,0,1,.63-.19.7.7,0,0,1,.47.24,1.44,1.44,0,0,1,.3.62,4.17,4.17,0,0,1,.1,1v.27a.63.63,0,0,1,0,.22.49.49,0,0,1-.12.15l-2.24,1.29v.06a1.22,1.22,0,0,0,.27.89.52.52,0,0,0,.64,0,1.68,1.68,0,0,0,.54-.46,3.93,3.93,0,0,0,.29-.52.76.76,0,0,1,.09-.16.36.36,0,0,1,.11-.1l.2-.11a.1.1,0,0,1,.13,0s0,.08,0,.16a2,2,0,0,1-.11.46,3,3,0,0,1-.26.55,3.1,3.1,0,0,1-.43.56,2.55,2.55,0,0,1-.6.47.93.93,0,0,1-.58.16.69.69,0,0,1-.46-.22,1.39,1.39,0,0,1-.3-.54,3.08,3.08,0,0,1-.15-.82A7.06,7.06,0,0,1,241.27,296.9Zm.58-.3,1.83-1.06v0a1.13,1.13,0,0,0-.25-.83.55.55,0,0,0-.67,0,1.22,1.22,0,0,0-.35.3,2.69,2.69,0,0,0-.29.43,3.13,3.13,0,0,0-.19.54,3,3,0,0,0-.08.6Z" style="fill: rgb(69, 90, 100); transform-origin: 242.765px 296.526px;" id="ell16dyr2z1" class="animable"></path>
                <path d="M247.92,295.89a.44.44,0,0,1,0,.22.31.31,0,0,1-.12.15l-.24.14a.09.09,0,0,1-.12,0,.2.2,0,0,1-.06-.16v-4.07L246,293V297a.42.42,0,0,1-.05.22.27.27,0,0,1-.12.15l-.24.14a.09.09,0,0,1-.13,0,.22.22,0,0,1,0-.16v-4.07l-.54.32a.1.1,0,0,1-.13,0,.23.23,0,0,1,0-.16v-.32a.59.59,0,0,1,0-.22.39.39,0,0,1,.13-.15l.54-.32v-.5a3.5,3.5,0,0,1,.26-1.42,1.91,1.91,0,0,1,.81-.9l.41-.23a.09.09,0,0,1,.13,0,.22.22,0,0,1,.05.16v.32a.63.63,0,0,1-.05.22.39.39,0,0,1-.13.15l-.34.2a1.05,1.05,0,0,0-.28.22,1.12,1.12,0,0,0-.16.28,1.23,1.23,0,0,0-.08.33,2.31,2.31,0,0,0,0,.37v.45l1.37-.79v-1.93a.46.46,0,0,1,.06-.22.31.31,0,0,1,.12-.15l.24-.14a.09.09,0,0,1,.12,0,.19.19,0,0,1,0,.16Z" style="fill: rgb(69, 90, 100); transform-origin: 246.392px 293.182px;" id="ellrans5086bb" class="animable"></path>
                <path d="M248.74,292.59a5.21,5.21,0,0,1,.15-.95,4.69,4.69,0,0,1,.3-.87,3.75,3.75,0,0,1,.45-.73,2,2,0,0,1,.59-.51,1.11,1.11,0,0,1,.63-.19.68.68,0,0,1,.47.23,1.54,1.54,0,0,1,.3.63,4.08,4.08,0,0,1,.1,1v.27a.54.54,0,0,1,0,.22.39.39,0,0,1-.12.15l-2.24,1.29v.06a1.19,1.19,0,0,0,.27.89.51.51,0,0,0,.64,0,1.58,1.58,0,0,0,.54-.47,3.3,3.3,0,0,0,.29-.51.76.76,0,0,1,.09-.16.27.27,0,0,1,.11-.1l.2-.11a.1.1,0,0,1,.13,0,.18.18,0,0,1,0,.17,2,2,0,0,1-.11.46,3,3,0,0,1-.26.55,3.5,3.5,0,0,1-.43.56,2.55,2.55,0,0,1-.6.47,1,1,0,0,1-.58.16.74.74,0,0,1-.46-.22,1.39,1.39,0,0,1-.3-.54,3.16,3.16,0,0,1-.15-.82A7.06,7.06,0,0,1,248.74,292.59Zm.58-.31,1.83-1v0a1.11,1.11,0,0,0-.25-.83c-.17-.15-.39-.14-.67,0a1.37,1.37,0,0,0-.35.3,2.31,2.31,0,0,0-.29.43,2.86,2.86,0,0,0-.19.54,2.89,2.89,0,0,0-.08.59Z" style="fill: rgb(69, 90, 100); transform-origin: 250.235px 292.22px;" id="el0nxx7f6z3qx" class="animable"></path>
                <path d="M253.73,290.85l-.92,2.3-.07.16a.34.34,0,0,1-.14.15l-.3.17a.09.09,0,0,1-.11,0,.2.2,0,0,1,0-.14.77.77,0,0,1,0-.15l1.19-3-1.12-1.52a.23.23,0,0,1,0-.13.41.41,0,0,1,0-.18.33.33,0,0,1,.11-.14l.31-.18c.06,0,.1,0,.13,0l.08.07.86,1.18.86-2.17.07-.16a.34.34,0,0,1,.14-.15l.3-.17a.09.09,0,0,1,.11,0,.2.2,0,0,1,0,.14.77.77,0,0,1,0,.15L254.12,290l1.17,1.59a.21.21,0,0,1,0,.12.49.49,0,0,1,0,.19.29.29,0,0,1-.11.13l-.31.18c-.06,0-.1,0-.13,0l-.08-.08Z" style="fill: rgb(69, 90, 100); transform-origin: 253.738px 290.21px;" id="elha0wlze8fdo" class="animable"></path>
                <path d="M258,288.08a1.9,1.9,0,0,0,.11.61.83.83,0,0,0,.21.34.45.45,0,0,0,.29.1.63.63,0,0,0,.31-.1,1.68,1.68,0,0,0,.54-.49,2.38,2.38,0,0,0,.34-.8.74.74,0,0,1,.1-.26.3.3,0,0,1,.12-.13l.19-.11a.09.09,0,0,1,.13,0s.05.08.05.16a3.07,3.07,0,0,1-.1.58,4,4,0,0,1-.28.69,4.28,4.28,0,0,1-.46.68,2.28,2.28,0,0,1-.63.53,1.11,1.11,0,0,1-.63.19.77.77,0,0,1-.47-.22,1.47,1.47,0,0,1-.29-.56,2.64,2.64,0,0,1-.12-.83c0-.11,0-.24,0-.39a2.66,2.66,0,0,1,0-.41,5.37,5.37,0,0,1,.13-1,5,5,0,0,1,.29-.9,3.17,3.17,0,0,1,.47-.75,2.3,2.3,0,0,1,.62-.55,1.15,1.15,0,0,1,.63-.19.87.87,0,0,1,.46.15,1,1,0,0,1,.28.37,1.52,1.52,0,0,1,.1.47.47.47,0,0,1-.05.22.39.39,0,0,1-.13.15l-.19.11a.14.14,0,0,1-.12,0,.29.29,0,0,1-.1-.16.56.56,0,0,0-.34-.41.79.79,0,0,0-.54.13,1.48,1.48,0,0,0-.32.27,2,2,0,0,0-.28.43,2.9,2.9,0,0,0-.22.59,3.9,3.9,0,0,0-.1.74A4.08,4.08,0,0,0,258,288.08Z" style="fill: rgb(69, 90, 100); transform-origin: 258.896px 287.17px;" id="el03ypajzfwtt3" class="animable"></path>
                <path d="M261,287.33a2.68,2.68,0,0,1,.11-.76,2.73,2.73,0,0,1,.29-.68,4,4,0,0,1,.42-.59,4.85,4.85,0,0,1,.52-.49l.91-.76v-.13c0-.34-.07-.55-.21-.64a.52.52,0,0,0-.55.06,1.13,1.13,0,0,0-.43.41,2.78,2.78,0,0,0-.25.52.79.79,0,0,1-.08.16.45.45,0,0,1-.13.1l-.19.11a.1.1,0,0,1-.13,0,.22.22,0,0,1-.05-.17,1.86,1.86,0,0,1,.12-.51,2.45,2.45,0,0,1,.27-.57,3.92,3.92,0,0,1,.39-.52,2.42,2.42,0,0,1,.48-.39,1.18,1.18,0,0,1,.55-.18.57.57,0,0,1,.42.13.91.91,0,0,1,.28.46,2.49,2.49,0,0,1,.1.79v3.05a.44.44,0,0,1-.05.22.31.31,0,0,1-.12.15l-.24.14a.1.1,0,0,1-.13,0,.23.23,0,0,1-.05-.16v-.41a1.85,1.85,0,0,1-.18.44,3.71,3.71,0,0,1-.28.41,3.58,3.58,0,0,1-.33.34,1.53,1.53,0,0,1-.33.23,1,1,0,0,1-.46.16.54.54,0,0,1-.36-.09.62.62,0,0,1-.22-.31A1.29,1.29,0,0,1,261,287.33Zm1.2-.14a1.62,1.62,0,0,0,.43-.36,2.41,2.41,0,0,0,.33-.5,2.32,2.32,0,0,0,.22-.59,2.73,2.73,0,0,0,.07-.64v-.25l-.76.63a3.22,3.22,0,0,0-.65.7,1.26,1.26,0,0,0-.24.71.64.64,0,0,0,0,.2.4.4,0,0,0,.1.15.29.29,0,0,0,.19.05A.68.68,0,0,0,262.15,287.19Z" style="fill: rgb(69, 90, 100); transform-origin: 262.42px 285.275px;" id="els2ndfy1d9z" class="animable"></path>
                <path d="M265.33,281.49l.14-.32a1.74,1.74,0,0,1,.18-.33,1.54,1.54,0,0,1,.23-.29,1.91,1.91,0,0,1,.32-.24.86.86,0,0,1,.66-.16.69.69,0,0,1,.41.41,3.38,3.38,0,0,1,.18-.44,2,2,0,0,1,.22-.41,2.39,2.39,0,0,1,.28-.35,1.87,1.87,0,0,1,.38-.28c.43-.25.75-.23.95.05a2.75,2.75,0,0,1,.3,1.49v2.77a.63.63,0,0,1-.05.22.49.49,0,0,1-.12.15l-.25.14a.11.11,0,0,1-.12,0,.23.23,0,0,1-.05-.16v-2.67a1.79,1.79,0,0,0-.2-1c-.13-.17-.32-.18-.57,0a1.39,1.39,0,0,0-.55.67,3,3,0,0,0-.21,1.2v2.74a.54.54,0,0,1,0,.22.28.28,0,0,1-.13.15l-.24.14c-.05,0-.09,0-.12,0a.17.17,0,0,1-.05-.15v-2.68a1.81,1.81,0,0,0-.2-1q-.19-.25-.57,0a1.36,1.36,0,0,0-.56.67,2.93,2.93,0,0,0-.21,1.2v2.74a.44.44,0,0,1-.05.22.38.38,0,0,1-.12.15l-.24.14a.1.1,0,0,1-.13,0,.23.23,0,0,1-.05-.16v-4.66a.59.59,0,0,1,.05-.22.39.39,0,0,1,.13-.15l.24-.14a.09.09,0,0,1,.12,0,.19.19,0,0,1,.05.16Z" style="fill: rgb(69, 90, 100); transform-origin: 267.189px 282.705px;" id="elnqwc46pz3b" class="animable"></path>
                <path d="M270.37,280.1a4.36,4.36,0,0,1,.14-.94,5.09,5.09,0,0,1,.31-.88,3.18,3.18,0,0,1,.45-.73,2.09,2.09,0,0,1,.58-.5,1.15,1.15,0,0,1,.63-.19.68.68,0,0,1,.47.23,1.48,1.48,0,0,1,.3.63,4,4,0,0,1,.1,1V279a.44.44,0,0,1-.05.22.31.31,0,0,1-.12.15l-2.24,1.29v.06a1.2,1.2,0,0,0,.27.89.52.52,0,0,0,.64,0,1.56,1.56,0,0,0,.54-.46,2.65,2.65,0,0,0,.29-.51.45.45,0,0,1,.09-.16.31.31,0,0,1,.12-.1l.19-.12a.12.12,0,0,1,.13,0,.19.19,0,0,1,.05.16,2.51,2.51,0,0,1-.11.46,2.33,2.33,0,0,1-.27.55,2.7,2.7,0,0,1-.43.56,2.55,2.55,0,0,1-.6.47,1,1,0,0,1-.58.17.74.74,0,0,1-.45-.22,1.37,1.37,0,0,1-.31-.55,2.74,2.74,0,0,1-.14-.82A4.71,4.71,0,0,1,270.37,280.1Zm.57-.3,1.83-1.05v0a1.05,1.05,0,0,0-.25-.83c-.17-.15-.39-.15-.67,0a1.93,1.93,0,0,0-.35.3,2.37,2.37,0,0,0-.28.44,2.24,2.24,0,0,0-.2.53,3.09,3.09,0,0,0-.08.6Z" style="fill: rgb(69, 90, 100); transform-origin: 271.849px 279.745px;" id="el78w3tq0siau" class="animable"></path>
                <path d="M275.86,274.83a.11.11,0,0,1,.12,0,.25.25,0,0,1,0,.16v.32a.54.54,0,0,1,0,.22.32.32,0,0,1-.12.15l-.38.22a1.67,1.67,0,0,0-.75,1.58v2.92a.54.54,0,0,1-.05.22.32.32,0,0,1-.12.15l-.24.14a.09.09,0,0,1-.13,0,.22.22,0,0,1-.05-.16v-4.66a.63.63,0,0,1,.05-.22.39.39,0,0,1,.13-.15l.24-.14a.11.11,0,0,1,.12,0,.25.25,0,0,1,.05.16v.31a2.56,2.56,0,0,1,.31-.62,1.49,1.49,0,0,1,.48-.41Z" style="fill: rgb(69, 90, 100); transform-origin: 275.066px 277.875px;" id="elgwx7988to59" class="animable"></path>
                <path d="M276.3,278.47a2.68,2.68,0,0,1,.11-.76,3.14,3.14,0,0,1,.28-.68,4,4,0,0,1,.43-.59,4.7,4.7,0,0,1,.51-.49l.92-.76v-.13c0-.34-.07-.55-.22-.64a.5.5,0,0,0-.54.06,1.13,1.13,0,0,0-.43.41,2.78,2.78,0,0,0-.25.52.76.76,0,0,1-.09.16.4.4,0,0,1-.12.1l-.2.11a.09.09,0,0,1-.12,0,.22.22,0,0,1-.05-.17,2.24,2.24,0,0,1,.11-.51,3,3,0,0,1,.28-.57,3.21,3.21,0,0,1,.39-.52,1.91,1.91,0,0,1,.48-.39,1.21,1.21,0,0,1,.54-.19.59.59,0,0,1,.43.14.93.93,0,0,1,.28.46,2.49,2.49,0,0,1,.1.79v3.05a.63.63,0,0,1-.05.22.49.49,0,0,1-.12.15l-.24.14a.12.12,0,0,1-.13,0,.23.23,0,0,1-.05-.16v-.41a2,2,0,0,1-.18.44,3.71,3.71,0,0,1-.28.41,3.64,3.64,0,0,1-.34.34,1.49,1.49,0,0,1-.32.23,1.06,1.06,0,0,1-.46.16.54.54,0,0,1-.36-.09.68.68,0,0,1-.23-.31A1.52,1.52,0,0,1,276.3,278.47Zm1.2-.14a1.79,1.79,0,0,0,.43-.36,2.41,2.41,0,0,0,.33-.5,2.76,2.76,0,0,0,.22-.59,2.73,2.73,0,0,0,.07-.64V276l-.77.63a3.45,3.45,0,0,0-.65.69,1.29,1.29,0,0,0-.24.71.42.42,0,0,0,0,.2.29.29,0,0,0,.1.16.29.29,0,0,0,.19.05A.68.68,0,0,0,277.5,278.33Z" style="fill: rgb(69, 90, 100); transform-origin: 277.721px 276.411px;" id="el7x3s8ilqkn" class="animable"></path>
                <path d="M248,300.79a1.1,1.1,0,0,1,.56,0,.9.9,0,0,1,.4.21,1.14,1.14,0,0,1,.25.39,1.82,1.82,0,0,1,.1.45.59.59,0,0,1,0,.23.39.39,0,0,1-.13.15l-.45.26c-.08,0-.13.05-.17,0a.25.25,0,0,1-.1-.12.49.49,0,0,0-.3-.26c-.13,0-.32,0-.58.15a2.52,2.52,0,0,0-.33.24,1.59,1.59,0,0,0-.26.32,1.23,1.23,0,0,0-.24.73.59.59,0,0,0,.07.34.27.27,0,0,0,.23.11,1.3,1.3,0,0,0,.41,0l.6-.14a2.06,2.06,0,0,1,.66-.07.74.74,0,0,1,.45.16.86.86,0,0,1,.24.42,2.73,2.73,0,0,1,.08.69,3.64,3.64,0,0,1-.1.79,4.45,4.45,0,0,1-.28.77,4.1,4.1,0,0,1-.46.71,3.41,3.41,0,0,1-.6.6v.68a.59.59,0,0,1,0,.22.49.49,0,0,1-.12.15l-.53.3q-.08,0-.12,0a.22.22,0,0,1-.06-.17v-.67a1.51,1.51,0,0,1-.54.08.81.81,0,0,1-.45-.15.91.91,0,0,1-.31-.38,1.54,1.54,0,0,1-.13-.62.36.36,0,0,1,.05-.22.38.38,0,0,1,.12-.15l.46-.26a.24.24,0,0,1,.17,0,.19.19,0,0,1,.09.13,1.22,1.22,0,0,0,.12.19.31.31,0,0,0,.17.13.51.51,0,0,0,.28,0,1.34,1.34,0,0,0,.41-.18,2.86,2.86,0,0,0,.38-.26,1.93,1.93,0,0,0,.31-.33,1.26,1.26,0,0,0,.22-.39,1.07,1.07,0,0,0,.09-.43.43.43,0,0,0-.09-.31.34.34,0,0,0-.28-.08,2.46,2.46,0,0,0-.46.07l-.63.14a1.87,1.87,0,0,1-.56,0,.69.69,0,0,1-.41-.17.93.93,0,0,1-.23-.43,2.5,2.5,0,0,1-.08-.73,3.46,3.46,0,0,1,.09-.78,4,4,0,0,1,.28-.75,3.62,3.62,0,0,1,.41-.68,3.88,3.88,0,0,1,.52-.55v-.69a.42.42,0,0,1,.06-.21.3.3,0,0,1,.12-.16l.53-.3a.08.08,0,0,1,.12,0,.22.22,0,0,1,0,.16Z" style="fill: rgb(69, 90, 100); transform-origin: 247.639px 304.601px;" id="elr0nf91m8voa" class="animable"></path>
                <path d="M252.26,305.38a.08.08,0,0,1-.12,0s0-.08,0-.16V304l-2,1.16a.09.09,0,0,1-.12,0,.25.25,0,0,1,0-.16v-.71a.77.77,0,0,1,0-.27l.1-.29,2-5a.84.84,0,0,1,.26-.41l.5-.28c.05,0,.09,0,.12,0s.05.08.05.16v4l.56-.33a.12.12,0,0,1,.13,0,.25.25,0,0,1,0,.16v.71a.54.54,0,0,1,0,.22.28.28,0,0,1-.13.15l-.56.33v1.21a.47.47,0,0,1-.05.22.31.31,0,0,1-.12.15Zm-.17-2.63v-2.14L251,303.39Z" style="fill: rgb(69, 90, 100); transform-origin: 251.88px 301.724px;" id="eloceebpqnyk" class="animable"></path>
                <path d="M256.47,303a.12.12,0,0,1-.13,0,.25.25,0,0,1,0-.16v-1.21l-2,1.16a.1.1,0,0,1-.13,0,.23.23,0,0,1-.05-.16v-.71a1.26,1.26,0,0,1,0-.27c0-.09.06-.18.1-.28l2-5a.8.8,0,0,1,.27-.4l.49-.29a.1.1,0,0,1,.13,0,.23.23,0,0,1,.05.16v4l.56-.32a.08.08,0,0,1,.12,0,.22.22,0,0,1,0,.16v.71a.63.63,0,0,1,0,.22.49.49,0,0,1-.12.15l-.56.32v1.21a.59.59,0,0,1-.05.22.39.39,0,0,1-.13.15Zm-.18-2.63v-2.14L255.18,301Z" style="fill: rgb(69, 90, 100); transform-origin: 256.024px 299.338px;" id="elxue5oldlp3" class="animable"></path>
                <path d="M259.06,301.19a.25.25,0,0,1,0-.09.14.14,0,0,1,0-.06l.86-2.39a1.32,1.32,0,0,1-.68.22.89.89,0,0,1-.54-.17,1.26,1.26,0,0,1-.36-.55,2.69,2.69,0,0,1-.13-.91,4.24,4.24,0,0,1,.13-1.07,4.33,4.33,0,0,1,.37-1,3.85,3.85,0,0,1,.55-.81,2.94,2.94,0,0,1,.68-.56,1.54,1.54,0,0,1,.69-.22.81.81,0,0,1,.55.16,1.26,1.26,0,0,1,.36.55,2.7,2.7,0,0,1,.14.92,3.43,3.43,0,0,1,0,.56,3.23,3.23,0,0,1-.11.54c0,.17-.09.34-.14.5s-.11.33-.17.48L260,300.7c0,.05,0,.11-.07.17a.32.32,0,0,1-.13.15l-.59.34a.09.09,0,0,1-.12,0A.19.19,0,0,1,259.06,301.19Zm.06-4.46q0,.56.24.72a.58.58,0,0,0,.61-.06,1.6,1.6,0,0,0,.61-.65,2,2,0,0,0,.24-1q0-.56-.24-.72a.55.55,0,0,0-.61.05,1.64,1.64,0,0,0-.61.66A2,2,0,0,0,259.12,296.73Z" style="fill: rgb(69, 90, 100); transform-origin: 259.95px 297.48px;" id="elztr9swma7gm" class="animable"></path>
                <path d="M262.73,297.82a.09.09,0,0,1,.12,0,.23.23,0,0,1,0,.16v1a.59.59,0,0,1,0,.22.38.38,0,0,1-.12.15l-.61.35a.09.09,0,0,1-.12,0,.19.19,0,0,1-.05-.16v-1a.44.44,0,0,1,.05-.22.38.38,0,0,1,.12-.15Z" style="fill: rgb(69, 90, 100); transform-origin: 262.406px 298.76px;" id="elcm9ja3igi6q" class="animable"></path>
                <path d="M264,298.32v-.09l0-.06.86-2.38a1.4,1.4,0,0,1-.68.21.83.83,0,0,1-.54-.17,1.2,1.2,0,0,1-.36-.55,2.66,2.66,0,0,1-.13-.91,4.36,4.36,0,0,1,.5-2,3.54,3.54,0,0,1,.55-.8,2.75,2.75,0,0,1,.68-.57,1.5,1.5,0,0,1,.68-.22.83.83,0,0,1,.56.16,1.26,1.26,0,0,1,.36.55,2.7,2.7,0,0,1,.14.92,3.43,3.43,0,0,1-.05.56,3,3,0,0,1-.11.54,4.13,4.13,0,0,1-.15.5c-.05.17-.11.33-.16.48L265,297.83a1.22,1.22,0,0,1-.07.17.28.28,0,0,1-.13.15l-.59.34a.09.09,0,0,1-.12,0A.19.19,0,0,1,264,298.32Zm.06-4.46c0,.38.08.61.24.72a.58.58,0,0,0,.61-.06,1.6,1.6,0,0,0,.61-.65,2,2,0,0,0,.24-1q0-.56-.24-.72a.55.55,0,0,0-.61,0,1.57,1.57,0,0,0-.61.66A2,2,0,0,0,264.09,293.86Z" style="fill: rgb(69, 90, 100); transform-origin: 264.885px 294.645px;" id="elxndb5eb0ec9" class="animable"></path>
                <path d="M268,296.05a.19.19,0,0,1,0-.08s0-.05,0-.06l.86-2.39a1.5,1.5,0,0,1-.68.22.86.86,0,0,1-.54-.18,1.22,1.22,0,0,1-.36-.54,2.69,2.69,0,0,1-.13-.91,4.18,4.18,0,0,1,.13-1.07,5,5,0,0,1,.37-1,4.19,4.19,0,0,1,.55-.8,2.89,2.89,0,0,1,.68-.56,1.56,1.56,0,0,1,.69-.23.82.82,0,0,1,.55.17,1.21,1.21,0,0,1,.37.55,2.92,2.92,0,0,1,.13.91,5.1,5.1,0,0,1,0,.57c0,.18-.07.36-.11.53l-.15.51c-.06.16-.11.32-.17.47l-1.22,3.39c0,.05,0,.11-.07.17a.37.37,0,0,1-.13.15l-.59.33a.09.09,0,0,1-.12,0A.26.26,0,0,1,268,296.05Zm.06-4.45c0,.37.08.61.24.71a.55.55,0,0,0,.61-.05,1.65,1.65,0,0,0,.62-.66,2,2,0,0,0,.24-1,.83.83,0,0,0-.24-.71.55.55,0,0,0-.62.05,1.67,1.67,0,0,0-.61.65A2.08,2.08,0,0,0,268,291.6Z" style="fill: rgb(69, 90, 100); transform-origin: 268.888px 292.336px;" id="elylrcev15kh9" class="animable"></path>
              </g>
            </g>
            <g id="freepik--Puff--inject-95" class="animable" style="transform-origin: 410.036px 294.406px;">
              <g id="freepik--bean-bag--inject-95" class="animable" style="transform-origin: 410.036px 294.406px;">
                <path d="M452.24,222.77c15.29,8.83,37.41,29.7,37.41,67.36s-17.8,65.67-51.88,78.18c-18.23,6.69-47.46,7-66.24.33-19.14-9.79-32-19.87-39.06-38.16-7.24-21.22,5.71-43.44,23.3-56.15s15.51-27.17,28-42.19c6.69-8.08,16.54-15,28.56-16.33C425.4,214.35,440.61,216.05,452.24,222.77Z" style="fill: rgb(146, 227, 169); transform-origin: 410.036px 294.406px;" id="eltrn4epg9ct" class="animable"></path>
                <g id="elbn2mv4frny7">
                  <path d="M452.24,222.77c15.29,8.83,37.41,29.7,37.41,67.36s-17.8,65.67-51.88,78.18c-18.23,6.69-47.46,7-66.24.33-19.14-9.79-32-19.87-39.06-38.16-7.24-21.22,5.71-43.44,23.3-56.15s15.51-27.17,28-42.19c6.69-8.08,16.54-15,28.56-16.33C425.4,214.35,440.61,216.05,452.24,222.77Z" style="fill: rgb(255, 255, 255); opacity: 0.8; transform-origin: 410.036px 294.406px;" class="animable"></path>
                </g>
                <g id="eldmbx3uyf11i">
                  <path d="M379.71,371c21.83,2.56,44.65-2.81,62.56-15,27.43-18.66,39.3-48.24,33.43-83.29-5.19-31-20.66-48-36.2-55.06a55,55,0,0,1,10,3.7c12.58,9.18,23.77,25.5,28.05,51,6,35.82-6.15,66.06-34.23,85.16a94.4,94.4,0,0,1-46.74,15.75A104.9,104.9,0,0,1,379.71,371Z" style="fill: rgb(146, 227, 169); opacity: 0.15; transform-origin: 429.379px 295.455px;" class="animable"></path>
                </g>
              </g>
            </g>
            <g id="freepik--Character--inject-95" class="animable" style="transform-origin: 389.79px 297.784px;">
              <g id="elwpknv3sex9">
                <path d="M366.47,285.73c11.34-6.27,13.54-13.13,23.39-38.7s27.79-23.63,27.79-23.63,26.36.89,32.66,19.68c4.34,13,4.26,38.54.15,52.33-2.46,8.26-5.82,12.17-5.82,12.17,12.95-2.55,23.28,6.19,23.28,6.19s-9.72-3.63-27.66,3.57c-10.63,4.26-19.95,9.68-31.18,15.3-8.95,4.47-14.51,8.24-15.45,10.21-3.59,7.54-5.06,18.35-9.6,29a64.44,64.44,0,0,1-13.14-3.54c-11.42-5.89-20.58-11.92-27.57-19.8-.22-2.55-.06-5.17-.13-7.72-.25-9.41-.37-18.84.13-28.24C344.47,290.94,355.13,292,366.47,285.73Z" style="fill: rgb(146, 227, 169); opacity: 0.15; transform-origin: 405.447px 297.608px;" class="animable"></path>
              </g>
              <g id="freepik--character--inject-95" class="animable" style="transform-origin: 381.869px 297.784px;">
                <path d="M364.59,369.76l-.4,19.56a7.6,7.6,0,0,0,5.93,6.2c5.25,1.08,6-5.48,6-5.48l4.13-23Z" style="fill: rgb(255, 168, 167); transform-origin: 372.22px 381.34px;" id="elw5n5ufjcmum" class="animable"></path>
                <path d="M377.46,400.11a6.36,6.36,0,0,1-.4,2.24c-.4.9-2.82,2-4.15,2.65a7.52,7.52,0,0,0-4.22,4,15.08,15.08,0,0,1-7.09,6.88A21.51,21.51,0,0,1,348.82,417c-5.33-1-5.71-4.2-5.71-4.2s-.24-3,1.1-3.41S377.46,400.11,377.46,400.11Z" style="fill: rgb(38, 50, 56); transform-origin: 360.277px 408.804px;" id="el1gmcisst4mzi" class="animable"></path>
                <path d="M376.38,388.61c.4.07.45.53.51,1.89s.56,3.07.71,4.37a15.67,15.67,0,0,1-.11,5.63c-1.52,2.55-4.64,2.11-7.62,4.78-2.74,2.45-5.44,8.27-12.45,9.55s-12.29-1-13.55-3-.05-3.86,4.47-6.21c4.8-2.49,11.18-10.05,11.18-10.05.77-1,1.55-2,2.27-3.14a8.15,8.15,0,0,0,.8-1.9l.75-2.23c.11-.34.27-.73.61-.83a1.17,1.17,0,0,1,.89.21,26.16,26.16,0,0,0,6.34,2.47,1.24,1.24,0,0,1,.69.35,1.29,1.29,0,0,1,0,1.06,4.9,4.9,0,0,0-.09,1.43c0,.21.09.47.3.51s.36-.17.46-.35l1-1.78a2.9,2.9,0,0,1,1.54-1.32,7,7,0,0,0,1.09-.64Z" style="fill: rgb(69, 90, 100); transform-origin: 360.602px 401.327px;" id="el02zrk86uj9qq" class="animable"></path>
                <path d="M362,413.23a8.89,8.89,0,0,0-5.06-7.67c-5.23-2.77-8.45-1.09-10.09.05-1.94,1.35-4.73,4.19-3.1,6.32,1.77,2.29,4.64,3.41,10,3.56S362,413.23,362,413.23Z" style="fill: rgb(250, 250, 250); transform-origin: 352.63px 409.775px;" id="el1ec3zaqf6e6" class="animable"></path>
                <path d="M369.45,397a.62.62,0,0,1-.48-.26c-.58-.85-4.37-4-8.09-3.05-.18-.43.14-.87.91-1.31,3.89-.29,7.49,2.7,8.19,3.72a.56.56,0,0,1-.17.8A.68.68,0,0,1,369.45,397Z" style="fill: rgb(250, 250, 250); transform-origin: 365.456px 394.68px;" id="el5jiio6q7x4b" class="animable"></path>
                <path d="M358.71,396.45a1.83,1.83,0,0,1,1.07-1.27,11.05,11.05,0,0,1,7.72,3.75.57.57,0,0,1-.17.8.6.6,0,0,1-.37.09.57.57,0,0,1-.47-.25C365.92,398.75,362.32,395.74,358.71,396.45Z" style="fill: rgb(250, 250, 250); transform-origin: 363.153px 397.501px;" id="elyqt2idz4vut" class="animable"></path>
                <path d="M352.81,402.36a3.34,3.34,0,0,1,1.68-1.53c3.83-.19,7.34,2.73,8,3.73a.58.58,0,0,1-.17.81.63.63,0,0,1-.84-.17C360.9,404.31,356.71,400.83,352.81,402.36Z" style="fill: rgb(250, 250, 250); transform-origin: 357.699px 403.142px;" id="elbywgyrryszu" class="animable"></path>
                <path d="M364.48,402.64a.64.64,0,0,1-.48-.25c-.58-.84-4.29-3.95-8-3.08A2.11,2.11,0,0,1,357.3,398a11,11,0,0,1,7.71,3.75.56.56,0,0,1-.17.8A.59.59,0,0,1,364.48,402.64Z" style="fill: rgb(250, 250, 250); transform-origin: 360.555px 400.321px;" id="elre2m8uye69" class="animable"></path>
                <path d="M335.84,353.05l-1.64,19.5a7.59,7.59,0,0,0,5.52,6.56c5.17,1.42,6.34-5.09,6.34-5.09l5.59-22.69Z" style="fill: rgb(255, 168, 167); transform-origin: 342.925px 365.321px;" id="elj8vptt9dkza" class="animable"></path>
                <path d="M346.75,384.16a6.52,6.52,0,0,1-.54,2.21c-.46.87-2.95,1.84-4.31,2.38a7.54,7.54,0,0,0-4.47,3.71,15.07,15.07,0,0,1-7.51,6.41,21.54,21.54,0,0,1-12.83.3c-5.24-1.38-5.43-4.55-5.43-4.55s0-3,1.32-3.33S346.75,384.16,346.75,384.16Z" style="fill: rgb(38, 50, 56); transform-origin: 329.205px 392.082px;" id="elphl4gmxmivo" class="animable"></path>
                <path d="M346.4,372.62c.41.09.42.55.39,1.91s.37,3.1.44,4.41a15.68,15.68,0,0,1-.47,5.62c-1.69,2.44-4.77,1.8-7.91,4.28-2.89,2.27-6,7.91-13,8.74s-12.19-1.81-13.32-3.9.19-3.86,4.85-5.91c5-2.19,11.8-9.32,11.8-9.32.84-1,1.68-1.94,2.46-3a8,8,0,0,0,.92-1.85c.3-.72.6-1.45.89-2.18.14-.33.32-.7.67-.79a1.21,1.21,0,0,1,.87.28,26.86,26.86,0,0,0,6.17,2.87,1.13,1.13,0,0,1,.67.38,1.31,1.31,0,0,1,0,1.06,4.94,4.94,0,0,0-.18,1.42c0,.22.06.48.27.53s.37-.15.48-.32c.37-.57.74-1.14,1.12-1.71a2.92,2.92,0,0,1,1.62-1.22,6,6,0,0,0,1.13-.57Z" style="fill: rgb(69, 90, 100); transform-origin: 329.727px 384.182px;" id="el72lo661cph6" class="animable"></path>
                <path d="M330.46,396.27a8.88,8.88,0,0,0-4.56-8c-5-3.11-8.37-1.63-10.07-.6-2,1.22-5,3.88-3.5,6.1,1.62,2.4,4.41,3.71,9.77,4.2S330.46,396.27,330.46,396.27Z" style="fill: rgb(250, 250, 250); transform-origin: 321.194px 392.221px;" id="el4xqhq9t81hw" class="animable"></path>
                <path d="M339,380.55a.61.61,0,0,1-.45-.28c-.53-.88-4.11-4.3-7.88-3.56-.16-.44.19-.86,1-1.25,3.91,0,7.31,3.17,7.95,4.23a.58.58,0,0,1-.22.79A.63.63,0,0,1,339,380.55Z" style="fill: rgb(250, 250, 250); transform-origin: 335.163px 378.009px;" id="elnthl5qec6rq" class="animable"></path>
                <path d="M328.28,379.32a1.78,1.78,0,0,1,1.15-1.2,11.06,11.06,0,0,1,7.46,4.23.58.58,0,0,1-.22.79.72.72,0,0,1-.38.07.6.6,0,0,1-.45-.29C335.32,382.07,331.93,378.83,328.28,379.32Z" style="fill: rgb(250, 250, 250); transform-origin: 332.622px 380.666px;" id="elg980tyeoppe" class="animable"></path>
                <path d="M322,384.84a3.36,3.36,0,0,1,1.78-1.42c3.83.05,7.15,3.19,7.78,4.23a.56.56,0,0,1-.22.79.6.6,0,0,1-.82-.21C330,387.3,326,383.55,322,384.84Z" style="fill: rgb(250, 250, 250); transform-origin: 326.821px 385.971px;" id="elpo9cwgmjukq" class="animable"></path>
                <path d="M333.63,385.86a.6.6,0,0,1-.45-.29c-.53-.86-4-4.2-7.76-3.57a2,2,0,0,1,1.35-1.23,11.1,11.1,0,0,1,7.46,4.23.58.58,0,0,1-.22.79A.63.63,0,0,1,333.63,385.86Z" style="fill: rgb(250, 250, 250); transform-origin: 329.862px 383.318px;" id="eliis0axhnbxn" class="animable"></path>
                <path d="M413.85,263.47c1.14,7.91,1.42,12.37-1.61,20.21s-9.88,20.25-21.19,26.09c-9.35,4.84-23.46,9.54-26.18,10.74-3.47,1.52-4.12,2.71-4.81,8.43-1,8.33-2.83,12-6.36,21.34-1.6,4.22-5.74,16-5.74,16-3.69.84-9.65-.59-13.23-2.74,0,0,4.09-48.22,6.73-54.87s25.26-16.8,32.94-20.89,12-6.66,20.77-12.2Z" style="fill: rgb(55, 71, 79); transform-origin: 374.69px 314.997px;" id="elq7egpj7xc4" class="animable"></path>
                <path d="M364.87,320.51c-3.47,1.52-4.12,2.71-4.81,8.43a54.49,54.49,0,0,1-3.69,14.43c.56-2.15,1.83-9.68,2-11,.87-6.17.69-10.32,1.56-12.33s8.9-5.79,8.9-5.79l20-15.83c-3.42-4.23-2.23-10.84-2.23-10.84a15.31,15.31,0,0,0,7.1,11.89c-3.82,2.77-19.94,17.07-21.15,18.15C368.86,319,366,320,364.87,320.51Z" style="fill: rgb(38, 50, 56); transform-origin: 375.035px 315.475px;" id="el2f05lwo8gl5" class="animable"></path>
                <path d="M393.63,287.15a61.43,61.43,0,0,0,5.33,7.2l-1.62,1.28A22.33,22.33,0,0,1,393.63,287.15Z" style="fill: rgb(55, 71, 79); transform-origin: 396.295px 291.39px;" id="eln139ifs2x4l" class="animable"></path>
                <path d="M436.73,293.29c-1.55,9.83-4.34,14.71-7.16,17.66-4.27,4.45-8.07,7.13-19.37,13-9.36,4.84-18.43,10.81-21,12.3-2.94,1.7-3.74,3.09-3.94,6.92-.65,12.87-4.73,27.28-7,39.48,0,0-8.81,3.3-14-2,0,0-1.62-51.13,1-57.78S399,294.35,399,294.35s-2.17-10.86-3.79-18.77Z" style="fill: rgb(55, 71, 79); transform-origin: 400.247px 329.623px;" id="elf2t07dabyqr" class="animable"></path>
                <path d="M407.53,226c-4.62-.37-9.93.28-12.66,3.8-3.64,4.67-11,23.64-13.39,33.1-.65,2.58,11.34,8.48,11.34,8.48l7.55-17Z" style="fill: rgb(242, 143, 143); transform-origin: 394.492px 248.644px;" id="elfh8mnvxh54" class="animable"></path>
                <path d="M406.75,225.85c-5.21-.34-8.85.62-11.25,2.89s-5,8.08-6.39,11.44-4.32,11.68-4.32,11.68,3.67,4.92,13.64,6.4C401.37,248.64,406.75,225.85,406.75,225.85Z" style="fill: rgb(146, 227, 169); transform-origin: 395.77px 242.022px;" id="el62m8712ems2" class="animable"></path>
                <path d="M442.94,235.37c.44,4,.34,34.44-6.93,61.77-12.67,6-43.67-4.36-46.32-19.68,0,0,4.19-6.34,5.73-11,.35-7.77,1.44-20.26,1.95-24.82,1-9,5-15,9.38-15.82l14.59.21,8.16,1.65S442.66,232.87,442.94,235.37Z" style="fill: rgb(69, 90, 100); transform-origin: 416.388px 262.344px;" id="el10vrypulmsua" class="animable"></path>
                <path d="M431.53,189.43a7.09,7.09,0,0,1,5.32,4c1.08,2.29.21,6.83-1.22,12.82A54.5,54.5,0,0,1,432,217.48a4.55,4.55,0,0,1-2.62,2.14l.11-7.1.22-4.94s-4.45,1.38-5.59-5.82c-.72-4.54-.12-6-.12-6Z" style="fill: rgb(38, 50, 56); transform-origin: 430.55px 204.525px;" id="elr8l8xxc9eeh" class="animable"></path>
                <path d="M426.14,197.19A13.24,13.24,0,1,1,412.83,184,13.24,13.24,0,0,1,426.14,197.19Z" style="fill: rgb(38, 50, 56); transform-origin: 412.9px 197.24px;" id="elqt8v7zmynn" class="animable"></path>
                <path d="M428.05,207c1.34.39,2-2,3-3a4.23,4.23,0,0,1,6.39,1c2.27,3.13-.29,8.25-2.72,9.4-3.57,1.69-5.2-1.91-5.2-1.91l-.24,16s-.92,3-7,5.22c-4.77,1.72-11.8-1-7.39-5.63v-4.86a20.66,20.66,0,0,1-6.93.17c-2.38-.43-4.23-2.29-5.35-6-2.17-7.25-1.72-16.83,1-25.49,3.63-2.85,12.75-1,20.44,3.81C424.28,205.65,426.53,206.6,428.05,207Z" style="fill: rgb(255, 168, 167); transform-origin: 419.778px 212.39px;" id="elf6ovbvhh06" class="animable"></path>
                <path d="M417,213.75l-6.33,1.54a3.13,3.13,0,0,0,3.82,2.45A3.43,3.43,0,0,0,417,213.75Z" style="fill: rgb(177, 102, 104); transform-origin: 413.868px 215.79px;" id="elvqciudexr" class="animable"></path>
                <path d="M416.72,215.91a2.72,2.72,0,0,0-4.46,1.53,3.12,3.12,0,0,0,2.24.3A3.39,3.39,0,0,0,416.72,215.91Z" style="fill: rgb(242, 143, 143); transform-origin: 414.49px 216.533px;" id="elwn45ryfe9pk" class="animable"></path>
                <path d="M400.75,183.21a.59.59,0,0,1,.47-.37.7.7,0,0,1,.45.19,21,21,0,0,0,2.91,1.93,10.55,10.55,0,0,1-.08-4c.14-.8.35-2.66,1.34-2.87.59-.12,1.11.37,1.56.78,4,3.69,9.18,1.93,14,2,4,.1,8.86,1.69,10.7,5.58.84,1.77,1,3.65-.63,4.9a40.45,40.45,0,0,1-7.46,4.33c-4.38,1.32-15.8,3-21.77-4.43a11.73,11.73,0,0,1-2-4.84,6,6,0,0,1,.46-3.24S400.74,183.22,400.75,183.21Z" style="fill: rgb(38, 50, 56); transform-origin: 416.453px 187.452px;" id="ell0yhdzyox2" class="animable"></path>
                <path d="M414.81,223.28s6-1.56,8.42-3.14a11.39,11.39,0,0,0,3.79-4.07s-.59,3.41-2.27,5.21c-2.95,3.15-9.94,3.93-9.94,3.93Z" style="fill: rgb(242, 143, 143); transform-origin: 420.915px 220.64px;" id="elai0i73a72l" class="animable"></path>
                <path d="M414.94,205.9a1.46,1.46,0,1,0,1.45-1.52A1.49,1.49,0,0,0,414.94,205.9Z" style="fill: rgb(38, 50, 56); transform-origin: 416.399px 205.84px;" id="elsz648svkvfh" class="animable"></path>
                <path d="M416.26,199.64l3.19,1.58a1.87,1.87,0,0,0-.83-2.45A1.75,1.75,0,0,0,416.26,199.64Z" style="fill: rgb(38, 50, 56); transform-origin: 417.943px 199.908px;" id="eld3uzwhty07" class="animable"></path>
                <path d="M403,200.3l3.43-1a1.72,1.72,0,0,0-2.17-1.24A1.89,1.89,0,0,0,403,200.3Z" style="fill: rgb(38, 50, 56); transform-origin: 404.689px 199.143px;" id="el1yt4tt2as4e" class="animable"></path>
                <path d="M403.93,205a1.46,1.46,0,1,0,1.45-1.52A1.49,1.49,0,0,0,403.93,205Z" style="fill: rgb(38, 50, 56); transform-origin: 405.389px 204.94px;" id="elo7fa9ud5ku" class="animable"></path>
                <polygon points="410.85 211.97 406.03 210.43 411.04 202.41 410.85 211.97" style="fill: rgb(242, 143, 143); transform-origin: 408.535px 207.19px;" id="el876d92xyzth" class="animable"></polygon>
                <path d="M374.76,254.26h0l1-.81h0a1.14,1.14,0,0,1,1-.07l10.71,3.44a4,4,0,0,1,2.34,2.39l6.79,22.87a1,1,0,0,1-.2,1.14h0l-1.07.85v0a1.12,1.12,0,0,1-1,.08l-10.72-3.44a4,4,0,0,1-2.33-2.39c-1.33-4.49-5.46-18.37-6.79-22.87C374.34,254.88,374.45,254.47,374.76,254.26Z" style="fill: rgb(69, 90, 100); transform-origin: 385.56px 268.764px;" id="elz5yjz2lg8ce" class="animable"></path>
                <path d="M387.63,258.33a3.84,3.84,0,0,1,1.19,1.71l6.79,22.86a1.08,1.08,0,0,1-.16,1.13l1-.79h0a1,1,0,0,0,.2-1.15l-6.79-22.87a4,4,0,0,0-1.09-1.62Z" style="fill: rgb(38, 50, 56); transform-origin: 392.19px 270.815px;" id="elfmlwpukxx1" class="animable"></path>
                <path d="M395.61,282.91,388.82,260a4,4,0,0,0-2.34-2.39l-10.71-3.44c-1-.32-1.56.23-1.26,1.23,1.33,4.5,5.46,18.38,6.79,22.87a4,4,0,0,0,2.33,2.39l10.72,3.44C395.34,284.46,395.9,283.91,395.61,282.91Z" style="fill: rgb(55, 71, 79); transform-origin: 385.058px 269.145px;" id="el3tnxxu3ig7u" class="animable"></path>
                <path d="M376.71,262.85c-1.89-.54-3,1.48-2.86,5s2.27,10,7.7,8.37c2.11-.65,3.61-4.18,1.38-5.73a36.93,36.93,0,0,1-5.18-4.11Z" style="fill: rgb(242, 143, 143); transform-origin: 378.936px 269.621px;" id="el0a6m6ewd3y6" class="animable"></path>
                <path d="M443.7,284a96.6,96.6,0,0,0,7.24-26.56c1.73-14.85.73-20.76-9.88-24.06-7.72,6.88-5.32,23.53-5.32,23.53-1.32,6.22-3.52,16.8-5,20.74-10,0-28.29-5.55-30.34-6.64a12.21,12.21,0,0,1-2.9-3.1,7.88,7.88,0,0,0-2.74-2.49c-1.4-.73-2.55-1.16-3.55-1.7l1.41,4.78c-.11.27-.74.34-1,.44a17.32,17.32,0,0,1-6.59.84c-4.65-.33-9-3.69-8.1.44.37,1.64,1.06,4.72,3.53,6.79s11.53,3,14.08,4.14c0,0,23.32,9.76,32.87,10.88C437.57,293.17,439.45,292.8,443.7,284Z" style="fill: rgb(255, 168, 167); transform-origin: 414.215px 262.937px;" id="elbntdpsifu7p" class="animable"></path>
                <path d="M440.1,232.73c5.15,1.48,9.52,2.74,11.44,10.08s-1.91,25.12-1.91,25.12-9.62,2.09-16.21-2l1.5-9C434.4,246.4,435.42,237.78,440.1,232.73Z" style="fill: rgb(146, 227, 169); transform-origin: 442.749px 250.597px;" id="eldjwzn0ddg7l" class="animable"></path>
              </g>
            </g>
            <defs>
              <filter id="active" height="200%">
                <feMorphology in="SourceAlpha" result="DILATED" operator="dilate" radius="2"></feMorphology>
                <feFlood flood-color="#32DFEC" flood-opacity="1" result="PINK"></feFlood>
                <feComposite in="PINK" in2="DILATED" operator="in" result="OUTLINE"></feComposite>
                <feMerge>
                  <feMergeNode in="OUTLINE"></feMergeNode>
                  <feMergeNode in="SourceGraphic"></feMergeNode>
                </feMerge>
              </filter>
              <filter id="hover" height="200%">
                <feMorphology in="SourceAlpha" result="DILATED" operator="dilate" radius="2"></feMorphology>
                <feFlood flood-color="#ff0000" flood-opacity="0.5" result="PINK"></feFlood>
                <feComposite in="PINK" in2="DILATED" operator="in" result="OUTLINE"></feComposite>
                <feMerge>
                  <feMergeNode in="OUTLINE"></feMergeNode>
                  <feMergeNode in="SourceGraphic"></feMergeNode>
                </feMerge>
                <feColorMatrix type="matrix" values="0   0   0   0   0                0   1   0   0   0                0   0   0   0   0                0   0   0   1   0 "></feColorMatrix>
              </filter>
            </defs>
          </svg>
          <h1 class="jumbotron-heading">👋 Welcome to BASSuperApp SDK v0.1 for WooCommerce</h1>
          <p class="lead text-muted">Accepts payments with the BASSuperApp SDK v0.1 Gateway for WooCommerce<br />This is the Official BASSuperApp SDK v0.1 login and payment plugin for WooCommerce. Allows you to accept payments with the WooCommerce plugin. from differnt Wallets in Yemen It uses a seamless integration.</p>
          <p>
            <a target="_blank" href="https://github.com/magic-coding/thawani-pay-woocommerce" class="btn btn-dark my-2"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-github" viewBox="0 0 16 16">
                <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z" />
              </svg> Plugin website</a>
            <a target="_blank" href="https://docs.thawani.om/" class="btn btn-secondary my-2"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-code" viewBox="0 0 16 16">
                <path d="M6.646 5.646a.5.5 0 1 1 .708.708L5.707 8l1.647 1.646a.5.5 0 0 1-.708.708l-2-2a.5.5 0 0 1 0-.708l2-2zm2.708 0a.5.5 0 1 0-.708.708L10.293 8 8.646 9.646a.5.5 0 0 0 .708.708l2-2a.5.5 0 0 0 0-.708l-2-2z" />
                <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z" />
              </svg> Thawani developer docs</a>
          </p>
          <p style="right: 0;position: absolute;margin-top: 17px;margin-right: 30px;">
          </p>
          <p style="left: 0;position: absolute;margin-top: 13px;margin-left: 20px;color: gray;font-style: oblique;">
            <span class="text-grey">Latest update: 01/10/2024 • <a href="https://github.com/magic-coding/thawani-pay-woocommerce/releases" target="_blank">version 0.1.0</a></span>
          </p>
          <p style="left: 0;position: absolute;margin-top: 35px;margin-left: 20px;color: gray;font-style: oblique;">
            <span class="text-grey">Author: <a href="https://twitter.com/magic_coding" target="_blank">Abdullah AlAnsi</a></span>
          </p>
        </div>
      </section>

      <section>
        <div class="d-md-flex flex-md-equal w-100">
          <div class="col-md-6 text-dark pt-2 px-2 pt-md-3 px-md-3 overflow-hidden" style="background: #dff7e6 !important;">
            <div class="my-2 py-2">
              <p class="text-center">
                <svg width="40%" class="animated" id="freepik_stories-payment-information" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.com/svgjs">
                  <style>
                    svg#freepik_stories-payment-information:not(.animated) .animable {
                      opacity: 0;
                    }

                    svg#freepik_stories-payment-information.animated #freepik--background-simple--inject-32 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) zoomIn;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-payment-information.animated #freepik--Graphics--inject-32 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) lightSpeedLeft;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-payment-information.animated #freepik--Device--inject-32 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideRight;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-payment-information.animated #freepik--Hand--inject-32 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideDown;
                      animation-delay: 0s;
                    }

                    @keyframes zoomIn {
                      0% {
                        opacity: 0;
                        transform: scale(0.5);
                      }

                      100% {
                        opacity: 1;
                        transform: scale(1);
                      }
                    }

                    @keyframes lightSpeedLeft {
                      from {
                        transform: translate3d(-50%, 0, 0) skewX(20deg);
                        opacity: 0;
                      }

                      60% {
                        transform: skewX(-10deg);
                        opacity: 1;
                      }

                      80% {
                        transform: skewX(2deg);
                      }

                      to {
                        opacity: 1;
                        transform: translate3d(0, 0, 0);
                      }
                    }

                    @keyframes slideRight {
                      0% {
                        opacity: 0;
                        transform: translateX(30px);
                      }

                      100% {
                        opacity: 1;
                        transform: translateX(0);
                      }
                    }

                    @keyframes slideDown {
                      0% {
                        opacity: 0;
                        transform: translateY(-30px);
                      }

                      100% {
                        opacity: 1;
                        transform: translateY(0);
                      }
                    }
                  </style>
                  <g id="freepik--background-simple--inject-32" class="animable" style="transform-origin: 252.269px 250.609px;">
                    <g id="elsrfk9jtmvw8">
                      <path d="M67.93,167.09s-27.88,72,11.3,144.5S199.11,422.39,263.7,455.14s131.66,16.8,163.53-36.84-11.84-91.93-11.9-168.42,11.45-96.74-30-161.36-143.06-78.93-219.9-31S67.93,167.09,67.93,167.09Z" style="fill: rgb(255, 255, 255); opacity: 0.7; transform-origin: 248.588px 250.609px;" class="animable"></path>
                    </g>
                    <path d="M132,427.57c23.6,24.92,81.54,15.79,106.64,0C320.14,376.32,462.92,291,469.39,167.4c2.75-52.53-43.58-80.12-62.93-88.21C360,59.75,309,53.92,258.65,51.67c-52.57-2.35-109-.52-156.31,25.13C84.4,86.51,67.48,99,55.9,116c-11.76,17.28-18.53,37.86-20.33,58.63-2.89,33.5,6.12,67.09,19.75,97.83s31.8,59.2,48.48,88.4C114.69,379.89,112.16,406.58,132,427.57Z" style="fill: rgb(146, 227, 169); transform-origin: 252.269px 246.971px;" id="el3qadtee14qs" class="animable"></path>
                    <g id="el4929oy5m9t1">
                      <path d="M132,427.57c23.6,24.92,81.54,15.79,106.64,0C320.14,376.32,462.92,291,469.39,167.4c2.75-52.53-43.58-80.12-62.93-88.21C360,59.75,309,53.92,258.65,51.67c-52.57-2.35-109-.52-156.31,25.13C84.4,86.51,67.48,99,55.9,116c-11.76,17.28-18.53,37.86-20.33,58.63-2.89,33.5,6.12,67.09,19.75,97.83s31.8,59.2,48.48,88.4C114.69,379.89,112.16,406.58,132,427.57Z" style="fill: rgb(255, 255, 255); opacity: 0.7; transform-origin: 252.269px 246.971px;" class="animable"></path>
                    </g>
                  </g>
                  <g id="freepik--Graphics--inject-32" class="animable" style="transform-origin: 246.233px 239.213px;">
                    <g id="elh6lbb8xa7k9">
                      <rect x="83.13" y="132.19" width="56.97" height="36.43" rx="3.51" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 111.615px 150.405px; transform: rotate(16.8087deg);" class="animable"></rect>
                    </g>
                    <line x1="87.18" y1="132.84" x2="141.69" y2="149.36" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 114.435px 141.1px;" id="elespha8ld5q5" class="animable"></line>
                    <line x1="85.52" y1="138.31" x2="140.04" y2="154.83" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 112.78px 146.57px;" id="elje4ksk5n1d" class="animable"></line>
                    <path d="M123.45,165.06a3.56,3.56,0,0,0,.31,2.78,3.6,3.6,0,0,1-2.63.22,3.55,3.55,0,1,1,4.12-5.14A3.6,3.6,0,0,0,123.45,165.06Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 121.928px 164.662px;" id="elqs05zj1ige" class="animable"></path>
                    <path d="M130.24,167.12a3.55,3.55,0,1,1-2.36-4.43A3.55,3.55,0,0,1,130.24,167.12Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 126.844px 166.085px;" id="elyrhzwizmp0j" class="animable"></path>
                    <g id="elr89fdt75mg">
                      <rect x="418.6" y="202.08" width="48.45" height="30.98" rx="3.51" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 442.825px 217.57px; transform: rotate(16.86deg);" class="animable"></rect>
                    </g>
                    <line x1="422.04" y1="202.62" x2="468.41" y2="216.67" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 445.225px 209.645px;" id="elnjr9aywjipb" class="animable"></line>
                    <line x1="420.63" y1="207.28" x2="466.99" y2="221.33" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 443.81px 214.305px;" id="el01jj4uvnh3am" class="animable"></line>
                    <path d="M452.89,230a3,3,0,0,0,.26,2.36,3,3,0,1,1-.48-5.58,3,3,0,0,1,1.75,1.4A3,3,0,0,0,452.89,230Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 451.629px 229.662px;" id="el9trhhefyl1e" class="animable"></path>
                    <path d="M458.66,231.78a3,3,0,1,1-2-3.77A3,3,0,0,1,458.66,231.78Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 455.797px 230.883px;" id="elccoj0n1dqjk" class="animable"></path>
                    <g id="el130gucsvfmb9">
                      <rect x="349.8" y="110.95" width="63.66" height="33.12" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 381.63px 127.51px; transform: rotate(-24.6deg);" class="animable"></rect>
                    </g>
                    <path d="M412.49,125l-8.76-19.13a3.4,3.4,0,0,1-4.5-1.67l-46.79,21.42a3.4,3.4,0,0,1-1.67,4.5l8.76,19.13a3.39,3.39,0,0,1,4.5,1.68l46.78-21.43A3.39,3.39,0,0,1,412.49,125Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 381.63px 127.565px;" id="elvcliex4541h" class="animable"></path>
                    <g id="elmgich622bcc">
                      <circle cx="381.63" cy="127.51" r="8.7" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 381.63px 127.51px; transform: rotate(-37.53deg);" class="animable"></circle>
                    </g>
                    <path d="M384,131.74l.65,1.42-.62.28-.64-1.4a5.11,5.11,0,0,1-3.47.22l0-.87a4.59,4.59,0,0,0,3.08-.15L381.53,128c-1.52.29-3.09.51-3.84-1.13-.54-1.18-.16-2.6,1.66-3.61l-.65-1.43.62-.28L380,123a5.27,5.27,0,0,1,2.86-.38l0,.87a5,5,0,0,0-2.53.29l1.5,3.28c1.55-.31,3.2-.59,4,1.07C386.33,129.28,385.9,130.73,384,131.74Zm-2.86-4.61L379.72,124c-1.23.69-1.5,1.61-1.14,2.39C379,127.37,380,127.33,381.13,127.13Zm3.77,1.41c-.44-1-1.5-.9-2.68-.67l1.41,3.09C385,130.24,385.26,129.33,384.9,128.54Z" style="fill: rgb(38, 50, 56); transform-origin: 381.743px 127.495px;" id="eln6fui5j20ir" class="animable"></path>
                    <g id="el3ukpl1nwzux">
                      <rect x="39.58" y="237.71" width="44.51" height="23.15" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 61.835px 249.285px; transform: rotate(-5.14276deg);" class="animable"></rect>
                    </g>
                    <path d="M82.75,254.86l-1.26-14.65a2.38,2.38,0,0,1-2.57-2.16l-35.84,3.1a2.39,2.39,0,0,1-2.16,2.57l1.26,14.65a2.37,2.37,0,0,1,2.57,2.16l35.84-3.1A2.39,2.39,0,0,1,82.75,254.86Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 61.835px 249.29px;" id="elmvy1eo30kp" class="animable"></path>
                    <path d="M67.9,248.77a6.09,6.09,0,1,1-6.59-5.54A6.08,6.08,0,0,1,67.9,248.77Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 61.8329px 249.297px;" id="el18x577lu7nx" class="animable"></path>
                    <path d="M62.4,252.63l.09,1.09-.47,0-.1-1.08a3.57,3.57,0,0,1-2.33-.67l.22-.56a3.26,3.26,0,0,0,2.06.62l-.21-2.49c-1.07-.16-2.16-.39-2.26-1.64-.08-.91.5-1.76,1.94-2l-.09-1.09.47,0,.09,1.07a3.76,3.76,0,0,1,2,.43l-.19.57a3.56,3.56,0,0,0-1.73-.4l.21,2.51c1.09.16,2.25.37,2.36,1.64C64.52,251.56,63.89,252.41,62.4,252.63Zm-.8-3.71-.21-2.37c-1,.17-1.36.71-1.31,1.31S60.8,248.79,61.6,248.92Zm2.15,1.82c-.07-.75-.78-1-1.61-1.08l.2,2.37C63.38,251.86,63.8,251.34,63.75,250.74Z" style="fill: rgb(38, 50, 56); transform-origin: 61.9283px 249.265px;" id="elcsi9iw8ntls" class="animable"></path>
                    <circle cx="444.33" cy="104.01" r="8.7" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 444.33px 104.01px;" id="elmmuvafwi15s" class="animable"></circle>
                    <path d="M444.72,108.84v1.56H444v-1.55a5.18,5.18,0,0,1-3.25-1.23l.39-.78A4.69,4.69,0,0,0,444,108v-3.57c-1.5-.37-3-.83-3-2.63,0-1.3.94-2.43,3-2.59V97.62h.68v1.55a5.27,5.27,0,0,1,2.76.85l-.34.8a5,5,0,0,0-2.42-.79v3.6c1.53.37,3.15.8,3.15,2.63C447.87,107.57,446.88,108.72,444.72,108.84Zm-.68-5.39v-3.39c-1.4.12-2,.84-2,1.7C442,102.79,442.92,103.17,444,103.45Zm2.84,2.86c0-1.07-1-1.44-2.16-1.73V108C446.21,107.87,446.88,107.18,446.88,106.31Z" style="fill: rgb(38, 50, 56); transform-origin: 444.291px 104.01px;" id="el51qstxc9lhx" class="animable"></path>
                    <g id="el1112tx2pzvdc">
                      <circle cx="385.04" cy="211.92" r="8.7" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 385.04px 211.92px; transform: rotate(-13.26deg);" class="animable"></circle>
                    </g>
                    <path d="M382.84,216.24l-.81,1.33-.58-.36.81-1.32a5.09,5.09,0,0,1-2.12-2.75l.74-.46a4.66,4.66,0,0,0,1.83,2.47l1.88-3.05c-1.09-1.09-2.14-2.28-1.2-3.81.68-1.11,2.07-1.58,3.93-.63l.82-1.34.58.36-.81,1.32a5.42,5.42,0,0,1,1.91,2.16l-.71.51a5.1,5.1,0,0,0-1.65-1.94l-1.89,3.07c1.12,1.12,2.27,2.33,1.31,3.89C386.19,216.81,384.75,217.26,382.84,216.24Zm2.25-5,1.77-2.89c-1.25-.63-2.16-.35-2.61.38C383.71,209.66,384.28,210.47,385.09,211.29Zm.92,3.92c.56-.91-.09-1.74-.94-2.6l-1.78,2.9C384.62,216.2,385.55,216,386,215.21Z" style="fill: rgb(38, 50, 56); transform-origin: 384.98px 211.945px;" id="el7yg3qaat23v" class="animable"></path>
                    <path d="M260.18,44a8.7,8.7,0,1,1-11.53-4.3A8.69,8.69,0,0,1,260.18,44Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 252.266px 47.6114px;" id="elgfixwqf5o2k" class="animable"></path>
                    <path d="M254.63,51.83l.65,1.42-.62.29L254,52.13a5.11,5.11,0,0,1-3.47.22l0-.87a4.62,4.62,0,0,0,3.07-.14l-1.48-3.26c-1.52.29-3.09.5-3.84-1.13-.54-1.19-.16-2.6,1.67-3.61l-.65-1.43.61-.28.65,1.41a5.27,5.27,0,0,1,2.86-.38l0,.87a5.05,5.05,0,0,0-2.53.29l1.5,3.28c1.55-.31,3.2-.58,4,1.07C257,49.37,256.54,50.82,254.63,51.83Zm-2.86-4.61-1.41-3.09c-1.23.69-1.5,1.61-1.14,2.38C249.65,47.45,250.64,47.42,251.77,47.22Zm3.77,1.41c-.45-1-1.5-.89-2.68-.67l1.41,3.09C255.58,50.33,255.9,49.43,255.54,48.63Z" style="fill: rgb(38, 50, 56); transform-origin: 252.348px 47.585px;" id="el8rcnuikx2wi" class="animable"></path>
                    <path d="M230.88,396.33a13.07,13.07,0,1,1-17.32-6.46A13.07,13.07,0,0,1,230.88,396.33Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 218.991px 401.758px;" id="elm1m6f2gwl6" class="animable"></path>
                    <path d="M222.53,408.11l1,2.14-.93.42-1-2.12a7.66,7.66,0,0,1-5.21.34l.06-1.3a6.93,6.93,0,0,0,4.61-.23l-2.23-4.88c-2.27.43-4.64.75-5.76-1.7-.82-1.78-.24-3.91,2.5-5.43l-1-2.13.92-.43,1,2.12a8,8,0,0,1,4.3-.57l0,1.3a7.54,7.54,0,0,0-3.79.44l2.24,4.92c2.33-.46,4.81-.87,5.95,1.61C226.05,404.42,225.41,406.6,222.53,408.11Zm-4.28-6.93-2.13-4.64c-1.83,1-2.24,2.41-1.7,3.59C215.06,401.54,216.54,401.49,218.25,401.18Zm5.66,2.13c-.67-1.47-2.26-1.35-4-1l2.12,4.64C224,405.86,224.45,404.5,223.91,403.31Z" style="fill: rgb(38, 50, 56); transform-origin: 219.135px 401.73px;" id="elblo7iejs9z" class="animable"></path>
                    <path d="M43.66,193.23a13.07,13.07,0,1,1,1.19-18.45A13.07,13.07,0,0,1,43.66,193.23Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 35.0346px 183.41px;" id="elzdd3cqmqe4l" class="animable"></path>
                    <path d="M30,188.63l-1.76,1.55-.68-.77,1.75-1.53a7.75,7.75,0,0,1-1.83-4.9l1.27-.32A7,7,0,0,0,30.26,187l4-3.54c-1.07-2.05-2.06-4.23,0-6,1.48-1.29,3.68-1.35,5.92.83L42,176.75l.67.76-1.75,1.54A8,8,0,0,1,42.66,183l-1.24.41a7.67,7.67,0,0,0-1.51-3.51l-4.07,3.57c1.11,2.09,2.22,4.35.17,6.16C34.52,190.93,32.25,191,30,188.63Zm5.4-6.1,3.83-3.37c-1.52-1.46-3-1.45-3.92-.6C34.11,179.58,34.59,181,35.37,182.53Zm-.41,6c1.22-1.06.65-2.55-.19-4.15l-3.83,3.37C32.54,189.36,34,189.42,35,188.56Z" style="fill: rgb(38, 50, 56); transform-origin: 35.075px 183.543px;" id="elsfox8jybvg" class="animable"></path>
                    <g id="el5qi42kji4zc">
                      <circle cx="96.56" cy="303.17" r="6.31" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 96.56px 303.17px; transform: rotate(-87.7094deg);" class="animable"></circle>
                    </g>
                    <path d="M99,305.68l.72.87-.37.31-.72-.86a3.72,3.72,0,0,1-2.39.82l-.14-.62a3.37,3.37,0,0,0,2.12-.68l-1.66-2c-1,.49-2.06.94-2.9-.06-.6-.72-.6-1.79.48-2.85l-.73-.87.38-.31.72.86a3.87,3.87,0,0,1,1.93-.81l.18.6a3.79,3.79,0,0,0-1.71.69l1.67,2c1-.51,2.13-1,3,0C100.19,303.51,100.17,304.61,99,305.68ZM96.14,303l-1.58-1.89c-.72.72-.73,1.41-.34,1.89S95.39,303.35,96.14,303Zm2.91.27c-.5-.59-1.22-.34-2,.05l1.58,1.89C99.4,304.45,99.45,303.75,99.05,303.26Z" style="fill: rgb(38, 50, 56); transform-origin: 96.6169px 303.145px;" id="el5wdrejbyk8s" class="animable"></path>
                    <path d="M297.59,429.17a6.31,6.31,0,1,1-8.89-.81A6.32,6.32,0,0,1,297.59,429.17Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 292.74px 433.209px;" id="elhx27sxcpxgw" class="animable"></path>
                    <path d="M295.19,435.72l.73.87-.38.31-.72-.86a3.69,3.69,0,0,1-2.38.82l-.14-.62a3.31,3.31,0,0,0,2.12-.68l-1.66-2c-1,.49-2.07.94-2.9-.06-.61-.72-.61-1.79.47-2.85l-.72-.87.38-.31.72.86a3.79,3.79,0,0,1,1.93-.81l.18.6a3.83,3.83,0,0,0-1.72.69l1.68,2c1-.51,2.13-1,3,0C296.36,433.55,296.34,434.65,295.19,435.72ZM292.32,433l-1.58-1.89c-.73.72-.74,1.41-.34,1.89S291.56,433.39,292.32,433Zm2.9.28c-.5-.6-1.22-.35-2,0l1.58,1.89C295.58,434.49,295.63,433.79,295.22,433.31Z" style="fill: rgb(38, 50, 56); transform-origin: 292.802px 433.185px;" id="el1gatbkn1lsu" class="animable"></path>
                    <g id="eldv9byytqvz">
                      <circle cx="118.61" cy="69.11" r="6.31" style="fill: none; stroke: rgb(38, 50, 56); stroke-miterlimit: 10; transform-origin: 118.61px 69.11px; transform: rotate(-84.76deg);" class="animable"></circle>
                    </g>
                    <path d="M121.07,71.62l.72.87-.38.31-.72-.86a3.67,3.67,0,0,1-2.38.82l-.14-.62a3.37,3.37,0,0,0,2.12-.68l-1.66-2c-1,.49-2.07.94-2.9-.06-.6-.72-.61-1.79.48-2.85l-.73-.87.38-.31.72.86a3.83,3.83,0,0,1,1.93-.81l.18.6a3.79,3.79,0,0,0-1.71.69l1.67,2c1-.51,2.13-1,3,0C122.24,69.45,122.21,70.55,121.07,71.62Zm-2.88-2.69L116.61,67c-.72.72-.74,1.41-.34,1.89S117.43,69.29,118.19,68.93Zm2.91.27c-.5-.59-1.23-.34-2,0l1.58,1.89C121.45,70.39,121.5,69.69,121.1,69.2Z" style="fill: rgb(38, 50, 56); transform-origin: 118.677px 69.085px;" id="eleycs42tecq" class="animable"></path>
                  </g>
                  <g id="freepik--Device--inject-32" class="animable" style="transform-origin: 196.533px 259.285px;">
                    <g id="el3xgakw35t6n">
                      <path d="M260.81,327.81s-7.71,39.13-55,48c0,0-12.35,9.85-16.46,15.68s-26,15.74-48.3,46.66-55.5-67.27-55.5-67.27l73.38-95.3Z" style="fill-opacity: 0.7; opacity: 0.2; transform-origin: 173.18px 359.877px;" class="animable"></path>
                    </g>
                    <path d="M302.84,144.08s25.5-12.47,31.11-10.32,11,15.05-10.45,27.4-15.05,35.36-15.05,35.36h15.3s9.18,3.74,12.75,8.55,6.12,21.21,8.16,26.77,1,11.13-4.59,12.94c0,0-3.57,15.56-2.55,22.46s.51,11.34-6.12,12.18a11.57,11.57,0,0,1-12.75-12.18l-5.1,18.48,3.57,7.1a46.16,46.16,0,0,1,13.77,8.34c7.14,6.12,14.28,7.79,12.24,14.61s-6.12,14.17-27.54,5.65-39.27-42-39.27-42L263.45,162.68Z" style="fill: rgb(255, 255, 255); transform-origin: 304.538px 229.386px;" id="el1nswwtj0vrm" class="animable"></path>
                    <g id="el92jve5r1i1s">
                      <path d="M329.68,211.14c-11.56,15.76,1.23,33.91,1.23,33.91s-8,4.46-8.23,15.84c-.09,4.73,1.1,11.34,2.52,17.46a11.46,11.46,0,0,1-6.54-11.11l-5.11,18.48,3.57,7.09a43.77,43.77,0,0,1,10.52,5.79c-3.17,7.28-4.14,16.28-3.86,25.56a68.24,68.24,0,0,1-8.19-2.74c-21.42-8.53-39.27-42-39.27-42L263.45,162.67l39.39-18.59s13.93-6.81,23.47-9.5c-.74,8.92,1,18.15,2.26,23.29a53.8,53.8,0,0,1-5.07,3.29c-21.42,12.35-15.05,35.36-15.05,35.36h15.31s8.72,3.56,12.46,8.19A25,25,0,0,0,329.68,211.14Z" style="fill: rgb(146, 227, 169); opacity: 0.4; transform-origin: 299.835px 229.37px;" class="animable"></path>
                    </g>
                    <path d="M263,309s-27.74,54.39-77.74,66.73c0,0-1.91,10.76-10.37,15.38-4.52,2.46-22.76,29.79-38.77,54.74L47.44,391.45c5.46-5.4,11.68-11.62,18-18.11,18.5-18.86,38.24-40,44.28-50.46,5.4-9.3,12.23-25.44,18.18-40.61,7.62-19.4,13.82-37.22,13.82-37.22l29.56-32.86,46.47-51.67,2,6.61Z" style="fill: rgb(255, 255, 255); transform-origin: 155.22px 303.185px;" id="el1fyo7g0wzv1" class="animable"></path>
                    <g id="eltqm0v3e4">
                      <path d="M217.79,160.52,263,309s-27.74,54.39-77.74,66.73c0,0-1.91,10.76-10.37,15.38-4.06,2.21-19.17,24.46-33.79,47,5.42-18.6,21.11-48.25,21.11-48.25-10.07-11.93-7.13-24.5-3.1-20.92s14.16,13.64,14.8,14.27c-4.45-5.24-5.48-13.45-5.48-13.45s12.48,8.3,13.49-5-7.48-45.8-7.48-45.8L152.15,233.5Z" style="fill: rgb(146, 227, 169); opacity: 0.4; transform-origin: 202.05px 299.315px;" class="animable"></path>
                    </g>
                    <path d="M263.45,162.68l39.39-18.6s25.5-12.47,31.11-10.32,11,15.05-10.45,27.4-15.05,35.36-15.05,35.36h15.3s9.18,3.74,12.75,8.55,6.12,21.21,8.16,26.77,1,11.13-4.59,12.94c0,0-3.57,15.56-2.55,22.46s.51,11.34-6.12,12.18a11.57,11.57,0,0,1-12.75-12.18l-5.1,18.48,3.57,7.1a46.16,46.16,0,0,1,13.77,8.34c7.14,6.12,14.28,7.79,12.24,14.61s-6.12,14.17-27.54,5.65-39.27-42-39.27-42" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 304.538px 229.386px;" id="eljrbrflqwfr" class="animable"></path>
                    <path d="M340.07,244.78c-7.65,2.07-16.57.54-19.89-13s3.32-28,7.65-29.33" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 329.676px 223.992px;" id="elqojq8j9n8uj" class="animable"></path>
                    <path d="M318.65,267.24s-.51-17.41,5.87-21.71" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 321.58px 256.385px;" id="eldzbis4uzhnn" class="animable"></path>
                    <g id="elo55e5hxq31r">
                      <path d="M132.62,353.71s-18.69-2.33-22.86-10.69c-3.45-6.92-22.15,19-44.28,30.32,18.5-18.86,38.24-40,44.28-50.46,5.4-9.3,12.23-25.44,18.18-40.61v0c-.3,12.58-7,31.84-5.09,41.87a8.05,8.05,0,0,0-5.75,9.74C118.53,341.85,132.62,353.71,132.62,353.71Z" style="fill: rgb(146, 227, 169); opacity: 0.4; transform-origin: 99.05px 327.805px;" class="animable"></path>
                    </g>
                    <path d="M263,309s-27.74,54.39-77.75,66.73c0,0-1.9,10.76-10.36,15.38-4.52,2.46-22.75,29.79-38.78,54.74" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 199.555px 377.425px;" id="ellecqb72ngm9" class="animable"></path>
                    <path d="M47.44,391.45c21.36-21.11,54.19-54.58,62.32-68.57,12.3-21.17,32-77.84,32-77.84l76-84.52" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 132.6px 275.985px;" id="elj2k03jqmfyd" class="animable"></path>
                    <path d="M174.39,225.07s11.68,10.46,15,20" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 181.89px 235.07px;" id="eljbhzqzk7jog" class="animable"></path>
                    <rect x="179.86" y="72.72" width="139.13" height="280.99" rx="6.73" style="fill: rgb(38, 50, 56); transform-origin: 249.425px 213.215px;" id="el4odgjy84m4q" class="animable"></rect>
                    <path d="M277,80.37l-2.66,4.87A3.32,3.32,0,0,1,271.43,87h-44a3.35,3.35,0,0,1-2.92-1.74l-2.65-4.87a3.32,3.32,0,0,0-2.92-1.74H188.19A3.33,3.33,0,0,0,184.87,82V345a3.33,3.33,0,0,0,3.32,3.33H310.65A3.33,3.33,0,0,0,314,345V82a3.33,3.33,0,0,0-3.33-3.33H279.93A3.32,3.32,0,0,0,277,80.37Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 249.435px 213.49px;" id="elctip7ex5flt" class="animable"></path>
                    <circle cx="239.88" cy="80.94" r="1.79" style="fill: none; stroke: rgb(255, 255, 255); stroke-miterlimit: 10; transform-origin: 239.88px 80.94px;" id="elqwpmcia40mg" class="animable"></circle>
                    <path d="M260.75,80.94a1.11,1.11,0,1,1-1.11-1.12A1.11,1.11,0,0,1,260.75,80.94Z" style="fill: none; stroke: rgb(255, 255, 255); stroke-miterlimit: 10; transform-origin: 259.64px 80.93px;" id="el5dk3hbucl1v" class="animable"></path>
                    <path d="M255.77,80.24H244.89a.69.69,0,0,0-.69.7h0a.69.69,0,0,0,.69.69h10.88a.69.69,0,0,0,.69-.69h0A.69.69,0,0,0,255.77,80.24Z" style="fill: rgb(255, 255, 255); transform-origin: 250.33px 80.935px;" id="el4m0nth74mw8" class="animable"></path>
                    <rect x="195.91" y="85.32" width="11.73" height="4.98" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 201.775px 87.81px;" id="el4gf7wwvclw2" class="animable"></rect>
                    <rect x="207.63" y="86.16" width="1.82" height="3.31" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 208.54px 87.815px;" id="elxneqii9h7il" class="animable"></rect>
                    <rect x="288.68" y="88.5" width="2.08" height="1.81" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 289.72px 89.405px;" id="el768v1edbfk" class="animable"></rect>
                    <rect x="292.74" y="86.68" width="2.08" height="3.63" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 293.78px 88.495px;" id="elrd7rji8ay" class="animable"></rect>
                    <rect x="296.8" y="84.86" width="2.08" height="5.44" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 297.84px 87.58px;" id="elg90ju2a6drb" class="animable"></rect>
                    <rect x="300.85" y="83.04" width="2.08" height="7.26" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 301.89px 86.67px;" id="el6dbfq05gn1" class="animable"></rect>
                    <g id="elllhu8r0jf9j">
                      <rect x="185.7" y="136.76" width="127.44" height="121.55" style="fill: rgb(255, 255, 255); opacity: 0.5; transform-origin: 249.42px 197.535px;" class="animable"></rect>
                    </g>
                    <path d="M220.9,117.35v2.11c0,2.61-1.25,4-3.81,4h-1.21v6.11h-2.56V113.32h3.77C219.65,113.32,220.9,114.74,220.9,117.35Zm-5-1.7v5.51h1.21c.81,0,1.25-.37,1.25-1.53v-2.45c0-1.16-.44-1.53-1.25-1.53Z" style="fill: rgb(38, 50, 56); transform-origin: 217.11px 121.445px;" id="elhdeojdotw48" class="animable"></path>
                    <path d="M230.07,129.6h-2.58l-.45-2.95h-3.13l-.45,2.95h-2.35l2.61-16.28h3.74Zm-5.84-5.16h2.47l-1.21-8.23h-.05Z" style="fill: rgb(38, 50, 56); transform-origin: 225.59px 121.46px;" id="el9a10l489l6" class="animable"></path>
                    <path d="M232.51,124.21l-3.23-10.89H232l1.93,7.42h.05l1.93-7.42h2.44l-3.23,10.89v5.39h-2.56Z" style="fill: rgb(38, 50, 56); transform-origin: 233.815px 121.46px;" id="elxi39qr2oqq" class="animable"></path>
                    <path d="M244.72,124.88h.05l1.72-11.56h3.56V129.6h-2.42V117.93h0l-1.72,11.67h-2.42l-1.86-11.51h0V129.6H239.3V113.32h3.56Z" style="fill: rgb(38, 50, 56); transform-origin: 244.675px 121.46px;" id="elh5j68d1sqn8" class="animable"></path>
                    <path d="M254.47,120.18H258v2.33h-3.51v4.77h4.42v2.32h-7V113.32h7v2.33h-4.42Z" style="fill: rgb(38, 50, 56); transform-origin: 255.41px 121.46px;" id="el0q9c3k17odv" class="animable"></path>
                    <path d="M262.8,117.81h-.05V129.6h-2.3V113.32h3.21l2.58,9.75h0v-9.75h2.28V129.6h-2.62Z" style="fill: rgb(38, 50, 56); transform-origin: 264.485px 121.46px;" id="eleq0o5qd5bxr" class="animable"></path>
                    <path d="M269.66,113.32h7.91v2.33h-2.68V129.6h-2.56V115.65h-2.67Z" style="fill: rgb(38, 50, 56); transform-origin: 273.615px 121.46px;" id="elfcl2bvlrsf7" class="animable"></path>
                    <path d="M282.13,113.14c2.48,0,3.76,1.49,3.76,4.09v.51h-2.42v-.67c0-1.17-.46-1.61-1.28-1.61s-1.27.44-1.27,1.61.51,2.07,2.18,3.53c2.14,1.89,2.82,3.23,2.82,5.09,0,2.61-1.31,4.1-3.82,4.1s-3.81-1.49-3.81-4.1v-1h2.42v1.17c0,1.16.51,1.58,1.32,1.58s1.33-.42,1.33-1.58-.51-2.07-2.19-3.54c-2.14-1.88-2.81-3.23-2.81-5.09C278.36,114.63,279.64,113.14,282.13,113.14Z" style="fill: rgb(38, 50, 56); transform-origin: 282.105px 121.465px;" id="elvnkcihcq51k" class="animable"></path>
                    <rect x="195.91" y="151.49" width="107.02" height="15.99" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 249.42px 159.485px;" id="elbzscq4zxn8" class="animable"></rect>
                    <rect x="195.91" y="181.48" width="107.02" height="15.99" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 249.42px 189.475px;" id="elpee9ednrqwo" class="animable"></rect>
                    <rect x="195.91" y="208.75" width="107.02" height="15.99" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 249.42px 216.745px;" id="el9ff6gwhrs3l" class="animable"></rect>
                    <rect x="195.91" y="230.57" width="50.67" height="18.72" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 221.245px 239.93px;" id="el4me7zqdjtsm" class="animable"></rect>
                    <rect x="252.27" y="230.57" width="50.67" height="18.72" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 277.605px 239.93px;" id="elmxjx32kne6" class="animable"></rect>
                    <path d="M202.58,148.7v.43h-3.35V144.4h3.25v.43h-2.74v1.69h2.44v.42h-2.44v1.76Z" style="fill: rgb(38, 50, 56); transform-origin: 200.905px 146.765px;" id="elfb0hthqrnxi" class="animable"></path>
                    <path d="M209.52,147.07v2.06H209v-2c0-.75-.38-1.14-1-1.14a1.15,1.15,0,0,0-1.19,1.29v1.86h-.48v-2c0-.75-.38-1.14-1-1.14a1.15,1.15,0,0,0-1.19,1.29v1.86h-.48v-3.55h.46v.65a1.44,1.44,0,0,1,1.31-.68,1.26,1.26,0,0,1,1.26.75,1.56,1.56,0,0,1,1.41-.75A1.34,1.34,0,0,1,209.52,147.07Z" style="fill: rgb(38, 50, 56); transform-origin: 206.595px 147.358px;" id="el77iqcfskf3e" class="animable"></path>
                    <path d="M213.51,146.93v2.2h-.46v-.55a1.36,1.36,0,0,1-1.23.59c-.8,0-1.29-.42-1.29-1s.35-1,1.37-1H213v-.22c0-.61-.35-.94-1-.94a1.79,1.79,0,0,0-1.18.42l-.22-.36a2.3,2.3,0,0,1,1.45-.48A1.26,1.26,0,0,1,213.51,146.93Zm-.48,1.14v-.59h-1.12c-.69,0-.91.27-.91.64s.33.67.9.67A1.15,1.15,0,0,0,213,148.07Z" style="fill: rgb(38, 50, 56); transform-origin: 212.022px 147.374px;" id="elxv0oet8zob" class="animable"></path>
                    <path d="M214.7,144.46a.35.35,0,0,1,.35-.34.34.34,0,1,1,0,.68A.35.35,0,0,1,214.7,144.46Zm.11,1.12h.48v3.55h-.48Z" style="fill: rgb(38, 50, 56); transform-origin: 215.046px 146.625px;" id="elh7gpu0o8vxm" class="animable"></path>
                    <path d="M216.63,144.12h.48v5h-.48Z" style="fill: rgb(38, 50, 56); transform-origin: 216.87px 146.62px;" id="eldhayy7o1ngu" class="animable"></path>
                    <path d="M205.89,161v.43h-3.35v-4.73h3.25v.43H203v1.68h2.45v.43H203V161Z" style="fill: rgb(38, 50, 56); transform-origin: 204.215px 159.065px;" id="elvtq0hot68q" class="animable"></path>
                    <path d="M212.83,159.35v2.06h-.48v-2c0-.75-.38-1.14-1-1.14a1.14,1.14,0,0,0-1.19,1.28v1.87h-.48v-2c0-.75-.38-1.14-1-1.14a1.14,1.14,0,0,0-1.19,1.28v1.87H207v-3.56h.46v.65a1.45,1.45,0,0,1,1.31-.67,1.28,1.28,0,0,1,1.26.75,1.55,1.55,0,0,1,1.41-.75A1.34,1.34,0,0,1,212.83,159.35Z" style="fill: rgb(38, 50, 56); transform-origin: 209.92px 159.638px;" id="elke60dy6wgxa" class="animable"></path>
                    <path d="M216.81,159.21v2.2h-.46v-.55a1.33,1.33,0,0,1-1.22.58c-.8,0-1.3-.42-1.3-1s.36-1,1.38-1h1.13v-.21c0-.61-.35-.94-1-.94a1.83,1.83,0,0,0-1.19.42l-.21-.36a2.22,2.22,0,0,1,1.45-.48A1.26,1.26,0,0,1,216.81,159.21Zm-.47,1.13v-.58h-1.12c-.69,0-.91.27-.91.64s.33.67.9.67A1.14,1.14,0,0,0,216.34,160.34Z" style="fill: rgb(38, 50, 56); transform-origin: 215.322px 159.652px;" id="eldcmcz19q2lu" class="animable"></path>
                    <path d="M218,156.73a.35.35,0,0,1,.35-.34.34.34,0,0,1,.35.34.35.35,0,1,1-.7,0Zm.11,1.12h.48v3.56h-.48Z" style="fill: rgb(38, 50, 56); transform-origin: 218.35px 158.9px;" id="elkpsvfkd9q2f" class="animable"></path>
                    <path d="M219.94,156.39h.47v5h-.47Z" style="fill: rgb(38, 50, 56); transform-origin: 220.175px 158.89px;" id="elvoyy82wa7qj" class="animable"></path>
                    <path d="M225,159.35v2.06h-.48v-2a1,1,0,0,0-1.06-1.14,1.16,1.16,0,0,0-1.25,1.28v1.87h-.48v-3.56h.46v.66a1.5,1.5,0,0,1,1.35-.68A1.36,1.36,0,0,1,225,159.35Z" style="fill: rgb(38, 50, 56); transform-origin: 223.369px 159.628px;" id="el3hihjt6awm" class="animable"></path>
                    <path d="M229,159.21v2.2h-.46v-.55a1.33,1.33,0,0,1-1.22.58c-.81,0-1.3-.42-1.3-1s.35-1,1.37-1h1.13v-.21c0-.61-.34-.94-1-.94a1.79,1.79,0,0,0-1.18.42l-.22-.36a2.24,2.24,0,0,1,1.45-.48A1.26,1.26,0,0,1,229,159.21Zm-.48,1.13v-.58h-1.11c-.69,0-.92.27-.92.64s.33.67.9.67A1.14,1.14,0,0,0,228.53,160.34Z" style="fill: rgb(38, 50, 56); transform-origin: 227.512px 159.652px;" id="elv7y3xq9o8dr" class="animable"></path>
                    <path d="M236.18,159.35v2.06h-.48v-2c0-.75-.38-1.14-1-1.14a1.14,1.14,0,0,0-1.19,1.28v1.87H233v-2c0-.75-.38-1.14-1-1.14a1.14,1.14,0,0,0-1.19,1.28v1.87h-.48v-3.56h.46v.65a1.45,1.45,0,0,1,1.31-.67,1.26,1.26,0,0,1,1.26.75,1.56,1.56,0,0,1,1.41-.75A1.34,1.34,0,0,1,236.18,159.35Z" style="fill: rgb(38, 50, 56); transform-origin: 233.26px 159.638px;" id="el77rb36eglpn" class="animable"></path>
                    <path d="M240.59,159.78h-3A1.32,1.32,0,0,0,239,161a1.41,1.41,0,0,0,1.09-.47l.27.31a1.88,1.88,0,0,1-3.24-1.23,1.71,1.71,0,0,1,1.74-1.8,1.69,1.69,0,0,1,1.72,1.8S240.59,159.73,240.59,159.78Zm-3-.36h2.52a1.26,1.26,0,0,0-2.52,0Z" style="fill: rgb(38, 50, 56); transform-origin: 238.854px 159.616px;" id="elpo9xb83wht" class="animable"></path>
                    <path d="M247.55,159.62c0,1.15-.43,1.82-1.13,1.82a.72.72,0,0,1-.76-.73,1.51,1.51,0,0,1-1.35.73,1.74,1.74,0,0,1,0-3.48,1.5,1.5,0,0,1,1.34.71V158h.44v2.56c0,.38.19.52.42.52.41,0,.68-.52.68-1.43a2.58,2.58,0,0,0-2.77-2.65,2.72,2.72,0,1,0,0,5.44,3,3,0,0,0,1.3-.3l.11.33a3.26,3.26,0,0,1-1.41.31,3.06,3.06,0,1,1,0-6.12A2.92,2.92,0,0,1,247.55,159.62Zm-1.9.07a1.29,1.29,0,1,0-2.57,0,1.29,1.29,0,1,0,2.57,0Z" style="fill: rgb(38, 50, 56); transform-origin: 244.452px 159.716px;" id="elldf8pa0fjfo" class="animable"></path>
                    <path d="M251.65,159.78h-3a1.32,1.32,0,0,0,1.4,1.24,1.41,1.41,0,0,0,1.09-.47l.27.31a1.88,1.88,0,0,1-3.24-1.23,1.71,1.71,0,0,1,1.74-1.8,1.69,1.69,0,0,1,1.72,1.8S251.65,159.73,251.65,159.78Zm-3-.36h2.52a1.26,1.26,0,0,0-2.52,0Z" style="fill: rgb(38, 50, 56); transform-origin: 249.909px 159.636px;" id="el1k8s8vn8i5n" class="animable"></path>
                    <path d="M258.51,159.35v2.06H258v-2c0-.75-.38-1.14-1-1.14a1.14,1.14,0,0,0-1.19,1.28v1.87h-.48v-2c0-.75-.38-1.14-1-1.14a1.14,1.14,0,0,0-1.19,1.28v1.87h-.48v-3.56h.46v.65a1.44,1.44,0,0,1,1.3-.67,1.29,1.29,0,0,1,1.27.75,1.53,1.53,0,0,1,1.4-.75A1.34,1.34,0,0,1,258.51,159.35Z" style="fill: rgb(38, 50, 56); transform-origin: 255.59px 159.638px;" id="elehuq7dofq64" class="animable"></path>
                    <path d="M262.49,159.21v2.2H262v-.55a1.34,1.34,0,0,1-1.22.58c-.81,0-1.3-.42-1.3-1s.35-1,1.37-1H262v-.21c0-.61-.34-.94-1-.94a1.79,1.79,0,0,0-1.18.42l-.22-.36a2.24,2.24,0,0,1,1.45-.48A1.26,1.26,0,0,1,262.49,159.21Zm-.48,1.13v-.58H260.9c-.69,0-.92.27-.92.64s.33.67.9.67A1.14,1.14,0,0,0,262,160.34Z" style="fill: rgb(38, 50, 56); transform-origin: 260.987px 159.651px;" id="el7eylw6yrqwa" class="animable"></path>
                    <path d="M263.69,156.73a.35.35,0,1,1,.35.35A.35.35,0,0,1,263.69,156.73Zm.1,1.12h.48v3.56h-.48Z" style="fill: rgb(38, 50, 56); transform-origin: 264.04px 158.895px;" id="el7sfezlaioc8" class="animable"></path>
                    <path d="M265.61,156.39h.48v5h-.48Z" style="fill: rgb(38, 50, 56); transform-origin: 265.85px 158.89px;" id="elloynobalpz" class="animable"></path>
                    <path d="M267.12,161.07a.35.35,0,0,1,.36-.36.36.36,0,0,1,.36.36.37.37,0,0,1-.36.37A.36.36,0,0,1,267.12,161.07Z" style="fill: rgb(38, 50, 56); transform-origin: 267.48px 161.075px;" id="elu0xkbnl3xt" class="animable"></path>
                    <path d="M268.4,159.63a1.75,1.75,0,0,1,1.83-1.8,1.55,1.55,0,0,1,1.4.7l-.36.24a1.22,1.22,0,0,0-1-.52,1.39,1.39,0,0,0,0,2.77,1.2,1.2,0,0,0,1-.52l.36.24a1.57,1.57,0,0,1-1.4.7A1.75,1.75,0,0,1,268.4,159.63Z" style="fill: rgb(38, 50, 56); transform-origin: 270.014px 159.635px;" id="elzm4qgtxgjkp" class="animable"></path>
                    <path d="M272.15,159.63a1.81,1.81,0,1,1,1.81,1.81A1.75,1.75,0,0,1,272.15,159.63Zm3.13,0A1.33,1.33,0,1,0,274,161,1.3,1.3,0,0,0,275.28,159.63Z" style="fill: rgb(38, 50, 56); transform-origin: 273.959px 159.631px;" id="elkq5t8cmp8yl" class="animable"></path>
                    <path d="M282.61,159.35v2.06h-.48v-2c0-.75-.38-1.14-1-1.14a1.14,1.14,0,0,0-1.19,1.28v1.87h-.48v-2c0-.75-.38-1.14-1-1.14a1.14,1.14,0,0,0-1.19,1.28v1.87h-.48v-3.56h.46v.65a1.44,1.44,0,0,1,1.3-.67,1.29,1.29,0,0,1,1.27.75,1.54,1.54,0,0,1,1.4-.75A1.34,1.34,0,0,1,282.61,159.35Z" style="fill: rgb(38, 50, 56); transform-origin: 279.705px 159.638px;" id="elsm3c8tkvkt" class="animable"></path>
                    <path d="M203.39,189.56l.84.48-.15.28-.85-.49v1h-.31v-1l-.85.49-.16-.28.85-.48-.85-.47.16-.29.85.5v-1h.31v1l.85-.5.15.29Z" style="fill: rgb(38, 50, 56); transform-origin: 203.07px 189.565px;" id="ele7tzep5x577" class="animable"></path>
                    <path d="M206.14,189.56l.84.48-.16.28-.84-.49v1h-.32v-1l-.84.49-.17-.28.85-.48-.85-.47.17-.29.84.5v-1H206v1l.84-.5.16.29Z" style="fill: rgb(38, 50, 56); transform-origin: 205.825px 189.565px;" id="elm4wd7fsa7ir" class="animable"></path>
                    <path d="M208.88,189.56l.84.48-.16.28-.84-.49v1h-.31v-1l-.84.49-.16-.28.84-.48-.84-.47.16-.29.84.5v-1h.31v1l.84-.5.16.29Z" style="fill: rgb(38, 50, 56); transform-origin: 208.565px 189.565px;" id="el8cyk5goy4dg" class="animable"></path>
                    <path d="M211.62,189.56l.84.48-.15.28-.85-.49v1h-.31v-1l-.85.49-.16-.28.85-.48-.85-.47.16-.29.85.5v-1h.31v1l.85-.5.15.29Z" style="fill: rgb(38, 50, 56); transform-origin: 211.3px 189.565px;" id="eln37z0s50jw" class="animable"></path>
                    <path d="M214.37,189.56l.84.48-.16.28-.84-.49v1h-.32v-1l-.84.49-.17-.28.85-.48-.85-.47.17-.29.84.5v-1h.32v1l.84-.5.16.29Z" style="fill: rgb(38, 50, 56); transform-origin: 214.045px 189.565px;" id="elfstlg7ieykk" class="animable"></path>
                    <path d="M217.11,189.56l.84.48-.16.28-.84-.49v1h-.31v-1l-.84.49-.16-.28.84-.48-.84-.47.16-.29.84.5v-1H217v1l.84-.5.16.29Z" style="fill: rgb(38, 50, 56); transform-origin: 216.82px 189.565px;" id="eleyb50dysfih" class="animable"></path>
                    <path d="M219.85,189.56l.84.48-.15.28-.85-.49v1h-.31v-1l-.85.49-.16-.28.85-.48-.85-.47.16-.29.85.5v-1h.31v1l.85-.5.15.29Z" style="fill: rgb(38, 50, 56); transform-origin: 219.53px 189.565px;" id="eltij338eayy9" class="animable"></path>
                    <path d="M222.6,189.56l.84.48-.16.28-.85-.49v1h-.31v-1l-.84.49-.17-.28.85-.48-.85-.47.17-.29.84.5v-1h.31v1l.85-.5.16.29Z" style="fill: rgb(38, 50, 56); transform-origin: 222.275px 189.565px;" id="eldnclshkwg6u" class="animable"></path>
                    <path d="M225.34,189.56l.84.48-.16.28-.84-.49v1h-.31v-1l-.84.49-.16-.28.84-.48-.84-.47.16-.29.84.5v-1h.31v1l.84-.5.16.29Z" style="fill: rgb(38, 50, 56); transform-origin: 225.025px 189.565px;" id="elt8z49hgv3b" class="animable"></path>
                    <path d="M228.08,189.56l.84.48-.15.28-.85-.49v1h-.31v-1l-.85.49-.16-.28.84-.48-.84-.47.16-.29.85.5v-1h.31v1l.85-.5.15.29Z" style="fill: rgb(38, 50, 56); transform-origin: 227.76px 189.565px;" id="elkvhxbxqjral" class="animable"></path>
                    <path d="M230.83,189.56l.83.48-.15.28-.85-.49v1h-.31v-1l-.84.49-.17-.28.85-.48-.85-.47.17-.29.84.5v-1h.31v1l.85-.5.15.29Z" style="fill: rgb(38, 50, 56); transform-origin: 230.5px 189.565px;" id="elgzfldkb4pcq" class="animable"></path>
                    <path d="M202.94,176c0,1-.73,1.62-1.93,1.62h-1.27v1.49h-.51V174.4H201C202.21,174.4,202.94,175,202.94,176Zm-.5,0c0-.76-.5-1.19-1.45-1.19h-1.25v2.37H201C201.94,177.2,202.44,176.77,202.44,176Z" style="fill: rgb(38, 50, 56); transform-origin: 201.085px 176.755px;" id="elz1utiafpaa" class="animable"></path>
                    <path d="M206.57,176.93v2.2h-.46v-.55a1.35,1.35,0,0,1-1.23.58c-.8,0-1.29-.41-1.29-1s.35-1,1.37-1h1.13v-.21c0-.61-.35-.94-1-.94a1.79,1.79,0,0,0-1.18.42l-.22-.36a2.24,2.24,0,0,1,1.45-.48A1.26,1.26,0,0,1,206.57,176.93Zm-.48,1.13v-.58H205c-.69,0-.91.27-.91.64s.33.67.9.67A1.14,1.14,0,0,0,206.09,178.06Z" style="fill: rgb(38, 50, 56); transform-origin: 205.082px 177.372px;" id="elsklgsbloxca" class="animable"></path>
                    <path d="M207.38,178.73l.21-.38a2.2,2.2,0,0,0,1.27.4c.67,0,.95-.23.95-.59,0-.92-2.3-.19-2.3-1.6,0-.58.5-1,1.4-1a2.45,2.45,0,0,1,1.26.33l-.21.38a1.86,1.86,0,0,0-1.05-.3c-.64,0-.92.25-.92.59,0,1,2.3.24,2.3,1.6,0,.62-.54,1-1.47,1A2.37,2.37,0,0,1,207.38,178.73Z" style="fill: rgb(38, 50, 56); transform-origin: 208.835px 177.361px;" id="elsoxkgr84gr" class="animable"></path>
                    <path d="M210.68,178.73l.22-.38a2.15,2.15,0,0,0,1.26.4c.68,0,1-.23,1-.59,0-.92-2.3-.19-2.3-1.6,0-.58.5-1,1.41-1a2.39,2.39,0,0,1,1.25.33l-.21.38a1.85,1.85,0,0,0-1-.3c-.65,0-.93.25-.93.59,0,1,2.3.24,2.3,1.6,0,.62-.54,1-1.46,1A2.38,2.38,0,0,1,210.68,178.73Z" style="fill: rgb(38, 50, 56); transform-origin: 212.18px 177.363px;" id="elrc2w0frz7ms" class="animable"></path>
                    <path d="M219.63,175.58l-1.35,3.55h-.45l-1.12-2.91-1.12,2.91h-.46l-1.33-3.55h.46l1.11,3,1.14-3h.42l1.13,3,1.13-3Z" style="fill: rgb(38, 50, 56); transform-origin: 216.715px 177.355px;" id="elnrekwebk6ms" class="animable"></path>
                    <path d="M219.89,177.35a1.81,1.81,0,1,1,1.81,1.81A1.75,1.75,0,0,1,219.89,177.35Zm3.13,0a1.32,1.32,0,1,0-2.64,0,1.32,1.32,0,1,0,2.64,0Z" style="fill: rgb(38, 50, 56); transform-origin: 221.699px 177.351px;" id="eljrbijax63zn" class="animable"></path>
                    <path d="M226.28,175.55V176h-.12a1.15,1.15,0,0,0-1.19,1.31v1.81h-.48v-3.55H225v.69A1.33,1.33,0,0,1,226.28,175.55Z" style="fill: rgb(38, 50, 56); transform-origin: 225.385px 177.333px;" id="elncmyhhs8ee" class="animable"></path>
                    <path d="M230.35,174.12v5h-.46v-.7a1.53,1.53,0,0,1-1.37.73,1.81,1.81,0,0,1,0-3.61,1.54,1.54,0,0,1,1.35.7v-2.13Zm-.47,3.23a1.32,1.32,0,1,0-1.32,1.39A1.3,1.3,0,0,0,229.88,177.35Z" style="fill: rgb(38, 50, 56); transform-origin: 228.597px 176.631px;" id="elg7js26y0m6a" class="animable"></path>
                    <path d="M237.46,215v4.74H237v-4.3h-1.12V215Z" style="fill: rgb(38, 50, 56); transform-origin: 236.67px 217.37px;" id="el2uk4v4m48dg" class="animable"></path>
                    <path d="M241.82,219.28v.44H238.5v-.35l2-1.93c.53-.52.64-.84.64-1.16,0-.55-.39-.89-1.11-.89a1.59,1.59,0,0,0-1.29.53l-.35-.3a2.14,2.14,0,0,1,1.68-.68c1,0,1.57.49,1.57,1.28a2,2,0,0,1-.78,1.48l-1.61,1.58Z" style="fill: rgb(38, 50, 56); transform-origin: 240.105px 217.329px;" id="elc9rtzn40hwt" class="animable"></path>
                    <path d="M245.56,218.36c0,.79-.58,1.4-1.72,1.4a2.46,2.46,0,0,1-1.7-.61l.23-.39a2.09,2.09,0,0,0,1.47.55c.79,0,1.22-.36,1.22-.95s-.4-.94-1.28-.94h-.34v-.36l1.31-1.64h-2.42V215h3v.35L244,217C245.05,217.07,245.56,217.6,245.56,218.36Z" style="fill: rgb(38, 50, 56); transform-origin: 243.85px 217.381px;" id="el0hzljmmisy4" class="animable"></path>
                    <path d="M250.26,218.47h-.95v1.25h-.48v-1.25h-2.67v-.35l2.51-3.14h.54L246.79,218h2.05v-1.1h.47V218h.95Z" style="fill: rgb(38, 50, 56); transform-origin: 248.21px 217.35px;" id="el98h7lxr4gvd" class="animable"></path>
                    <path d="M255.69,218.32c0,.81-.56,1.44-1.72,1.44a2.47,2.47,0,0,1-1.7-.61l.24-.39a2,2,0,0,0,1.45.55c.81,0,1.23-.39,1.23-1s-.38-1-1.58-1h-1l.25-2.38h2.59v.44h-2.17l-.16,1.5h.61C255.15,216.92,255.69,217.49,255.69,218.32Z" style="fill: rgb(38, 50, 56); transform-origin: 253.98px 217.346px;" id="eld6ylot771g" class="animable"></path>
                    <path d="M259.86,218.3a1.48,1.48,0,0,1-1.62,1.46c-1.26,0-1.94-.88-1.94-2.37s.88-2.45,2.16-2.45a2.22,2.22,0,0,1,1.13.26l-.2.39a1.68,1.68,0,0,0-.92-.22c-1,0-1.68.65-1.68,1.94,0,.11,0,.24,0,.38a1.53,1.53,0,0,1,1.45-.81A1.44,1.44,0,0,1,259.86,218.3Zm-.48,0c0-.63-.46-1-1.19-1a1.09,1.09,0,0,0-1.22,1c0,.52.43,1,1.25,1A1,1,0,0,0,259.38,218.32Z" style="fill: rgb(38, 50, 56); transform-origin: 258.08px 217.353px;" id="elmieljfrkjmt" class="animable"></path>
                    <path d="M263.7,215v.35l-2,4.39h-.53l1.95-4.3h-2.43v.88h-.48V215Z" style="fill: rgb(38, 50, 56); transform-origin: 261.955px 217.37px;" id="elxxuofbya8lk" class="animable"></path>
                    <path d="M267.85,218.4c0,.84-.71,1.36-1.84,1.36s-1.82-.52-1.82-1.36a1.19,1.19,0,0,1,.91-1.17,1,1,0,0,1-.74-1c0-.77.65-1.25,1.65-1.25s1.67.48,1.67,1.25a1.06,1.06,0,0,1-.76,1A1.2,1.2,0,0,1,267.85,218.4Zm-.5,0c0-.58-.5-.95-1.34-.95s-1.33.37-1.33.95.49,1,1.33,1S267.35,219,267.35,218.39ZM266,217.06c.74,0,1.18-.33,1.18-.85s-.47-.85-1.18-.85-1.17.32-1.17.85S265.27,217.06,266,217.06Z" style="fill: rgb(38, 50, 56); transform-origin: 266.02px 217.37px;" id="elk93fafxb819" class="animable"></path>
                    <path d="M273.71,217.31c0,1.61-.88,2.45-2.17,2.45a2.21,2.21,0,0,1-1.12-.26l.2-.39a1.68,1.68,0,0,0,.92.22c1,0,1.67-.66,1.67-1.94a2.62,2.62,0,0,0,0-.38,1.5,1.5,0,0,1-1.44.81,1.44,1.44,0,0,1-1.6-1.42,1.48,1.48,0,0,1,1.62-1.46C273,214.94,273.71,215.82,273.71,217.31Zm-.67-.94c0-.52-.43-1-1.25-1a1,1,0,0,0-1.16,1c0,.63.46,1,1.19,1A1.1,1.1,0,0,0,273,216.37Z" style="fill: rgb(38, 50, 56); transform-origin: 271.94px 217.347px;" id="elyky5hqy3aj" class="animable"></path>
                    <path d="M275.71,215v4.74h-.49v-4.3H274.1V215Z" style="fill: rgb(38, 50, 56); transform-origin: 274.905px 217.37px;" id="eldem1ozmkg4s" class="animable"></path>
                    <path d="M276.83,217.35c0-1.51.8-2.41,1.89-2.41s1.89.9,1.89,2.41-.79,2.41-1.89,2.41S276.83,218.86,276.83,217.35Zm3.28,0c0-1.26-.56-2-1.39-2s-1.39.7-1.39,2,.57,2,1.39,2S280.11,218.61,280.11,217.35Z" style="fill: rgb(38, 50, 56); transform-origin: 278.72px 217.35px;" id="el06f5x4wwv8lb" class="animable"></path>
                    <path d="M282.61,215v4.74h-.49v-4.3H281V215Z" style="fill: rgb(38, 50, 56); transform-origin: 281.805px 217.37px;" id="elxdbgxwerazq" class="animable"></path>
                    <path d="M286.82,215v4.74h-.49v-4.3h-1.12V215Z" style="fill: rgb(38, 50, 56); transform-origin: 286.015px 217.37px;" id="elpthyjo6va5e" class="animable"></path>
                    <path d="M289.25,215v4.74h-.48v-4.3h-1.12V215Z" style="fill: rgb(38, 50, 56); transform-origin: 288.45px 217.37px;" id="elvyjkizbh6ue" class="animable"></path>
                    <path d="M293.61,219.28v.44h-3.32v-.35l2-1.93c.53-.52.64-.84.64-1.16,0-.55-.39-.89-1.11-.89a1.59,1.59,0,0,0-1.29.53l-.35-.3a2.16,2.16,0,0,1,1.68-.68c1,0,1.57.49,1.57,1.28a2,2,0,0,1-.78,1.48L291,219.28Z" style="fill: rgb(38, 50, 56); transform-origin: 291.895px 217.329px;" id="elh50rybgzon5" class="animable"></path>
                    <path d="M295.53,215v4.74H295v-4.3h-1.11V215Z" style="fill: rgb(38, 50, 56); transform-origin: 294.71px 217.37px;" id="elrqzs4s1f5vg" class="animable"></path>
                    <path d="M215.79,213.9v-.72a1.26,1.26,0,0,0-1.26-1.26H201.76a1.26,1.26,0,0,0-1.26,1.26v.72Z" style="fill: rgb(38, 50, 56); transform-origin: 208.145px 212.91px;" id="elreu4zios6q" class="animable"></path>
                    <path d="M200.5,214.89v5.3a1.26,1.26,0,0,0,1.26,1.26h12.77a1.26,1.26,0,0,0,1.26-1.26v-5.3Z" style="fill: rgb(38, 50, 56); transform-origin: 208.145px 218.17px;" id="elrcdl8w7koaj" class="animable"></path>
                    <path d="M200.69,242.8c0-1.51.79-2.4,1.89-2.4s1.89.89,1.89,2.4-.8,2.41-1.89,2.41S200.69,244.31,200.69,242.8Zm3.28,0c0-1.26-.57-2-1.39-2s-1.39.7-1.39,2,.56,2,1.39,2S204,244.07,204,242.8Z" style="fill: rgb(38, 50, 56); transform-origin: 202.58px 242.805px;" id="eluq9ffaktuhd" class="animable"></path>
                    <path d="M208.8,243.85c0,.84-.71,1.36-1.84,1.36s-1.82-.52-1.82-1.36a1.17,1.17,0,0,1,.91-1.16,1.07,1.07,0,0,1-.74-1c0-.77.65-1.25,1.65-1.25s1.67.48,1.67,1.25a1.08,1.08,0,0,1-.75,1A1.18,1.18,0,0,1,208.8,243.85Zm-.5,0c0-.58-.5-.94-1.34-.94s-1.33.36-1.33.94.5,1,1.33,1S208.3,244.43,208.3,243.84ZM207,242.51c.74,0,1.18-.33,1.18-.84s-.47-.86-1.18-.86-1.17.32-1.17.85S206.23,242.51,207,242.51Z" style="fill: rgb(38, 50, 56); transform-origin: 206.97px 242.825px;" id="elhlg3lv4r4xn" class="animable"></path>
                    <path d="M211.19,239.48h.44l-2.24,6.36H209Z" style="fill: rgb(38, 50, 56); transform-origin: 210.315px 242.66px;" id="el8buj0o8y1dy" class="animable"></path>
                    <path d="M215,244.73v.44h-3.32v-.35l2-1.92c.53-.53.63-.85.63-1.17,0-.55-.38-.89-1.1-.89a1.61,1.61,0,0,0-1.3.53l-.34-.3a2.12,2.12,0,0,1,1.68-.67c1,0,1.56.48,1.56,1.27a1.94,1.94,0,0,1-.77,1.48l-1.61,1.58Z" style="fill: rgb(38, 50, 56); transform-origin: 213.285px 242.783px;" id="elgocektxlmga" class="animable"></path>
                    <path d="M218.79,243.77c0,.81-.56,1.44-1.72,1.44a2.47,2.47,0,0,1-1.7-.61l.24-.39a2,2,0,0,0,1.45.55c.81,0,1.23-.39,1.23-1s-.37-1-1.58-1h-1l.24-2.37h2.59v.43h-2.17l-.16,1.51h.61C218.25,242.38,218.79,242.94,218.79,243.77Z" style="fill: rgb(38, 50, 56); transform-origin: 217.08px 242.801px;" id="elu1mw6d1h5l9" class="animable"></path>
                    <path d="M202.9,237.39v.27h-2.08v-2.94h2V235h-1.7v1h1.52v.27h-1.52v1.09Z" style="fill: rgb(38, 50, 56); transform-origin: 201.86px 236.19px;" id="eld08lfqrnyav" class="animable"></path>
                    <path d="M205.43,237.66l-.94-1.3-1,1.3h-.36l1.12-1.51-1-1.43h.35l.89,1.2.88-1.2h.33l-1,1.42,1.12,1.52Z" style="fill: rgb(38, 50, 56); transform-origin: 204.475px 236.19px;" id="elptbyvow0xka" class="animable"></path>
                    <path d="M208.55,235.73c0,.62-.46,1-1.2,1h-.79v.93h-.31v-2.94h1.1C208.09,234.72,208.55,235.1,208.55,235.73Zm-.31,0c0-.47-.31-.74-.9-.74h-.78v1.47h.78C207.93,236.46,208.24,236.19,208.24,235.73Z" style="fill: rgb(38, 50, 56); transform-origin: 207.4px 236.19px;" id="el533n089et3s" class="animable"></path>
                    <path d="M210.37,234.72h1.19a1.47,1.47,0,1,1,0,2.94h-1.19Zm1.17,2.67a1.2,1.2,0,1,0,0-2.4h-.86v2.4Z" style="fill: rgb(38, 50, 56); transform-origin: 211.7px 236.19px;" id="el0z6o5bsbl96" class="animable"></path>
                    <path d="M215.44,236.29v1.37h-.29v-.35a.82.82,0,0,1-.76.37c-.49,0-.8-.26-.8-.64s.22-.63.85-.63h.7v-.13a.54.54,0,0,0-.62-.59,1.13,1.13,0,0,0-.74.26l-.13-.22a1.39,1.39,0,0,1,.9-.3A.78.78,0,0,1,215.44,236.29Zm-.3.7v-.36h-.69c-.43,0-.57.17-.57.4s.21.41.56.41A.7.7,0,0,0,215.14,237Z" style="fill: rgb(38, 50, 56); transform-origin: 214.518px 236.552px;" id="ela3810gbubro" class="animable"></path>
                    <path d="M217.41,237.52a.66.66,0,0,1-.46.16.59.59,0,0,1-.65-.64V235.7h-.39v-.25h.39V235h.3v.48h.67v.25h-.67V237a.35.35,0,0,0,.38.4.52.52,0,0,0,.33-.11Z" style="fill: rgb(38, 50, 56); transform-origin: 216.66px 236.342px;" id="eltbojfm5qb8" class="animable"></path>
                    <path d="M219.8,236.65H218a.82.82,0,0,0,.87.76.86.86,0,0,0,.68-.29l.17.2a1.12,1.12,0,0,1-.86.36,1.09,1.09,0,0,1-1.15-1.13,1.06,1.06,0,0,1,1.07-1.12,1,1,0,0,1,1.07,1.12Zm-1.85-.23h1.57a.79.79,0,0,0-1.57,0Z" style="fill: rgb(38, 50, 56); transform-origin: 218.783px 236.554px;" id="elkld42s79th" class="animable"></path>
                    <path d="M256.54,240.44v4.73h-.48v-4.3h-1.12v-.43Z" style="fill: rgb(38, 50, 56); transform-origin: 255.74px 242.805px;" id="elhnow22sdfpk" class="animable"></path>
                    <path d="M260.9,244.73v.44h-3.32v-.35l2-1.92c.53-.53.64-.85.64-1.17,0-.55-.39-.89-1.11-.89a1.59,1.59,0,0,0-1.29.53l-.35-.3a2.14,2.14,0,0,1,1.68-.67c1,0,1.57.48,1.57,1.27a2,2,0,0,1-.78,1.48l-1.61,1.58Z" style="fill: rgb(38, 50, 56); transform-origin: 259.185px 242.783px;" id="el6k6a69hsqks" class="animable"></path>
                    <path d="M264.64,243.81c0,.79-.58,1.4-1.72,1.4a2.46,2.46,0,0,1-1.7-.61l.24-.39a2,2,0,0,0,1.46.55c.79,0,1.22-.36,1.22-.95s-.4-.94-1.28-.94h-.34v-.36l1.31-1.64h-2.42v-.43h3v.34l-1.34,1.68C264.13,242.52,264.64,243.05,264.64,243.81Z" style="fill: rgb(38, 50, 56); transform-origin: 262.93px 242.826px;" id="elhucckenaqlu" class="animable"></path>
                    <path d="M255.1,236.19a1.48,1.48,0,0,1,1.54-1.5,1.43,1.43,0,0,1,1.07.43l-.19.2a1.19,1.19,0,0,0-.87-.35,1.22,1.22,0,1,0,0,2.43,1.15,1.15,0,0,0,.87-.35l.19.2a1.4,1.4,0,0,1-1.08.43A1.47,1.47,0,0,1,255.1,236.19Z" style="fill: rgb(38, 50, 56); transform-origin: 256.405px 236.185px;" id="el2pu2nsvhccb" class="animable"></path>
                    <path d="M262.38,234.72l-1,2.94h-.33l-.86-2.51-.86,2.51H259l-1-2.94h.32l.85,2.53.88-2.53h.29l.86,2.54.86-2.54Z" style="fill: rgb(38, 50, 56); transform-origin: 260.19px 236.19px;" id="eljsbazh52trp" class="animable"></path>
                    <path d="M230.31,270.58h-2.64l-.57,1.27h-.52l2.16-4.73h.5l2.16,4.73h-.53Zm-.19-.4L229,267.64l-1.14,2.54Z" style="fill: rgb(38, 50, 56); transform-origin: 228.99px 269.485px;" id="eljfjf5j8b2tm" class="animable"></path>
                    <path d="M231.69,270.07a1.75,1.75,0,0,1,1.83-1.81,1.54,1.54,0,0,1,1.39.71l-.35.24a1.23,1.23,0,0,0-1-.53,1.39,1.39,0,0,0,0,2.78,1.24,1.24,0,0,0,1-.52l.35.24a1.56,1.56,0,0,1-1.39.7A1.75,1.75,0,0,1,231.69,270.07Z" style="fill: rgb(38, 50, 56); transform-origin: 233.299px 270.07px;" id="eluuyuwnth9eg" class="animable"></path>
                    <path d="M237.76,271.63a1.11,1.11,0,0,1-.75.25.94.94,0,0,1-1-1V268.7h-.63v-.41H236v-.78h.48v.78h1.09v.41h-1.09v2.12a.57.57,0,0,0,.62.65.83.83,0,0,0,.53-.18Z" style="fill: rgb(38, 50, 56); transform-origin: 236.57px 269.696px;" id="ela54vy6755yb" class="animable"></path>
                    <path d="M238.53,267.17a.35.35,0,0,1,.7,0,.35.35,0,0,1-.7,0Zm.11,1.12h.48v3.56h-.48Z" style="fill: rgb(38, 50, 56); transform-origin: 238.88px 269.335px;" id="elql25zxrknm" class="animable"></path>
                    <path d="M240.09,270.07a1.81,1.81,0,1,1,1.82,1.81A1.76,1.76,0,0,1,240.09,270.07Zm3.13,0a1.32,1.32,0,1,0-1.31,1.39A1.3,1.3,0,0,0,243.22,270.07Z" style="fill: rgb(38, 50, 56); transform-origin: 241.9px 270.071px;" id="elqhbiyagdgj8" class="animable"></path>
                    <path d="M248,269.79v2.06h-.48v-2a1,1,0,0,0-1.07-1.13,1.16,1.16,0,0,0-1.24,1.28v1.87h-.48v-3.56h.46V269a1.51,1.51,0,0,1,1.35-.69A1.37,1.37,0,0,1,248,269.79Z" style="fill: rgb(38, 50, 56); transform-origin: 246.367px 270.088px;" id="elb1ltnkeeh3s" class="animable"></path>
                    <path d="M251.64,267.55v1.86h2.44v.43h-2.44v2h-.5v-4.73h3.24v.43Z" style="fill: rgb(38, 50, 56); transform-origin: 252.76px 269.475px;" id="el9xl9uawuhxa" class="animable"></path>
                    <path d="M255.14,267.17a.35.35,0,0,1,.7,0,.35.35,0,0,1-.7,0Zm.1,1.12h.48v3.56h-.48Z" style="fill: rgb(38, 50, 56); transform-origin: 255.49px 269.335px;" id="el7ffy6d1eype" class="animable"></path>
                    <path d="M260.35,268.29v3.13a1.55,1.55,0,0,1-1.77,1.77,2.49,2.49,0,0,1-1.66-.54l.24-.37a2.14,2.14,0,0,0,1.4.49c.9,0,1.31-.42,1.31-1.29V271a1.57,1.57,0,0,1-1.38.69,1.73,1.73,0,1,1,0-3.45,1.61,1.61,0,0,1,1.4.71v-.68Zm-.46,1.69a1.35,1.35,0,1,0-1.35,1.31A1.27,1.27,0,0,0,259.89,270Z" style="fill: rgb(38, 50, 56); transform-origin: 258.499px 270.722px;" id="elrgfkk3okwso" class="animable"></path>
                    <path d="M264.9,268.29v3.56h-.46v-.65a1.42,1.42,0,0,1-1.28.68,1.38,1.38,0,0,1-1.5-1.53v-2.06h.48v2a1,1,0,0,0,1.07,1.14,1.16,1.16,0,0,0,1.21-1.29v-1.87Z" style="fill: rgb(38, 50, 56); transform-origin: 263.276px 270.078px;" id="elgi4djo7vyx" class="animable"></path>
                    <path d="M268,268.26v.47h-.12a1.16,1.16,0,0,0-1.2,1.32v1.81h-.48v-3.56h.46v.7A1.37,1.37,0,0,1,268,268.26Z" style="fill: rgb(38, 50, 56); transform-origin: 267.1px 270.057px;" id="elw6flza0aqmr" class="animable"></path>
                    <path d="M272,270.22h-3a1.32,1.32,0,0,0,1.4,1.24,1.41,1.41,0,0,0,1.09-.47l.27.31a1.88,1.88,0,0,1-3.24-1.23,1.72,1.72,0,0,1,1.74-1.81,1.69,1.69,0,0,1,1.72,1.81S272,270.16,272,270.22Zm-3-.36h2.52a1.26,1.26,0,0,0-2.52,0Z" style="fill: rgb(38, 50, 56); transform-origin: 270.259px 270.071px;" id="elsnf1jjqbold" class="animable"></path>
                    <path d="M232.43,290.82v2.08h-1.85v-2a8.79,8.79,0,0,1-4.63-1.49l1-2.26a7.5,7.5,0,0,0,3.61,1.32v-3.1c-2.07-.51-4.37-1.19-4.37-3.85,0-2,1.43-3.66,4.37-4v-2.06h1.85v2a8.39,8.39,0,0,1,3.77,1.14l-.93,2.28a7.68,7.68,0,0,0-2.84-1v3.16c2.06.48,4.33,1.17,4.33,3.79C236.76,288.8,235.35,290.47,232.43,290.82Zm-1.85-8.2V280c-1,.22-1.38.76-1.38,1.37S229.76,282.36,230.58,282.62Zm3.19,4.47c0-.65-.54-1-1.34-1.28v2.56C233.36,288.17,233.77,287.69,233.77,287.09Z" style="fill: rgb(38, 50, 56); transform-origin: 231.345px 284.18px;" id="elf34418hpcjd" class="animable"></path>
                    <path d="M247.71,288.22v2.46h-9.82v-1.95l5-4.74c1.16-1.1,1.36-1.76,1.36-2.4,0-1-.71-1.61-2.08-1.61a3.18,3.18,0,0,0-2.69,1.3l-2.19-1.41a6,6,0,0,1,5.16-2.42c2.9,0,4.83,1.49,4.83,3.85,0,1.26-.36,2.41-2.18,4.1l-3,2.82Z" style="fill: rgb(38, 50, 56); transform-origin: 242.5px 284.06px;" id="el3t1po8eeh6o" class="animable"></path>
                    <path d="M248.92,284.18c0-4.29,2.38-6.73,5.57-6.73s5.57,2.44,5.57,6.73-2.36,6.72-5.57,6.72S248.92,288.47,248.92,284.18Zm8.12,0c0-3-1-4.18-2.55-4.18s-2.52,1.22-2.52,4.18,1,4.17,2.52,4.17S257,287.13,257,284.18Z" style="fill: rgb(38, 50, 56); transform-origin: 254.49px 284.175px;" id="el4ouzowurhqh" class="animable"></path>
                    <path d="M261.53,284.18c0-4.29,2.38-6.73,5.57-6.73s5.57,2.44,5.57,6.73-2.36,6.72-5.57,6.72S261.53,288.47,261.53,284.18Zm8.11,0c0-3-1-4.18-2.54-4.18s-2.53,1.22-2.53,4.18,1,4.17,2.53,4.17S269.64,287.13,269.64,284.18Z" style="fill: rgb(38, 50, 56); transform-origin: 267.1px 284.175px;" id="elgj4vseiyot7" class="animable"></path>
                    <path d="M270.15,323.78H228.69a9.71,9.71,0,0,1-9.7-9.7h0a9.71,9.71,0,0,1,9.7-9.71h41.46a9.7,9.7,0,0,1,9.7,9.71h0A9.7,9.7,0,0,1,270.15,323.78Z" style="fill: rgb(38, 50, 56); transform-origin: 249.42px 314.075px;" id="elgor3ritukra" class="animable"></path>
                    <path d="M236.12,313c0,1.22-.88,1.95-2.33,1.95h-1.54v1.8h-.6v-5.7h2.14C235.24,311.09,236.12,311.82,236.12,313Zm-.61,0c0-.91-.6-1.43-1.74-1.43h-1.52v2.85h1.52C234.91,314.46,235.51,313.94,235.51,313Z" style="fill: rgb(255, 255, 255); transform-origin: 233.885px 313.9px;" id="el0h8al3x2hneh" class="animable"></path>
                    <path d="M240.49,314.13v2.66h-.55v-.67a1.62,1.62,0,0,1-1.48.71c-1,0-1.56-.5-1.56-1.25s.42-1.21,1.65-1.21h1.36v-.26c0-.73-.41-1.13-1.21-1.13a2.2,2.2,0,0,0-1.43.5l-.26-.43a2.71,2.71,0,0,1,1.75-.58A1.52,1.52,0,0,1,240.49,314.13Zm-.58,1.37v-.7h-1.34c-.83,0-1.1.33-1.1.77s.4.81,1.08.81A1.36,1.36,0,0,0,239.91,315.5Z" style="fill: rgb(255, 255, 255); transform-origin: 238.699px 314.645px;" id="el5li07aivl1" class="animable"></path>
                    <path d="M245.58,312.5l-2.14,4.8c-.36.84-.81,1.11-1.42,1.11A1.48,1.48,0,0,1,241,318l.27-.43a1.07,1.07,0,0,0,.78.33c.39,0,.65-.18.89-.72l.19-.42-1.92-4.28h.61l1.61,3.65L245,312.5Z" style="fill: rgb(255, 255, 255); transform-origin: 243.29px 315.445px;" id="elyta8iy2m0dc" class="animable"></path>
                    <path d="M250.47,316.83v.94h-.41v-.93a3,3,0,0,1-1.95-.74l.24-.47a2.73,2.73,0,0,0,1.71.69v-2.14c-.9-.22-1.81-.5-1.81-1.58,0-.78.56-1.46,1.81-1.55v-.94h.41V311a3.15,3.15,0,0,1,1.65.5l-.2.48a3.14,3.14,0,0,0-1.45-.47v2.16c.92.22,1.89.48,1.89,1.57C252.36,316.07,251.76,316.76,250.47,316.83Zm-.41-3.22v-2c-.84.07-1.22.5-1.22,1S249.39,313.43,250.06,313.61Zm1.7,1.71c0-.65-.59-.87-1.29-1v2C251.36,316.25,251.76,315.84,251.76,315.32Z" style="fill: rgb(255, 255, 255); transform-origin: 250.235px 313.94px;" id="elg3tqviydxm" class="animable"></path>
                    <path d="M257.06,316.27v.52h-4v-.41l2.37-2.33c.64-.63.76-1,.76-1.41,0-.66-.46-1.06-1.33-1.06a2,2,0,0,0-1.56.63l-.42-.36a2.59,2.59,0,0,1,2-.81c1.15,0,1.89.59,1.89,1.54a2.34,2.34,0,0,1-.94,1.78l-1.94,1.91Z" style="fill: rgb(255, 255, 255); transform-origin: 254.97px 313.914px;" id="el6v5jm3vxmgv" class="animable"></path>
                    <path d="M257.76,313.94c0-1.82.95-2.9,2.27-2.9s2.28,1.08,2.28,2.9-1,2.9-2.28,2.9S257.76,315.76,257.76,313.94Zm3.95,0c0-1.52-.68-2.36-1.68-2.36s-1.68.84-1.68,2.36.69,2.36,1.68,2.36S261.71,315.46,261.71,313.94Z" style="fill: rgb(255, 255, 255); transform-origin: 260.035px 313.94px;" id="elhxbsxwnbolc" class="animable"></path>
                    <path d="M263.15,313.94c0-1.82,1-2.9,2.28-2.9s2.28,1.08,2.28,2.9-1,2.9-2.28,2.9S263.15,315.76,263.15,313.94Zm4,0c0-1.52-.68-2.36-1.67-2.36s-1.68.84-1.68,2.36.68,2.36,1.68,2.36S267.1,315.46,267.1,313.94Z" style="fill: rgb(255, 255, 255); transform-origin: 265.43px 313.94px;" id="elhqdj65muf7f" class="animable"></path>
                    <path d="M186.69,293.24c1.5,23.05-9.18,48.72-9.18,48.72l-57.69-16.24-10.06-2.84c10-13.89,14.76-41.25,12.15-63.39-.2-1.67-.32-3.24-.41-4.74-1-18.26,6.59-23.6,13-31.67,7-8.74,22.58-41.74,22.58-49.55s19.55-26.93,15.2-48.63c0,0,5.91-3.67,11.85-.66,2.7,1.36,5.39,4.09,7.56,9.15,5.09,11.89-2.06,45.5-8.14,53.74l-11.71,48.48s3.95,6.81,7.83,19.17A164.89,164.89,0,0,1,186.69,293.24Z" style="fill: rgb(255, 255, 255); transform-origin: 151.592px 232.507px;" id="el7gha336su45" class="animable"></path>
                    <g id="elytpwcep7uym">
                      <path d="M121.5,254.75c3.27,8.95,6.74,15,6.44,27.54s-7,31.84-5.09,41.87a8.83,8.83,0,0,0-3,1.56l-10.06-2.84c10-13.89,14.76-41.25,12.15-63.39C121.71,257.82,121.59,256.25,121.5,254.75Z" style="fill: rgb(146, 227, 169); opacity: 0.4; transform-origin: 118.874px 290.235px;" class="animable"></path>
                    </g>
                    <g id="elwmxtvjgzn2d">
                      <path d="M183.55,187.13l-11.71,48.48s3.95,6.81,7.83,19.17l0,0C171.74,242,165,238.18,165,238.18c5.28-6.33,9.35-52.63,9.35-52.63s16.55-19.45,15-39.8a65,65,0,0,0-5.22-21.51c2.7,1.36,5.39,4.09,7.56,9.15C196.78,145.28,189.63,178.89,183.55,187.13Z" style="fill: rgb(146, 227, 169); opacity: 0.4; transform-origin: 179.212px 189.51px;" class="animable"></path>
                    </g>
                    <path d="M109.76,322.88c10-13.89,14.76-41.25,12.16-63.39s5.64-27.68,12.59-36.42,22.57-41.73,22.57-49.55,19.54-26.91,15.2-48.62c0,0,12.47-7.73,19.41,8.49,5.09,11.9-2.06,45.5-8.14,53.75l-11.7,48.47s12.49,21.55,14.84,57.63c1.5,23.05-9.18,48.73-9.18,48.73" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 151.592px 232.515px;" id="el6iz843b6vny" class="animable"></path>
                    <path d="M148.32,360.41s13.13,8.08,20.79,20.86" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 158.715px 370.84px;" id="eljoigppbxymq" class="animable"></path>
                    <path d="M185.2,375.7s-11.31,1.61-21.53-10.62c-6.55-7.83-26.44-11.37-31-31.85" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 158.935px 354.5px;" id="elptqbtubfddg" class="animable"></path>
                  </g>
                  <g id="freepik--Hand--inject-32" class="animable" style="transform-origin: 361.25px 314.799px;">
                    <g id="elt1x31eszjo">
                      <path d="M349.58,436.26c-28.95-13.85-11.42-24.95-51.25-42.14s-40.23-31.88-35.43-65.66,1-69.71,12.58-72.76-9.06-49.64,4.44-66.11l66.44,129.24Z" style="fill-opacity: 0.7; opacity: 0.2; transform-origin: 305.195px 312.925px;" class="animable"></path>
                    </g>
                    <path d="M457.45,434.91c-1.36-3.89-2.74-8.14-3.81-12-1.38-5-2.46-9-5-13.65-.4-.74-.83-1.52-1.31-2.36-2.71-4.83-6.42-11.44-8.59-19.56-1.46-5.47,0-18.94,1.36-32,1-9.38,1.91-18.23,1.64-23.25l-.08-1.78c-.68-13.13-2.1-40.45-5-48-2.85-7.28-5.51-8.37-10.36-10.31-.5-.22-1-.42-1.58-.66-1.82-.76-4.92-2.36-8.49-4.21-8.05-4.17-18.07-9.36-23.16-10a45.36,45.36,0,0,0-14.27.66c-6.27-4.81-16.94-11-20.12-11.13-2.6-.13-8.28,1.5-11,2.35l-1.37.44a41.17,41.17,0,0,0-5.46-3.58c-.13-.07-.26-.12-.37-.17-3.53-1.45-8.86-1.5-11.77-1.42-.88,0-1.57.09-1.88.12-5.49-8.45-10.89-16.86-13.24-20.55-4.9-7.76-20.07-39.69-20.22-40a.27.27,0,0,0-.2-.16.51.51,0,0,0-.14,0c-1.18-.34-9.87-2.55-13.36,5.87-3.82,9.17,1.63,29.3,6.95,36.61,2.56,3.52,9.55,17.88,16.64,32.74-1.82,1.69-16.38,15.14-23.45,17.65-5.79,2.05-4.43,31.73-3.34,55.56.41,8.8.75,16.37.65,20.92-.2,8.8,15.81,20.92,31.33,32.64,14,10.62,28.52,21.59,30.22,29.56.47,2.24,1.81,4.06,3.41,6.11,2.67,3.4,6.11,7.41,7.57,15,.43,2.28,4.09,5.26,7,10.13H461.69C460.81,444.11,459.15,439.79,457.45,434.91ZM311.78,276.81c5.09,10.66,8.24,17,9,17.71s.71,4.09.69,7.38a59.35,59.35,0,0,0,.3,8.63c-1.24,1.09-9.07,8.22-10.65,14l-6.65-7.83-1.68-24.57C303.79,291.07,310.63,283.84,311.78,276.81Z" style="fill: rgb(255, 255, 255); transform-origin: 368.597px 314.829px;" id="el3nssqea2n8n" class="animable"></path>
                    <g id="el6j9ea7q97dl">
                      <path d="M427.31,345.31c2.47,23.05,2.5,35.55,5.27,42.22,4.68,11.26,11.57,13.54,11.57,13.54a69.45,69.45,0,0,1-5.43-13.71c-1.45-5.47,0-18.94,1.36-32,1-9.38,1.92-18.24,1.66-23.26l-.09-1.78c-.68-13.12-2.1-40.44-5-48-2.84-7.28-5.51-8.37-10.36-10.32-.5-.21-1-.41-1.58-.65-1.82-.76-4.91-2.36-8.49-4.21-8.05-4.17-18.07-9.36-23.16-10a45,45,0,0,0-14.27.66c-6.27-4.81-16.94-11-20.12-11.13-2.6-.13-9.65,1.92-12.39,2.79,12,9.79,29.28,22,29.28,22,13.67,2.72,11.92,8.65,17.45,12,5,3,9.78-5.78,14-6.55,5.52-1,11.79,4.82,11.51,11.88C417.66,308.24,425.19,325.52,427.31,345.31Z" style="fill: rgb(146, 227, 169); opacity: 0.4; transform-origin: 395.215px 323.867px;" class="animable"></path>
                    </g>
                    <g id="elbdolbnz15c">
                      <path d="M358.21,273.88s-10.83-9-16-12-6-13.34-1.92-15.85a1.14,1.14,0,0,1,.51-.16l-.37-.17c-3.53-1.45-8.86-1.51-11.77-1.42,6,11.17,10.79,19.74,12.08,21.62C345.09,272.18,358.21,273.88,358.21,273.88Z" style="fill: rgb(146, 227, 169); opacity: 0.4; transform-origin: 343.435px 259.067px;" class="animable"></path>
                    </g>
                    <g id="elpi2zjp5mjj8">
                      <path d="M341.78,413.84c.46-2.39-3.48-9.39-5.21-12.23-3.93-6.48-49.56-46.8-42.95-57.56s12.65-2.8,16.83-2.1c3,.5,11.24-4.41,11.2-4.82-.94-8.29,6.71-13.68,6.71-13.68s-.7-33-2.46-32.44-22.82-49.55-22.82-49.55-22.26-29.6-21-43.41S293,184,293,184l0-.37c-1.18-.34-9.87-2.55-13.36,5.87-3.82,9.17,1.63,29.3,6.95,36.61,2.56,3.52,9.55,17.88,16.64,32.74-1.82,1.69-16.38,15.14-23.45,17.65-5.79,2.05-4.43,31.73-3.34,55.56.41,8.8.75,16.37.65,20.92-.2,8.8,15.81,20.92,31.33,32.64,14,10.62,28.52,21.59,30.22,29.56.47,2.24,1.81,4.06,3.41,6.11A40.55,40.55,0,0,1,341.78,413.84Zm-30-137c5.09,10.66,8.24,17,9,17.71s.71,4.09.69,7.38a59.35,59.35,0,0,0,.3,8.63c-1.24,1.09-9.07,8.22-10.65,14l-6.65-7.83-1.68-24.57C303.79,291.07,310.63,283.84,311.78,276.81Z" style="fill: rgb(146, 227, 169); opacity: 0.4; transform-origin: 308.762px 302.204px;" class="animable"></path>
                    </g>
                    <path d="M352,273.75a22.74,22.74,0,0,1-11.24-7.88c-4.57-6.12-22.52-34-27.49-41.86S293,184,293,184s-9.4-3.15-13.06,5.63,1.35,28.62,6.92,36.26S318.9,292.36,321,294.26s-1.05,17.6,3.16,21.4" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 315.345px 249.555px;" id="elh8jvlpjae8u" class="animable"></path>
                    <path d="M322.14,310.67s-9.7,8.45-10.84,14.62l-7.2-8.47L302.4,292s8.69-8.7,9.17-16.44" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 312.27px 300.425px;" id="elpbcn8t6ctj9" class="animable"></path>
                    <path d="M303.63,258.88s-16.11,15.2-23.77,17.91-2.07,59.66-2.45,76.15,57.95,45.22,61.53,62.1c1.42,6.72,7.3,8.09,10.89,21" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 312.813px 347.46px;" id="eldwdoyde6ti" class="animable"></path>
                    <path d="M326.56,244.73s8.77-.79,13.71,1.27,25,19.29,29.84,22.58,12.12,4.54,13.5,8.09" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 355.085px 260.626px;" id="elpkltr4uai6p" class="animable"></path>
                    <path d="M346.17,249.81s9-3,12.44-2.83,16.38,7.8,22.18,12.9a49.44,49.44,0,0,0,15.11,9.18" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 371.035px 258.017px;" id="eloboq42mcuia" class="animable"></path>
                    <path d="M378.68,258.15a43.89,43.89,0,0,1,14.3-.69c7.32,1,25.7,11.8,31.56,14.24s8.66,2.89,11.74,10.77,4.49,37.84,5.11,49.68-5.55,45.75-3,55.29,7.15,16.9,9.94,22" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 413.505px 333.303px;" id="el6k5f8bzmj3v" class="animable"></path>
                    <path d="M302.4,292S301,286.15,299,285.4" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 300.7px 288.7px;" id="eln69uieveup7" class="animable"></path>
                    <path d="M304.1,316.82a74.52,74.52,0,0,0,2.73,10.05c1.44,3.55-.8,8.69-.8,8.69" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 305.706px 326.19px;" id="elg0deanukk57" class="animable"></path>
                    <path d="M311.3,325.29c-.23,5.68,5.42,12.32,6.79,11.38" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 314.692px 331.025px;" id="elwv54vp9cg5o" class="animable"></path>
                    <path d="M340.07,292s6.07-9.82,11.89-10.3,12.54,7.78,16.13,7,8.11-6.06,14.15-7,8.87,1.94,13.66,2.18,5.28-6.16,11-7,7.67,2,11.51,2.74,4.8-2.29,7.91-4" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 383.195px 283.81px;" id="elg81fow38t5h" class="animable"></path>
                  </g>
                  <defs>
                    <filter id="active" height="200%">
                      <feMorphology in="SourceAlpha" result="DILATED" operator="dilate" radius="2"></feMorphology>
                      <feFlood flood-color="#32DFEC" flood-opacity="1" result="PINK"></feFlood>
                      <feComposite in="PINK" in2="DILATED" operator="in" result="OUTLINE"></feComposite>
                      <feMerge>
                        <feMergeNode in="OUTLINE"></feMergeNode>
                        <feMergeNode in="SourceGraphic"></feMergeNode>
                      </feMerge>
                    </filter>
                    <filter id="hover" height="200%">
                      <feMorphology in="SourceAlpha" result="DILATED" operator="dilate" radius="2"></feMorphology>
                      <feFlood flood-color="#ff0000" flood-opacity="0.5" result="PINK"></feFlood>
                      <feComposite in="PINK" in2="DILATED" operator="in" result="OUTLINE"></feComposite>
                      <feMerge>
                        <feMergeNode in="OUTLINE"></feMergeNode>
                        <feMergeNode in="SourceGraphic"></feMergeNode>
                      </feMerge>
                      <feColorMatrix type="matrix" values="0   0   0   0   0                0   1   0   0   0                0   0   0   0   0                0   0   0   1   0 "></feColorMatrix>
                    </filter>
                  </defs>
                </svg>
              </p>
              <h2 class="display-5 text-center">🤩Featurs</h2>
              <p class="lead">
              <ul style="font-size: 15px;list-style: decimal-leading-zero;padding-left: 40px;">
                <li> Easy configuration in WooCommerce - only API secret key and Publishable key need to be copied from Thawani Merchant Portal</li>
                <li> Easy customization - payment method <b>title</b>, <b>description</b>, <b>environment: UAT/Production</b>, <b>cancel url</b>, <b>success url</b>, <b>client reference id Prefix</b> and more can be changed easily</li>
                <li> Compatible with UAT and production API.</li>
                <li> Developer logs feature: receive new emails when something happens with the payment API</li>
                <li> Compatible with WooCommerce shop coupons.</li>
              </ul>
              </p>
            </div>
            <!-- <div class="bg-light box-shadow mx-auto" style="width: 80%; height: 300px; border-radius: 21px 21px 0 0;"></div> -->
          </div>
          <div class="col-md-6 bg-light pt-2 px-2 pt-md-3 px-md-3 text-center overflow-hidden">
            <div class="my-2 p-2">
              <p class="text-center">
                <svg width="40%" class="animated" id="freepik_stories-icon-design" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.com/svgjs">
                  <style>
                    svg#freepik_stories-icon-design:not(.animated) .animable {
                      opacity: 0;
                    }

                    svg#freepik_stories-icon-design.animated #freepik--background-simple--inject-187 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideUp;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-icon-design.animated #freepik--Floor--inject-187 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) lightSpeedLeft;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-icon-design.animated #freepik--Graphics--inject-187 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideRight;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-icon-design.animated #freepik--Plant--inject-187 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideRight;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-icon-design.animated #freepik--Device--inject-187 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) zoomOut;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-icon-design.animated #freepik--Desk--inject-187 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) zoomIn;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-icon-design.animated #freepik--Character--inject-187 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) zoomIn;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-icon-design.animated #freepik--icon-3--inject-187 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) slideLeft;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-icon-design.animated #freepik--icon-2--inject-187 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) fadeIn;
                      animation-delay: 0s;
                    }

                    svg#freepik_stories-icon-design.animated #freepik--icon-1--inject-187 {
                      animation: 1s 1 forwards cubic-bezier(.36, -0.01, .5, 1.38) zoomOut;
                      animation-delay: 0s;
                    }

                    @keyframes slideUp {
                      0% {
                        opacity: 0;
                        transform: translateY(30px);
                      }

                      100% {
                        opacity: 1;
                        transform: inherit;
                      }
                    }

                    @keyframes lightSpeedLeft {
                      from {
                        transform: translate3d(-50%, 0, 0) skewX(20deg);
                        opacity: 0;
                      }

                      60% {
                        transform: skewX(-10deg);
                        opacity: 1;
                      }

                      80% {
                        transform: skewX(2deg);
                      }

                      to {
                        opacity: 1;
                        transform: translate3d(0, 0, 0);
                      }
                    }

                    @keyframes slideRight {
                      0% {
                        opacity: 0;
                        transform: translateX(30px);
                      }

                      100% {
                        opacity: 1;
                        transform: translateX(0);
                      }
                    }

                    @keyframes zoomOut {
                      0% {
                        opacity: 0;
                        transform: scale(1.5);
                      }

                      100% {
                        opacity: 1;
                        transform: scale(1);
                      }
                    }

                    @keyframes zoomIn {
                      0% {
                        opacity: 0;
                        transform: scale(0.5);
                      }

                      100% {
                        opacity: 1;
                        transform: scale(1);
                      }
                    }

                    @keyframes slideLeft {
                      0% {
                        opacity: 0;
                        transform: translateX(-30px);
                      }

                      100% {
                        opacity: 1;
                        transform: translateX(0);
                      }
                    }

                    @keyframes fadeIn {
                      0% {
                        opacity: 0;
                      }

                      100% {
                        opacity: 1;
                      }
                    }
                  </style>
                  <g id="freepik--background-simple--inject-187" class="animable" style="transform-origin: 251.257px 259.246px;">
                    <path d="M443.4,330.93s51.09-57.42,34.85-157.33-99.67-136.72-155-117.49-67.81,52.7-113,71.84-131.06,6-174,70.29,11.88,170.82,81.67,237.62S365.8,460.84,443.4,330.93Z" style="fill: rgb(146, 227, 169); transform-origin: 251.257px 259.246px;" id="elokgfiaib38" class="animable"></path>
                    <g id="el8eeesec5soj">
                      <path d="M443.4,330.93s51.09-57.42,34.85-157.33-99.67-136.72-155-117.49-67.81,52.7-113,71.84-131.06,6-174,70.29,11.88,170.82,81.67,237.62S365.8,460.84,443.4,330.93Z" style="fill: rgb(255, 255, 255); opacity: 0.7; transform-origin: 251.257px 259.246px;" class="animable"></path>
                    </g>
                  </g>
                  <g id="freepik--Floor--inject-187" class="animable" style="transform-origin: 252.15px 475.44px;">
                    <line x1="445" y1="475.44" x2="477.66" y2="475.44" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 461.33px 475.44px;" id="elm67jrt7l7h" class="animable"></line>
                    <line x1="26.64" y1="475.44" x2="435" y2="475.44" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 230.82px 475.44px;" id="el2ivo6o121su" class="animable"></line>
                  </g>
                  <g id="freepik--Graphics--inject-187" class="animable" style="transform-origin: 438.356px 317.995px;">
                    <path d="M439,401.5l-16.5,23,5.83,3.94a13.88,13.88,0,0,1,6.11,11.5h11.94a13.87,13.87,0,0,1,6.1-11.5l5.83-3.94-16.49-23Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 440.405px 420.72px;" id="elcbqksoitogw" class="animable"></path>
                    <line x1="440.19" y1="401.8" x2="440.19" y2="420.33" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 440.19px 411.065px;" id="elc6wpz8lonbj" class="animable"></line>
                    <circle cx="440.19" cy="420.33" r="3.95" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 440.19px 420.33px;" id="eldsc0ze29o1g" class="animable"></circle>
                    <path d="M468,420.33a27.85,27.85,0,0,0-55.7,0" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 440.15px 406.405px;" id="el4ipxo38lqpy" class="animable"></path>
                    <rect x="465.96" y="418.25" width="4.16" height="4.16" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 468.04px 420.33px;" id="elymjgn7a55vq" class="animable"></rect>
                    <rect x="409.96" y="418.25" width="4.16" height="4.16" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 412.04px 420.33px;" id="eljth66i3e5mg" class="animable"></rect>
                    <rect x="438.11" y="390.4" width="4.16" height="4.16" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 440.19px 392.48px;" id="el37twlig8qw" class="animable"></rect>
                    <g id="elib07pfp9gcn">
                      <circle cx="435.8" cy="321.62" r="27.59" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 435.8px 321.62px; transform: rotate(-13.44deg);" class="animable"></circle>
                    </g>
                    <circle cx="435.8" cy="321.62" r="22.63" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 435.8px 321.62px;" id="el9p0nxl0uh4c" class="animable"></circle>
                    <polyline points="435.8 330.63 435.8 321.62 455.67 321.62" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 445.735px 326.125px;" id="elhx6q8e2n9ar" class="animable"></polyline>
                    <rect x="433.6" y="291.83" width="4.41" height="4.41" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 435.805px 294.035px;" id="elku99lyc8xv" class="animable"></rect>
                    <rect x="433.6" y="347.06" width="4.41" height="4.41" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 435.805px 349.265px;" id="eluiii63r0zsc" class="animable"></rect>
                    <rect x="461.47" y="319.79" width="4.41" height="4.41" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 463.675px 321.995px;" id="elr5fqux16n4e" class="animable"></rect>
                    <rect x="405.93" y="319.79" width="4.41" height="4.41" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 408.135px 321.995px;" id="el67r13s5li2" class="animable"></rect>
                    <polygon points="455.06 340.52 458 364.69 463.41 355.4 474.16 355.61 455.06 340.52" style="fill: rgb(38, 50, 56); transform-origin: 464.61px 352.605px;" id="elfn1r4j6ywhr" class="animable"></polygon>
                    <rect x="465.89" y="363.86" width="4.14" height="4.14" style="fill: rgb(38, 50, 56); transform-origin: 467.96px 365.93px;" id="elkvg5s63itwo" class="animable"></rect>
                    <rect x="409.15" y="196.05" width="61.78" height="45.61" rx="4.4" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 440.04px 218.855px;" id="el4ir8h6krm8" class="animable"></rect>
                    <line x1="409.42" y1="229.89" x2="470.66" y2="229.89" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 440.04px 229.89px;" id="elrrnqpw145qp" class="animable"></line>
                    <rect x="432.07" y="241.65" width="15.95" height="12.2" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 440.045px 247.75px;" id="elhrdyxkzzyz8" class="animable"></rect>
                    <rect x="426.9" y="253.88" width="26.07" height="2.33" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 439.935px 255.045px;" id="elemxz0hix6w8" class="animable"></rect>
                  </g>
                  <g id="freepik--Plant--inject-187" class="animable" style="transform-origin: 65.4499px 367.22px;">
                    <line x1="47.01" y1="304.33" x2="60.51" y2="433.66" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 53.76px 368.995px;" id="el8vi3xfmoagx" class="animable"></line>
                    <line x1="57.85" y1="408.37" x2="52.22" y2="402.44" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 55.035px 405.405px;" id="elsr358bxmy8" class="animable"></line>
                    <path d="M54.08,404.38a6.94,6.94,0,0,0-2.44-5.19c-1.57-1.4-9.83-4.88-10.62-2C40.17,400.39,48.63,406.61,54.08,404.38Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 47.5203px 400.467px;" id="el4a21dgu0133" class="animable"></path>
                    <line x1="54.08" y1="404.38" x2="46.21" y2="399.48" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 50.145px 401.93px;" id="elc7udmwfr2gf" class="animable"></line>
                    <line x1="59.25" y1="421.73" x2="53.75" y2="417.15" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 56.5px 419.44px;" id="els8ckbr30fgr" class="animable"></line>
                    <path d="M55.61,419.09a6.91,6.91,0,0,0-2.43-5.19c-1.57-1.4-9.84-4.88-10.62-2C41.7,415.1,50.16,421.31,55.61,419.09Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 49.0546px 415.175px;" id="eldemi0n3tr2f" class="animable"></path>
                    <line x1="55.61" y1="419.09" x2="47.74" y2="414.19" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 51.675px 416.64px;" id="el8e8o4mjfivn" class="animable"></line>
                    <line x1="54.03" y1="371.55" x2="48.47" y2="366.55" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 51.25px 369.05px;" id="elft9pc09l4em" class="animable"></line>
                    <path d="M50.33,368.49a6.91,6.91,0,0,0-2.43-5.19c-1.57-1.4-9.84-4.88-10.62-2C36.42,364.5,44.88,370.72,50.33,368.49Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 43.7746px 364.577px;" id="elovfeva4jrzo" class="animable"></path>
                    <line x1="50.33" y1="368.49" x2="42.46" y2="363.59" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 46.395px 366.04px;" id="elwj29luzhcrl" class="animable"></line>
                    <line x1="55.58" y1="387.19" x2="50.01" y2="381.26" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 52.795px 384.225px;" id="elrphak5kedq" class="animable"></line>
                    <path d="M51.87,383.2A6.94,6.94,0,0,0,49.43,378c-1.57-1.4-9.84-4.88-10.62-2C38,379.21,46.42,385.42,51.87,383.2Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 45.3128px 379.28px;" id="eld0mxpw5ob4k" class="animable"></path>
                    <line x1="51.87" y1="383.2" x2="44" y2="378.3" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 47.935px 380.75px;" id="eleooedej8l1f" class="animable"></line>
                    <line x1="51.55" y1="348.34" x2="45.98" y2="342.66" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 48.765px 345.5px;" id="el6w9ypudow0t" class="animable"></line>
                    <path d="M47.84,344.6a6.94,6.94,0,0,0-2.44-5.19c-1.57-1.4-9.83-4.88-10.62-2C33.93,340.61,42.39,346.82,47.84,344.6Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 41.2803px 340.685px;" id="ellyclmq8erf" class="animable"></path>
                    <line x1="47.84" y1="344.6" x2="39.97" y2="339.7" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 43.905px 342.15px;" id="el3wh3vnlmzdx" class="animable"></line>
                    <line x1="48.87" y1="322.79" x2="43.47" y2="318.67" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 46.17px 320.73px;" id="elyqthn9589oj" class="animable"></line>
                    <path d="M45.33,320.6a6.91,6.91,0,0,0-2.43-5.19c-1.57-1.4-9.84-4.88-10.62-2C31.42,316.61,39.88,322.83,45.33,320.6Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 38.7746px 316.687px;" id="elx88ozb7kavo" class="animable"></path>
                    <line x1="45.33" y1="320.6" x2="37.46" y2="315.7" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 41.395px 318.15px;" id="eljzhmexopqap" class="animable"></line>
                    <line x1="57.31" y1="400.21" x2="61.96" y2="392.62" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 59.635px 396.415px;" id="elr09zbyfesr" class="animable"></line>
                    <path d="M60.54,394.9a6.94,6.94,0,0,1,1.31-5.58c1.25-1.7,8.62-6.81,10-4.12C73.33,388.12,66.33,396,60.54,394.9Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 66.2295px 389.718px;" id="elyy71olapfof" class="animable"></path>
                    <line x1="60.54" y1="394.9" x2="66.08" y2="389.7" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 63.31px 392.3px;" id="el8alcv3zpdig" class="animable"></line>
                    <line x1="53.27" y1="361.5" x2="57.92" y2="353.91" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 55.595px 357.705px;" id="elrxpxakc1aje" class="animable"></line>
                    <path d="M56.5,356.19a6.94,6.94,0,0,1,1.31-5.58c1.25-1.69,7-5.94,8.38-3.26C67.68,350.27,62.29,357.25,56.5,356.19Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 61.4046px 351.392px;" id="elt6iwmfck0q" class="animable"></path>
                    <line x1="56.5" y1="356.19" x2="63.59" y2="349.42" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 60.045px 352.805px;" id="eludw3c30scup" class="animable"></line>
                    <line x1="51.49" y1="344.49" x2="56.15" y2="336.91" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 53.82px 340.7px;" id="elc8iid1tlqj" class="animable"></line>
                    <path d="M54.73,339.18A6.94,6.94,0,0,1,56,333.6c1.24-1.69,8.61-6.8,10-4.12C67.51,332.41,60.52,340.24,54.73,339.18Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 60.3993px 333.998px;" id="elw3choqm1y4m" class="animable"></path>
                    <line x1="54.73" y1="339.18" x2="61.81" y2="332.41" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 58.27px 335.795px;" id="el6fp4sxwkjrp" class="animable"></line>
                    <line x1="48.89" y1="319.56" x2="53.54" y2="311.97" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 51.215px 315.765px;" id="elx5mf6g3wywg" class="animable"></line>
                    <path d="M52.12,314.25a6.92,6.92,0,0,1,1.32-5.58c1.24-1.7,8.61-6.81,10-4.12C64.91,307.47,57.92,315.3,52.12,314.25Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 57.8137px 309.064px;" id="elgtppq88h5tt" class="animable"></path>
                    <line x1="52.12" y1="314.25" x2="59.21" y2="307.48" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 55.665px 310.865px;" id="elekkyldcpe5" class="animable"></line>
                    <path d="M46.9,303.61A6.94,6.94,0,0,1,48.21,298c1.25-1.69,8.62-6.8,10-4.12C59.69,296.84,52.69,304.67,46.9,303.61Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 52.5871px 298.413px;" id="elwp0whgggnsm" class="animable"></path>
                    <line x1="46.9" y1="303.61" x2="53.99" y2="296.84" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 50.445px 300.225px;" id="elzb6k0iml4vg" class="animable"></line>
                    <path d="M47.31,303.91A7,7,0,0,1,41.9,302c-1.56-1.42-5.87-9.29-3-10.36C41.91,290.48,49,298.26,47.31,303.91Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 42.7519px 297.729px;" id="els4mcy54wa9" class="animable"></path>
                    <line x1="47.31" y1="303.91" x2="41.32" y2="296.15" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 44.315px 300.03px;" id="elx8pygeln07m" class="animable"></line>
                    <line x1="67.23" y1="269.48" x2="67.23" y2="433.03" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 67.23px 351.255px;" id="elm90mtd8o4e" class="animable"></line>
                    <line x1="82.61" y1="322.14" x2="69.19" y2="451.47" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 75.9px 386.805px;" id="elxd56gxnlmv" class="animable"></line>
                    <line x1="72.03" y1="425.05" x2="68.92" y2="421.5" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 70.475px 423.275px;" id="ely3xtm5syyro" class="animable"></line>
                    <path d="M68.92,421.5a6.94,6.94,0,0,0-1.31-5.58c-1.25-1.69-8.62-6.8-10-4.11C56.13,414.73,63.13,422.56,68.92,421.5Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 63.2305px 416.32px;" id="el8bgvrrdgn4f" class="animable"></path>
                    <line x1="68.92" y1="421.5" x2="62.23" y2="415.08" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 65.575px 418.29px;" id="elxrkp32qmcpm" class="animable"></line>
                    <line x1="75.82" y1="388.13" x2="71.21" y2="384.28" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 73.515px 386.205px;" id="elhuyjkmnid1q" class="animable"></line>
                    <path d="M72.65,385.61A6.92,6.92,0,0,0,71.33,380c-1.25-1.7-8.62-6.8-10-4.11C59.86,378.84,66.86,386.67,72.65,385.61Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 66.9588px 380.415px;" id="elsnr2e7kw05l" class="animable"></path>
                    <line x1="72.65" y1="385.61" x2="65.96" y2="379.19" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 69.305px 382.4px;" id="elu5u4h4bz8pb" class="animable"></line>
                    <line x1="74.36" y1="405.62" x2="69.7" y2="398.04" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 72.03px 401.83px;" id="el1r9boj3u3xf" class="animable"></line>
                    <path d="M71.12,400.32a6.94,6.94,0,0,0-1.31-5.58c-1.25-1.7-8.62-6.8-10-4.12C58.33,393.55,65.33,401.37,71.12,400.32Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 65.4305px 395.136px;" id="el92g3apkw66" class="animable"></path>
                    <line x1="71.12" y1="400.32" x2="64.43" y2="393.9" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 67.775px 397.11px;" id="eljwc2u7ijz1" class="animable"></line>
                    <line x1="78.15" y1="364.56" x2="73.71" y2="359.44" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 75.93px 362px;" id="elcrm7cq4z8vs" class="animable"></line>
                    <path d="M75.13,361.72a6.92,6.92,0,0,0-1.32-5.58c-1.25-1.7-8.62-6.8-10-4.12C62.34,355,69.34,362.77,75.13,361.72Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 69.4364px 356.536px;" id="el2o860eji0m" class="animable"></path>
                    <line x1="75.13" y1="361.72" x2="68.44" y2="355.3" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 71.785px 358.51px;" id="ellzm6w80wb4b" class="animable"></line>
                    <line x1="80.49" y1="342.1" x2="76.2" y2="335.44" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 78.345px 338.77px;" id="ell2bfh3kw86" class="animable"></line>
                    <path d="M77.62,337.72a6.92,6.92,0,0,0-1.32-5.58c-1.25-1.7-8.62-6.8-10-4.12C64.83,331,71.83,338.77,77.62,337.72Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 71.9264px 332.536px;" id="elsn3yjymooc" class="animable"></path>
                    <line x1="77.62" y1="337.72" x2="70.93" y2="331.3" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 74.275px 334.51px;" id="elnfswszodhng" class="animable"></line>
                    <line x1="72.94" y1="418.08" x2="79.06" y2="411.61" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 76px 414.845px;" id="els58nm0at3co" class="animable"></line>
                    <path d="M77.2,413.55a6.91,6.91,0,0,1,2.43-5.19c1.57-1.4,9.84-4.89,10.62-2C91.11,409.55,82.65,415.77,77.2,413.55Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 83.7554px 409.633px;" id="elap0dlvn4vot" class="animable"></path>
                    <line x1="77.2" y1="413.55" x2="85.53" y2="408.38" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 81.365px 410.965px;" id="elrdvf4svjybn" class="animable"></line>
                    <line x1="76.96" y1="379.37" x2="83.08" y2="372.91" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 80.02px 376.14px;" id="el28uxam8dr0k" class="animable"></line>
                    <path d="M81.22,374.84a6.92,6.92,0,0,1,2.43-5.19c1.57-1.4,9.83-4.88,10.62-2C95.12,370.84,86.67,377.07,81.22,374.84Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 87.7747px 370.926px;" id="elvx12lud9mbo" class="animable"></path>
                    <line x1="81.22" y1="374.84" x2="89.55" y2="369.68" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 85.385px 372.26px;" id="el7awg2bigv0o" class="animable"></line>
                    <line x1="78.73" y1="362.36" x2="84.84" y2="355.9" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 81.785px 359.13px;" id="elim5gg2hb6hm" class="animable"></line>
                    <path d="M83,357.84a7,7,0,0,1,2.44-5.2c1.56-1.4,9.83-4.88,10.61-2C96.89,353.83,88.43,360.06,83,357.84Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 89.554px 353.92px;" id="elnvoog7tkfg" class="animable"></path>
                    <line x1="82.98" y1="357.84" x2="91.31" y2="352.67" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 87.145px 355.255px;" id="elzps788hhm" class="animable"></line>
                    <line x1="81.24" y1="336.15" x2="87.43" y2="330.96" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 84.335px 333.555px;" id="el1nt6whbdks7" class="animable"></line>
                    <path d="M85.57,332.9A6.91,6.91,0,0,1,88,327.71c1.57-1.41,9.84-4.89,10.62-2C99.48,328.9,91,335.12,85.57,332.9Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 92.1253px 328.982px;" id="elpuhlruqcy7" class="animable"></path>
                    <line x1="85.57" y1="332.9" x2="93.9" y2="327.73" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 89.735px 330.315px;" id="eli065was95u" class="animable"></line>
                    <path d="M82.65,321.42a6.94,6.94,0,0,1,2.43-5.2c1.57-1.4,9.83-4.88,10.62-2C96.56,317.42,88.1,323.64,82.65,321.42Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 89.2054px 317.5px;" id="elsxxkr8gnwhq" class="animable"></path>
                    <line x1="82.65" y1="321.42" x2="90.98" y2="316.25" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 86.815px 318.835px;" id="elp7zaxunjrj" class="animable"></line>
                    <path d="M83,321.79a7,7,0,0,1-4.91-3c-1.23-1.7-3.82-10.29-.85-10.76C80.47,307.54,85.77,316.6,83,321.79Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 79.8209px 314.9px;" id="el2ev6gmkb8en" class="animable"></path>
                    <line x1="82.99" y1="321.79" x2="78.72" y2="312.96" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 80.855px 317.375px;" id="elc1peycd2idk" class="animable"></line>
                    <line x1="67.01" y1="408.14" x2="61.95" y2="401.37" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 64.48px 404.755px;" id="elm0mhkjqtcir" class="animable"></line>
                    <path d="M63.6,403.49a6.91,6.91,0,0,0-1.89-5.41c-1.41-1.56-9.27-5.88-10.36-3.06C50.18,398.08,58,405.14,63.6,403.49Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 57.4307px 398.911px;" id="el5r6v16k5rcu" class="animable"></path>
                    <line x1="63.6" y1="403.49" x2="56.28" y2="397.8" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 59.94px 400.645px;" id="elj3p1nxi5k4" class="animable"></line>
                    <line x1="67.19" y1="375.52" x2="61.95" y2="370.64" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 64.57px 373.08px;" id="ele5xwcs9v38a" class="animable"></line>
                    <path d="M63.6,372.76a6.93,6.93,0,0,0-1.89-5.42c-1.41-1.56-9.27-5.87-10.36-3.06C50.18,367.34,58,374.4,63.6,372.76Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 57.4307px 368.177px;" id="el7jbskpgm7bh" class="animable"></path>
                    <line x1="63.6" y1="372.76" x2="56.28" y2="367.06" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 59.94px 369.91px;" id="ellggxp6n4w7" class="animable"></line>
                    <line x1="67.36" y1="341.62" x2="61.95" y2="334.55" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 64.655px 338.085px;" id="el6fk0klznwb9" class="animable"></line>
                    <path d="M63.6,336.67a6.91,6.91,0,0,0-1.89-5.41c-1.41-1.56-9.27-5.88-10.36-3.06C50.18,331.26,58,338.32,63.6,336.67Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 57.4307px 332.091px;" id="elnqd29posgh" class="animable"></path>
                    <line x1="63.6" y1="336.67" x2="56.28" y2="330.98" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 59.94px 333.825px;" id="el2cbqz96w3p8" class="animable"></line>
                    <line x1="67.01" y1="315.09" x2="61.95" y2="310.53" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 64.48px 312.81px;" id="el3qj9kx8enxc" class="animable"></line>
                    <path d="M63.6,312.65a6.91,6.91,0,0,0-1.89-5.41c-1.41-1.56-9.27-5.88-10.36-3.06C50.18,307.24,58,314.3,63.6,312.65Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 57.4307px 308.071px;" id="elz78f50xeqe" class="animable"></path>
                    <line x1="63.6" y1="312.65" x2="56.28" y2="306.96" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 59.94px 309.805px;" id="el1vxfog2zbks" class="animable"></line>
                    <line x1="67.3" y1="291.06" x2="62.05" y2="287.21" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 64.675px 289.135px;" id="elhfn75lok4rb" class="animable"></line>
                    <path d="M63.6,288.52a6.91,6.91,0,0,0-1.89-5.41c-1.41-1.56-9.27-5.88-10.36-3.06C50.18,283.11,58,290.17,63.6,288.52Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 57.4307px 283.941px;" id="elhuzgvky1fiq" class="animable"></path>
                    <line x1="63.6" y1="288.52" x2="56.28" y2="282.83" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 59.94px 285.675px;" id="elo8h823jurxc" class="animable"></line>
                    <line x1="68.29" y1="416.3" x2="72.66" y2="410.76" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 70.475px 413.53px;" id="eldzrxpzjlr0l" class="animable"></line>
                    <path d="M71,412.88a7,7,0,0,1,1.88-5.42c1.42-1.56,9.28-5.87,10.36-3.06C84.43,407.46,76.66,414.53,71,412.88Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 77.1674px 408.298px;" id="elww5jjhleosi" class="animable"></path>
                    <line x1="71.01" y1="412.88" x2="78.76" y2="406.88" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 74.885px 409.88px;" id="el7ecma4l23ex" class="animable"></line>
                    <line x1="67.24" y1="368.94" x2="72.66" y2="361.88" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 69.95px 365.41px;" id="el8xjcz7sqxol" class="animable"></line>
                    <path d="M71,364a7,7,0,0,1,1.88-5.42c1.42-1.56,9.28-5.87,10.36-3.06C84.43,358.58,76.66,365.64,71,364Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 77.1674px 359.417px;" id="elrb7xk3hddtq" class="animable"></path>
                    <line x1="71.01" y1="364" x2="78.76" y2="357.99" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 74.885px 360.995px;" id="elb30azcw3d4r" class="animable"></line>
                    <line x1="67.24" y1="344.81" x2="72.66" y2="337.75" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 69.95px 341.28px;" id="elop9kw5hk8q9" class="animable"></line>
                    <path d="M71,339.87a7,7,0,0,1,1.88-5.42c1.42-1.55,9.28-5.87,10.36-3.06C84.43,334.45,76.66,341.52,71,339.87Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 77.1674px 335.289px;" id="elhz0nadxwvf" class="animable"></path>
                    <line x1="71.01" y1="339.87" x2="78.76" y2="333.87" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 74.885px 336.87px;" id="elrdv6olr157" class="animable"></line>
                    <line x1="67.24" y1="312.92" x2="72.66" y2="305.86" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 69.95px 309.39px;" id="elppaiqgntum" class="animable"></line>
                    <path d="M71,308a7,7,0,0,1,1.88-5.42c1.42-1.55,9.28-5.87,10.36-3.06C84.43,302.56,76.66,309.63,71,308Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 77.1674px 303.416px;" id="el4shezpkzpn4" class="animable"></path>
                    <line x1="71.01" y1="307.98" x2="78.76" y2="301.98" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 74.885px 304.98px;" id="el37ncqy7xchm" class="animable"></line>
                    <line x1="67.48" y1="285.4" x2="72.79" y2="281.26" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 70.135px 283.33px;" id="eln4ty34cv5k" class="animable"></line>
                    <path d="M71,282.91a7,7,0,0,1,1.88-5.42c1.42-1.55,9.28-5.87,10.36-3.06C84.43,277.49,76.66,284.56,71,282.91Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 77.1674px 278.329px;" id="elbpu9sj8aa6k" class="animable"></path>
                    <line x1="71.01" y1="282.91" x2="78.76" y2="276.91" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 74.885px 279.91px;" id="el45emdh2eenk" class="animable"></line>
                    <path d="M66.92,271.79a7,7,0,0,1,1.88-5.42c1.42-1.55,9.28-5.87,10.36-3.06C80.34,266.37,72.57,273.44,66.92,271.79Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 73.0864px 267.209px;" id="elnorupbzx1n" class="animable"></path>
                    <line x1="66.92" y1="271.79" x2="74.67" y2="265.79" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 70.795px 268.79px;" id="el2u0f1fg370y" class="animable"></line>
                    <path d="M67.3,272.12a6.94,6.94,0,0,1-5.19-2.44c-1.4-1.57-4.87-9.84-2-10.62C63.32,258.21,69.53,266.68,67.3,272.12Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 63.3892px 265.56px;" id="elgyhaws5901" class="animable"></path>
                    <line x1="67.3" y1="272.12" x2="62.14" y2="263.79" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 64.72px 267.955px;" id="eldyki165vmh4" class="animable"></line>
                    <polygon points="73.96 475.44 56.64 475.44 54.48 431.7 76.13 431.7 73.96 475.44" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 65.305px 453.57px;" id="el7qb4g3vkztq" class="animable"></polygon>
                  </g>
                  <g id="freepik--Device--inject-187" class="animable" style="transform-origin: 285.095px 253.37px;">
                    <polygon points="277.49 286.82 285.13 305.93 265 305.93 265 309.37 308.45 309.37 297.22 286.66 277.49 286.82" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 286.725px 298.015px;" id="elj8n2mj56aar" class="animable"></polygon>
                    <polygon points="275.64 286.82 283.29 305.93 263.15 305.93 263.15 309.37 306.6 309.37 295.37 286.66 275.64 286.82" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 284.875px 298.015px;" id="elusebgdytld" class="animable"></polygon>
                    <path d="M339.87,287H231.58a3.65,3.65,0,0,1-3.64-3.81l3.53-81.75a4.26,4.26,0,0,1,4.25-4.07H344a3.84,3.84,0,0,1,3.83,4l-3.51,81.33A4.51,4.51,0,0,1,339.87,287Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 287.885px 242.185px;" id="elu5ulbjumch" class="animable"></path>
                    <path d="M334.33,287H226a3.64,3.64,0,0,1-3.64-3.81l3.53-81.75a4.25,4.25,0,0,1,4.25-4.07H338.5a3.84,3.84,0,0,1,3.83,4l-3.52,81.33A4.49,4.49,0,0,1,334.33,287Z" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 282.345px 242.185px;" id="elfwy7719r8ws" class="animable"></path>
                    <polygon points="334.21 268.28 228.27 268.28 231.17 201.12 337.11 201.12 334.21 268.28" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 282.69px 234.7px;" id="elfj7n693jp56" class="animable"></polygon>
                    <path d="M270.08,230.06a12.66,12.66,0,0,0-13.69-13.3,14.16,14.16,0,0,0-12.88,12.14,12.82,12.82,0,0,0,2.61,9.69,12.55,12.55,0,0,1,2.66,8.39l-.08,1.83h14.41l.08-1.83a13.66,13.66,0,0,1,3.41-8.38A13.94,13.94,0,0,0,270.08,230.06Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 256.742px 232.763px;" id="elufybxx1wc6" class="animable"></path>
                    <polygon points="262.95 252.38 248.54 252.38 248.7 248.81 263.11 248.81 262.95 252.38" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 255.825px 250.595px;" id="elqvc8lfnun1" class="animable"></polygon>
                    <path d="M259.23,256h-7.29a3.38,3.38,0,0,1-3.4-3.57H263A3.76,3.76,0,0,1,259.23,256Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 255.767px 254.215px;" id="el0ek8d52mch97" class="animable"></path>
                    <path d="M257.25,259.51H253.6a3.38,3.38,0,0,1-3.4-3.57H261A3.76,3.76,0,0,1,257.25,259.51Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 255.597px 257.725px;" id="elt6d1mto0gd" class="animable"></path>
                    <line x1="271.97" y1="230.13" x2="278.07" y2="230.13" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 275.02px 230.13px;" id="eldasyea71wb7" class="animable"></line>
                    <line x1="234.83" y1="230.13" x2="241.02" y2="230.13" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 237.925px 230.13px;" id="elxzudswd6hek" class="animable"></line>
                    <line x1="257.15" y1="214.63" x2="257.42" y2="208.51" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 257.285px 211.57px;" id="elvl9lu59rqcj" class="animable"></line>
                    <line x1="268.02" y1="219.06" x2="272.42" y2="214.84" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 270.22px 216.95px;" id="eliy6b9urgqrh" class="animable"></line>
                    <line x1="240.48" y1="245.42" x2="244.95" y2="241.14" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 242.715px 243.28px;" id="el48jxop8thsi" class="animable"></line>
                    <line x1="245.76" y1="218.94" x2="241.85" y2="214.84" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 243.805px 216.89px;" id="elm4g5zvolnyt" class="animable"></line>
                    <line x1="271.06" y1="245.42" x2="267.16" y2="241.34" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 269.11px 243.38px;" id="elg70k74vjyv9" class="animable"></line>
                    <path d="M320,230.06a12.65,12.65,0,0,0-13.68-13.3,14.15,14.15,0,0,0-12.88,12.14,12.82,12.82,0,0,0,2.6,9.69,12.62,12.62,0,0,1,2.67,8.39l-.09,1.83h14.42l.08-1.83a13.66,13.66,0,0,1,3.41-8.38A13.87,13.87,0,0,0,320,230.06Z" style="fill: rgb(38, 50, 56); stroke: rgb(255, 255, 255); stroke-linecap: round; stroke-linejoin: round; transform-origin: 306.667px 232.763px;" id="elpaw9k0mshdq" class="animable"></path>
                    <polygon points="312.92 252.38 298.5 252.38 298.66 248.81 313.08 248.81 312.92 252.38" style="fill: rgb(38, 50, 56); stroke: rgb(255, 255, 255); stroke-linecap: round; stroke-linejoin: round; transform-origin: 305.79px 250.595px;" id="elfecmugb3q64" class="animable"></polygon>
                    <path d="M309.19,256h-7.28a3.39,3.39,0,0,1-3.41-3.57h14.42A3.77,3.77,0,0,1,309.19,256Z" style="fill: rgb(38, 50, 56); stroke: rgb(255, 255, 255); stroke-linecap: round; stroke-linejoin: round; transform-origin: 305.708px 254.215px;" id="els89ix55mhr" class="animable"></path>
                    <path d="M307.22,259.51h-3.65a3.39,3.39,0,0,1-3.41-3.57h10.78A3.77,3.77,0,0,1,307.22,259.51Z" style="fill: rgb(38, 50, 56); stroke: rgb(255, 255, 255); stroke-linecap: round; stroke-linejoin: round; transform-origin: 305.548px 257.725px;" id="elpzu6kc4qtja" class="animable"></path>
                    <line x1="321.93" y1="230.13" x2="328.04" y2="230.13" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 324.985px 230.13px;" id="eljxekukz3z" class="animable"></line>
                    <line x1="284.8" y1="230.13" x2="290.99" y2="230.13" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 287.895px 230.13px;" id="elirkw93i2d8" class="animable"></line>
                    <line x1="307.11" y1="214.63" x2="307.39" y2="208.51" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 307.25px 211.57px;" id="elcfzub70ljh" class="animable"></line>
                    <line x1="317.99" y1="219.06" x2="322.39" y2="214.84" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 320.19px 216.95px;" id="elcyldxnedyr" class="animable"></line>
                    <line x1="290.45" y1="245.42" x2="294.92" y2="241.14" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 292.685px 243.28px;" id="elvpxhdcyw70t" class="animable"></line>
                    <line x1="295.73" y1="218.94" x2="291.82" y2="214.84" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 293.775px 216.89px;" id="elcxz39vhy2f" class="animable"></line>
                    <line x1="321.02" y1="245.42" x2="317.13" y2="241.34" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; transform-origin: 319.075px 243.38px;" id="el1yqr7fvdc8b" class="animable"></line>
                    <path d="M334.33,287H226a3.64,3.64,0,0,1-3.64-3.81l.45-10.51h116.4l-.44,10A4.49,4.49,0,0,1,334.33,287Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 280.783px 279.84px;" id="elucv9dqck7v" class="animable"></path>
                  </g>
                  <g id="freepik--Desk--inject-187" class="animable" style="transform-origin: 264.99px 392.235px;">
                    <rect x="312.41" y="311.95" width="55.71" height="163.51" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 340.265px 393.705px;" id="elj6rx6v5s5vs" class="animable"></rect>
                    <rect x="316.04" y="311.95" width="55.71" height="163.51" style="fill: rgb(125, 125, 125); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 343.895px 393.705px;" id="el79lenxpc46" class="animable"></rect>
                    <rect x="316.04" y="311.95" width="55.71" height="8.58" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 343.895px 316.24px;" id="eluwe7hsgnvc" class="animable"></rect>
                    <rect x="151.93" y="311.95" width="55.71" height="163.51" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 179.785px 393.705px;" id="elsm733fxp0p" class="animable"></rect>
                    <rect x="155.56" y="311.95" width="55.71" height="163.51" style="fill: rgb(125, 125, 125); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 183.415px 393.705px;" id="eldkx4r8fdf4k" class="animable"></rect>
                    <rect x="144.05" y="309.01" width="241.88" height="5.54" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 264.99px 311.78px;" id="ely0myyicske" class="animable"></rect>
                    <rect x="313.14" y="309.01" width="72.79" height="5.54" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 349.535px 311.78px;" id="el9v2az446vsv" class="animable"></rect>
                  </g>
                  <g id="freepik--Character--inject-187" class="animable" style="transform-origin: 200.388px 322.309px;">
                    <path d="M296,309l-.11-.29a11.72,11.72,0,0,0-10.91-7.44h-4.63a11.74,11.74,0,0,0-10,5.64L269,309Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 282.5px 305.135px;" id="elr74x4hgtv19" class="animable"></path>
                    <path d="M198,185.66s0,.75,0,1.85c-.07,2.72-.19,7.62-.19,8.78,0,1.61-2.54,3.92-2.54,5.54s1.39,6.7,1.85,8.31-4.16,3-4.16,3-2.08,7.63-5.08,9.47-9,0-10.16-.23-4.85-1.38-4.85-1.38l-.23,11.77-19.17-7.85,1.46-12.9.16-1.42s.69-10.85-.23-16.16c-1.62-9.29,2.08-21.25,16.39-24.25a25.22,25.22,0,0,1,24.71,8.31C197.56,180.58,198,185.66,198,185.66Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 175.735px 201.18px;" id="elo8z3hhuduo" class="animable"></path>
                    <line x1="174.93" y1="197.44" x2="200.33" y2="199.98" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 187.63px 198.71px;" id="elj16140zvsrr" class="animable"></line>
                    <path d="M196.64,198.83l4.85.46s-.7,6.46-5.55,6.46" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 198.715px 202.29px;" id="el779sejhj3fk" class="animable"></path>
                    <path d="M190.17,214.53s-2.08.23-3.46-3.23" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 188.44px 212.917px;" id="elt0qmtkihi1g" class="animable"></path>
                    <line x1="194.1" y1="199.98" x2="192.71" y2="202.98" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 193.405px 201.48px;" id="ely5rsekf6suc" class="animable"></line>
                    <path d="M192.71,196.75s1.62-2.54,4.16.69" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 194.79px 196.62px;" id="eloyj0pqtkqzb" class="animable"></path>
                    <path d="M198,185.66s0,.75,0,1.85l-.34-.32s-6.32-5.74-10.63-6.6-3.16,5.45-2.29,6.6,6.6,3.44,3.72,5.46-5.45,3.73-5.45,3.73l-1.82,4.86a2.37,2.37,0,0,1-4.38.15c-.77-1.7-2.34-5.1-3-6.17-.86-1.43-6.54.51-6,6a6,6,0,0,0,3,5s2.76,1.9-3.55,5.92-8.61,4.59-11.77.58c-.17-.21-.34-.45-.53-.72l.16-1.42s.69-10.85-.23-16.16c-1.62-9.29,2.08-21.25,16.39-24.25a25.22,25.22,0,0,1,24.71,8.31C197.56,180.58,198,185.66,198,185.66Z" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 176.268px 192.513px;" id="elq1bob503rej" class="animable"></path>
                    <path d="M251.94,453.67s9.55,3.38,15.09,3.08,9.85-2.78,11.09-.62-1.24,5.54-1.85,6.77-12.63,4.32-16.63,6.16-14.47,5.24-18.78,5.85-6.16-10.47-6.16-10.47Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 256.581px 464.302px;" id="elu1acbksxs5" class="animable"></path>
                    <path d="M254.71,467.52a179.74,179.74,0,0,1-17.58,4.57c.94,1.75,2.17,3.05,3.73,2.82,4.31-.61,14.78-4,18.78-5.85s16-4.92,16.63-6.16,3.08-4.61,1.85-6.77C273.5,465.06,264.26,464.44,254.71,467.52Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 257.795px 465.533px;" id="elyzwpk5yirt" class="animable"></path>
                    <path d="M211.91,369.29l22.79,95.15a31.6,31.6,0,0,0,9.54-2.46c4.93-2.16,6.47-3.39,7.7-4.93s-.31-6.77-.31-7.69-2.77-2.16-2.77-2.16l-8.62-81.91Z" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 232.163px 414.865px;" id="elto5sk6ytym" class="animable"></path>
                    <path d="M231.24,456.25s3.7,5.85,7.08,7.7,6.47,3.08,8.32,3.08,3.39,3.7,3.69,4.93a15.69,15.69,0,0,1,.31,3.08H222.31s-17.24-2.16-18.78-6.16,10.16-19.09,10.16-19.09Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 227.015px 462.415px;" id="elppiz6wqvxl8" class="animable"></path>
                    <path d="M250.45,472.56c-6.63-.07-21.49-.38-28.75-1.83a182.83,182.83,0,0,1-17.89-4.85,4.85,4.85,0,0,0-.28,3c1.54,4,18.78,6.16,18.78,6.16h28.33A17.83,17.83,0,0,0,250.45,472.56Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 227.025px 470.46px;" id="elehbgmm0v28e" class="animable"></path>
                    <path d="M188.25,326.85l8.62,7.69s37.26,2.78,50.19,3.39,21.55,4.31,21.25,10.47-36.25,102.82-36.25,102.82l-.05,5.64s-1.54,1.54-8.31,0a33.47,33.47,0,0,1-11.09-4.62s-1.23-5.85,1.85-8.31,6.43-17.62,6.43-17.62l12.62-61H139.59a70.53,70.53,0,0,1-1.84-34.18S170.39,337.32,188.25,326.85Z" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 202.39px 392.197px;" id="elsaj8fuddrw" class="animable"></path>
                    <path d="M176.3,234.49a152.34,152.34,0,0,0-14.77-9.57c-8.11-4.57-10-2.28-14.14.21s-22.47,14.36-23.92,18.31,0,8.94,0,8.94,7.07,43.69,8.94,61.16,7.28,26.21,18.72,27.46,29.54-5,34.33-7.7,5.2-1.66,4.78-6.86-5-23.72-5.62-27.88-.2-13.52-.2-13.52,4.36-21,1-32.45A51.39,51.39,0,0,0,176.3,234.49Z" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 156.596px 281.731px;" id="eltrkcfmyilpe" class="animable"></path>
                    <path d="M234.51,292.36,268,299.29s9.7-5.31,12.24-5.55,8.08,3.93,9.24,4.39,2.31,2.31,2.77,3.93,2.31,1.15.69,2.54-3.69-1.39-3.69-1.39-4.62.23-7.16,1.62-5.78,3.46-9.24,3.93a24.34,24.34,0,0,1-7.85-.7l-33.95-2.54S235.67,298.13,234.51,292.36Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 262.321px 300.593px;" id="elhi81p21mpk" class="animable"></path>
                    <path d="M283.93,300.67s2.78-.92,4.62-.23,3.7,1.62,3.7,1.62" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 288.09px 301.122px;" id="el1gkdrly5s88j" class="animable"></path>
                    <line x1="175.54" y1="280.67" x2="165.81" y2="278.4" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 170.675px 279.535px;" id="el5zmi9a2fclb" class="animable"></line>
                    <path d="M173.08,233.93s6.24,1.39,10.16,5.31,30.72,43.19,31.64,43.88,15,8.08,15,8.08,1.16,6.7-.92,9-7.16,5.31-7.16,5.31-12.47-1.38-16.4-2.31-39.49-29.79-39.49-29.79" style="fill: rgb(146, 227, 169); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 198.056px 269.72px;" id="ell0ab0yzwq1o" class="animable"></path>
                    <path d="M208.13,286c1.39.06,2.81.09,4.27.09" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 210.265px 286.045px;" id="eljtk49or02ec" class="animable"></path>
                    <path d="M182.13,281.48a97.9,97.9,0,0,0,22.43,4.31" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 193.345px 283.635px;" id="elmm97ys1gyxa" class="animable"></path>
                    <path d="M224.12,287.74s2.31,6.24.23,10.62a21.43,21.43,0,0,1-5.77,7.16s10.62,1.85,12.7,1.85,5.77-9,5.54-13.63S224.12,287.74,224.12,287.74Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 227.705px 297.555px;" id="eliq1qenxu018" class="animable"></path>
                    <path d="M233.51,365.34A59.69,59.69,0,0,0,252,356.1" style="fill: none; stroke: rgb(135, 135, 135); stroke-linejoin: round; transform-origin: 242.755px 360.72px;" id="el07m6ymdckq04" class="animable"></path>
                    <path d="M239.36,363.8s8.93-1.23,13.86-4.62" style="fill: none; stroke: rgb(135, 135, 135); stroke-linejoin: round; transform-origin: 246.29px 361.49px;" id="el7ujfc3wg5hh" class="animable"></path>
                    <path d="M192.12,366.17s-25.53-41.6-32.21-99.63c-1.58-13.72-12.59-24.07-25.7-24.07h0c-15.47,0-27.49,14.23-25.72,30.45,2.77,25.34,8.9,62.3,22.36,94.26Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 150.218px 304.825px;" id="el29s3kjxl18r" class="animable"></path>
                    <path d="M188.58,366.17s-25.53-41.6-32.21-99.63c-1.58-13.72-12.59-24.07-25.7-24.07h0c-15.47,0-27.49,14.23-25.72,30.45,2.77,25.34,8.9,62.3,22.36,94.26Z" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 146.678px 304.825px;" id="elqki99w6wobs" class="animable"></path>
                    <rect x="176.85" y="374.04" width="12.74" height="69.76" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 183.22px 408.92px;" id="eltprfln6zyro" class="animable"></rect>
                    <rect x="172" y="431.37" width="21.54" height="15.47" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 182.77px 439.105px;" id="elt8cz6uvy88" class="animable"></rect>
                    <polygon points="172.3 439.26 131.96 464.13 131.96 468.68 135.9 468.68 135.9 465.64 172 446.84 172.3 439.26" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 152.13px 453.97px;" id="elpvkj14hwat" class="animable"></polygon>
                    <path d="M142,469.59a5.46,5.46,0,1,0-5.46,5.46A5.46,5.46,0,0,0,142,469.59Z" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 136.54px 469.59px;" id="eljva1jezoxff" class="animable"></path>
                    <polygon points="193.53 439.26 233.87 464.13 233.87 468.68 229.93 468.68 229.93 465.64 193.83 446.84 193.53 439.26" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 213.7px 453.97px;" id="elhvdkat2ccp8" class="animable"></polygon>
                    <path d="M223.86,469.59a5.46,5.46,0,1,1,5.46,5.46A5.46,5.46,0,0,1,223.86,469.59Z" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 229.32px 469.59px;" id="elynwmwjwuuxc" class="animable"></path>
                    <path d="M177.76,469.59a5.46,5.46,0,1,1,5.46,5.46A5.45,5.45,0,0,1,177.76,469.59Z" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 183.22px 469.59px;" id="elyc5l7z1amhs" class="animable"></path>
                    <rect x="181.4" y="438.95" width="3.34" height="25.18" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 183.07px 451.54px;" id="elvqjq9sz9f1c" class="animable"></rect>
                    <g id="elyr6gntn2xy">
                      <rect x="194.82" y="345.17" width="37.34" height="6.47" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 213.49px 348.405px; transform: rotate(-76.66deg);" class="animable"></rect>
                    </g>
                    <rect x="191.94" y="323.46" width="46.19" height="9.24" rx="4.62" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 215.035px 328.08px;" id="elbw1ujo6zvye" class="animable"></rect>
                    <path d="M233.2,376.73H130.05a5.54,5.54,0,0,1-5.54-5.54h0a5.55,5.55,0,0,1,5.54-5.55H233.2a5.56,5.56,0,0,1,5.55,5.55h0A5.55,5.55,0,0,1,233.2,376.73Z" style="fill: rgb(38, 50, 56); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 181.63px 371.185px;" id="elmdgt7yxcc9s" class="animable"></path>
                    <path d="M233.2,376.73H188.55a5.54,5.54,0,0,1-5.54-5.54h0a5.55,5.55,0,0,1,5.54-5.55H233.2a5.56,5.56,0,0,1,5.55,5.55h0A5.55,5.55,0,0,1,233.2,376.73Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 210.88px 371.185px;" id="elieswam89kvk" class="animable"></path>
                  </g>
                  <g id="freepik--icon-3--inject-187" class="animable" style="transform-origin: 376.92px 94.715px;">
                    <rect x="327.59" y="36.48" width="98.66" height="116.47" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 376.92px 94.715px;" id="el2cn3bzutb8z" class="animable"></rect>
                    <path d="M407.65,85.83a30.07,30.07,0,1,0-53.18,19.23A29.54,29.54,0,0,1,361.32,124v4.12h32.51V124a29.47,29.47,0,0,1,6.84-18.9A30,30,0,0,0,407.65,85.83Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 377.58px 91.9356px;" id="el2m93vf9h2bg" class="animable"></path>
                    <rect x="361.32" y="128.11" width="32.51" height="8.04" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 377.575px 132.13px;" id="el12tky8oufmuo" class="animable"></rect>
                    <path d="M361.32,136.17h32.51a0,0,0,0,1,0,0v3.32a4.73,4.73,0,0,1-4.73,4.73H366a4.73,4.73,0,0,1-4.73-4.73v-3.32A0,0,0,0,1,361.32,136.17Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 377.55px 140.195px;" id="elkgqa7b1wx9q" class="animable"></path>
                    <path d="M365.42,144.2h24.3a0,0,0,0,1,0,0v1.63a6.41,6.41,0,0,1-6.41,6.41H371.83a6.41,6.41,0,0,1-6.41-6.41V144.2A0,0,0,0,1,365.42,144.2Z" style="fill: rgb(255, 255, 255); stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 377.57px 148.22px;" id="eljes997g7mma" class="animable"></path>
                    <line x1="411.91" y1="85.98" x2="425.69" y2="85.98" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 418.8px 85.98px;" id="elr1rok2hicp" class="animable"></line>
                    <line x1="328.17" y1="85.98" x2="342.12" y2="85.98" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 335.145px 85.98px;" id="ele8b3oxtl005" class="animable"></line>
                    <line x1="376.93" y1="51.04" x2="376.93" y2="37.23" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 376.93px 44.135px;" id="eln43cw8cd07g" class="animable"></line>
                    <line x1="401.9" y1="61.01" x2="411.41" y2="51.51" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 406.655px 56.26px;" id="el92am1zkw28h" class="animable"></line>
                    <line x1="342.45" y1="120.46" x2="352.1" y2="110.81" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 347.275px 115.635px;" id="el1c68lo5ku02" class="animable"></line>
                    <line x1="351.69" y1="60.74" x2="342.45" y2="51.51" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 347.07px 56.125px;" id="elwrmi0m6p9gn" class="animable"></line>
                    <line x1="411.41" y1="120.46" x2="402.21" y2="111.27" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 406.81px 115.865px;" id="elp0bc2utxgle" class="animable"></line>
                  </g>
                  <g id="freepik--icon-2--inject-187" class="animable" style="transform-origin: 253.33px 94.715px;">
                    <rect x="204" y="36.48" width="98.66" height="116.47" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 253.33px 94.715px;" id="el7s4bcgrsu7d" class="animable"></rect>
                    <path d="M283.48,85.83a30.07,30.07,0,1,0-53.17,19.23A29.54,29.54,0,0,1,237.16,124v4.12h32.5V124a29.47,29.47,0,0,1,6.84-18.9A29.92,29.92,0,0,0,283.48,85.83Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 253.41px 91.9296px;" id="eliqz7u5x4gp" class="animable"></path>
                    <rect x="237.16" y="128.11" width="32.51" height="8.04" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 253.415px 132.13px;" id="eli3pg9aoj2wi" class="animable"></rect>
                    <path d="M237.16,136.17h32.51a0,0,0,0,1,0,0v3.32a4.73,4.73,0,0,1-4.73,4.73H241.88a4.73,4.73,0,0,1-4.73-4.73v-3.32A0,0,0,0,1,237.16,136.17Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 253.41px 140.195px;" id="elwcvfi7ystgs" class="animable"></path>
                    <path d="M241.26,144.2h24.3a0,0,0,0,1,0,0v1.63a6.41,6.41,0,0,1-6.41,6.41H247.67a6.41,6.41,0,0,1-6.41-6.41V144.2A0,0,0,0,1,241.26,144.2Z" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 253.41px 148.22px;" id="ely8tonz1hb8" class="animable"></path>
                    <line x1="218.5" y1="85.98" x2="288.29" y2="85.98" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 253.395px 85.98px;" id="el7j7wolpi90a" class="animable"></line>
                    <line x1="253.31" y1="134.74" x2="253.31" y2="51.04" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 253.31px 92.89px;" id="eldbz7ilxaflp" class="animable"></line>
                    <line x1="228.48" y1="110.81" x2="278.28" y2="61.01" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 253.38px 85.91px;" id="elubtqi0wpfg" class="animable"></line>
                    <line x1="278.59" y1="111.27" x2="228.06" y2="60.74" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 253.325px 86.005px;" id="ele66fq84p57p" class="animable"></line>
                    <circle cx="253.4" cy="85.93" r="49.2" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 253.4px 85.93px;" id="elgxj94scraj9" class="animable"></circle>
                    <circle cx="253.4" cy="85.93" r="34.9" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 253.4px 85.93px;" id="elxllwwpphv4r" class="animable"></circle>
                    <line x1="287.75" y1="85.98" x2="301.52" y2="85.98" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 294.635px 85.98px;" id="elwrtphet1y4h" class="animable"></line>
                    <line x1="204.01" y1="85.98" x2="217.96" y2="85.98" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 210.985px 85.98px;" id="elxwv1d0r6o1e" class="animable"></line>
                    <line x1="252.76" y1="51.04" x2="252.76" y2="37.23" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 252.76px 44.135px;" id="ella7ep347zr" class="animable"></line>
                    <line x1="277.73" y1="61.01" x2="287.24" y2="51.51" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 282.485px 56.26px;" id="elf37n1emsa0m" class="animable"></line>
                    <line x1="218.29" y1="120.46" x2="227.94" y2="110.81" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 223.115px 115.635px;" id="elpnhicef2v6l" class="animable"></line>
                    <line x1="227.52" y1="60.74" x2="218.29" y2="51.51" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 222.905px 56.125px;" id="elok0vozhw84m" class="animable"></line>
                    <line x1="287.24" y1="120.46" x2="278.05" y2="111.27" style="fill: none; stroke: rgb(38, 50, 56); stroke-linecap: round; stroke-linejoin: round; stroke-width: 3px; transform-origin: 282.645px 115.865px;" id="elthgr4qa4dk" class="animable"></line>
                  </g>
                  <g id="freepik--icon-1--inject-187" class="animable" style="transform-origin: 129.735px 94.715px;">
                    <rect x="80.28" y="36.48" width="98.66" height="116.47" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 129.61px 94.715px;" id="elloojq21tya" class="animable"></rect>
                    <circle cx="129.99" cy="85.83" r="30.07" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 129.99px 85.83px;" id="eljvv4crxpapr" class="animable"></circle>
                    <rect x="113.74" y="90.72" width="32.51" height="37.38" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 129.995px 109.41px;" id="elvoknefui7sd" class="animable"></rect>
                    <rect x="113.74" y="128.11" width="32.51" height="8.04" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 129.995px 132.13px;" id="elb9rgyn71wfg" class="animable"></rect>
                    <rect x="113.74" y="136.17" width="32.51" height="8.04" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 129.995px 140.19px;" id="elws9marl0u1q" class="animable"></rect>
                    <rect x="117.84" y="144.2" width="24.3" height="8.04" style="fill: none; stroke: rgb(38, 50, 56); stroke-linejoin: round; transform-origin: 129.99px 148.22px;" id="elhi754smeje" class="animable"></rect>
                    <line x1="81.17" y1="86.04" x2="178.69" y2="86.04" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 129.93px 86.04px;" id="elq9c2gl5gqvc" class="animable"></line>
                    <line x1="129.81" y1="135.23" x2="129.81" y2="37.23" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 129.81px 86.23px;" id="el1bfbvw4bbzo" class="animable"></line>
                    <line x1="95.11" y1="120.72" x2="164.7" y2="51.17" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 129.905px 85.945px;" id="elbfk7618hdvm" class="animable"></line>
                    <line x1="164.48" y1="120.56" x2="94.98" y2="51.36" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 129.73px 85.96px;" id="elw9x8yqdkc7a" class="animable"></line>
                    <circle cx="129.99" cy="85.93" r="49.2" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 129.99px 85.93px;" id="el6bpjyvnlqd" class="animable"></circle>
                    <circle cx="129.99" cy="85.93" r="34.9" style="fill: none; stroke: rgb(146, 227, 169); stroke-linejoin: round; transform-origin: 129.99px 85.93px;" id="elu699u6preh" class="animable"></circle>
                  </g>
                  <defs>
                    <filter id="active" height="200%">
                      <feMorphology in="SourceAlpha" result="DILATED" operator="dilate" radius="2"></feMorphology>
                      <feFlood flood-color="#32DFEC" flood-opacity="1" result="PINK"></feFlood>
                      <feComposite in="PINK" in2="DILATED" operator="in" result="OUTLINE"></feComposite>
                      <feMerge>
                        <feMergeNode in="OUTLINE"></feMergeNode>
                        <feMergeNode in="SourceGraphic"></feMergeNode>
                      </feMerge>
                    </filter>
                    <filter id="hover" height="200%">
                      <feMorphology in="SourceAlpha" result="DILATED" operator="dilate" radius="2"></feMorphology>
                      <feFlood flood-color="#ff0000" flood-opacity="0.5" result="PINK"></feFlood>
                      <feComposite in="PINK" in2="DILATED" operator="in" result="OUTLINE"></feComposite>
                      <feMerge>
                        <feMergeNode in="OUTLINE"></feMergeNode>
                        <feMergeNode in="SourceGraphic"></feMergeNode>
                      </feMerge>
                      <feColorMatrix type="matrix" values="0   0   0   0   0                0   1   0   0   0                0   0   0   0   0                0   0   0   1   0 "></feColorMatrix>
                    </filter>
                  </defs>
                </svg>
              </p>
              <h2 class="display-5">🤩Share your feedback</h2>
              <p class="lead">We’re using Github Discussions as a place to connect with other members of our community. We hope that you:</p>
              <ul>
                <li>Ask questions you’re wondering about.</li>
                <li>Share ideas.</li>
                <li>Engage with other community members.</li>
                <li>Welcome others and are open-minded. Remember that this is a community we<br>
                  build together 💪.</li>
              </ul>
              <a target="_blank" href="https://github.com/magic-coding/thawani-pay-woocommerce/discussions" class="btn btn-dark my-2"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chat-square" viewBox="0 0 16 16">
                  <path d="M14 1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-2.5a2 2 0 0 0-1.6.8L8 14.333 6.1 11.8a2 2 0 0 0-1.6-.8H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2.5a1 1 0 0 1 .8.4l1.9 2.533a1 1 0 0 0 1.6 0l1.9-2.533a1 1 0 0 1 .8-.4H14a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z" />
                </svg> Discussions</a>

              <a target="_blank" href="https://github.com/magic-coding/thawani-pay-woocommerce/issues" class="btn btn-dark my-2"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bug" viewBox="0 0 16 16">
                  <path d="M4.355.522a.5.5 0 0 1 .623.333l.291.956A4.979 4.979 0 0 1 8 1c1.007 0 1.946.298 2.731.811l.29-.956a.5.5 0 1 1 .957.29l-.41 1.352A4.985 4.985 0 0 1 13 6h.5a.5.5 0 0 0 .5-.5V5a.5.5 0 0 1 1 0v.5A1.5 1.5 0 0 1 13.5 7H13v1h1.5a.5.5 0 0 1 0 1H13v1h.5a1.5 1.5 0 0 1 1.5 1.5v.5a.5.5 0 1 1-1 0v-.5a.5.5 0 0 0-.5-.5H13a5 5 0 0 1-10 0h-.5a.5.5 0 0 0-.5.5v.5a.5.5 0 1 1-1 0v-.5A1.5 1.5 0 0 1 2.5 10H3V9H1.5a.5.5 0 0 1 0-1H3V7h-.5A1.5 1.5 0 0 1 1 5.5V5a.5.5 0 0 1 1 0v.5a.5.5 0 0 0 .5.5H3c0-1.364.547-2.601 1.432-3.503l-.41-1.352a.5.5 0 0 1 .333-.623zM4 7v4a4 4 0 0 0 3.5 3.97V7H4zm4.5 0v7.97A4 4 0 0 0 12 11V7H8.5zM12 6a3.989 3.989 0 0 0-1.334-2.982A3.983 3.983 0 0 0 8 2a3.983 3.983 0 0 0-2.667 1.018A3.989 3.989 0 0 0 4 6h8z" />
                </svg> Report a bug</a>
            </div>
            <!-- <div class="bg-dark box-shadow mx-auto" style="width: 80%; height: 300px; border-radius: 21px 21px 0 0;"></div> -->
          </div>
        </div>
      </section>

    </div>
<?php
  }

  add_action('admin_head', 'thawani_welcome_remove_menus');

  function thawani_welcome_remove_menus()
  {
    remove_submenu_page('index.php', 'bas-sdk-v1');
  }
}
