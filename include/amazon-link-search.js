/**
 * Handle: amazon-link-search
 * Version: 0.0.1
 * Deps: jquery
 * Enqueue: true
 */

var wpAmazonLinkSearcher = function () {}

wpAmazonLinkSearcher.prototype = {
    search_options    : {},
    sendingAmazonRequest : false,

    incPage : function(event) {
      var page = jQuery(event).find("#amazon-link-search[name='page']");
	if( !this['sendingAmazonRequest'] ) {
           jQuery(page).val(parseInt(jQuery(page).val())+1);
          this.searchAmazon(event);
        }
    },

    decPage : function(event) {
	if( !this['sendingAmazonRequest'] ) {
          var page = jQuery(event).find("#amazon-link-search[name='page']");
          var p = parseInt(jQuery(page).val())-1;
          if (p == 0) p =1;
          jQuery(page).val(p);
          this.searchAmazon(event);
        }
    },

    clearResults : function(event) {
        jQuery(event).find('#amazon-link-result-list').empty();
    },

   searchAmazon : function(event) {
        var collection = jQuery(event).find("[id^=amazon-link-search]");
        var $ths = this;
	if( !this['sendingAmazonRequest'] ) {
           this['sendingAmazonRequest'] = true;
           collection.each(function () {
              $ths['search_options'][this.name] = jQuery(this).val();
           });
           $ths['search_options']['action'] = 'amazon-link-search';

           jQuery('#amazon-link-result-list').empty();
           jQuery('#amazon-link-error').hide();
           jQuery('#amazon-link-results').show();
           jQuery('#amazon-link-status').removeClass('ajax-feedback');
           jQuery.post('admin-ajax.php', $ths['search_options'] , $ths.showResults, 'json');
	}
   },

   showResults : function (response, status){
      wpAmazonLinkSearch['sendingAmazonRequest'] = false;
      jQuery('#amazon-link-status').addClass('ajax-feedback');
      if( response["success"] == false ) {
         jQuery('#amazon-link-results').hide();
         jQuery('#amazon-link-error').show();
      } else {
         jQuery('#amazon-link-error').hide();
         jQuery('#amazon-link-results').show();
         for (index in response['items'])
         {
            jQuery('#amazon-link-result-list').append(response['items'][index]['template']);
         }
      }
   }
}

var wpAmazonLinkSearch = new wpAmazonLinkSearcher();