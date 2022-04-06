var ajaxurl = "<?php echo admin_url('admin-ajax.php') ?>";


$('.students').on('click', function(){
  let id = $(this).attr('id');
  let first_name = $('#first_name'+id).val();
  let last_name = $('#last_name'+id).val();
              
  let m_card_data = { id, first_name, last_name }
  $('#alert').html('<div class="alert alert-warning font-weight-bold" role="alert">Processing...</div>').slideDown("slow");
  $.ajax({
    url:ajaxurl,
    type:'POST',
    data:{action:'update_mcard_using_id', m_card_data},
    success:function(data){
      // console.log(data)
      $('#alert').html('<div class="alert alert-success font-weight-bold" role="alert">M-Card Updated Successfully...</div>').slideDown("slow");
      setTimeout(() => { $('#alert').slideUp("slow") }, 5000);
    }
  });
});
