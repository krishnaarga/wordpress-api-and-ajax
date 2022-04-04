<?php

add_action('rest_api_init', function () {

  /*****************************
  *          DATABASE          *
  ******************************/

  // Testing API
  register_rest_route('mobile-api', 'testing', array(
    'methods'  => 'GET',
    'callback' => 'mobile_api_testing'
  ));

  // Login API Endpoint Create
  register_rest_route('mobile-api', 'login', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_login'
  ));

  // Create Customer API Endpoint
  register_rest_route('mobile-api', 'create-customer', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_create_customer'
  ));

  // Forgot Password Endpoint Create
  register_rest_route('mobile-api', 'forgot-password', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_forgot_password'
  ));
  // Resend OTP Endpoint Create
  register_rest_route('mobile-api', 'resend-otp', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_resend_otp'
  ));
  // Reset Password Endpoint Create
  register_rest_route('mobile-api', 'reset-password', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_reset_password'
  ));

  // SIM Type for DataPlan Endpoint Create
  register_rest_route('mobile-api', 'sim-type', array(
    'methods'  => 'GET',
    'callback' => 'mobile_api_sim_type'
  ));

  // TruFreedom SIM Type for DataPlan Endpoint Create
  register_rest_route('mobile-api', 'trufreedom-sim-type', array(
    'methods'  => 'GET',
    'callback' => 'mobile_api_trufreedom_sim_type'
  ));

  // DataPlan List Using SIM Type Endpoint Create
  register_rest_route('mobile-api', 'dataplan-using-sim-type', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_dataplan_using_sim_type'
  ));

 // dataplan details using id
  register_rest_route('mobile-api', 'dataplan-details-using-id', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_dataplan_details_using_id'
  ));

  // customer dataplan details device id
  register_rest_route('mobile-api', 'customer-dataplan-details-device-id', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_customer_dataplan_details_using_device_id'
  ));

 // verify device and iccid
  register_rest_route('mobile-api', 'verify-device-and-iccid', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_verify_device_or_iccid'
  ));

  // card verify for chargeover using id
  register_rest_route('mobile-api', 'card-verify-for-chargeover-using-id', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_card_verify_for_chargeover_using_user_id'
  ));

  // card verify for chargeover using email
  register_rest_route('mobile-api', 'card-verify-for-chargeover-using-email', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_card_verify_for_chargeover_using_user_email'
  ));
  
  // add chargeover customer id in database
  register_rest_route('mobile-api', 'add-chargeover-customer-id-in-database', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_add_chargeover_customer_id_in_database'
  ));

  // add customer data plan response in database
  register_rest_route('mobile-api', 'add-customer-data-plan-response-in-database', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_add_customer_data_plan_response_in_database'
  ));

  // add sim activation response in database
  register_rest_route('mobile-api', 'add-sim-activation-response-in-database', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_add_sim_activation_response_in_database'
 )); 
 // sim activated
  register_rest_route('mobile-api', 'sim-activated', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_sim_activated'
  ));




  // ShipHero Webhook EndPoint
  register_rest_route('shiphero', 'inventory-update', array(
    'methods'  => 'GET',
    'callback' => 'shiphero_inventory_update'
  ));
  
});


/*****************************
*          DATABASE          *
******************************/

// Testing API / Demo API
function mobile_api_testing(){
  echo 'hi';
}

// User Login Functionality
function mobile_api_login($request){
  $email = $request["email"];
  $username = $request["username"];
  $password = $request["password"];

  if(empty($username)){
    if(!empty($email)){
      $user = get_user_by('email', $email);
      if(!empty($user)){
        $username = $user->user_login;
      }else{
        $response = ['status'=>404, 'message'=>'Please enter a valid email.', 'data'=>[]];
        return $response;
      }
    }else{
      $response = ['status'=>404, 'message'=>'Username/email can\'t be blank.', 'data'=>[]];
      return $response;
    }
  }

  $creds = array();
  $creds['user_login'] = $username;
  $creds['user_password'] = $password;
  $creds['remember'] = true;
  $user = wp_signon($creds, false);

  if(is_wp_error($user)){
    // $message = $user->get_error_message();
    $response = ['status'=>404, 'message'=>'You have entered the wrong username/email or password.', 'data'=>[]];
    return $response;
  }else{
    // Basic Auth.
    $args = array(
			'headers' => array(
				'Authorization' => 'Basic '.base64_encode($username.':'.$password),
			),
		);
		$user->Authorization = 'Basic '.base64_encode($username.':'.$password);
    
    $user_id = $user->ID;
    $user_email = $user->user_email;
    $display_name = $user->display_name;
    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);
    $response = ['status'=>200, 'message'=>'You are successfully logged in.', 'data'=>
      [
        'user_id'     => $user_id,
        'user_email'  =>$user_email,
        'first_name'  =>$first_name,
        'last_name'   =>$last_name,
        'display_name'=>$display_name
      ]
    ];
    return $response;
  }
}

