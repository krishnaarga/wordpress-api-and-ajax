<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementorChild
 */

/**
 * Load child theme css and optional scripts
 *
 * @return void
 */


//  File Include for Mobile API
include('functions-mobile-api.php');

add_action('wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20);
function hello_elementor_child_enqueue_scripts(){
  wp_enqueue_style('hello-elementor-child-style', get_stylesheet_directory_uri() . '/style.css', ['hello-elementor-theme-style', ], '1.0.0');
}

add_filter ( 'woocommerce_account_menu_items', 'misha_remove_my_account_links' );
function misha_remove_my_account_links( $menu_links ){
    unset( $menu_links['downloads'] ); // Disable Downloads
    // unset( $menu_links['customer-logout'] ); // Disable Logout
    unset( $menu_links['subscriptions'] ); // Remove Account details tab
    return $menu_links;
}

/*
 * Step 1. Add Link (Tab) to My Account menu
 */
add_filter ( 'woocommerce_account_menu_items', 'log_history_link', 40 );
function log_history_link( $menu_links ){
  $menu_links = array_slice( $menu_links, 0, 5, true ) 
  + array( 'log-history' => 'Invoice' )
  + array_slice( $menu_links, 5, NULL, true );

  return $menu_links;
}

add_filter ( 'woocommerce_account_menu_items', 'subscription_link', 40 );
function subscription_link( $menu_links ){
    $menu_links = array_slice( $menu_links, 0, 5, true ) 
    + array( 'subscription' => 'Subscription' )
    + array_slice( $menu_links, 5, NULL, true );
    
    return $menu_links;
}


/*
 * Step 2. Register Permalink Endpoint
 */
add_action( 'init', 'log_history_add_endpoint' );
function log_history_add_endpoint() {
  // WP_Rewrite is my Achilles' heel, so please do not ask me for detailed explanation
  add_rewrite_endpoint( 'log-history', EP_PAGES );
}

add_action( 'init', 'subscription_add_endpoint' );
function subscription_add_endpoint() {
  // WP_Rewrite is my Achilles' heel, so please do not ask me for detailed explanation
  add_rewrite_endpoint( 'subscription', EP_PAGES );
}


/*
 * Step 3. Content for the new page in My Account, woocommerce_account_{ENDPOINT NAME}_endpoint
 */
add_action( 'woocommerce_account_log-history_endpoint', 'log_history_endpoint_content' );
function log_history_endpoint_content() {
  global $wpdb;

  require_once (get_stylesheet_directory() . '/chargeover_php/ChargeOverAPI.php');
  require_once (get_stylesheet_directory() . '/chargeover_php/config.php');

  $API = new ChargeOverAPI($url, $authmode, $username, $password);

  $current_user_datails = wp_get_current_user();
  $user_id = $current_user_datails->ID;

  $query_cc_id = "SELECT chargeover_customer_id FROM card_verify_for_chargeover WHERE user_id = $user_id ";
  $customer_id = $wpdb->get_results($query_cc_id);
  $customer_id = $customer_id[0]->chargeover_customer_id;


  // Find invoices belonging to a customer
  $resp = $API->find(ChargeOverAPI_Object::TYPE_INVOICE, array( 'customer_id:EQUALS:'.$customer_id ), array('invoice_id:DESC'));

  if (!$API->isError($resp)){
    $invoices = $resp->response;    
    echo '<link rel="stylesheet" href="'.get_stylesheet_directory_uri().'/assets/css/bootstrap.min.css">';
    echo "<div class='table-responsive'>";
    echo '<table  id="myAccountTable" class="display table table-striped table-hover table-sm">';
    echo'<thead class="thead-dark">
          <tr>
            <th>Sl. No.</th>
            <th>Invoice ID</th>
            <th>Invoice Date</th>
            <th>Due</th>
            <th>Total</th>
            <th>Status</th>
            <th>Download</th>
          </tr>
        </thead>';
    $count = 0;
    foreach ($invoices as $invoice){
      $download_invoice = download_invoice_chargeover($invoice->invoice_id);
      $download_invoice = json_decode($download_invoice);
      $download_invoice = $download_invoice->response->url_pdflink;
      $count++;
      echo'<tbody>
        <tr>
          <td class="align-middle">'.$count.'</td>
          <td class="align-middle">'.$invoice->invoice_id.'</td>
          <td class="align-middle">'.date('d-M-Y h:i:s A', strtotime($invoice->write_datetime)).'</td>
          <td class="align-middle">'.date('d-M-Y', strtotime($invoice->due_date)).'</td>
          <td class="align-middle">$'.$invoice->total.'</td>
          <td class="align-middle">'.$invoice->invoice_status_name.'</td>
          <td class="align-middle"><a href="'.$download_invoice.'" class="btn btn-success btn-sm" target="_block">Download</a></td>
        </tr>
      </tbody>';
    }
    echo '</table>';
    echo '</div>';
  }else{
      print('There was an error looking up the invoice!' . "\n");

      print('Error: ' . $API->lastError());
      print('Request: ' . $API->lastRequest());
      print('Response: ' . $API->lastResponse());
      print('Info: ' . print_r($API->lastInfo(), true));
  }
}

add_action( 'woocommerce_account_subscription_endpoint', 'subscription_endpoint_content' );
function subscription_endpoint_content() {
  global $wpdb;

  $dp_query = "SELECT id, plan_name, amount FROM dataplan ";
  $dp_result = $wpdb->get_results($dp_query);

  $dp = [];
  foreach($dp_result as $v){
      $dp[$v->id] = $v;
  }

  $current_user_datails = wp_get_current_user();
  $user_id = $current_user_datails->ID;

  $query = "SELECT DISTINCT `iccid`, `sim_type`, `user_id` FROM customer_data_plan WHERE user_id = $user_id ";
  $result = $wpdb->get_results($query);

  echo '<link rel="stylesheet" href="'.get_stylesheet_directory_uri().'/assets/css/bootstrap.min.css">';
  echo "<div class='table-responsive'>";
  echo '<table id="myAccountTable" class="display table table-striped table-hover table-sm">';
  echo '<thead class="thead-dark">
          <tr>
              <th>Sl. No.</th>
              <th>SIM Type</th>
              <th>ICCID</th>
              <th>View Status</th>
          </tr>
      </thead>';
  $count = 0;
  foreach($result as $value){
    $sim_type_text = '';
      switch ($value->sim_type){
        case 'trufreedom': $sim_type_text = 'TruFreedom'; break;
        case 'redfreedom': $sim_type_text = 'RedFreedom'; break;
        case 'blufreedom': $sim_type_text = 'BluFreedom'; break;
      }

      $count++;
      echo'<tbody>
            <tr>
              <td class="align-middle">'.$count.'</td>
              <td class="align-middle">'.$sim_type_text.'</td>
              <td class="align-middle">'.$value->iccid.'</td>
              
              <td class="align-middle">'.
                ($value->sim_type == 'trufreedom'?'<button type="button" id="'.$value->iccid.'" class="btn btn-primary btn-sm subscriptions" data-toggle="modal">
                  view <span class="subscriptions'.$value->iccid.'"></span>
                </button>':'').'
                <a href="'.get_site_url().'/shop/data-plans/" class="btn btn-warning btn-sm text-white">Add More Data</a>
                <button type="button" rel="tooltip" class="btn btn-success btn-sm check_uses_data" data-iccid="'.$device->iccid.'" title="edit">
                  View Data
                </button>
              </td>
            </tr>
          </tbody>';
  }
  echo '</table>';
  echo '</div>';

?>
  <!-- Modal -->
  <div class="modal fade" id="subModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="subModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="py-3 rounded text-center text-white" style="background-color:#1F2E5F;">
          <h5 class="modal-title" id="subModalLabel">Plan Details</h5>
        </div>
        <div class="modal-body pb-0 subscription_sim_details" id="subscription_sim_details"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
<?php
}
/*
 * Step 4
 */
// Go to Settings > Permalinks and just push "Save Changes" button.


// after login start
add_action('wp_login', 'after_login', 10, 2);
function after_login($user_login, $user) {
  $after_login = $_COOKIE['after_login'];
  if(isset($after_login)){
    setcookie('after_login', '', time(), "/");
    wp_safe_redirect( $after_login );
    exit();
  }
}
// after login end


// Converting Start
function convert_bytes_to_gb($size){
  $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
  $power = $size > 0 ? floor(log($size, 1024)) : 0;
  return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}

function convert_bytes_to_mb($bytes){ 
  return $bytes / (1024*1024); 
}

function convert_data_mb_to_percentage($total_data, $consume_data){
  return $consume_data/$total_data*100;
}
// Converting End

// Error Log start
function sim_error_log($error_type, $error_message='', $response=''){
  global $wpdb;
  $error_query = "INSERT INTO `sim_error_log`(`error_type`, `error_message`, `response`) VALUES ('$error_type', '$error_message', '$response')";
  $wpdb->query($error_query);
}
// Error Log End



// Custom API Create Start
// add_action('rest_api_init', function () {
//   register_rest_route( 'custom-rest-api', 'chargeover-webhook',array(
//     'methods'  => 'POST',
//     'callback' => 'chargeover_webhook_method'
//   ));
// });

// function chargeover_webhook_method($request){

function chargeover_webhook_method(){
  $response = '{
    "context_str": "transaction",
    "context_id": "7",
    "event": "insert",
    "data": {
        "transaction": {
            "transaction_id": 7,
            "gateway_id": 201,
            "currency_id": 1,
            "external_key": null,
            "token": "k4ufy9i769vt",
            "transaction_date": "2017-06-08",
            "gateway_status": 1,
            "gateway_transid": "*CHARGE: Test Credit Card* [1496950958]",
            "gateway_msg": "",
            "gateway_err_code": 0,
            "gateway_err_detail": null,
            "gateway_method": "visa",
            "amount": 60.95,
            "fee": 0,
            "transaction_type": "pay",
            "transaction_method": "Visa",
            "transaction_detail": "x4444",
            "transaction_datetime": "2017-06-08 15:42:38",
            "transaction_ipaddr": "127.0.0.1",
            "void_datetime": null,
            "transaction_status_name": "Success",
            "transaction_status_str": "ok-successful",
            "transaction_status_state": "o",
            "transaction_type_name": "Payment",
            "applied": 60.95,
            "currency_symbol": "$",
            "currency_iso4217": "USD",
            "url_self": "http:\/\/dev.chargeover.com\/admin\/r\/transaction\/view\/7",
            "customer_id": 8,
            "unapplied": 0,
            "applied_to": [
                {
                    "invoice_id": 5007,
                    "applied": 10.95
                },
                {
                    "invoice_id": 5008,
                    "applied": 50
                }
            ]
        },
        "customer": {
            "superuser_id": 354,
            "external_key": null,
            "token": "2ygprd9569t4",
            "company": "John Does Company, LLC",
            "bill_addr1": "34 Address Street",
            "bill_addr2": null,
            "bill_addr3": null,
            "bill_city": "City",
            "bill_state": "State",
            "bill_postcode": null,
            "bill_country": "United States",
            "bill_notes": null,
            "ship_addr1": null,
            "ship_addr2": null,
            "ship_addr3": null,
            "ship_city": null,
            "ship_state": null,
            "ship_postcode": null,
            "ship_country": null,
            "ship_notes": null,
            "terms_id": 2,
            "class_id": null,
            "custom_1": null,
            "custom_2": null,
            "custom_3": null,
            "custom_4": null,
            "custom_5": null,
            "custom_6": null,
            "admin_id": 3,
            "campaign_id": null,
            "currency_id": 1,
            "language_id": 1,
            "brand_id": 1,
            "no_taxes": false,
            "no_dunning": false,
            "write_datetime": "2017-06-08 15:39:59",
            "write_ipaddr": "127.0.0.1",
            "mod_datetime": "2017-06-08 15:39:59",
            "mod_ipaddr": "127.0.0.1",
            "terms_name": "Net 30",
            "terms_days": 30,
            "paid": 60.95,
            "total": 60.95,
            "balance": 0,
            "url_paymethodlink": "http:\/\/dev.chargeover.com\/r\/paymethod\/i\/2ygprd9569t4",
            "url_self": "http:\/\/dev.chargeover.com\/admin\/r\/customer\/view\/8",
            "admin_name": "Karli Palmer",
            "admin_email": "karli@chargeover.com",
            "currency_symbol": "$",
            "currency_iso4217": "USD",
            "display_as": "John Does Company, LLC",
            "ship_block": "",
            "bill_block": "34 Address Street\nCity State\nUnited States",
            "superuser_name": "John Doe",
            "superuser_first_name": "John",
            "superuser_last_name": "Doe",
            "superuser_phone": "",
            "superuser_email": "john@example.com",
            "superuser_token": "4e7t9r5a03fv",
            "customer_id": 8,
            "invoice_delivery": "email",
            "dunning_delivery": "email",
            "customer_status_id": 1,
            "customer_status_name": "Current",
            "customer_status_str": "active-current",
            "customer_status_state": "a",
            "superuser_username": "john@example.com"
        },
        "user": {
            "user_id": 354,
            "external_key": null,
            "first_name": "John",
            "middle_name_glob": null,
            "last_name": "Doe",
            "name_suffix": null,
            "title": "",
            "email": "john@example.com",
            "token": "4e7t9r5a03fv",
            "phone": "",
            "user_type_id": 1,
            "write_datetime": "2017-06-08 15:39:59",
            "mod_datetime": "2017-06-08 15:39:59",
            "name": "John Doe",
            "display_as": "John Doe",
            "url_self": "http:\/\/dev.chargeover.com\/admin\/r\/contact\/view\/354",
            "user_type_name": "Billing",
            "username": "john@example.com",
            "customer_id": 8
        }
    },
    "security_token": "9aCm8BdvtVT3JzA2GKHFu1fMilwIDXRo"
  }';
}
// Custom API Create End




/*
********************************
*       TRUPHONE START         *
********************************
*/

// Access Token Generaate Start
function access_token_generate_truphone(){
  $truphone_url = "https://services.truphone.com/auth/realms/tru-staff/protocol/openid-connect/token";
  $truphone_client_id = CLIENT_ID_FOR_TRUPHONE;
  $truphone_secret = SECRET_FOR_TRUPHONE;

  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => $truphone_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_USERPWD => $truphone_client_id.":".$truphone_secret,
    CURLOPT_POSTFIELDS => "grant_type=client_credentials",
    CURLOPT_HTTPHEADER => array(
    "Accept: application/json",
    "Accept-Language: en_US"
    ),
  ));

  $result = curl_exec($curl);
  $response = json_decode($result, true);
  return $response['access_token'];
}
// Access Token Generaate End

