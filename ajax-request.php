<!-- 
  COPY the bellow code and PAST where you want to call ajax
-->
<script>
  var ajaxurl = "<?= admin_url('admin-ajax.php'); ?>";

  // All Student List using button
  $('#button').on('click', function(){
    
    $.ajax({
      url:ajaxurl,
      type:'GET',
      data:{action:'all_student_list'},
      success:function(data){
        console.log(data)
      }
    });

  });


   // Get Student Details using button
  $('#button').on('click', function(){
    
    // Replace your from data with id and define in JSON data.
    let id = 123;

    // JSON data
    let data = { id };
    
    $.ajax({
      url:ajaxurl,
      type:'POST',
      data:{action:'get_student_details', data },
      success:function(data){
        console.log(data)
      }
    });

  });

</script>


<!-- 
  COPY the bellow code and PAST in the function.php
-->

<?php

// Get Dataplan Details using ID
add_action('wp_ajax_nopriv_all_student_list', 'all_student_list');
add_action('wp_ajax_all_student_list', 'all_student_list');
function all_student_list(){
  global $wpdb;

  $query = "SELECT * FROM students";
  $response = $wpdb->get_results($query);

  echo json_encode($response);

  exit;
}


add_action('wp_ajax_nopriv_get_student_details', 'get_student_details');
add_action('wp_ajax_get_student_details', 'get_student_details');
function get_student_details(){
  global $wpdb;

  $id = $_POST['id'];

  $query = "SELECT * FROM students WHERE id = $id ";
  $response = $wpdb->get_results($query);
  
  echo json_encode($response);

  exit;
}