// Create Customer
function mobile_api_create_customer($request){

  // Woocommerce credential
  // $username = 'ck_c9e8f5c2d7c2d189bfd7d3d4522e26f87ab05b82';
  // $password = 'cs_7518f96634bbdb17deee7350f18415e8e43f31ee';


  $email = $request['email']; 
  $password = $request['password'];
  $first_name = $request['first_name']; 
  $last_name = $request['last_name']; 
  
  if(empty($email)){
    $response = ['message'=>'Email can\'t be blank', 'data'=>["status"=> 401]];
    return $response;
	}
  if(empty($password)){
    $response = ['message'=>'Password can\'t be blank', 'data'=>["status"=> 401]];
    return $response;
	}
  if(empty($first_name)){
    $response = ['message'=>'First name can\'t be blank', 'data'=>["status"=> 401]];
    return $response;
	}
  if(empty($last_name)){
    $response = ['message'=>'Last name can\'t be blank', 'data'=>["status"=> 401]];
    return $response;
	}

  $data = '{
    "email": "'.$email.'",
    "first_name": "'.$first_name.'",
    "last_name": "'.$last_name.'",
    "password": "'.$password.'"
  }';


  $curl = curl_init();
  
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://wifiinmotionst.wpengine.com/wp-json/wc/v3/customers',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS =>$data,
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic Y2tfYzllOGY1YzJkN2MyZDE4OWJmZDdkM2Q0NTIyZTI2Zjg3YWIwNWI4Mjpjc183NTE4Zjk2NjM0YmJkYjE3ZGVlZTczNTBmMTg0MTVlOGU0M2YzMWVl',
      'Content-Type: application/json',
      'Cookie: wpe-auth=b4caedc359d130db4927e95460540fff1952dd605b417be73580eb94b7279c2e'
    ),
  ));
  
  $response = curl_exec($curl);
  
  curl_close($curl);
  echo $response;
  exit;
}

// Forgot Password Functionality
function mobile_api_forgot_password($request){
  $email = $request['email'];

	if(empty($email)){
    $response = ['status'=>404, 'message'=>'Email can\'t be blank', 'data'=>[]];
    return $response;
	}

  $user = get_user_by('email', $email);

  if(empty($user)){
    $response = ['status'=>404, 'message'=>'Please enter a valid email', 'data'=>[]];
    return $response;
	}else{
    $user_id = $user->ID;
    $otp = rand(100000, 999999);
    update_user_meta($user_id, 'mobile_api_user_password_reset_otp', $otp);

    $subject = 'Wifiinmotion OTP';
    $body = '<h1>'.$otp.' is your Wifiinmotion OTP for reset password</h1>';
    send_mail($email, $subject, $body);

    $response = ['status'=>200, 'message'=>'OTP sent to your email', 'data'=>['otp'=>$otp]];
    return $response;
  }
}

