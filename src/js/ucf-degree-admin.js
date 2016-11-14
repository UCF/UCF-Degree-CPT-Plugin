var initializeColorPicker = function($) {
  if ( $.fn.wpColorPicker ) {
    $('.wp-color-field').wpColorPicker();
  }
};

if ( typeof jQuery !== 'undefined' ) {
  jQuery(document).ready(function($) {
    initializeColorPicker($);
  }); 
}
