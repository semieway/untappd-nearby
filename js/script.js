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

   // Date delimiters in the checkin list.
   let currentTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
   document.cookie = 'timezone='+currentTimezone;

   let todayDate = new Date().toDateString();
   let yesterday = new Date();
   yesterday.setDate(yesterday.getDate() - 1);
   let yesterdayDate = yesterday.toDateString();

   let $checkins = $('.checkin');
   let currentDate;

   $checkins.each(function() {
      let date = new Date($(this).data('time')).toDateString();
      let dateName;

      if (date === currentDate) {
         return;
      } else {
         if (date === todayDate) {
            dateName = 'Today';
         } else if (date === yesterdayDate) {
            dateName = 'Yesterday';
         } else {
            dateName = date.slice(0, -4);
         }

         $div = $('<div/>')
             .addClass('date-message')
             .html(`<i class="far fa-calendar"></i> ${dateName}`);
         $(this).prev().css('border-bottom', 'none');
         $(this).before($div);
      }

      currentDate = date;
   });
});