// Resend OTP Functionality
function mobile_api_resend_otp($request){
  $email = $request['email'];

	if(empty($email)){
    $response = ['status'=>404, 'message'=>'Email can\'t be blank', 'data'=>[]];
    return $response;
	}

  $user = get_user_by('email', $email);

  if(empty($user)){
    $response = ['status'=>404, 'message'=>'Please enter a valid email', 'data'=>[]];
    return $response;
	}else{
    $user_id = $user->ID;
    $get_opt_from_user_meta = get_user_meta($user_id, 'mobile_api_user_password_reset_otp', true);

    if(!empty($get_opt_from_user_meta)){
      $subject = 'Wifiinmotion OTP';
      $body = '<h1>'.$get_opt_from_user_meta.' is your Wifiinmotion OTP for reset password</h1>';
      send_mail($email, $subject, $body);

      $response = ['status'=>200, 'message'=>'OTP sent to your email', 'data'=>['otp'=>$get_opt_from_user_meta]];
      return $response;
    }else{
      $otp = rand(100000, 999999);
      update_user_meta($user_id, 'mobile_api_user_password_reset_otp', $otp);

      $subject = 'Wifiinmotion OTP';
      $body = '<h1>'.$otp.' is your Wifiinmotion OTP for reset password</h1>';
      send_mail($email, $subject, $body);

      $response = ['status'=>200, 'message'=>'OTP sent to your email', 'data'=>['otp'=>$otp]];
      return $response;
    }
  }
}

// Reset Password Functionality
function mobile_api_reset_password($request){
  $otp = $request['otp'];
  $email = $request['email'];
	$password = $request['password'];
	$confirm_password = $request['confirm_password'];
  
  if(empty($email)){
    $response = ['status'=>404, 'message'=>'Email can\'t be blank.', 'data'=>[]];
    return $response;
  }elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    $response = ['status'=>404, 'message'=>'Invalid email format.', 'data'=>[]];
    return $response;
  }

  if(empty($password)){
    $response = ['status'=>404, 'message'=>'Password can\'t be blank.', 'data'=>[]];
    return $response;
  }
  if(empty($confirm_password)){
    $response = ['status'=>404, 'message'=>'Confirm password can\'t be blank.', 'data'=>[]];
    return $response;
  }
  if($password != $confirm_password){
    $response = ['status'=>404, 'message'=>'Your password and confirmation password do not match.', 'data'=>[]];
    return $response;
  }

  if(empty($otp)){
    $response = ['status'=>404, 'message'=>'OTP can\'t be blank.', 'data'=>[]];
    return $response;
  }elseif(strlen($otp) != 6){
    $response = ['status'=>404, 'message'=>'OTP length should be six characters.', 'data'=>[]];
    return $response;
  }

  $user = get_user_by('email', $email );
	if(!empty($user)){
    $user_id = $user->ID;
  }else{
    $response = ['status'=>404, 'message'=>'Email can\'t be blank.', 'data'=>[]];
    return $response;
  }

  if(!empty($user_id)){
    $get_opt_from_user_meta = get_user_meta($user_id, 'mobile_api_user_password_reset_otp', true);
    if($get_opt_from_user_meta == $otp){
      wp_set_password($password , $user_id);
      update_user_meta($user_id, 'mobile_api_user_password_reset_otp', '');
      $response = ['status'=>200, 'message'=>'Your password has been successfully changed.', 'data'=>[]];
      return $response;
    }else{
      $response = ['status'=>404, 'message'=>'Enter valid OTP', 'data'=>[]];
      return $response;
    }
  }

 
  

}

// SIM Category for DataPlan Functionality
function mobile_api_sim_type(){
  global $wpdb;

  $query = "SELECT DISTINCT sim_type FROM `dataplan` WHERE status = 1 ";
  $response = $wpdb->get_results($query);
 
  $sim_category_list = [];
  foreach($response as $row){
    $sim_category_list[] = ucfirst(str_replace('freedom', 'Freedom', $row->sim_type));
  }

  $response = ['status'=>200, 'message'=>'', 'data'=>$sim_category_list];
  return $response;
}

// TruFreedom SIM Type for Data Plan Functionality
function mobile_api_trufreedom_sim_type(){
  global $wpdb;

  $query = "SELECT DISTINCT `country` FROM `dataplan` WHERE `sim_type` = 'trufreedom' AND status = 1 ";
  $response = $wpdb->get_results($query);
 
  $sim_category_list = [];
  $count = 0;
  foreach($response as $row){
    if($row->country == 'USA'){
      $sim_category_list[$count]['sim_type'] = 'trufreedom';
      $sim_category_list[$count]['sim_type_name'] = 'TruFreedom';
      $sim_category_list[$count]['country'] = $row->country;
    }elseif($row->country == 'USCANADA'){
      $sim_category_list[$count]['sim_type'] = 'trufreedom';
      $sim_category_list[$count]['sim_type_name'] = 'TruFreedom +';
      $sim_category_list[$count]['country'] = $row->country;
    }
    $count++;
  }

  $response = ['status'=>200, 'message'=>'', 'data'=>$sim_category_list];
  return $response;
}