// Product Details Start
function product_details($product_id){
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_TRUPHONE.'/v2/products/'.$product_id,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '.access_token_generate_truphone(),
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}
// Product Details End

// SIM Status Start
function sim_status($iccid){
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_TRUPHONE.'/v2/sims/'.$iccid,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Bearer '.access_token_generate_truphone(),
    ),
  ));

  $response = curl_exec($curl);
  $status = curl_getinfo($curl)['http_code'];
  curl_close($curl);
  return '{"status":"'.$status.'",'.substr($response, 1);
}
// SIM Status End

function sim_activation($product_id, $iccid, $amount, $user_email, $first_name, $last_name, $esim_psim){
  $datetimetz = date('Y-m-d').'T'.date('H:i:s').'Z';

  // if($esim_psim == 'ESIM'){
  //     $data = '{
  //       "operation_type": "ACTIVATION",
  //       "sim_type": "ESIM",
  //       "country": "GB",
  //       "product": {
  //         "id": "'.$product_id.'"
  //       },
  //       "subscriber": {
  //         "email": "'.$user_email.'",
  //         "first_name": "'.$first_name.'",
  //         "last_name": "'.$last_name.'",
  //         "country_of_residence": "US",
  //         "device": {
  //           "operating_system": "ios",
  //           "model": "iPhone",
  //           "id": "1234567"
  //         }
  //       }
  //     }';
  // }

  if($esim_psim == 'PSIM'){
    $data = '{
      "operation_type": "ACTIVATION",
      "sim_type": "PSIM",
      "country": "US",
      "product": {
        "id": "'.$product_id.'",
        "sold_price": "'.$amount.'",
        "sold_currency": "USD",
        "activation_date": "'.$datetimetz.'"
      },
      "subscriber": {
        "email": "'.$user_email.'",
        "first_name": "'.$first_name.'",
        "last_name": "'.$last_name.'",
        "country_of_residence": "US",
        "language": "en",
        "sim": {
          "iccid": "'.$iccid.'"
        }
      }
    }';
  }
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_TRUPHONE.'/v2/orders',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => array(
      'Authorization: Bearer '.access_token_generate_truphone(),
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);

  return $response;

  // for eSIM only
  // $response = json_decode($response);
  // $response = (array)$response;
  // return $response['id'];
}

