// Prevent form resubmission.
if ( window.history.replaceState ) {
   window.history.replaceState( null, null, window.location.href );
}

$(document).ready(function() {

   // Beer rating caps.
   $('.caps').each(function() {
      let rating = +$(this).data('rating');
      rating = parseInt(rating.toPrecision(2) * 100);

      $(this).find('.cap').each(function() {
         if (rating - 100 >= 0) {
            rating -= 100;
            $(this).addClass('cap-100');
         } else if (rating > 0) {
            $(this).addClass('cap-'+rating);
            rating = 0;
         }
      });
   });

   // Admin page remove beer buttons.
   $('.beer-remove').on('click', function() {
      let isRemove = confirm('Please confirm beer removal');

      if (isRemove) {
         let id = $(this).data('id');
         $.post('admin.php', { remove_id: id }, function(data) {
            location.reload();
         });
      }
   });

});