/*
DataPlan List Using SIM Type Functionality & 
No need to pass country parameters for BluFreedom & RedFreedom 
Only required for TruFreedom
*/
function mobile_api_dataplan_using_sim_type($request){
  global $wpdb;

  $sim_type = $request['sim_type'];
  $country = $request['country'];
  $where = '';

  if(!empty($sim_type)){
    switch ($sim_type) {
      case 'trufreedom':
        if(!empty($country)){
          switch ($country) {
            case 'USA':
              $where = " WHERE `sim_type` = '$sim_type' AND country = '$country' AND `status` = 1";
              break;
            case 'USCANADA':
              $where = " WHERE `sim_type` = '$sim_type' AND country = '$country' AND `status` = 1";
              break;
            default:
              $response = ['status'=>404, 'message'=>'You have not given the correct country.', 'data'=>[]];
              return $response;
              break;
          }
        }else{
          $response = ['status'=>404, 'message'=>'Country can\'t be blank.', 'data'=>[]];
          return $response;
        }
        break;
      case 'blufreedom':
        $where = " WHERE `sim_type` = '$sim_type' AND `status` = 1";
        break;
      case 'redfreedom':
        $where = " WHERE `sim_type` = '$sim_type' AND `status` = 1";
        break;
      
      default:
        $response = ['status'=>404, 'message'=>'You have not given the correct sim type.', 'data'=>[]];
        return $response;
        break;
    }

  }else{
    $response = ['status'=>404, 'message'=>'SIM Type can\'t be blank.', 'data'=>[]];
    return $response;
  }

  $query = "SELECT * FROM `dataplan` ".$where;
  $all_dataplan = $wpdb->get_results($query);

  $dataplan_list = [];
  $count = 0;
  foreach($all_dataplan as $dataplan){
    $data_in_gb = explode(' ', $dataplan->plan_name);
    $data_in_gb = end($data_in_gb);

    $dataplan_list[$count]['id'] = $dataplan->id;
    $dataplan_list[$count]['plan_name'] = $dataplan->plan_name;
    $dataplan_list[$count]['amount'] = $dataplan->amount;
    $dataplan_list[$count]['data'] = $data_in_gb.'GB';
    $dataplan_list[$count]['country'] = $dataplan->country;
    $dataplan_list[$count]['truphone_product_id'] = $dataplan->product_id;
    $dataplan_list[$count]['chargeover_product_id'] = $dataplan->chargeover_product_id;
    $count++;
  }

  $response = ['status'=>200, 'message'=>'', 'data'=>$dataplan_list];
  return $response;
}

// Get DataPlan Details Using ID
function mobile_api_dataplan_details_using_id($request){
  global $wpdb;

  $dataplan_id = $request['dataplan_id'];

  if(empty($dataplan_id)){
    $response = ['status'=>404, 'message'=>'Data Plan ID can\'t be blank.', 'data'=>[]];
    return $response;
  }

  $query = "SELECT * FROM `dataplan` WHERE `id` = $dataplan_id AND `status` = 1 ";
  $dataplan_details = $wpdb->get_results($query)[0];

  if($wpdb->num_rows != 1){
    $response = ['status'=>404, 'message'=>'You have given wrong Data Plan ID.', 'data'=>[]];
    return $response;
  }else{
    $response = ['status'=>200, 'message'=>'', 'data'=>[
      "amount"    => $dataplan_details->amount,
      "country"   => $dataplan_details->country,
      "sim_type"  => $dataplan_details->sim_type,
      "plan_name" => $dataplan_details->plan_name,
      "truphone_product_id" => $dataplan_details->product_id,
      "chargeover_product_id" => $dataplan_details->chargeover_product_id
    ]];
    return $response;
  }
}