// Order Details by id Start
function get_order_details($order_id){
   
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_TRUPHONE."/v2/orders/$order_id",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '.access_token_generate_truphone(),
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

// TOPUP start
function sim_topup($user_id, $amount_id, $product_id, $iccid, $amount, $esim_psim){
    global $wpdb;

    $datetimetz = date('Y-m-d').'T'.date('H:i:s').'Z';

    // if($esim_psim == 'ESIM'){
    //     $data = '{
    //         "operation_type": "TOPUP",
    //         "sim_type": "ESIM",
    //         "country": "GB",
    //         "product": {
    //           "id": "'.$product_id.'"
    //         },
    //         "subscriber": {
    //           "device": {
    //             "operating_system": "ios",
    //             "model": "iPhone",
    //             "id": "1234567"
    //           },
    //           "sim": {
    //             "iccid": '.$iccid.'
    //           }
    //         }
    //     }';
    // }

    if($esim_psim == 'PSIM'){
      $data = '{
          "operation_type": "TOPUP",
          "sim_type": "PSIM",
          "country": "US",
          "product": {
            "id": "'.$product_id.'",
            "sold_price": "'.$amount.'",
            "sold_currency": "USD",
            "activation_date": "'.$datetimetz.'"
          },
          "subscriber": {
            "sim": {
              "iccid": "'.$iccid.'"
            }
          }
      }';
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_TRUPHONE.'/v2/orders',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $data,
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '.access_token_generate_truphone(),
        'Content-Type: application/json'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    // echo $query = "INSERT INTO `topup`(`user_id`, `dataplan_id`, `product_id`, `iccid`, `topup_response`) VALUES ('$user_id', '$amount_id', '$product_id', '$iccid', '$response')";
    $query = "INSERT INTO `topup`(`user_id`, `dataplan_id`, `product_id`, `iccid`) VALUES ('$user_id', '$amount_id', '$product_id', '$iccid')";
    $result = $wpdb->query($query);

    return $response;
}
// TOPUP end

// subscriptions
add_action('wp_ajax_nopriv_subscriptions_plan', 'subscriptions_plan');
add_action('wp_ajax_subscriptions_plan', 'subscriptions_plan');
function subscriptions_plan(){
    global $wpdb;

    $subscriptions_id = $_POST['subscriptions_id'];
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_TRUPHONE.'/v2/subscriptions?iccid='.$subscriptions_id,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '.access_token_generate_truphone(),
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    $responses = json_decode($response);
    $subscriptions_data = [];
    $count = 0;
    foreach($responses->data as $response){
      $count++;
      $subscriptions_data[$count]['name'] = $response->name;
      $subscriptions_data[$count]['activation_date'] = date('d-M-Y h:i:s A', strtotime($response->activation_date));
      $subscriptions_data[$count]['expiry_date'] = date('d-M-Y h:i:s A', strtotime($response->expiry_date));
      $subscriptions_data[$count]['initial_balance'] = convert_bytes_to_gb($response->initial_balance);
      $subscriptions_data[$count]['current_balance'] = convert_bytes_to_gb($response->current_balance);
      $subscriptions_data[$count]['sold_product_price'] = $response->sold_product_price;
    }
    
    echo json_encode($subscriptions_data);
    exit;
}

function all_subscriptions_truphone(){
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_TRUPHONE.'/v2/subscriptions',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Bearer '.access_token_generate_truphone(),
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  return $response;
}

function subscription_using_iccid_truphone($iccid){

  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_TRUPHONE.'/v2/subscriptions?iccid='.$iccid,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Bearer '.access_token_generate_truphone(),
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  return $response;
}

/*
********************************
*       TRUPHONE END           *
********************************
*/



/*
********************************
*       CHARGEOVER START       *
********************************
*/

function chargover_api_key(){
  $username = USERNAME_FOR_CHARGEOVER;
  $password = PASSWORD_FOR_CHARGEOVER;

  return base64_encode($username.":".$password);
}

function create_customer_chargeover(String $first_name='', String $last_name='', String $display_name, String $email){
    $company = !empty($first_name)?$first_name.' '.$last_name: $display_name;

    $data = '{
        "company": "'.$company.'",
        "bill_addr1": "",
        "bill_addr2": "",
        "bill_city": "",
        "bill_state": "",
        "superuser_name": "",
        "superuser_phone": "",
        "superuser_email": "'.$email.'"
    }';

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/customer',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>$data,
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic '.chargover_api_key(),
        'Content-Type: application/json',
        'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

function add_credit_card_chargeover(Int $customer_id, Int $card_number, String $card_name, Int $expdate_year, Int $expdate_month){
    $data = '{
        "customer_id": "'.$customer_id.'",
        "number": "'.$card_number.'",
        "expdate_year": "'.$expdate_year.'",
        "expdate_month": "'.$expdate_month.'",
        "name": "'.$card_name.'"
    }';

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/creditcard',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>$data,
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic '.chargover_api_key(),
        'Content-Type: application/json',
        'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

function make_payment_chargeover(Int $customer_id, Int $credit_card_id, $amount){
    // $data = '{
    //     "customer_id": '.$customer_id.',
    //     "gateway_id": '.$credit_card_id.',
    //     "gateway_status": 1,
    //     "gateway_method": "credit",
    //     "amount": '.$amount.',
    //     "transaction_type": "pay",
    //     "transaction_detail": "'.$card_no_and_name.'"
    // }';

    $data = '{
      "customer_id": '.$customer_id.',
      "amount": '.$amount.',
      "paymethods": [
          {
              "creditcard_id": '.$credit_card_id.'
          }
      ]
  }';

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/transaction?action=pay',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>$data,
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic '.chargover_api_key(),
        'Content-Type: application/json',
        'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

function payment_void_chargeover($transaction_id){

  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/transaction/'.$transaction_id.'?action=void',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic '.chargover_api_key(),
      'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  return $response;
}

// get credit card details using credit_card_id
function credit_card_details_chargeover($credit_card_id){
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/creditcard?where=creditcard_id:EQUALS:'.$credit_card_id,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic '.chargover_api_key(),
        'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

// get All card using customer_id
function get_all_save_card_chargeover($customer_id){
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/creditcard?where=customer_id:EQUALS:'.$customer_id,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic '.chargover_api_key(),
        'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

function download_invoice_chargeover($invoice_number){
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/invoice/'.$invoice_number,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic '.chargover_api_key(),
      'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  return $response;
}

function create_subscription_chargeover($customer_id, $chargeover_product_id, $sim_type){
  $paycycle = '';
  switch ($sim_type) {
    case 'trufreedom':
      $paycycle = 'yrl';
      break;
    case 'redfreedom':
      $paycycle = 'mon';
      break;
    case 'blufreedom':
      $paycycle = 'mon';
      break;
  }

  $data = '{
    "customer_id": '.$customer_id.',
    "paycycle":"'.$paycycle.'",
    "line_items": [
      {
        "item_id": '.$chargeover_product_id.',
        "line_quantity": 1
      }
    ]
  }';

  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/package',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS =>$data,
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic '.chargover_api_key(),
      'Content-Type: application/json',
      'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  return $response;
}

function create_subscription_invoice_chargeover($subscription_id){
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/package/'.$subscription_id.'?action=invoice',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic '.chargover_api_key(),
      'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  return $response;
}

function invoice_make_apid_chargeover($customer_id, $amount, $invoice_id){
  $data = '{
      "customer_id": '.$customer_id.',
      "amount": '.$amount.',
      "applied_to": [
          {
              "invoice_id": '.$invoice_id.'
          }
      ]
  }';
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/transaction?action=pay',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS =>$data,
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic '.chargover_api_key(),
      'Content-Type: application/json',
      'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  return $response;
}

function subscriptions_list_chargeover(){
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/package',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic '.chargover_api_key(),
      'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  return $response;
}

function get_all_customer_chargeover(){
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => API_URL_FOR_CHARGEOVER.'/api/v3/customer',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic '.chargover_api_key(),
      'Cookie: coinstance_sid=0d8a7ed192c4cb6d2126fc362d08dd6ca019d0a2'
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  return $response;
}


/*
********************************
*       CHARGEOVER END         *
********************************
*/

/*
********************************
*     MAIL TEMPLATE START      *
********************************
*/

function mail_template($plan_name, $iccid, $data, $amount, $transaction_id, $payment_date, $country, $sim_type, $activation_type){

  $router_sim_text = '';
  
  if($sim_type == 'trufreedom'){
    if($activation_type == 'insidesim'){
      $router_sim_text = '<tr><th colspan="2" style="text-align: left;">Thank you for activating your Go4v6 device with TruFreedom! Your device should now be activated and ready for use! To access our knowledge base of commonly asked questions, please visit <a href="https://support.yoursinglepoint.com/wifi-in-motion-products">https://support.yoursinglepoint.com/wifi-in-motion-products</a>. Thanks again and please don’t hesitate to reach out to us if you have any questions. <a href="tel:1-866-959-9434">1-866-959-WIFI (9434)</a> or live chat with us at <a href="www.wifiinmotion.com">www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner. </th></tr>';
    }elseif($activation_type == 'onlysim'){
      $router_sim_text = '<tr><th colspan="2" style="text-align: left;">Thank you for activating your TruFreedom SIM! Your SIM should now be activated and ready for use! You will need to update the APN of your device with connectivity.io To access our knowledge base of commonly asked questions, please visit <a href="https://support.yoursinglepoint.com/wifi-in-motion-products">https://support.yoursinglepoint.com/wifi-in-motion-products</a>.Thanks again and please don’t hesitate to reach out to us if you have any questions. <a href="tel:1-866-959-9434">1-866-959-WIFI (9434)</a> or live chat with us at <a href="www.wifiinmotion.com">www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner. </th></tr>';
    }
  }

  // return '<div class="container" style="width: 750px; margin:auto; padding:50px;font-family: halyard-display, sans-serif;">
  //     <section style="background-color:#1F2E5F; padding: 8px;">
  //       <img src="'.get_site_url().'/wp-content/uploads/2020/12/Wifi-in-motion-12@2x.png" alt="Site Image" style="width: 150px;">
  //     </section>
  //     <section style="display: flex; justify-content: space-between;text-align: center; margin-top: 20px; padding: 0px 10px;">
  //       <div class="left-section">
  //         <h3 style="font-weight:700; margin-bottom: 4px;">Invoice</h3>
  //         <span style="color: grey;font-weight:700; font-size: 14px;">Your Invoice details</span>
  //       </div>
  //       <div class="center-section">
  //         <h3 style="font-weight:700; margin-bottom: 4px;">Plan Name</h3>
  //         <span style="color: grey;font-weight:700; font-size: 14px;">'.$plan_name.'</span>
  //       </div>
  //       <div class="right-section">
  //         <h3 style="font-weight:700; margin-bottom: 4px;">Payment Date</h3>
  //         <span  style="color: grey; font-weight:700; font-size: 14px;">'.$payment_date.'</span>
  //       </div>
  //     </section>
  //     <table style="width:100%; height:auto; background-color:#fff;text-align:center; padding:10px; background:#fafafa; margin-top: 30px;">
  //       <tbody>
  //         <tr style="color:#6c757d; font-size: 20px;">
  //           <td style="border-right: 1.5px dashed  #DCDCDC ;width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Transaction ID</td>
  //           <td style="border-right:1.5px dashed  #DCDCDC ;width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Duration</td>
  //           <td style="border-right:1.5px dashed  #DCDCDC ;width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Data</td>
  //           <td style="width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;border-right:1.5px dashed  #DCDCDC;">ICCID</td>
  //           <td style=" width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Country</td>
  //         </tr>
  //         <tr style="background-color:#fff; font-size:16px; color:#262626;">
  //           <td style="border-right:1.5px dashed  #DCDCDC ;width:20% ; font-weight:bold;background: #fafafa;">'.$transaction_id.'</td>
  //           <td style="border-right:1.5px dashed  #DCDCDC ;width:20%; font-weight:bold;background: #fafafa;">12 Months</td>
  //           <td style="border-right:1.5px dashed  #DCDCDC ;width:20%; font-weight:bold;background: #fafafa;">'.$data.'</td>
  //           <td style="width:20%; font-weight:bold;background: #fafafa;border-right:1.5px dashed #DCDCDC; padding: 10px;">'.$iccid.'</td>
  //           <td style="width:20%; font-weight:bold;background: #fafafa;">'.$country.'</td>
  //         </tr>
  //       </tbody>
  //     </table>
  //     <table style="width:100%; height:auto; background-color:#fff;padding:40px 20px; font-size:12px; border: 1px solid #ebebeb; border-top: 0;">
  //       <tbody>
  //         <tr style="padding:20px;color:#000;">
  //           <td style="font-weight: bolder;padding:5px 0px; font-size: 16px;">Price</td>
  //           <td style="text-align:right;padding:5px 0px;font-weight: bolder; font-size: 24px; color: #1F2E5F;">$'.$amount.'</td>
  //         </tr>
  //         <tr>
  //           <td colspan="2">
  //             <a href="'.get_site_url().'/my-account/subscription/"><strong>Go to Subscription Tab</strong></a><br><br>
  //           </td>
  //         </tr>
  //         '.$router_sim_text.'
  //         <tr>
  //           <td colspan="2" style="font-weight:bold; padding-top: 14px;">Thank you for Contact with us!</td>
  //         </tr>
  //       </tbody>
  //       <tfoot style="padding-top:20px;font-weight: bold;">
  //         <tr>
  //           <td style="padding-top:3px;">Need help? Contact us <span style="color:#c61932">1-866-959-WIFI (9434) </span></td>
  //         </tr>
  //       </tfoot>
  //     </table>
  // </div>';
}

function sim_activation_mail_template($plan_name, $iccid, $data, $amount, $transaction_id, $payment_date, $country, $trufreedom_notification='', $user_name, $duration_of_months){
  return '<div style="width: 700px; margin:auto; font-family: halyard-display, sans-serif; border: 1px solid #1F2E5F;">
    <header style="background-color:#1F2E5F; padding: 8px; text-align: center;">
      <img src="'.get_site_url().'/wp-content/uploads/2020/12/Wifi-in-motion-12@2x.png" alt="Site Image" style="width: 150px;">
    </header>

    <table style="width:100%; height:auto; border: 1px solid #efefef; background-color:#fff;text-align:center; padding:10px; background:#fafafa;">
      <tr>
        <td style="border-right: 1.5px dashed  #DCDCDC ;width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Invoice</td>
        <td style="border-right:1.5px dashed  #DCDCDC ;width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Plan Name</td>
        <td style="width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Payment Date</td>
      </tr>
      <tr>
        <td style="border-right: 1.5px dashed  #DCDCDC ;width:20%;font-size:14px;padding: 0px 0px 10px 0px;">
          <span style="color: grey; font-size: 14px;">'.$user_name.'</span>
        </td>
        <td style="border-right: 1.5px dashed  #DCDCDC ;width:20%;font-size:14px;padding: 0px 0px 10px 0px;">
          <span style="color: grey; font-size: 14px;">'.$plan_name.'</span>
        </td>
        <td style="width:20%;font-size:14px;padding: 0px 0px 10px 0px;">
          <span style="color: grey; font-size: 14px;">'.$payment_date.'</span>
        </td>
      </tr>
    </table>

    <table style="width:100%; height:auto; border: 1px solid #efefef; background-color:#fff;text-align:center; padding:10px; background:#fafafa; margin-top: 10px;">
      <tbody>
        <tr>
          <td style="border-right: 1.5px dashed  #DCDCDC ;width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Transaction ID</td>
          <td style="border-right:1.5px dashed  #DCDCDC ;width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Duration</td>
          <td style="border-right:1.5px dashed  #DCDCDC ;width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Data</td>
          <td style="width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;border-right:1.5px dashed  #DCDCDC;">ICCID</td>
          <td style=" width:20%;font-size:14px;font-weight:700;padding: 0px 0px 10px 0px;">Country</td>
        </tr>
        <tr style="background-color:#fff; font-size:16px; color:grey;">
          <td style="border-right:1.5px dashed  #DCDCDC ;width:20% ;background: #fafafa;">'.$transaction_id.'</td>
          <td style="border-right:1.5px dashed  #DCDCDC ;width:20%; background: #fafafa;">'.$duration_of_months.'</td>
          <td style="border-right:1.5px dashed  #DCDCDC ;width:20%; background: #fafafa;">'.$data.'</td>
          <td style="width:20%; background: #fafafa;border-right:1.5px dashed #DCDCDC; padding: 10px;">'.$iccid.'</td>
          <td style="  width:20%; background: #fafafa;">'.$country.'</td>
        </tr>
      </tbody>
    </table>

    <table style="width:100%; height:auto; background-color:#fff;padding:10px 20px; font-size:12px;">
      <tbody>
        <tr style="padding:20px;color:#000;">
          <td style="font-weight: bolder;padding:5px 0px; font-size: 16px;">Price</td>
          <td style="text-align:right;padding:5px 0px;font-weight: bolder; font-size: 24px; color: #ff3b01;">$'.$amount.'</td>
        </tr>
        <tr>
          <td colspan="2">
            <a style="color:#c61932" href="'.get_site_url().'/my-account/subscription/"><strong>Go to Subscription Tab</strong></a>
            <br><br>
          </td>
        </tr>
        '.$trufreedom_notification.'
      </tbody>
    </table>
    
    <table style="width:100%; height:auto; background-color:#fff;padding:20px; font-size:12px;">
      <tfoot style="padding-top:20px;font-weight: bold;">
        <tr><td style="padding-top:3px; color: #666464;">Need help? Contact us <a href="tel:1-866-959-9434" style="color:#c61932">1-866-959-WIFI (9434) </a></td></tr>
      </tfoot>
    </table>
  </div>';
}

function credit_card_expiry_mail_template($name, $card_type, $card_number){
  return '<div style="width: 700px; margin:auto; font-family: halyard-display, sans-serif; border: 1px solid #1F2E5F;">
    <header style="background-color:#1F2E5F; padding: 8px; text-align: center;">
      <img src="'.get_site_url().'/wp-content/uploads/2020/12/Wifi-in-motion-12@2x.png" alt="Site Image" style="width: 150px;">
    </header>
    <table style="width:100%; height:auto; border: 1px solid #efefef; background-color:#fff;padding:10px; background:#fafafa;">
      <tr><td style="font-weight:700; font-size: 20px; color:#1f2e5f;">Hello '.$name.'</td></tr>
      <tr><td style="font-size:14px;font-weight:700;color:rgb(199, 30, 30);">Your '.$card_type.' Card '.$card_number.' will be expired in 30 days.</td></tr>
    </table>
    
    <table style="width:100%; height:auto; background-color:#fff;padding:20px; font-size:12px;">
      <tfoot style="padding-top:20px;font-weight: bold;">
        <tr><td style="padding-top:3px; color: #666464;">Need help? Contact us <a href="tel:1-866-959-9434" style="color:#c61932">1-866-959-WIFI (9434) </a></td></tr>
      </tfoot>
    </table>
  </div>';
}

function subscriptions_overdue_mail_template($name){
  return '<div style="width: 700px; margin:auto; font-family: halyard-display, sans-serif; border: 1px solid #1F2E5F;">
    <header style="background-color:#1F2E5F; padding: 8px; text-align: center;">
      <img src="'.get_site_url().'/wp-content/uploads/2020/12/Wifi-in-motion-12@2x.png" alt="Site Image" style="width: 150px;">
    </header>
    <table style="width:100%; height:auto; border: 1px solid #efefef; background-color:#fff;padding:10px; background:#fafafa;">
      <tr><td style="font-weight:700; font-size: 20px; color:#1f2e5f;">Hello '.$name.'</td></tr>
      <tr><td style="font-size:14px;font-weight:700;color:rgb(199, 30, 30);">Your subscription plan will be expired in 30 days</td></tr>
      <tr><td><a href="'.get_site_url().'/shop/data-plans/" target="_blank" style="color:#c61932">'.get_site_url().'/shop/data-plans/</a></td></tr>
    </table>
    
    <table style="width:100%; height:auto; background-color:#fff;padding:20px; font-size:12px;">
      <tfoot style="padding-top:20px;font-weight: bold;">
        <tr><td style="padding-top:3px; color: #666464;">Need help? Contact us <a href="tel:1-866-959-9434" style="color:#c61932">1-866-959-WIFI (9434) </a></td></tr>
      </tfoot>
    </table>
  </div>';
}

function ninety_percent_uses_data_mail_template($name, $iccid){
  $date_time = date('d-m-Y h:i:s');
  return '<div style="width: 700px; margin:auto; font-family: halyard-display, sans-serif; border: 1px solid #1F2E5F;">
    <header style="background-color:#1F2E5F; padding: 8px; text-align: center;">
      <img src="'.get_site_url().'/wp-content/uploads/2020/12/Wifi-in-motion-12@2x.png" alt="Site Image" style="width: 150px;">
    </header>
    <table style="width:100%; height:auto; border: 1px solid #efefef; background-color:#fff;padding:10px; background:#fafafa;">
      <tr><td style="font-weight:700; font-size: 20px; color:#1f2e5f;">Hello '.$name.'</td></tr>
      <tr><td style="font-size:14px;font-weight:700;">ICCID: '.$iccid.'</td></tr>
      <tr>
        <td>
          <br><br><a style="color:#c61932" href="'.get_site_url().'/my-account/subscription/"><strong>Go to Subscription Tab</strong></a><br><br>
        </td>
      </tr>
      <tr>
        <td style="font-size:14px;"><br />
          Thank you for using our TruFreedom services! 
          You have reached 90% of your data allotment. 
          If you would like to add more data, simply go to <a href="'.get_site_url().'/shop/data-plans/" target="_blank" style="color:#c61932">'.get_site_url().'/shop/data-plans/</a> and select which amount of data you want to add. 
          Once your original plan fully depletes, it will then start to use your newly selected plan. 
          Have any questions? No problem! Either call us at <a href="tel:1-866-959-9434" style="color:#c61932">1-866-959-WIFI (9434)</a> or chat with us at <a href="'.get_site_url().'" target="_blank" style="color:#c61932">www.wifiinmotion.com</a>
        </td>
      </tr>
    </table>
    
    <table style="width:100%; height:auto; background-color:#fff;padding:20px; font-size:12px;">
      <tfoot style="padding-top:20px;font-weight: bold;">
        <tr><td style="padding-top:3px; color: #666464;">Need help? Contact us <a href="tel:1-866-959-9434" style="color:#c61932">1-866-959-WIFI (9434)</a></td></tr>
      </tfoot>
    </table>
  </div>';
}

function send_mail($to, $subject, $body){
	$headers = array('Content-Type: text/html; charset=UTF-8', 'From: WiFi In Motion <support@your1point.com>');
	wp_mail($to, $subject, $body, $headers);
}

/*
********************************
*      MAIL TEMPLATE END       *
********************************
*/


// Run cron in one time in a day for subscription payment reminder
function cron_woocommerce_cleanup_personal_data_7907d003() {
  ninety_percent_uses_data();
  send_mail_after_subscriptions_overdue();
  send_mail_for_credit_card_expiry();
}
add_action('woocommerce_cleanup_personal_data', 'cron_woocommerce_cleanup_personal_data_7907d003', 10, 0 );


// Cron Testing in one minute
// function cron_action_scheduler_run_queue_9c61084d( $arg0 ) {
//   send_mail('amitj@yopmail.com','Subscription Reminder', 'Hi Prabina your subscription plan will expire in ten days');
// }
// add_action( 'action_scheduler_run_queue', 'cron_action_scheduler_run_queue_9c61084d', 10, 1 );



function get_customer_email_for_payment_reminder($customer_id = ''){
  global $wpdb;
  if(!empty($customer_id)){
    $query = "SELECT `user_id` FROM `card_verify_for_chargeover` WHERE `chargeover_customer_id` = $customer_id";
    $result = $wpdb->get_results($query);
    return $result[0]->user_id;
  }
}

add_action('wp_ajax_nopriv_sim_activation_after_payment', 'sim_activation_after_payment');
add_action('wp_ajax_sim_activation_after_payment', 'sim_activation_after_payment');
function sim_activation_after_payment(){
    $product_id = $_COOKIE['product_id'];
    $sim_activation_id = $_COOKIE['sim_activation_id'];
    $esim_psim = $_COOKIE['esim_psim'];

    $iccid_from_order_details = get_order_details($sim_activation_id);

    $iccid_from_order_details = json_decode($iccid_from_order_details);
    $iccid_from_order_details = (array)$iccid_from_order_details;

    if($iccid_from_order_details['status'] == "FULFILLING"){
        $message = "Congratulations! Your payment was successful,
                    <div>
                        <p class='text-danger'>But your product is not activated! please click on the bellow button for activate your product</p>
                        <p><button type='button' id='sim_activation' class='btn btn-primary'>Activate</button></p>
                    </div>";
    }elseif($iccid_from_order_details['status'] == "COMPLETED"){
      $message = "Congratulations! <strong class='text-primary'>Your product is now activated!</strong> Please power on your device to confirm it is working as expected. If you have any issues or questions, please contact us <a href='tel:1-866-959-9434' style='color:#c61932'>1-866-959-WIFI (9434)</a> or email us at <a href='mailto:support@your1point.com' style='color:#c61932'>support@your1point.com</a>";
      $iccid_from_order = $iccid_from_order_details['output']->iccid;
      sleep(1);
      sim_topup($user_id, $amount_id, $product_id, $iccid_from_order, $esim_psim);

      setcookie('product_id', '', time(), "/");
      setcookie('sim_activation_id', '', time(), "/");
      setcookie('esim_psim', '', time(), "/");
    }

    echo json_encode(['status'=>'success', 'message'=>$message ]);
    exit;
}

function ninety_percent_uses_data(){
  global $wpdb;
  $all_subscriptions = all_subscriptions_truphone();
  $all_subscriptions = json_decode($all_subscriptions);

  $current_user_datails = wp_get_current_user();

  foreach($all_subscriptions->data as $response){
    if($response->status == 'ACTIVE'){
      if($response->spent_balance != 0){
        $total_data = convert_bytes_to_mb($response->initial_balance);
        $consume_data = convert_bytes_to_mb($response->spent_balance);
        $iccid = $response->sim_id;

        $query = "SELECT user_id FROM `customer_data_plan` WHERE `iccid` = '$iccid' ";
        $user_id = $wpdb->get_results($query);
        $user_id = $user_id[0]->user_id;

        if(!empty($user_id)){
          $user = get_user_by('id', $user_id);
          $user_email = $user->user_email;

          if(!empty($user_email)){    
            $user_name = $user->display_name;
            $left_data_in_percentage = convert_data_mb_to_percentage($total_data, $consume_data);

            if($left_data_in_percentage >= 90){
              $mail_template = ninety_percent_uses_data_mail_template($user_name, $iccid);
              $to = $user_email;
              $subject = '90% Used Data';
              $body = $mail_template;
              send_mail($to, $subject, $body);
            }
          }
        }
      }
    }
  }
}

function send_mail_after_subscriptions_overdue(){
  $subscriptions_list = subscriptions_list_chargeover();
  $subscriptions_list = json_decode($subscriptions_list);
  $subscriptions_list = $subscriptions_list->response;

  $current_date = new DateTime(date('Y-m-d'));
  $subject = 'Subscription Reminder';

  foreach($subscriptions_list as $response){
    $expiry_date = new DateTime(date('Y-m-d', strtotime($response->next_invoice_datetime)));

    $difference_date = $current_date->diff($expiry_date);
    $left_date = $difference_date->format("%a");

    $user_id = get_customer_email_for_payment_reminder($response->customer_id);
    if(!empty($user_id)){
      $user = get_user_by('id', $user_id);

      $user_name = $user->display_name;
      $user_email = $user->user_email;

      if($left_date == 30){
        $mail_template = subscriptions_overdue_mail_template($user_name);
        $body = $mail_template;
        send_mail($user_email, $subject, $body);
      }
    }
  }
}

function send_mail_for_credit_card_expiry(){
  global $wpdb;

  $get_all_customer = get_all_customer_chargeover();
  $get_all_customer = json_decode($get_all_customer);
  $get_all_customer = $get_all_customer->response;

  $current_date = new DateTime(date('d-m-Y'));
  foreach($get_all_customer as $customer_detail){
    $customer_id = $customer_detail->customer_id;

    $card_details = get_all_save_card_chargeover($customer_id);
    $card_details = json_decode($card_details);
    foreach($card_details->response as $card){
      $expiry_date = $card->expdate_month.'-'.$card->expdate_year;
      $expiry_date = new DateTime(date('d-m-Y', strtotime("-30 day", strtotime('01-'.$expiry_date))));

      $difference_date = $current_date->diff($expiry_date);
      $left_date = $difference_date->format("%a");

      if($left_date == 0){
        // $name = $card->name; // Credit Card Name
        $card_number = $card->mask_number;
        $card_type = $card->type_name;
        $customer_id = $card->customer_id;

        $query = "SELECT `user_id` FROM `card_verify_for_chargeover` WHERE `chargeover_customer_id` = $customer_id";
        $user_id = $wpdb->get_results($query);
        $user_id = $user_id[0]->user_id;

        $user = get_user_by('id', $user_id);
        if(!empty($user)){
          $name = $user->display_name;
          $email = $user->user_email;

          $to = $email;
          $subject = 'Card Expiry Notification';
          $mail_template = credit_card_expiry_mail_template($name, $card_type, $card_number);
          send_mail($to, $subject, $mail_template);
        }
      }
    }
  }
}

/*
********************************
*       HUBSPOT START          *
********************************
*/
function hubspot_api_key(){
  // ?hapikey=---------- API KEY OF HUBSPOT
  return API_KEY_FOR_HUBSPOT;
}

// get hubspot owner id Start
function get_hubspot_owner_id(){
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_HUBSPOT.'/owners/v2/owners?hapikey='.hubspot_api_key(),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    $response = json_decode($response);

    $ownerId = [];
    foreach($response as $res){
        $ownerId[] = $res->ownerId;
    }

    return $ownerId;
}
// get hubspot owner id End

function add_hubspot_task($product_id = '', $iccid, $country = '', $plan_name, $amount, $payment_date, $transaction_id, $sim_type, $activation_type, $hubspot_contact_id, $sim_topup_or_not = ''){  
    // Get owner id from hubspot
    // If you want to add the task for frand owner then uncomment the below function "$get_hubspot_owner_id = get_hubspot_owner_id()" and comment this "$get_hubspot_owner_id = ['49804330', '49804530'];"
    // $get_hubspot_owner_id = get_hubspot_owner_id();
    $get_hubspot_owner_id = ['49804330', '49804530'];
    
    $ownerId = $get_hubspot_owner_id[rand(0, count($get_hubspot_owner_id) - 1)];
    $due_date = strtotime(date('Y-m-d H:i:s').'+2 day') * 1000;

    $body  = '';
    $body .= !empty($sim_topup_or_not)?'<b>SIM TOPUP Pending</b><br><br>':'';
    $body .= 'Plan Name = '.$plan_name.'<br>';
    $body .= 'ICCID = '.$iccid.'<br>';

    $duration_of_months = '';

    if($sim_type == 'trufreedom'){
      $sim_type = 'TruFreedom SIM Activation';
      // Get dataplane Detalis
      $product_details = product_details($product_id);
      $product_details = json_decode($product_details);
      $product_details = (array)$product_details;
      
      $mail_data = $product_details['data'].' '.$product_details['data_unit'];

      $body .= 'Country = '.$country.'<br>';
      $body .= sprintf("Duration = %s %s",$product_details['duration'], $product_details['duration_unit']).'<br>';
      $body .= 'Data = '.$mail_data.'<br>';

      $trufreedom_notification = '';
      if($activation_type == 'insidesim'){
        $trufreedom_notification = '<tr><th colspan="2" style="text-align: left;">Thank you for activating your Go4v6 device with TruFreedom! Your device should now be activated and ready for use! To access our knowledge base of commonly asked questions, please visit <a style="color:#c61932" href="https://support.yoursinglepoint.com/wifi-in-motion-products">https://support.yoursinglepoint.com/wifi-in-motion-products</a>. Thanks again and please don’t hesitate to reach out to us if you have any questions. <a style="color:#c61932" href="tel:1-866-959-9434">1-866-959-WIFI (9434)</a> or live chat with us at <a style="color:#c61932" href="www.wifiinmotion.com">www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner. </th></tr>';
      }elseif($activation_type == 'onlysim'){
        $trufreedom_notification = '<tr><th colspan="2" style="text-align: left;">Thank you for activating your TruFreedom SIM! Your SIM should now be activated and ready for use! You will need to update the APN of your device with connectivity.io To access our knowledge base of commonly asked questions, please visit <a style="color:#c61932" href="https://support.yoursinglepoint.com/wifi-in-motion-products">https://support.yoursinglepoint.com/wifi-in-motion-products</a>.Thanks again and please don’t hesitate to reach out to us if you have any questions. <a style="color:#c61932" href="tel:1-866-959-9434">1-866-959-WIFI (9434)</a> or live chat with us at <a style="color:#c61932" href="www.wifiinmotion.com">www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner.</th></tr>';
      }

      $duration_of_months = '1 Year';

    }elseif($sim_type == 'redfreedom' || $sim_type == 'blufreedom'){
      $sim_type = ($sim_type == 'redfreedom'?'RedFreedom':'BluFreedom').' SIM Activation';
      $trufreedom_notification = '';
      $mail_data = $plan_name.' GB';
      $body .= 'Data = '.$mail_data.'<br>';
      $duration_of_months = '1 Month';
    }
    $body .= 'Price = $'.$amount.'<br>';
    $body .= 'Payment Date = '.$payment_date.'<br>';
    $body .= 'Transaction ID = '.$transaction_id;

    $data = '{
        "engagement": {
            "active": true,
            "ownerId": '.$ownerId.',
            "type": "TASK",
            "timestamp": '.$due_date.'
        },
        "associations": {
            "contactIds": ['.$hubspot_contact_id.']
        },
        "metadata": {
            "body": "'.$body.'",
            "subject": "'.$sim_type.'",
            "status": "NOT_STARTED",
            "forObjectType": "CONTACT"
          }
    }';

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_HUBSPOT.'/engagements/v1/engagements?hapikey='.hubspot_api_key(),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>$data,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $current_user_datails = wp_get_current_user();
    $user_email = $current_user_datails->user_email;
    $user_name = $current_user_datails->display_name;
    
    $mail_template = sim_activation_mail_template($plan_name, $iccid, $mail_data, $amount, $transaction_id, $payment_date, $country, $trufreedom_notification, $user_name, $duration_of_months);
    
    $to = ['lorna@your1point.com', 'rob@your1point.com', 'Josephine@your1point.com', $user_email];
    $subject = $sim_type;
    $body = $mail_template;
    send_mail($to, $subject, $body);
}

