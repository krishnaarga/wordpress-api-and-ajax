<?php

/*
--COPY the bellow code and PAST in the function.php file and access the URL using GET & POST method--

Get Method
----------
<YOUR BASE URL>/wp-json/mobile-api/testing


Post Method
-----------
<YOUR BASE URL>/wp-json/mobile-api/testing

--Pass JSON in body--
{
  "name":"Krishna"
}

*/

add_action('rest_api_init', function (){
  // Get Method
  register_rest_route('mobile-api', 'testing', array(
    'methods'  => 'GET',
    'callback' => 'mobile_api_get'
  ));

  // Post Method
  register_rest_route('mobile-api', 'testing', array(
    'methods'  => 'POST',
    'callback' => 'mobile_api_post'
  )); 
});


// Testing API / Demo API
function mobile_api_get(){
  echo 'Testing from ***> GET <*** method';
  exit;
}

// Testing API / Demo API
function mobile_api_post($request){
  $name = $request['name'];
  echo '*'.$name.'* from ***> POST <*** method';
  exit;
}