// Get Customer DataPlan Details Using Device ID
function mobile_api_customer_dataplan_details_using_device_id($request){
  global $wpdb;

  $device_id = $request['device_id'];

  if(empty($device_id)){
    $response = ['status'=>404, 'message'=>'Device ID can\'t be blank.', 'data'=>[]];
    return $response;
  }
  
  $query = "SELECT * FROM `customer_dataplan` WHERE `ddns` = '$device_id' ";
  $device_details = $wpdb->get_results($query)[0];

  if($wpdb->num_rows != 1){
    $response = ['status'=>404, 'message'=>'You have given wrong Device ID.', 'data'=>[]];
    return $response;
  }else{
    $response = ['status'=>200, 'message'=>'', 'data'=>[
      "id"    => $device_details->id,
      "sn"    => $device_details->sn,
      "mac"   => $device_details->mac,
      "ddns"  => $device_details->ddns,
      "iccid" => $device_details->iccid
    ]];
    return $response;
  }
}

// Verify Device Details or ICCID from database for Activating or TOPUP SIM
function mobile_api_verify_device_or_iccid($request){
  global $wpdb;

  $activation_type = $request['activation_type'];
  $sim_type = $request['sim_type'];
  
  $ddns_confirm = $request['ddns_confirm'];
  $iccid_confirm = $request['iccid_confirm'];

  $sn = '';
  $mac = '';
  $ddns = '';
  $iccid = '';

  if(empty($activation_type)){
    $response = ['status'=>404, 'message'=>'Activation Type can\'t be blank.', 'data'=>[]];
    return $response;
  }

  if(empty($sim_type)){
    $response = ['status'=>404, 'message'=>'SIM Type can\'t be blank.', 'data'=>[]];
    return $response;
  }

  if(empty($ddns_confirm) && empty($iccid_confirm)){
    $response = ['status'=>404, 'message'=>'DDNS and ICCID both can\'t be blank.', 'data'=>[]];
    return $response;
  }

  if(!empty($ddns_confirm)){
    $query = "SELECT * FROM `customer_dataplan` WHERE `ddns` = '$ddns_confirm' ";
    $result = $wpdb->get_results($query)[0];

    if($wpdb->num_rows != 1){
      $response = ['status'=>404, 'message'=>'You have given wrong Device ID.', 'data'=>[]];
      return $response;
    }else{
      $response = ['status'=>200, 'message'=>'', 'data'=>[
        'sn' => $result->sn,
        'mac' => $result->mac,
        'ddns' => $result->ddns,
        'iccid' => $result->iccid
      ]];
      return $response;
    }
  }elseif(!empty($iccid_confirm)){
    $response = ['status'=>200, 'message'=>'', 'data'=>['iccid' => $iccid_confirm]];
    return $response;
  }else{
    $response = ['status'=>404, 'message'=>'Something went wrong.', 'data'=>[]];
    return $response;
  }
  exit;
}

// mobile api card verify for chargeover using user id
function mobile_api_card_verify_for_chargeover_using_user_id($request){
  global $wpdb;
  $user_id = $request['user_id'];

  if(empty($user_id)){
    $response = ['status'=>404, 'message'=>'User ID can\'t be blank.', 'data'=>[]];
    return $response;
  }

  $query = "SELECT chargeover_customer_id FROM `card_verify_for_chargeover` WHERE `user_id` = '$user_id' ";
  $customer_id = $wpdb->get_results($query)[0];

  if($wpdb->num_rows == 1){
    $customer_id = $customer_id->chargeover_customer_id;
    $response = ['status'=>200, 'message'=>'', 'data'=>["customer_id"=>$customer_id]];
    return $response;
  }else{
    $response = ['status'=>200, 'message'=>'', 'data'=>["customer_id"=>'']];
    return $response;
  }
}

// mobile api card verify for chargeover using user email
function mobile_api_card_verify_for_chargeover_using_user_email($request){
  global $wpdb;
  $email = $request['email'];

  if(empty($email)){
    $response = ['status'=>404, 'message'=>'Email can\'t be blank.', 'data'=>[]];
    return $response;
  }

  $query = "SELECT chargeover_customer_id FROM `card_verify_for_chargeover` WHERE `chargeover_superuser_email` = '$email' ";
  $customer_id = $wpdb->get_results($query)[0];

  if($wpdb->num_rows == 1){
    $customer_id = $customer_id->chargeover_customer_id;
    $response = ['status'=>200, 'message'=>'', 'data'=>["customer_id"=>$customer_id]];
    return $response;
  }else{
    $response = ['status'=>200, 'message'=>'', 'data'=>["customer_id"=>'']];
    return $response;
  }
}