function get_contact_by_email_hubspot(String $email){
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_HUBSPOT.'/contacts/v1/contact/email/'.$email.'/profile?hapikey='.hubspot_api_key(),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

function create_contact_hubspot(String $email, String $firstname = '', String $lastname = ''){
    $data = '{
      "properties": [
        {
          "property": "email",
          "value": "'.$email.'"
        },
        {
          "property": "firstname",
          "value": "'.$firstname.'"
        },
        {
          "property": "lastname",
          "value": "'.$lastname.'"
        }
      ]
    }';

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => API_URL_FOR_HUBSPOT.'/contacts/v1/contact/?hapikey='.hubspot_api_key(),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>$data,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}

/*
********************************
*       HUBSPOT END            *
********************************
*/

/* custom data plan */

// activation type
add_action('wp_ajax_nopriv_activation_type', 'activation_type');
add_action('wp_ajax_activation_type', 'activation_type');
function activation_type(){
  extract($_POST['activation_type']);

  setcookie('activation_type', $activation_type, time() + (86400 * 30), "/");
  setcookie('sim_type', $sim_type, time() + (86400 * 30), "/");
exit;
}

// ICCID
add_action('wp_ajax_nopriv_iccid', 'iccid');
add_action('wp_ajax_iccid', 'iccid');
function iccid(){
    global $wpdb;

    $iccid = $_POST['iccid'];
    $query = "SELECT ddns FROM customer_dataplan WHERE `iccid` = '$iccid' ";
    $result = $wpdb->get_results($query);
    $result = (array)$result[0];
    echo $result['ddns'];
    exit;
}

