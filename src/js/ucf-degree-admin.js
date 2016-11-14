var initializeColorPicker = function($) {
  if ( $.fn.wpColorPicker ) {
    $('.wp-color-field').wpColorPicker();
  } else {
    console.log('is undefined');
  }
};

if ( typeof jQuery !== 'undefined' ) {
  jQuery(document).ready(function($) {
    initializeColorPicker($);
  }); 
}