// mobile api add chargeover customer id in database
function mobile_api_add_chargeover_customer_id_in_database($request){
  global $wpdb;

  $user_id = $request['user_id'];
  $user_email = $request['user_email'];
  $customer_id = $request['customer_id'];

  if(empty($user_id)){
    $response = ['status'=>404, 'message'=>'User ID can\'t be blank.', 'data'=>[]];
    return $response;
  }
  if(empty($customer_id)){
    $response = ['status'=>404, 'message'=>'Customer ID can\'t be blank.', 'data'=>[]];
    return $response;
  }

  $query = "INSERT INTO `card_verify_for_chargeover`(`user_id`, `chargeover_superuser_email`, `chargeover_customer_id`) VALUES ('$user_id', '$user_email', '$customer_id' )";
  $result = $wpdb->query($query);

  $response = $result
  ?['status'=>200, 'message'=>'Customer ID saved in database.', 'data'=>[]]
  :['status'=>404, 'message'=>'Something went wrong.', 'data'=>[]];

  return $response;
}

// 5:15 (Not updated in 5:15).
// mobile api add customer data plan response in database
function mobile_api_add_customer_data_plan_response_in_database($request){
  global $wpdb;

  $user_id = $request['user_id'];
  $sim_type = $request['sim_type'];
  $activation_type = $request['activation_type'];
  $dataplan_id = $request['dataplan_id'];
  $ddns = $request['ddns'];
  $mac = $request['mac'];
  $sn = $request['sn'];
  $iccid = $request['iccid'];
  $chargeover_subscription_id = $request['chargeover_subscription_id'];
  $card_holder_name = $request['card_holder_name'];
  $card_number = $request['card_number'];
  $transaction_id = $request['transaction_id'];
  $payment_date = $request['payment_date'];

  $query = "INSERT INTO `customer_data_plan`(
    `user_id`,
    `sim_type`,
    `activation_type`,
    `dataplan_id`,
    `ddns`,
    `mac`,
    `sn`,
    `iccid`,
    `chargeover_subscription_id`,
    `card_holder_name`,
    `card_number`,
    `transaction_id`,
    `payment_date`
  ) 
  VALUES(
    '$user_id',
    '$sim_type',
    '$activation_type',
    '$dataplan_id',
    '$ddns',
    '$mac',
    '$sn',
    '$iccid',
    '$chargeover_subscription_id',
    '$card_holder_name',
    '$card_number',
    '$transaction_id',
    '$payment_date'
  )";

  $result = $wpdb->query($query);

  $response = $result
  ?['status'=>200, 'message'=>'Customer DataPlan added in database.', 'data'=>[]]
  :['status'=>404, 'message'=>'Something went wrong.', 'data'=>[]];

  return $response;
}

// mobile api add sim activation response in database
function mobile_api_add_sim_activation_response_in_database($request){
  global $wpdb;

  $user_id = $request['user_id'];
  $dataplan_id = $request['dataplan_id'];
  $product_id = $request['product_id'];
  $activation_response = $request['activation_response'];

  $query = "INSERT INTO `sim_activation`(`user_id`, `dataplan_id`, `product_id`, `activation_response`) VALUES ('$user_id', '$dataplan_id', '$product_id', '$activation_response')";
  $result = $wpdb->query($query);

  $response = $result
  ?['status'=>200, 'message'=>'SIM activation response added in database.', 'data'=>[]]
  :['status'=>404, 'message'=>'Something went wrong.', 'data'=>[]];

  return $response;
}

// mobile api sim activated
function mobile_api_sim_activated($request){
  global $wpdb;

  $iccid = $request['iccid'];

  $result = $wpdb->update('customer_dataplan', array('is_active'=>'1'), array('iccid'=>$iccid));
  
  $response = $result
  ?['status'=>200, 'message'=>'ICCID '.$iccid.' is now activated.', 'data'=>[]]
  :['status'=>404, 'message'=>'Something went wrong.', 'data'=>[]];

  return $response;
}




// ShipHero Webhook API functionality
function shiphero_inventory_update($request){
  print_r($request);
  exit;
}