// DDNS
add_action('wp_ajax_nopriv_ddns', 'ddns');
add_action('wp_ajax_ddns', 'ddns');
function ddns(){
    global $wpdb;

    $ddns = $_POST['ddns'];
    $query = "SELECT * FROM customer_dataplan WHERE `ddns` = '$ddns' ";
    $result = $wpdb->get_results($query);

    echo json_encode(['status'=>'success', 'num_rows'=>$wpdb->num_rows, 'data'=>$result[0]]);
    exit;
}

add_action('wp_ajax_nopriv_get_save_card', 'get_save_card');
add_action('wp_ajax_get_save_card', 'get_save_card');
function get_save_card(){
    $customer_id = $_POST['customer_id'];

    $get_all_save_card = get_all_save_card_chargeover($customer_id);
    $get_all_save_card = json_decode($get_all_save_card);

    $get_all_save_card_response = [];
    $count = 0;
    foreach($get_all_save_card->response as $response){
      $get_all_save_card_response[$count]['creditcard_id'] = $response->creditcard_id;
      $get_all_save_card_response[$count]['name'] = $response->name;
      $get_all_save_card_response[$count]['card_number'] = $response->mask_number;
      $get_all_save_card_response[$count]['card_type'] = $response->type_name;
      $count++;
    }

    echo json_encode(['number_card'=>$count, 'card_list'=>$get_all_save_card_response]);
    exit;
}


// Account/Device Information
add_action('wp_ajax_nopriv_add_account', 'add_account');
add_action('wp_ajax_add_account', 'add_account');
function add_account(){
  global $wpdb;

  extract($_POST['accountInfo']);

  $activation_type = $_COOKIE['activation_type'];
  $sim_type = $_COOKIE['sim_type'];

  if(!empty($ddns_confirm)){
    $query = "INSERT INTO `customer_data_plan`(`sim_type`, `activation_type`, `ddns`, `mac`, `sn`, `iccid`) VALUES('$sim_type', '$activation_type', '$ddns', '$mac', '$sn', '$iccid')";
    setcookie('iccid', $iccid, time() + (86400 * 30), "/");
  }elseif(!empty($iccid_confirm)){
    
    switch ($activation_type) {
      case 'insidesim':
        $query = "SELECT NULL FROM customer_dataplan WHERE `iccid` = '$iccid_confirm' ";
        $result = $wpdb->get_results($query);
        if( $wpdb->num_rows == 1){
          $query = "INSERT INTO `customer_data_plan`(`sim_type`, `activation_type`, `iccid`) VALUES('$sim_type', '$activation_type', '$iccid_confirm')";
          setcookie('iccid', $iccid_confirm, time() + (86400 * 30), "/");
        }else{
          echo json_encode(['status'=>'error', 'message'=>'Please enter valid ICCID.']);
          exit;
        }
        break;
      case 'onlysim':
        $query = "INSERT INTO `customer_data_plan`(`sim_type`, `activation_type`, `iccid`) VALUES('$sim_type', '$activation_type', '$iccid_confirm')";
        setcookie('iccid', $iccid_confirm, time() + (86400 * 30), "/");
        break;
    }
  }

  $wpdb->query($query);
  setcookie('insert_last_id', $wpdb->insert_id, time() + (86400 * 1), "/");
  echo json_encode(['status'=>'success']);  

  exit;
}

/* Make Payment  */
/* Ajax function for add store data */
add_action('wp_ajax_nopriv_add_store', 'add_store');
add_action('wp_ajax_add_store', 'add_store');
function add_store(){
    global $wpdb;
    extract($_POST['cardInfo']);

    $path = get_stylesheet_directory();
    require_once (get_stylesheet_directory() . '/chargeover_php/ChargeOverAPI.php');
    require_once (get_stylesheet_directory() . '/chargeover_php/config.php');

    $API = new ChargeOverAPI($url, $authmode, $username, $password);

    $current_user_datails = wp_get_current_user();

    $user_id = $current_user_datails->ID;
    $user_name = $current_user_datails->user_login;
    $user_email = $current_user_datails->user_email;
    $user_nicename = $current_user_datails->user_nicename;
    
    // $customer_id = get_user_meta($user_id, 'customer_id');
    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);

    // Get Data Plan Details
    if(!empty($amount_id)){
      $query = "SELECT * FROM dataplan WHERE id = $amount_id ";
      $result = $wpdb->get_results($query);
      $amount = $result[0]->amount;
      $plan_name = $result[0]->plan_name;
      $country = $result[0]->country;
      $product_id = $result[0]->product_id;
      $chargeover_product_id = $result[0]->chargeover_product_id;
    }else{
      echo json_encode(['payment_status'=>'error', 'code'=>'404', 'message'=>'Data Plan is not set!']);
      exit;
    }

    $query_cc_id = "SELECT chargeover_customer_id FROM card_verify_for_chargeover WHERE user_id = $user_id ";
    $customer_id = $wpdb->get_results($query_cc_id);
    $customer_id = $customer_id[0]->chargeover_customer_id;

    if($payment_method == 'using_card'){

      if(empty($customer_id)){
        $querysuperuser_email = "SELECT chargeover_customer_id FROM card_verify_for_chargeover WHERE `chargeover_superuser_email` = '$user_email' ";
        $customer_id = $wpdb->get_results($querysuperuser_email);
        $customer_id = $customer_id[0]->chargeover_customer_id;
        $wpdb->update('card_verify_for_chargeover', array('user_id'=>$user_id), array('chargeover_superuser_email'=>$user_email));
      }

      // Create ChargeOver Customer if not exist.
      if (empty($customer_id)){
        $create_customer_chargeover = create_customer_chargeover($first_name, $last_name, $user_nicename, $user_email);
        $create_customer_chargeover = json_decode($create_customer_chargeover);
        if(!empty($create_customer_chargeover->response)){
          $customer_id = $create_customer_chargeover->response->id;
          $query = "INSERT INTO `card_verify_for_chargeover`(`user_id`, `chargeover_superuser_email`, `chargeover_customer_id`) VALUES ('$user_id', '$user_email', '$customer_id' )";
          $result = $wpdb->query($query);
        }else{
            echo json_encode(['payment_status'=>'error', 'code'=>'404', 'message'=>'Customer not created']);
            exit;
        }
      }

      // Add Credit Card using customer id
      if(!empty($customer_id)){
        $add_credit_card_chargeover = add_credit_card_chargeover($customer_id, $cardno, $cardholdername, $expyear, $expmonth);
        $add_credit_card_chargeover = json_decode($add_credit_card_chargeover);
        if(!empty($add_credit_card_chargeover->response)){
          $credit_card_id = $add_credit_card_chargeover->response->id;
          $card_no_for_success_page = substr($cardno, -4);
          $card_no_and_name = 'x'.$card_no_for_success_page." (".$cardholdername.")";
        }else{
            echo json_encode(['payment_status'=>'error', 'code'=>'404', 'message'=>'Invalid Card Number.']);
            exit;
        }
      }
    }elseif($payment_method == 'using_save_card'){
        $credit_card_id = $payment_using_save_card;

        // Validate Card using card id
        if(isset($credit_card_id) && !empty($credit_card_id)){
            $credit_card_details_chargeover = credit_card_details_chargeover($credit_card_id);
            $credit_card_details_chargeover = json_decode($credit_card_details_chargeover);
            if(isset($credit_card_details_chargeover->response) && !empty($credit_card_details_chargeover->response)){
              $cardholdername = $credit_card_details_chargeover->response[0]->name;
              $cardno = $credit_card_details_chargeover->response[0]->mask_number;
              $card_no_and_name = $cardno." (".$cardholdername.")";
            }else{
                echo json_encode(['payment_status'=>'error', 'code'=>'404', 'message'=>'Card ID not matched']);
                exit;
            }
        }else{
          echo json_encode(['payment_status'=>'error', 'code'=>'404', 'message'=>'Card ID not set.']);
          exit;
        }
    }

    // do Payment.
    if(isset($card_no_and_name) && !empty($card_no_and_name)){
      $make_payment_chargeover = make_payment_chargeover($customer_id, $credit_card_id, $amount, $card_no_and_name);
      $make_payment_chargeover = json_decode($make_payment_chargeover);
      if(isset($make_payment_chargeover->response->id) && !empty($make_payment_chargeover->response->id)){
        $transaction_id = $make_payment_chargeover->response->id;
        $payment_date = date("Y-m-d H:i:s");
      }else{
        echo json_encode(['payment_status'=>'error', 'code'=>'404', 'message'=>'Card is invalid']);
        exit;
      }
    }

    // After Payment Success.
    if(isset($transaction_id) && !empty($transaction_id)){
        $esim_psim = 'PSIM';
        $iccid = $_COOKIE['iccid'];
        $sim_type = $_COOKIE['sim_type'];
        $activation_type = $_COOKIE['activation_type'];
        $checkoutform_id = $_COOKIE['insert_last_id'];
        $hubspot_contact_id = '';
        $payment_status_array = [];

        $chargeover_subscription = create_subscription_chargeover($customer_id, $chargeover_product_id, $sim_type);
        $chargeover_subscription = json_decode($chargeover_subscription);
        $chargeover_subscription_id = $chargeover_subscription->response->id;

        $subscription_invoice = create_subscription_invoice_chargeover($chargeover_subscription_id);
        $subscription_invoice = json_decode($subscription_invoice);
        $subscription_invoice_id = $subscription_invoice->response->id;

        invoice_make_apid_chargeover($customer_id, $amount, $subscription_invoice_id);

        // insert Chargeover Subscription & paymente details in database.
        $wpdb->update('customer_data_plan', array('user_id'=>$user_id, 'dataplan_id'=>$amount_id, 'chargeover_subscription_id'=>$chargeover_subscription_id, 'card_holder_name'=>$cardholdername, 'card_number'=>$cardno, 'transaction_id'=>$transaction_id, 'payment_date'=>$payment_date), array('id'=>$checkoutform_id));

        
        // $payment_date = date('M-d-Y h:i:s A', strtotime($payment_date));
        $payment_date = date('M-d-Y', strtotime($payment_date));
        $cardno = !empty($card_no_for_success_page)?'x'.$card_no_for_success_page:$cardno;

        $get_contact_by_email = get_contact_by_email_hubspot($user_email);
        $get_contact_by_email = json_decode($get_contact_by_email);

        if(isset($get_contact_by_email->vid)){
          $hubspot_contact_id = $get_contact_by_email->vid;
        }

        if(isset($get_contact_by_email->status)){
          $create_contact_hubspot = create_contact_hubspot($user_email, $first_name, $last_name);
          $create_contact_hubspot = json_decode($create_contact_hubspot);
          $hubspot_contact_id = $create_contact_hubspot->vid;
        }

        if($sim_type == 'trufreedom'){
          // Check SIM status ACTIVATED or NOT
          $sim_status = sim_status($iccid);
          $sim_status = json_decode($sim_status);

          if($sim_status->status == 404){
            sim_error_log('SIM Status Error', 'SIM status 404 error');

            // Activat SIM
            $sim_activation = sim_activation($product_id, $iccid, $amount, $user_email, $first_name, $last_name, $esim_psim);
            
            $sim_activation_real_escape = $wpdb->_real_escape($sim_activation);
            $sim_acti_query = "INSERT INTO `sim_activation`(`user_id`, `dataplan_id`, `product_id`, `activation_response`) VALUES ('$user_id', '$amount_id', '$product_id', '$sim_activation_real_escape')";
            $wpdb->query($sim_acti_query);
            
            $sim_activation = json_decode($sim_activation);
            $sim_activation_message_real_escape = $wpdb->_real_escape($sim_activation->messages[0]);

            // SIM activation is getting error
            if(isset($sim_activation->type)){
              sim_error_log('SIM Activation Error', 'SIM is not activated after SIM status 404 error', $sim_activation_message_real_escape);
              $message = 'Congratulations! Your payment was successful, Thank you for activating your TruFreedom SIM! Your SIM should now be activated and ready for use! You will need to update the APN of your device with connectivity.io To access our knowledge base of commonly asked questions, please visit <a style="color:#c61932" href="https://support.yoursinglepoint.com/wifi-in-motion-products">https://support.yoursinglepoint.com/wifi-in-motion-products</a>.Thanks again and please don’t hesitate to reach out to us if you have any questions. <a style="color:#c61932" href="tel:1-866-959-9434">1-866-959-WIFI (9434)</a> or live chat with us at <a style="color:#c61932" href="www.wifiinmotion.com">www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner. please contact us at <a href="tel:1-866-959-9434" style="color:#c61932">1-866-959-WIFI (9434)</a> or email us at <a href="mailto:support@your1point.com" style="color:#c61932">support@your1point.com</a>';
              $payment_status_array = array_merge($payment_status_array, ['payment_status'=>'success', 'status'=>'error', 'code'=>'400', 'message'=>$message]);
              add_hubspot_task($product_id, $iccid, $country, $plan_name, $amount, $payment_date, $transaction_id, $sim_type, $activation_type, $hubspot_contact_id, 'pending');
            }elseif(isset($sim_activation->status) && $sim_activation->status == 'ACCEPTED'){
              $message = "Congratulations! Your payment was successful, Thank you for activating your TruFreedom SIM! Your SIM should now be activated and ready for use! You will need to update the APN of your device with connectivity.io To access our knowledge base of commonly asked questions, please visit <a href='https://support.yoursinglepoint.com/wifi-in-motion-products'>https://support.yoursinglepoint.com/wifi-in-motion-products</a>. Thanks again and please don’t hesitate to reach out to us if you have any questions. <a href='tel:1-866-959-9434'>1-866-959-WIFI (9434)</a> or live chat with us at <a href='www.wifiinmotion.com'>www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner or email us at <a href='mailto:support@your1point.com' style='color:#c61932'>support@your1point.com</a>";
              $payment_status_array = array_merge($payment_status_array, ['payment_status'=>'success', 'status'=>'success', 'code'=>'200', 'message'=>$message]);
              $wpdb->update('customer_dataplan', array('is_active'=>'1'), array('iccid'=>$iccid));
              add_hubspot_task($product_id, $iccid, $country, $plan_name, $amount, $payment_date, $transaction_id, $sim_type, $activation_type, $hubspot_contact_id);
            }else{
              sim_error_log('SIM Activation Error', 'SIM Activation status given insted of type or status', $sim_activation_message_real_escape);
              $message = 'Congratulations! Your payment was successful, Thank you for activating your TruFreedom SIM! Your SIM should now be activated and ready for use! You will need to update the APN of your device with connectivity.io To access our knowledge base of commonly asked questions, please visit <a style="color:#c61932" href="https://support.yoursinglepoint.com/wifi-in-motion-products">https://support.yoursinglepoint.com/wifi-in-motion-products</a>.Thanks again and please don’t hesitate to reach out to us if you have any questions. <a style="color:#c61932" href="tel:1-866-959-9434">1-866-959-WIFI (9434)</a> or live chat with us at <a style="color:#c61932" href="www.wifiinmotion.com">www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner. please contact us at <a href="tel:1-866-959-9434" style="color:#c61932">1-866-959-WIFI (9434)</a> or email us at <a href="mailto:support@your1point.com" style="color:#c61932">support@your1point.com</a>';
              $payment_status_array = array_merge($payment_status_array, ['payment_status'=>'success', 'status'=>'error', 'code'=>'400', 'message'=>$message]);
              add_hubspot_task($product_id, $iccid, $country, $plan_name, $amount, $payment_date, $transaction_id, $sim_type, $activation_type, $hubspot_contact_id, 'pending');
            }

          }elseif($sim_status->status == 200){
            $sim_topup = sim_topup($user_id, $amount_id, $product_id, $iccid, $amount, $esim_psim);
            $sim_topup = json_decode($sim_topup);
            $sim_topup_message_real_escape = $wpdb->_real_escape($sim_topup->messages[0]);

            if(isset($sim_topup->type)){
              sim_error_log('SIM TOPUP Error', 'SIM card TOPUP failed after SIM Status 200', $sim_topup_message_real_escape);
              $message = 'Congratulations! Your payment was successful, Thank you for activating your TruFreedom SIM! Your SIM should now be activated and ready for use! You will need to update the APN of your device with connectivity.io To access our knowledge base of commonly asked questions, please visit <a style="color:#c61932" href="https://support.yoursinglepoint.com/wifi-in-motion-products">https://support.yoursinglepoint.com/wifi-in-motion-products</a>.Thanks again and please don’t hesitate to reach out to us if you have any questions. <a style="color:#c61932" href="tel:1-866-959-9434">1-866-959-WIFI (9434)</a> or live chat with us at <a style="color:#c61932" href="www.wifiinmotion.com">www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner. please contact us at <a href="tel:1-866-959-9434" style="color:#c61932">1-866-959-WIFI (9434)</a> or email us at <a href="mailto:support@your1point.com" style="color:#c61932">support@your1point.com</a>';
              $payment_status_array = array_merge($payment_status_array, ['payment_status'=>'success', 'status'=>'error', 'code'=>'400', 'message'=>$message]);
              add_hubspot_task($product_id, $iccid, $country, $plan_name, $amount, $payment_date, $transaction_id, $sim_type, $activation_type, $hubspot_contact_id, 'pending');
            }elseif(isset($sim_topup->status) && $sim_topup->status == 'ACCEPTED'){
              $message = "Congratulations! Your payment was successful, Thank you for activating your TruFreedom SIM! Your SIM should now be activated and ready for use! You will need to update the APN of your device with connectivity.io To access our knowledge base of commonly asked questions, please visit <a href='https://support.yoursinglepoint.com/wifi-in-motion-products'>https://support.yoursinglepoint.com/wifi-in-motion-products</a>. Thanks again and please don’t hesitate to reach out to us if you have any questions. <a href='tel:1-866-959-9434'>1-866-959-WIFI (9434)</a> or live chat with us at <a href='www.wifiinmotion.com'>www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner or email us at <a href='mailto:support@your1point.com' style='color:#c61932'>support@your1point.com</a>";
              $payment_status_array = array_merge($payment_status_array, ['payment_status'=>'success', 'status'=>'success', 'code'=>'200', 'message'=>$message]);
              $wpdb->update('customer_dataplan', array('is_active'=>'1'), array('iccid'=>$iccid));
              add_hubspot_task($product_id, $iccid, $country, $plan_name, $amount, $payment_date, $transaction_id, $sim_type, $activation_type, $hubspot_contact_id);
            }else{
              sim_error_log('SIM TOPUP Error', 'SIM TOPUP status is not ACCEPTED', $sim_topup_message_real_escape);
              $message = 'Congratulations! Your payment was successful, Thank you for activating your TruFreedom SIM! Your SIM should now be activated and ready for use! You will need to update the APN of your device with connectivity.io To access our knowledge base of commonly asked questions, please visit <a style="color:#c61932" href="https://support.yoursinglepoint.com/wifi-in-motion-products">https://support.yoursinglepoint.com/wifi-in-motion-products</a>.Thanks again and please don’t hesitate to reach out to us if you have any questions. <a style="color:#c61932" href="tel:1-866-959-9434">1-866-959-WIFI (9434)</a> or live chat with us at <a style="color:#c61932" href="www.wifiinmotion.com">www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner. please contact us at <a href="tel:1-866-959-9434" style="color:#c61932">1-866-959-WIFI (9434)</a> or email us at <a href="mailto:support@your1point.com" style="color:#c61932">support@your1point.com</a>';
              $payment_status_array = array_merge($payment_status_array, ['payment_status'=>'success', 'status'=>'error', 'code'=>'400', 'message'=>$message]);
              add_hubspot_task($product_id, $iccid, $country, $plan_name, $amount, $payment_date, $transaction_id, $sim_type, $activation_type, $hubspot_contact_id, 'pending');
            }

          }else{
            sim_error_log('SIM Status Error', 'SIM Status is not 200 or 400');
            $message = 'Congratulations! Your payment was successful, Thank you for activating your TruFreedom SIM! Your SIM should now be activated and ready for use! You will need to update the APN of your device with connectivity.io To access our knowledge base of commonly asked questions, please visit <a style="color:#c61932" href="https://support.yoursinglepoint.com/wifi-in-motion-products">https://support.yoursinglepoint.com/wifi-in-motion-products</a>.Thanks again and please don’t hesitate to reach out to us if you have any questions. <a style="color:#c61932" href="tel:1-866-959-9434">1-866-959-WIFI (9434)</a> or live chat with us at <a style="color:#c61932" href="www.wifiinmotion.com">www.wifiinmotion.com</a> and click on the Live Chat link in the bottom right corner. please contact us at <a href="tel:1-866-959-9434" style="color:#c61932">1-866-959-WIFI (9434)</a> or email us at <a href="mailto:support@your1point.com" style="color:#c61932">support@your1point.com</a>';
            $payment_status_array = array_merge($payment_status_array, ['payment_status'=>'success', 'status'=>'error', 'code'=>'400', 'message'=>$message]);
            add_hubspot_task($product_id, $iccid, $country, $plan_name, $amount, $payment_date, $transaction_id, $sim_type, $activation_type, $hubspot_contact_id, 'pending');
          }

        }
        elseif($sim_type == 'redfreedom' || $sim_type == 'blufreedom'){
          add_hubspot_task($product_id, $iccid, $country, $plan_name, $amount, $payment_date, $transaction_id, $sim_type, $activation_type, $hubspot_contact_id);
          $message = "Payment was successful! We will send you an email as soon as your SIM card is activated.";
          $payment_status_array = array_merge($payment_status_array, ['payment_status'=>'success', 'status'=>'success', 'code'=>'200', 'message'=>$message]);
        }else{
          sim_error_log('SIM Type not matched', 'SIM is not TruFreedom, RedFreedom and BluFreedom', $sim_activation_message_real_escape);
          $message = "Payment was successful! but your SIM is not TruFreedom, RedFreedom and BluFreedom.";
          $payment_status_array = array_merge($payment_status_array, ['payment_status'=>'success', 'status'=>'error', 'code'=>'400', 'message'=>$message]);
        }

        $set_date = date('Y-m-d');

        echo json_encode(array_merge($payment_status_array, ['card_number'=>$cardno, 'transaction_id'=>$transaction_id, 'amount'=>$amount, 'plan_name'=>$plan_name, 'payment_date'=>$payment_date]));
        setcookie('insert_last_id', '', time(), "/");
        setcookie('sim_type', '', time(), "/");
        setcookie('iccid', '', time(), "/");
        setcookie('activation_type', '', time(), "/");
        exit;
    }else{
      echo json_encode(['payment_status'=>'error', 'status'=>'error', 'code'=>'404', 'message'=>'Payment not done']);
      exit;
    }
    die();
}



/*
####################################
#       ADMIN DASHBOARD PAGE       #
####################################
*/


// Total Device Count
add_action('wp_ajax_nopriv_count_all_and_active_inactive_device', 'count_all_and_active_inactive_device');
add_action('wp_ajax_count_all_and_active_inactive_device', 'count_all_and_active_inactive_device');
function count_all_and_active_inactive_device(){
  global $wpdb;

  $action_and_class = $_GET['action_and_class'];
  
  switch($action_and_class){
    case 'total_device_count':
      $query = "SELECT NULL FROM customer_dataplan";
      $wpdb->get_results($query);
      echo $wpdb->num_rows;
    break;
    case 'total_active_device_count':
      $query = "SELECT NULL FROM customer_dataplan WHERE `is_active` = 1 ";
      $wpdb->get_results($query);
      echo $wpdb->num_rows;
    break;
    case 'total_inactive_device_count':
      $query = "SELECT NULL FROM customer_dataplan WHERE `is_active` = 2 ";
      $wpdb->get_results($query);
      echo $wpdb->num_rows;
    break;
    case 'total_customer_count':
      $users = get_users($args);
      echo $number_of_users = count($users);
    break;

    default:
      echo '';
  }
  exit;
}


/*
################################
#       ADMIN LOGIN PAGE       #
################################
*/
add_action('wp_ajax_nopriv_custom_admin_login', 'custom_admin_login');
add_action('wp_ajax_custom_admin_login', 'custom_admin_login');
function custom_admin_login(){
  global $wpdb;

  foreach($_POST['custom_admin_login_data'] as $custom_admin_login) {
    $_POST['custom_admin_login_credentials'][$custom_admin_login['name']] = $custom_admin_login['value'];
  }
  
  extract($_POST['custom_admin_login_credentials']);

  if(empty($email)){
    $response = ['status'=>404, 'message'=>'Email can\'t be blank.', 'data'=>[]];
    echo json_encode($response);
    exit;
  }

  if(empty($password)){
    $response = ['status'=>404, 'message'=>'Password can\'t be blank.', 'data'=>[]];
    echo json_encode($response);
    exit;
  }

  if(!empty($email)){
    $user = get_user_by('email', $email);
    if(!empty($user)){
      $username = $user->user_login;
    }else{
      $response = ['status'=>404, 'message'=>'Please enter a valid email.', 'data'=>[]];
      echo json_encode($response);
      exit;
    }
  }else{
    $response = ['status'=>404, 'message'=>'Email can\'t be blank.', 'data'=>[]];
    echo json_encode($response);
    exit;
  }

  if(!empty($username)){
    $creds = array();
    $creds['user_login'] = $username;
    $creds['user_password'] = $password;
    $creds['remember'] = true;
    $user = wp_signon($creds, true);

    if(is_wp_error($user)){
      // $message = $user->get_error_message();
      $response = ['status'=>404, 'message'=>'You have entered the wrong email or password.', 'data'=>[]];
      echo json_encode($response);
      exit;
    }else{
      if($user->roles[0] == 'administrator'){
        // Basic Auth.
        $args = array(
          'headers' => array(
            'Authorization' => 'Basic '.base64_encode($username.':'.$password),
          ),
        );
        $user->Authorization = 'Basic '.base64_encode($username.':'.$password);

        $user_id = $user->ID;
        $response = ['status'=>200, 'message'=>'You are successfully logged in.', 'data'=>
          [
            'user_id'     => $user_id,
            'user_email'  =>$email
          ]
        ];
        echo json_encode($response);
        exit;
      }else{
        $response = ['status'=>404, 'message'=>'You are not an admin user.', 'data'=>[]];
        echo json_encode($response);
        exit;
      }
    }
  }
  exit;
}


/*
##################################
#     DEVICE MANAGEMENT PAGE     #
##################################
*/

// Create Device
add_action('wp_ajax_nopriv_add_device', 'add_device');
add_action('wp_ajax_add_device', 'add_device');
function add_device(){
  global $wpdb;

  foreach($_POST['device_data'] as $device) {
    $_POST['device'][$device['name']] = $device['value'];
  }

  extract($_POST['device']);

  if(empty($iccid)){
    echo json_encode(['status'=>false, 'message'=>"ICCID can't be blank."]);
    exit;
  }

  $query = $wpdb->prepare("INSERT INTO `customer_dataplan`(`ddns`, `mac`, `sn`, `iccid`, `pi_no`) VALUES ('$ddns', '$mac', '$sn', '$iccid', '$pi_no')");
  $response = $wpdb->query($query);
  if($response) echo json_encode(['status'=>true, 'message'=>'Device Information Added Successfully.']);
  else echo json_encode(['status'=>false, 'message'=>'Something Went Wrong.']);
  exit;
}

// Device Upload uisng CSV
add_action('wp_ajax_nopriv_csv_file_upload', 'csv_file_upload');
add_action('wp_ajax_csv_file_upload', 'csv_file_upload');
function csv_file_upload(){
  global $wpdb;

  extract($_FILES['csv_file']);

  if(!empty($name)){
    $file_extension = explode('.', $name);
    $file_extension = end($file_extension);
    if($file_extension != 'csv'){
      echo json_encode(['status'=>false, 'message'=>'Only CSV file accepted.']);
      exit;
    }
  }else{
    echo json_encode(['status'=>false, 'message'=>'Please select file.']);
    exit;
  }

  $get_csv_file = fopen($tmp_name, 'r');
  fgetcsv($get_csv_file, 1000, ',');

  $insert_value_data = '';
  while($data = fgetcsv($get_csv_file, 1000, ',')){
    $insert_value_data .= "('".$data[0]."', '".$data[1]."', '".$data[2]."', '".$data[3]."', '".$data[4]."'),";
  }

  $insert_value_data = rtrim($insert_value_data, ',');

  fclose($get_csv_file);
  
  $query = "INSERT INTO `customer_dataplan`(`ddns`, `mac`, `sn`, `iccid`, `pi_no`) VALUES $insert_value_data";
  $response = $wpdb->query($query);

  if($response) echo json_encode(['status'=>true, 'message'=>'Device Information Imported Successfully.']);
  else echo json_encode(['status'=>false, 'message'=>'Something Went Wrong.']);

  exit;
}

// Read Deveice 
add_action('wp_ajax_nopriv_all_device', 'all_device');
add_action('wp_ajax_all_device', 'all_device');
function all_device(){
  global $wpdb;

  $return_number_of_row = $_GET['return_number_of_row'];

  $return_number_of_row = empty($return_number_of_row)?
  $query = "SELECT * FROM customer_dataplan ORDER BY id DESC":
  $query = "SELECT * FROM customer_dataplan ORDER BY id DESC LIMIT $return_number_of_row";

  $all_device = $wpdb->get_results($query);
  $count = 0;
  foreach($all_device as $device){
    $count++;
    echo '<tr>
      <th>'.$count.'</th>
      <td>'.$device->ddns.'</td>
      <td>'.$device->mac.'</td>
      <td>'.$device->sn.'</td>
      <td>'.$device->iccid.'</td>
      <td>'.($device->is_active=='active'?'<span class="badge badge-success">ACTIVE</span>':'<span class="badge badge-secondary">IN-ACTIVE</span>').'</td>
      <td class="td-actions">
        <button type="button" rel="tooltip" class="btn btn-warning btn-sm iccid" id="'.$device->id.'" title="edit">Edit</button>
        <button type="button" rel="tooltip" class="btn btn-dark btn-sm check_uses_data_from_manage_device" data-iccid="'.$device->iccid.'" title="Check Uses Data">Check Uses Data</button>
        <button data-iccid="'.$device->iccid.'" class="btn btn-success btn-sm user_details_from_iccid">User Details</button>
      </td>
    </tr>';
  }
  exit;
}

// Update Device
add_action('wp_ajax_nopriv_update_device', 'update_device');
add_action('wp_ajax_update_device', 'update_device');
function update_device(){
  global $wpdb;

  foreach($_POST['device_data'] as $device) {
    $_POST['device'][$device['name']] = $device['value'];
  }

  extract($_POST['device']);

  if(empty($iccid)){
    echo json_encode(['status'=>false, 'message'=>"ICCID can't be blank."]);
    exit;
  }

  $response = $wpdb->update('customer_dataplan', array('ddns'=>$ddns, 'mac'=>$mac, 'sn'=>$sn, 'iccid'=>$iccid, 'pi_no'=>$pi_no), array('id'=>$update_id));

  if($response) echo json_encode(['status'=>true, 'message'=>'Device Information Updated Successfully.']);
  else echo json_encode(['status'=>false, 'message'=>'Device Information not Updated.']);

  exit;
}

// Get Device Details using ID
add_action('wp_ajax_nopriv_get_device_details_using_id', 'get_device_details_using_id');
add_action('wp_ajax_get_device_details_using_id', 'get_device_details_using_id');
function get_device_details_using_id(){
  global $wpdb;

  $id = $_POST['id'];
  $query = "SELECT * FROM customer_dataplan WHERE id = '$id' ";
  $device_details = $wpdb->get_results($query);
  echo json_encode($device_details);

  exit;
}

// Filter Device
add_action('wp_ajax_nopriv_filter_device_form', 'filter_device_form');
add_action('wp_ajax_filter_device_form', 'filter_device_form');
function filter_device_form(){
  global $wpdb;
  foreach($_POST['filter_device_data'] as $device) {
    $_POST['filter_device'][$device['name']] = $device['value'];
  }
  extract($_POST['filter_device']);
  
  $where = '';
  if(!empty($filter_ddns)){
    $where = "ddns = '".$filter_ddns."' ";
  }
  if(!empty($filter_iccid)){
    if(empty($where)) $where .= "iccid = '".$filter_iccid."' ";
    else $where .= "&& iccid = '".$filter_iccid."' ";
  }
  if(!empty($filter_sim_status)){
    if(empty($where)) $where .= "is_active = '".$filter_sim_status."'";
    else $where .= "&& is_active = '".$filter_sim_status."' ";
  }

  if(!empty($where)){
    $where = 'WHERE '.$where;
    $query = "SELECT * FROM customer_dataplan ".$where;
    $all_device = $wpdb->get_results($query);
    if($wpdb->num_rows > 0){
      $count = 0;
      foreach($all_device as $device){
        $count++;
        echo '<tr>
          <th>'.$count.'</th>
          <td>'.$device->ddns.'</td>
          <td>'.$device->mac.'</td>
          <td>'.$device->sn.'</td>
          <td>'.$device->iccid.'</td>
          <td>'.($device->is_active=='active'?'<span class="badge badge-success">ACTIVE</span>':'<span class="badge badge-secondary">IN-ACTIVE</span>').'</td>
          <td class="td-actions">
            <button type="button" rel="tooltip" class="btn btn-warning btn-sm iccid" id="'.$device->id.'" title="edit">Edit</button>
            <button type="button" rel="tooltip" class="btn btn-dark btn-sm check_uses_data_from_manage_device" data-iccid="'.$device->iccid.'" title="Check Uses Data">Check Uses Data</button>
            <button data-iccid="'.$device->iccid.'" class="btn btn-success btn-sm user_details_from_iccid">User Details</button>
          </td>
        </tr>';
      }
    }else{
      echo '<tr><td colspan="7"><h4 class="font-weight-bold">Details not matched</h4></td></tr>';
    }
  }else{
    echo '<tr><td colspan="7"><h4 class="font-weight-bold">Please fill any one field.</h4></td></tr>';
  }
  exit;
}

// Check Uses Data Using ICCID
add_action('wp_ajax_nopriv_check_uses_data_using_iccid', 'check_uses_data_using_iccid');
add_action('wp_ajax_check_uses_data_using_iccid', 'check_uses_data_using_iccid');
function check_uses_data_using_iccid(){

  $iccid = $_POST['iccid'];

  $subscription_using_iccid = subscription_using_iccid_truphone($iccid);
  $subscription_using_iccid = json_decode($subscription_using_iccid);
  
  $uses_data = [];
  if(!empty($subscription_using_iccid->data)){
    $response = $subscription_using_iccid->data[0];
    $uses_data['status'] = true;
    $uses_data['name'] = $response->name;
    $uses_data['activation_date'] = date('d-M-Y h:i:s A', strtotime($response->activation_date));
    $uses_data['expiry_date'] = date('d-M-Y h:i:s A', strtotime($response->expiry_date));
    $uses_data['initial_balance'] = convert_bytes_to_gb($response->initial_balance);
    $uses_data['current_balance'] = convert_bytes_to_gb($response->current_balance);
    $uses_data['sold_product_price'] = $response->sold_product_price;
    echo json_encode($uses_data);
  }else{
    $uses_data['status'] = false;
    echo json_encode($uses_data);
  }
  exit;
}


// User Details from ICCID
add_action('wp_ajax_nopriv_user_details_from_iccid', 'user_details_from_iccid');
add_action('wp_ajax_user_details_from_iccid', 'user_details_from_iccid');
function user_details_from_iccid(){
  global $wpdb;

  $iccid = $_POST['iccid'];

  $query = "SELECT user_id FROM customer_data_plan WHERE iccid = '$iccid' ";
  $all_device = $wpdb->get_results($query);

  if($wpdb->num_rows > 0){
    if($wpdb->num_rows == 1){
      $user_id = $all_device[0]->user_id;
      $user = get_user_by('id', $user_id);

      $name = $user->display_name;
      $email = $user->user_email;
      $mobile = '';
      echo json_encode(['status'=>true, 'message'=>"", 'data'=>['name'=>$name, 'email'=>$email, 'mobile'=>$mobile]]);
    }else{
      echo json_encode(['status'=>false, 'message'=>"SIM assigned for multiple users."]);
    }
  }else{
    echo json_encode(['status'=>false, 'message'=>"User Details not found for this ICCID."]);
  }
  
  exit;
}


// Download Devices
add_action('wp_ajax_nopriv_download_device_details', 'download_device_details');
add_action('wp_ajax_download_device_details', 'download_device_details');
function download_device_details(){
  global $wpdb;

  $query = "SELECT * FROM customer_dataplan";
  $all_device = $wpdb->get_results($query);

  $all_device_array = [];
  $count = 0;
  $all_device_array[]= ['Sl. No.', 'DDNS','MAC','SN','ICCID','PI. NO.','Is Active'];
  foreach($all_device as $d){
    $count++;
    $all_device_array[]= [$count, $d->ddns,$d->mac,$d->sn,$d->iccid,$d->pi_no,$d->is_active];

  }

  echo json_encode($all_device_array);
  exit;
}




/*
####################################
#     DATAPLAN MANAGEMENT PAGE     #
####################################
*/

// Create / Add Data Plan
add_action('wp_ajax_nopriv_add_dataplan', 'add_dataplan');
add_action('wp_ajax_add_dataplan', 'add_dataplan');
function add_dataplan(){
  global $wpdb;

  foreach($_POST['dataplan_data'] as $dataplan) {
    $_POST['dataplan'][$dataplan['name']] = $dataplan['value'];
  }
  extract($_POST['dataplan']);

  $is_validation_true = true;

  if(empty($sim_type)){
    $is_validation_true = false;
    echo json_encode(['status'=>false, 'message'=>"SIM Type can't be blank."]);
    exit;
  }
  if(empty($plan_name)){
    $is_validation_true = false;
    echo json_encode(['status'=>false, 'message'=>"Plan Name can't be blank."]);
    exit;
  }
  if(empty($amount)){
    $is_validation_true = false;
    echo json_encode(['status'=>false, 'message'=>"Amount can't be blank."]);
    exit;
  }
  
  if(empty($chargeover_product_id)){
    $is_validation_true = false;
    echo json_encode(['status'=>false, 'message'=>"Chargeover Product ID can't be blank."]);
    exit;
  }

  if($sim_type == 'trufreedom'){
    if(empty($country)){
      $is_validation_true = false;
      echo json_encode(['status'=>false, 'message'=>"Country can't be blank."]);
      exit;
    }
    if(empty($truphone_product_id)){
      $is_validation_true = false;
      echo json_encode(['status'=>false, 'message'=>"Country can't be blank."]);
      exit;
    }
  }


  if($is_validation_true){
    $query = $wpdb->prepare("INSERT INTO `dataplan`(`plan_name`, `amount`, `sim_type`, `country`, `product_id`, `chargeover_product_id`) VALUES ('$plan_name', '$amount', '$sim_type', '$country', '$truphone_product_id', '$chargeover_product_id')");
    $response = $wpdb->query($query);
    if($response) echo json_encode(['status'=>true, 'message'=>'Data Plan Information Added Successfully.']);
    else echo json_encode(['status'=>false, 'message'=>'Something Went Wrong.']);
  }else{
    echo json_encode(['status'=>false, 'message'=>'Data Plan not added.']);
  }
  exit;
}

// Read Dataplan 
add_action('wp_ajax_nopriv_all_dataplan', 'all_dataplan');
add_action('wp_ajax_all_dataplan', 'all_dataplan');
function all_dataplan(){
  global $wpdb;
  $return_number_of_row = $_GET['return_number_of_row'];

  $return_number_of_row = empty($return_number_of_row)
  ?$query = "SELECT * FROM dataplan ORDER BY sim_type"
  :$query = "SELECT * FROM dataplan ORDER BY sim_type ORDER BY id DESC LIMIT $return_number_of_row";

  $all_dataplan = $wpdb->get_results($query);
  
  $count = 0;
  foreach($all_dataplan as $dataplan){
    $count++;
    echo '<tr>
      <th>'.$count.'</th>
      <th class="text-left">'.$dataplan->plan_name.'</th>
      <td>$'.$dataplan->amount.'</td>
      <td>'.(ucfirst(str_replace('freedom', 'Freedom', $dataplan->sim_type))).'</td>
      <td>'.($dataplan->country=="USCANADA"?"USA CANADA":$dataplan->country).'</td>
      <td>
        <div class="togglebutton">
          <label>
            <input type="checkbox" class="dataplan_status" id="'.$dataplan->id.'" '.($dataplan->status == 'active'?'checked':'').'>
            <span class="toggle"></span>
            <span class="dataplan_status'.$dataplan->id.'">
              <span class="badge '.($dataplan->status == 'active'?'badge-success':'badge-danger').'">'.$dataplan->status.'</span>
            </span>
          </label>
        </div>
      </td>
      <td class="td-actions">
        <button type="button" class="btn btn-warning btn-link dataplan_id" id="'.$dataplan->id.'" title="edit">
          <i class="material-icons">edit</i>
        </button>
      </td>
    </tr>';
  }
  exit;
}

// Get Dataplan Details using ID
add_action('wp_ajax_nopriv_get_dataplan_details_using_id', 'get_dataplan_details_using_id');
add_action('wp_ajax_get_dataplan_details_using_id', 'get_dataplan_details_using_id');
function get_dataplan_details_using_id(){
  global $wpdb;

  $id = $_POST['id'];
  $query = "SELECT id, plan_name, amount, sim_type, country FROM dataplan WHERE id = '$id' ";
  $dataplan_details = $wpdb->get_results($query);
  echo json_encode($dataplan_details);

  exit;
}

// Update Dataplan
add_action('wp_ajax_nopriv_update_dataplan', 'update_dataplan');
add_action('wp_ajax_update_dataplan', 'update_dataplan');
function update_dataplan(){
  global $wpdb;
  foreach($_POST['dataplan_data'] as $dataplan) {
    $_POST['dataplan'][$dataplan['name']] = $dataplan['value'];
  }

  extract($_POST['dataplan']);

  $is_validation_true = true;

  if(empty($sim_type)){
    $is_validation_true = false;
    echo json_encode(['status'=>false, 'message'=>"SIM Type can't be blank."]);
    exit;
  }
  if(empty($plan_name)){
    $is_validation_true = false;
    echo json_encode(['status'=>false, 'message'=>"Plan Name can't be blank."]);
    exit;
  }
  if(empty($amount)){
    $is_validation_true = false;
    echo json_encode(['status'=>false, 'message'=>"Amount can't be blank."]);
    exit;
  }
  if($sim_type == 'trufreedom'){
    if(empty($country)){
      $is_validation_true = false;
      echo json_encode(['status'=>false, 'message'=>"Country can't be blank."]);
      exit;
    }
  }

  if($is_validation_true){
    $response = $wpdb->update('dataplan', array('plan_name'=>$plan_name, 'amount'=>$amount, 'sim_type'=>$sim_type, 'country'=>$country), array('id'=>$update_id));
    if($response) echo json_encode(['status'=>true, 'message'=>'Device Information Updated Successfully.']);
    else echo json_encode(['status'=>false, 'message'=>'Device Information not Updated!']);
  }else{
    echo json_encode(['status'=>false, 'message'=>'Device Information not Updated.']);
  }

  exit;
}


// Filter Data Plan 
add_action('wp_ajax_nopriv_filter_dataplan_form', 'filter_dataplan_form');
add_action('wp_ajax_filter_dataplan_form', 'filter_dataplan_form');
function filter_dataplan_form(){
  global $wpdb;
  foreach($_POST['filter_dataplan_data'] as $dataplan) {
    $_POST['filter_dataplan'][$dataplan['name']] = $dataplan['value'];
  }
  extract($_POST['filter_dataplan']);
  
  $where = '';
  // if(!empty($filter_ddns)){
  //   $where = "ddns = '".$filter_ddns."' ";
  // }

  if(!empty($filter_sim_type)){
    if(empty($where)) $where .= "sim_type = '".$filter_sim_type."'";
    else $where .= "&& sim_type = '".$filter_sim_type."' ";
  }

  if(!empty($where)){
    $where = 'WHERE '.$where;
    $query = "SELECT * FROM dataplan ".$where;
    $all_dataplan = $wpdb->get_results($query);
    if($wpdb->num_rows > 0){
      $count = 0;
      foreach($all_dataplan as $dataplan){
        $count++;
        echo '<tr>
          <th>'.$count.'</th>
          <th class="text-left">'.$dataplan->plan_name.'</th>
          <td>$'.$dataplan->amount.'</td>
          <td>'.(ucfirst(str_replace('freedom', 'Freedom', $dataplan->sim_type))).'</td>
          <td>'.($dataplan->country=="USCANADA"?"USA CANADA":$dataplan->country).'</td>
          <td>
            <div class="togglebutton">
              <label>
                <input type="checkbox" class="dataplan_status" id="'.$dataplan->id.'" '.($dataplan->status == 'active'?'checked':'').'>
                <span class="toggle"></span>
                <span class="dataplan_status'.$dataplan->id.'">
                  <span class="badge '.($dataplan->status == 'active'?'badge-success':'badge-danger').'">'.$dataplan->status.'</span>
                </span>
              </label>
            </div>
          </td>
          <td class="td-actions">
            <button type="button" rel="tooltip" class="btn btn-warning btn-link dataplan_id" id="'.$dataplan->id.'" title="edit">
              <i class="material-icons">edit</i>
            </button>
          </td>
        </tr>';
      }
    }else{
      echo '<tr><td colspan="6"><h4 class="font-weight-bold">Details not matched</h4></td></tr>';
    }
  }else{
    echo '<tr><td colspan="6"><h4 class="font-weight-bold">Please fill any one field.</h4></td></tr>';
  }
  exit;
}

add_action('wp_ajax_nopriv_dataplan_status_change', 'dataplan_status_change');
add_action('wp_ajax_dataplan_status_change', 'dataplan_status_change');
function dataplan_status_change(){
  global $wpdb;
  $id = $_POST['id'];
  $status = $_POST['status'];

  $wpdb->update('dataplan', array('status'=>$status), array('id'=>$id));

  exit;
}


// Download Data Plan
add_action('wp_ajax_nopriv_download_data_plan_details', 'download_data_plan_details');
add_action('wp_ajax_download_data_plan_details', 'download_data_plan_details');
function download_data_plan_details(){
  global $wpdb;

  $query = "SELECT * FROM dataplan";
  $all_dataplan = $wpdb->get_results($query);

  $all_dataplan_array = [];
  $count = 0;
  $all_dataplan_array[]= ['Sl. No.', 'Plan Name','Amount','Sim Type','Country','Truphone Product ID','Chargeover Product ID','Status'];
  foreach($all_dataplan as $d){
    $count++;
    $all_dataplan_array[]= [$count, $d->plan_name,$d->amount,$d->sim_type,$d->country,$d->product_id,$d->chargeover_product_id,$d->status ];

  }

  echo json_encode($all_dataplan_array);
  exit;
}






/*
#####################
#     SHORTCODE     #
#####################
*/

function ui_for_shortcode(Array $all_dataplan){
  $table_of_two = [0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20];
  $template = '';
  $count = 0;
  foreach($all_dataplan as $key => $dataplan){
    $data_in_gb = explode(' ', $dataplan->plan_name);
    $data_in_gb = end($data_in_gb);

    switch($dataplan->sim_type){
      case 'trufreedom' :
        $country = $dataplan->country=='USA'?'US ONLY':'US & CANADA';
        break;
      default:
        $up_to_mbps = 'Up to 25 Mbps speeds.';
    }

    if($key == $table_of_two[$count]){
      $template .= '<div class="custom-flex">';
      $template .= '<div class="custom-wdt mrr"><h2 style="font-size: 23px; font-weight: 900; color: #55595c; padding: 10px 0px 20px 0px; margin: 0px; font-family: \'monarcha\', sans-serif;">'.$dataplan->plan_name.'</h2><div><div>';

      $template .= (isset($country))?'<span style="padding: 0px; font-size: 1rem; color: #545454; font-weight: 400; font-family: \'halyard-display\', sans-serif;">'.$country.'</span>':'';
      $template .= '<div style="padding: 0px; font-size: 1rem; color: #545454; font-weight: 400; font-family: \'halyard-display\', sans-serif;">'.$data_in_gb.'GB | $'.$dataplan->amount.'</div>';
      $template .= (isset($up_to_mbps))?'<span style="padding: 0px; font-size: 1rem; color: #545454; font-weight: 400; font-family: \'halyard-display\', sans-serif;">'.$up_to_mbps.'</span>':'';

      $template .= '</div></div><div><a href="'.get_site_url().'/data-plan-checkout/?data_plan='.$dataplan->id.'" style="background: #1d1d1d; padding: 6px 20px; vertical-align: super; color: #fff; border: 0px; border-radius: 2px; font-size: 0.88rem; float: right;"><strong>Add Plan</strong></a></div></div>';
      
      if(count($all_dataplan)-1 == $key){
        $template .= '</div>';
      }
    }else{
      $template .= '<div class="custom-wdt mrl"><h2 style="font-size: 23px; font-weight: 900; color: #55595c; padding: 10px 0px 20px 0px; margin: 0px; font-family: \'monarcha\', sans-serif;">'.$dataplan->plan_name.'</h2><div><div>';
      
      $template .= (isset($country))?'<span style="padding: 0px; font-size: 1rem; color: #545454; font-weight: 400; font-family: \'halyard-display\', sans-serif;">'.$country.'</span>':'';
      $template .= '<div style="padding: 0px; font-size: 1rem; color: #545454; font-weight: 400; font-family: \'halyard-display\', sans-serif;">'.$data_in_gb.'GB | $'.$dataplan->amount.'</div>';
      $template .= (isset($up_to_mbps))?'<span style="padding: 0px; font-size: 1rem; color: #545454; font-weight: 400; font-family: \'halyard-display\', sans-serif;">'.$up_to_mbps.'</span>':'';

      $template .= '</div></div><div><a href="'.get_site_url().'/data-plan-checkout/?data_plan='.$dataplan->id.'" style="background: #1d1d1d; padding: 6px 20px; vertical-align: super; color: #fff; border: 2px; border-radius: 0px; font-size: 0.88rem; float: right;"><strong>Add Plan</strong></a></div></div></div>';
      $count++;
    }
  }
  return $template;
}

// TruFreedom USA
function dataplan_trufreedom_usa_shortcode(){
  global $wpdb;
  $query = "SELECT * FROM dataplan WHERE sim_type = 1 AND country = 1 AND status = 1 ORDER BY order_by";
  $all_dataplan = $wpdb->get_results($query);

  return ui_for_shortcode($all_dataplan);
}
add_shortcode('dataplan_trufreedom_usa_shortcode', 'dataplan_trufreedom_usa_shortcode');

// TruFreedom USA-CANADA
function dataplan_trufreedom_usa_canada_shortcode(){
  global $wpdb;
  $query = "SELECT * FROM dataplan WHERE sim_type = 1 AND country = 2 AND status = 1 ORDER BY order_by";
  $all_dataplan = $wpdb->get_results($query);
  
  return ui_for_shortcode($all_dataplan);
}
add_shortcode('dataplan_trufreedom_usa_canada_shortcode', 'dataplan_trufreedom_usa_canada_shortcode');

// RedFreedom
function dataplan_redfreedom_shortcode(){
  global $wpdb;
  $query = "SELECT * FROM dataplan WHERE sim_type = 2 AND status = 1";
  $all_dataplan = $wpdb->get_results($query);
  
  return ui_for_shortcode($all_dataplan);
}
add_shortcode('dataplan_redfreedom_shortcode', 'dataplan_redfreedom_shortcode');

// BluFreedom
function dataplan_blufreedom_shortcode(){
  global $wpdb;
  $query = "SELECT * FROM dataplan WHERE sim_type = 3 AND status = 1";
  $all_dataplan = $wpdb->get_results($query);
  
  return ui_for_shortcode($all_dataplan);
}
add_shortcode('dataplan_blufreedom_shortcode', 'dataplan_blufreedom_shortcode');