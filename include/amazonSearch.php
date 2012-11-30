<?php
/*****************************************************************************************/

/*
 * Amazon Link Search Class
 *
 * Provides a facility to do simple Amazon Searches via the ajax interface and return results in
 * an array.
 *
 * To use the default script and styles you must add the following on init (before the header).
 *    - wp_enqueue_script('amazon-link-search')
 *    - wp_enqueue_styles('amazon-link-styles')
 *
 * The page must consist of a form with input elements all with the id='amazon-link-search', and
 * with the following names:
 *    - s_title
 *    - s_index
 *    - s_author
 *    - s_page
 *    - s_template
 *
 * To initiate a search there must be an element in the form which triggers the javascript:
 * 'return wpAmazonLinkSearch.searchAmazon(this.form);'
 * 
 * The results are inserted into the html element on the page with the id='amazon-link-result-list'.
 * Which should be contained within an element of id='amazon-link-results', there should also be a hidden
 * element with the id='amazon-link-error' to report any errors that occur. As well as an element with the
 * id='amazon-link-status' to indicate a search in progress.
 *
 * The values of the form input items are used to control the search, 'title', 'author' are used as search terms,
 * 'index' should be a valid amazon search index (e.g. Books). 'page' should be used to set which page of the results
 * is to be displayed.
 * 'template' can be used to get the search engine to populate a predefined html template with values - this should be htmlencoded.
 * the following terms are replaced with values relevant to the search results:
 *    - %ASIN%         - Item's unique ASIN
 *    - %TITLE%        - Item't Title
 *    - %TEXT1%        - User Defined Text string
 *    - %TEXT2%        - User Defined Text string
 *    - %TEXT3%        - User Defined Text string
 *    - %TEXT4%        - User Defined Text string
 *    - %ARTIST%       - Item's Author, Artist or Creator
 *    - %MANUFACTURER% - Item's Manufacturer
 *    - %THUMB%        - URL to Thumbnail Image
 *    - %IMAGE%        - URL to Full size Image
 *    - %IMAGE_CLASS%  - Class of Image as defined in settings
 *    - %URL%          - The URL returned from the Item Search (not localised!)
 *    - %RANK%         - Amazon Rank
 *    - %RATING%       - Numeric User Rating - (No longer Available)
 *    - %PRICE%        - Price of Item
 *    - %TAG%          - Default Amazon Associate Tag (not localised!)
 *    - %DOWNLOADED%   - (1 if Images are in the local Wordpress media library)
 *    - %LINK_OPEN%    - Create a Amazon link with user defined content, of the form %LINK_OPEN%My Content%LINK_CLOSE%
 *    - %LINK_CLOSE%   - Must follow a LINK_OPEN (translates to '</a>').
 */

if (!class_exists('AmazonLinkSearch')) {
   class AmazonLinkSearch {

      var $data = array();

      function AmazonLinkSearch() {
         $this->__construct();
      }

      function __construct() {
         $this->URLRoot = plugins_url("", __FILE__);
         $this->base_name  = plugin_basename( __FILE__ );
         $this->plugin_dir = dirname( $this->base_name );
      }

      /*
       * Must be called by the client in its init function.
       */
      function init($parent) {

         $script = plugins_url("amazon-link-search.js", __FILE__);
         wp_register_script('amazon-link-search', $script, array('jquery'), '1.0.0');
         add_action('wp_ajax_amazon-link-search', array($this, 'performSearch'));      // Handle ajax search requests
         add_action('wp_ajax_amazon-link-get-image', array($this, 'grabImage'));       // Handle ajax image download
         add_action('wp_ajax_amazon-link-remove-image', array($this, 'removeImage'));  // Handle ajax image removal

         $this->alink    = $parent;
      }


/*****************************************************************************************/
      /// AJAX Call Handlers
/*****************************************************************************************/

      function performSearch($Opts='') {
         if (!is_array($Opts)) $Opts = $_POST;

         $Settings = array_merge($this->alink->getSettings(), $Opts);
         $Settings['multi_cc'] = '0';
         $Settings['localise'] = 0;

         if ( empty($Opts['s_title']) && empty($Opts['s_author']) ) {
            $Items = $this->alink->cached_query($Opts['asin'], $Settings);
         } else {
            $Settings['found'] = 1;
            if ($Settings['translate'] && !empty($Opts['s_title_trans'])) $Opts['s_title'] = $Opts['s_title_trans'];
            $Items = $this->do_search($Opts);
         }

         $results['message'] = 'No Error ';
         $results['success'] = 0;
         if (isset($Items['Error'])) {
            $results['message'] = 'Error: ' . (isset($Items['Error']['Message']) ? $Items['Error']['Message'] : 'No Error Message');
         } else if (is_array($Items) && (count($Items) >0)) {
            foreach($Items as $item) {
               $item = array_merge($Settings,$item);
               $results['items'][]['template'] = $this->parse_template($item);
            }
            $results['success'] = 1;
         }

         print json_encode($results);
         exit();
      }

      function removeImage() {
         $Opts = $_POST;

         /* Do we have this image? */
         $media_ids = $this->find_attachments( $Opts['asin'] );

         if (is_wp_error($media_ids)) {
            $results = array('in_library' => false, 'asin' => $Opts['asin'], 'error' => __('No matching image found', 'amazon-link'));
         } else {

            $results = array('in_library' => false, 'asin' => $Opts['asin'], 'error' => __('Images deleted','amazon-link'));

            /* Only remove images attached to this post */
            foreach ($media_ids as $id => $media_id) {
               if ($media_id->post_parent == $Opts['post']) {
                  /* Remove attachment */
                  wp_delete_attachment($media_id->ID);
               } else {
                  $results['in_library'] = true;
                  $results['id'] = $media_id->ID;
               }
            }
         }
         print json_encode($results);
         exit();         
      }

      function grabImage() {
         $Opts = $_POST;

         /* Do not upload if we already have this image */
         $media_ids = $this->find_attachments( $Opts['asin'] );

         if (!is_wp_error($media_ids)) {
            $results = array('in_library' => true, 'asin' => $Opts['asin'], 'id' => $media_ids[0]->ID);
         } else {

            /* Attempt to download the image */
            $result = $this->grab_image($Opts['asin'], $Opts['post']);
            if (is_wp_error($result))
            {
               $results = array('in_library' => false, 'asin' => $Opts['asin'], 'error' => $result->get_error_code());
            } else {
               $results = array('in_library' => true, 'asin' => $Opts['asin'], 'id' => $result);
            }
         }
         print json_encode($results);
         exit();         
      }


/*****************************************************************************************/
      /// Helper Functions
/*****************************************************************************************/


      function get_aws_info() {

         $search_index_by_locale = array( 
            'ca' => array('All', 'Blended', 'Books', 'Classical', 'DVD', 'Electronics', 'ForeignBooks', 'Kitchen', 'Music', 'Software', 'SoftwareVideoGames',
'VHS', 'Video', 'VideoGames'),
            'us' => array('All', 'Apparel', 'Appliances', 'ArtsAndCrafts', 'Automotive', 'Baby', 'Beauty', 'Blended', 'Books', 'Classical', 'DigitalMusic',
'Grocery', 'MP3Downloads', 'DVD', 'Electronics', 'HealthPersonalCare', 'HomeGarden', 'Industrial', 'Jewelry', 'KindleStore',
'Kitchen', 'Magazines', 'Merchants', 'Miscellaneous', 'MobileApps', 'Music', 'MusicalInstruments', 'MusicTracks',
'OfficeProducts', 'OutdoorLiving', 'PCHardware', 'PetSupplies', 'Photo', 'Shoes', 'Software', 'SportingGoods', 'Tools', 'Toys',
'UnboxVideo', 'VHS', 'Video', 'VideoGames', 'Watches', 'Wireless', 'WirelessAccessories'),
            'cn' => array('All', 'Apparel', 'Appliances', 'Automotive', 'Baby', 'Beauty', 'Books', 'Electronics', 'Grocery', 'HealthPersonalCare', 'Home',
'HomeImprovement', 'Jewelry', 'Misc', 'Music', 'OfficeProducts', 'Photo', 'Shoes', 'Software', 'SportingGoods', 'Toys', 'Video',
'VideoGames', 'Watches'),
            'de' => array('All', 'Apparel', 'Automotive', 'Baby', 'Blended', 'Beauty', 'Books', 'Classical', 'DVD', 'Electronics', 'ForeignBooks', 'Grocery',
'HealthPersonalCare', 'HomeGarden', 'Jewelry', 'KindleStore', 'Kitchen', 'Lighting', 'Magazines', 'MP3Downloads',
'Music', 'MusicalInstruments', 'MusicTracks', 'OfficeProducts', 'OutdoorLiving', 'Outlet', 'PCHardware', 'Photo', 'Software',
'SoftwareVideoGames', 'SportingGoods', 'Tools', 'Toys', 'VHS', 'Video', 'VideoGames', 'Watches'),
            'es' => array('All', 'Books', 'DVD', 'Electronics', 'ForeignBooks', 'Kitchen', 'Music', 'Software', 'Toys', 'VideoGames', 'Watches'),
            'fr' => array('All', 'Apparel', 'Baby', 'Beauty', 'Blended', 'Books', 'Classical', 'DVD', 'Electronics', 'ForeignBooks', 'HealthPersonalCare',
'Jewelry', 'Kitchen', 'Lighting', 'MP3Downloads', 'Music', 'MusicalInstruments', 'MusicTracks', 'OfficeProducts', 'Outlet',
'Shoes', 'Software', 'SoftwareVideoGames', 'VHS', 'Video', 'VideoGames', 'Watches'),
            'it' => array('All', 'Books', 'DVD', 'Electronics', 'ForeignBooksSearchIndex:Garden', 'Kitchen', 'Music', 'Shoes', 'Software', 'Toys',
'VideoGames', 'Watches'),
            'jp' => array('All', 'Apparel', 'Automotive', 'Baby', 'Beauty', 'Blended', 'Books', 'Classical', 'DVD', 'Electronics', 'ForeignBooks', 'Grocery',
'HealthPersonalCare', 'Hobbies', 'HomeImprovement', 'Jewelry', 'Kitchen', 'MP3Downloads', 'Music', 'MusicalInstruments',
'MusicTracks', 'OfficeProducts', 'Shoes', 'Software', 'SportingGoods', 'Toys', 'VHS', 'Video', 'VideoGames', 'Watches'),
            'uk' => array('All', 'Apparel', 'Automotive', 'Baby', 'Beauty', 'Blended', 'Books', 'Classical', 'DVD', 'Electronics', 'Grocery', 'HealthPersonalCare',
'HomeGarden', 'Jewelry', 'Kitchen', 'Lighting', 'MP3Downloads', 'Music', 'MusicalInstruments', 'MusicTracks',
'OfficeProducts', 'OutdoorLiving', 'Outlet', 'Shoes', 'Software', 'SoftwareVideoGames', 'Toys', 'VHS', 'Video', 'VideoGames', 'Watches'),
            'us' => array('All', 'Apparel', 'Appliances', 'ArtsAndCrafts', 'Automotive', 'Baby', 'Beauty', 'Blended', 'Books', 'Classical', 'DigitalMusic',
'Grocery', 'MP3Downloads', 'DVD', 'Electronics', 'HealthPersonalCare', 'HomeGarden', 'Industrial', 'Jewelry', 'KindleStore',
'Kitchen', 'Magazines', 'Merchants', 'Miscellaneous', 'MobileApps', 'Music', 'MusicalInstruments', 'MusicTracks',
'OfficeProducts', 'OutdoorLiving', 'PCHardware', 'PetSupplies', 'Photo', 'Shoes', 'Software', 'SportingGoods', 'Tools', 'Toys',
'UnboxVideo', 'VHS', 'Video', 'VideoGames', 'Watches', 'Wireless', 'WirelessAccessories'));

         return array('SearchIndexByLocale' => $search_index_by_locale);
      }

      function do_search($Opts) {

         $Settings = array_merge($this->alink->getSettings(), $Opts);
         $Settings['multi_cc'] = '0';
         $Settings['found'] = 1;
         $Settings['localise'] = 0;

         // Not working: Baby, MusicalInstruments
         $Creator = array( 'Author' => array( 'Books', 'ForeignBooks', 'MobileApps', 'MP3Downloads'),
                           'Actor' => array( 'DigitalMusic' ),
                           'Artist' => array('Music'),
                           'Director' => array('DVD', 'UnboxVideo', 'VHS', 'Video'),
                           'Publisher' => array('Magazines'),
                           'Brand' => array('Apparel', 'ArtsAndCrafts', 'Baby', 'Beauty', 'Grocery', 'Lighting', 'OfficeProducts', 'Miscellaneous', 'PetSupplies', 'Shoes', 'MusicalInstruments', 'VideoGames'),
                           'Manufacturer' => array('Appliances', 'Automotive', 'Electronics', 'Garden', 'HealthPersonalCare', 'Hobbies', 'Home', 'HomeGarden', 'HomeImprovement', 'Industrial', 'Kitchen',  'OutdoorLiving', 'Photo', 'Software', 'SoftwareVideoGames'),
                           'Composer' => array('Classical'));

         $Keywords = array('Blended', 'All', 'DigitalMusic', 'MusicTracks', 'Outlet');

         $Sort['uk'] = array('salesrank'       => array('Books', 'Classical', 'DVD', 'Electronics', 'HealthPersonalCare', 'HomeGarden', 'HomeImprovement', 'Kitchen', 'MarketPlace', 'Music', 'OutdoorLiving', 'PCHardware', 'Software', 'SoftwareVideoGames', 'Toys', 'VHS', 'Video', 'VideoGames'),
                             'relevancerank'   => array('Apparel', 'Automotive', 'Baby', 'Beauty', 'Grocery', 'Jewelry', 'KindleStore', 'MP3Downloads', 'MusicalInstruments', 'OfficeProducts', 'Shoes', 'Watches'),
                             'xsrelevancerank' => array('Shoes'));
         $Sort['us'] = array('salesrank'       => array('Books', 'Classical', 'DVD', 'Electronics', 'HealthPersonalCare', 'HomeGarden', 'HomeImprovement', 'Kitchen', 'MarketPlace', 'Music', 'OutdoorLiving', 'PCHardware', 'Software', 'SoftwareVideoGames', 'Toys', 'VHS', 'Video', 'VideoGames'),
                             'relevancerank'   => array('Apparel', 'Automotive', 'Baby', 'Beauty', 'Grocery', 'Jewelry', 'KindleStore', 'MP3Downloads', 'MusicalInstruments', 'OfficeProducts', 'Shoes', 'Watches'),
                             'xsrelevancerank' => array('Shoes'));

         // Create query to retrieve the first 10 matching items
         $request = array('Operation' => 'ItemSearch',
                          'ResponseGroup' => 'Offers,ItemAttributes,Small,EditorialReview,Images,SalesRank',
                          'SearchIndex'=>$Opts['s_index'],
                          'ItemPage'=>$Opts['s_page']);

         foreach ($Sort['uk'] as $Term => $Indices) {
            if (in_array($Opts['s_index'], $Indices)) {
               $request['Sort'] = $Term;
               continue;
            }
         }

         foreach ($Creator as $Term => $Indices) {
            if (in_array($Opts['s_index'], $Indices)) {
               $request[$Term] = $Opts['s_author'];
               continue;
            }
         }

         if (in_array($Opts['s_index'], $Keywords)) {
            $request['Keywords']  = $Opts['s_title'];
         } else {
            $request['Title'] = $Opts['s_title'];
         }

         $items = $this->alink->cached_query($request, $Settings);

         return $items;
      }

/*****************************************************************************************/

      function get_links ($asin, $settings, $local_info, &$data) {

         if (!isset($data['search_text_s'])) {
            $data['search_text_s'] = $settings['search_text'];
            foreach ($this->alink->get_keywords() as $keyword => $key_data) {
               $data['search_text_s'] = str_ireplace( '%' . $keyword . '%', '%' . $keyword . '%S#', $data['search_text_s']);

            }
         }
         $search_s = $data['search_text_s'];
         $search = $settings['search_text'];

         if (!isset($data[$local_info['cc']]['link_open'])) $data[$local_info['cc']]['link_open'] = $this->alink->make_link($asin,$settings, $local_info, array($search, $search_s), 'product');
         if (!isset($data[$local_info['cc']]['rlink_open'])) $data[$local_info['cc']]['rlink_open'] = $this->alink->make_link($asin,$settings, $local_info, array($search, $search_s), 'review');
         if (!isset($data[$local_info['cc']]['slink_open'])) $data[$local_info['cc']]['slink_open'] = $this->alink->make_link($asin,$settings, $local_info, array($search, $search_s), 'search');
         if (!isset($data[$local_info['cc']]['link_close'])) $data[$local_info['cc']]['link_close'] = '</a>';

      }

      function get_images ($asin, &$data) {
         /*
          * Check for image in uploads 
          */
         $media_ids = $this->find_attachments( $asin );
         if (!is_wp_error($media_ids)) {
            // Only do one country, as other countries may have a different ASIN specified.
            $data['media_id'] = $media_ids[0]->ID;
            $data['downloaded'] = '1';
            $data['thumb'] = wp_get_attachment_thumb_url($data['media_id']);
            $data['image'] = wp_get_attachment_url($data['media_id']);
         } else {
            $data['media_id'] = 0;
            $data['downloaded'] = '0';
         }
      }

      function remap_data ($data, $indexes, &$output) {
         foreach ($data as $key => $info) {
            if (is_array($info)) {
               /* Transpose data */
               foreach ($info as $cc => $item) $output[$cc][$key] = $item;
            } else {
               /* Apply to all 'indexes' */
               foreach($indexes as $cc) $output[$cc][$key] = $info;
            }
         }
      }

      /*
       * This will perform differently to the usual parser as it does the keywords in the order
       * found in the template - e.g. 'FOUND' will be done first!
       * We need to run the regex twice to catch new template tags replacing old ones (LINK_OPEN)
       */
      function parse_template ($item) {
         $countries        = array_keys($this->alink->get_country_data());

         $local_info       = $this->alink->get_local_info($item);
         $local_country    = $local_info['cc'];
         $default_country  = $item['default_cc'];
         $item['home_cc']  = $default_country;
         $item['local_cc'] = $local_country;
         
         // 'channel' used may be different for each shortcode or post so need to refresh every template
         $data = array( $local_country => $local_info);

         if (!is_array($item['asin'])) $item['asin'] = array($default_country => $item['asin']);

         if ($item['global_over']) {
            $this->remap_data($item, $countries, $data);
         } else {
            $this->remap_data($item, array($local_country), $data);
         }

         $input = htmlspecialchars_decode (stripslashes($item['template_content']));

         $this->settings = $item;
         $this->data     = $data;

         $countries       = implode('|',$countries);
         $this->settings['skip_calculated'] = True;
         $output = preg_replace_callback("!%(?<keyword>[A-Z_]+)%(?:(?<cc>$countries)?(?<escape>S)?#)?!i", array($this, 'parse_template_callback'), $input);
         $this->settings['skip_calculated'] = False;
         $output = preg_replace_callback("!%(?<keyword>[A-Z_]+)%(?:(?<cc>$countries)?(?<escape>S)?#)?!i", array($this, 'parse_template_callback'), $output);
         $this->alink->Settings['default_cc'] = $item['default_cc'];
         $this->alink->Settings['multi_cc'] = $item['multi_cc'];
         $this->alink->Settings['localise'] = $item['localise'];

         return $output;
      }

      function parse_template_callback ($args) {

         $keyword  = strtolower($args['keyword']);
         $keywords = $this->alink->get_keywords();
         $settings = $this->settings;

         if (!array_key_exists($keyword, $keywords) || (!empty($keywords[$keyword]['Calculated']) && $settings['skip_calculated'])) return $args[0]; // or '';
          
         $asins            = $settings['asin'];
         $default_country  = $settings['home_cc'];

         $key_data  = $keywords[$keyword];

         // Process Modifiers
         $escaped   = !empty($args['escape']);
         $local_settings = $settings;
         if (empty($args['cc'])) {
            $localised = true;
            $country   = $settings['local_cc'];
         } else {
            $country = strtolower($args['cc']);
            $local_settings['multi_cc'] = 0;
            $local_settings['localise'] = 0;
            $local_settings['default_cc'] = $country;
            $localised = false;
         }
   
         if (!isset($this->data[$country][$keyword])) {
            $local_info = $this->alink->get_local_info($local_settings);
            if (!isset($this->data[$country]['asin'])) {
               $this->data[$country]['asin'] = $this->data[$default_country]['asin'];
            }
            $asin = $this->data[$country]['asin'];

            if ($local_settings['live'] && $local_settings['prefetch']) {
echo "<PRE>Prefetch: $country -> $keyword </prE>";
               $item_data = array_shift($this->alink->cached_query($asin, $local_settings));
//echo "<PRE>item: "; print_r($item_data); echo " isset: "; var_export(isset($item_data[$keyword])); echo " </prE>";
               if ($item_data['found'] && empty($asins[$country])) {
                  $asins[$country] = $asin;
                  $this->settings['asin'][$country] = $asin;
               } else if ($localised && $settings['localise'] && ($country != $settings['default_cc'])) {
                  $local_settings['default_cc'] = $settings['default_cc'];
                  $local_settings['localise']   = 0;
                  $item_data = array_shift($this->alink->cached_query($asin, $local_settings));
               }
               $this->data[$country] = array_merge($item_data, (array)$this->data[$country]);
//echo "<PRE>data: "; print_r($this->data); echo " </prE>";
            }


            if ($key_data['Link']) {
               $this->get_links($asins, $local_settings, $local_info, $this->data);
            } else if ($key_data['Image']) {
               /* First try and get uploaded image info */
               $this->get_images($asin, $this->data[$country]);
            }

            if ($key_data['Live'] && !isset($this->data[$country][$keyword]) ) {
               if ($local_settings['live']) {
                  $item_data = array_shift($this->alink->cached_query($asin, $local_settings));
                  if ($item_data['found'] && empty($asins[$country])) {
//                    $asins[$country] = $asin;
//                    $this->settings['asin'][$country] = $asin;
//echo "<PRE>ASINS: $country:"; print_r($asins); echo "</pRE>";
                  } else if ($localised && $settings['localise'] && ($country != $settings['default_cc'])) {
                     $local_settings['default_cc'] = $settings['default_cc'];
                     $local_settings['localise']   = 0;
                     $item_data = array_shift($this->alink->cached_query($asin, $local_settings));

                  }
                  if ($settings['debug'] && isset($item_data['Error'])) {
                     echo "<!-- amazon-link ERROR: "; print_r($item_data); echo "-->";
                  }

                  $this->data[$country] = array_merge($item_data, (array)$this->data[$country]);

               } else {

                  // Live keyword, but live data not enabled and item not provided by the user
                  $this->data[$country][$keyword] = $key_data['Default'];
                  $this->data[$country]['found']  = 1;

               }

            } else if (($keyword == 'found') && !isset($this->data[$country][$keyword])) {
               $this->data[$country][$keyword] = 1;
            } else {
               $this->data[$country] = array_merge($local_info, $this->data[$country]);
               if (!isset($this->data[$country][$keyword])) $this->data[$country][$keyword] = 'NL';
            }
         }

         $phrase = $this->data[$country][$keyword];

         if ($settings['multi_cc'] && $key_data['Link']) unset ($this->data[$country][$keyword]); // Only use links once

         /*
          * We urlencode the "'","\r" and "\n" so the javascript parses correctly.
          * We encode the "&" so the parse_args & html_entity_decode do not see it as a field separator. (data inserted into shortcode from helper)
          * urlencode works for 'multisite' javascript
          * ''' & '\n' also causes problems for insertForm results form javascript
          * Don't do full urlencode as it makes the shortcode data unreadable in the post
          */
         if ($escaped) $phrase = addslashes(htmlspecialchars (str_ireplace(array( '&', "'", "\r", "\n"), array('%26', '&#39;','%0D','%0A'), $phrase),ENT_COMPAT | ENT_HTML401,'UTF-8')); //urlencode

         $this->data[$default_country]['unused_args'] = preg_replace('!(&?)'.$keyword.'=[^&]*(\1?)&?!','\2', $this->data[$default_country]['unused_args']);
         return $phrase;
      }

/*****************************************************************************************/

      function find_attachments ($asin) {

         // Do we already have a local image ? 
         $args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => 'all', 'no_filters' => true,
                        'meta_query' => array(array('key' => 'amazon-link-ASIN', 'value' => $asin)));
         $query = new WP_Query( $args );
         $media_ids = $query->posts;
         if ($media_ids) {
            return $media_ids;
         } else {
            return new WP_Error(__('No images found','amazon-link'));
         }
      }

      function grab_image ($ASIN, $post_id = 0) {

         $ASIN = strtoupper($ASIN);

         $data = array_shift($this->alink->cached_query($ASIN));

         if ( ! ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] ) )
            return new WP_Error($uploads['error']);

         $filename = $ASIN. '.JPG';
         $filename = '/' . wp_unique_filename( $uploads['path'], basename($filename));
         $filename_full = $uploads['path'] . $filename;

         $result = wp_remote_get($data['image']);
         if (is_wp_error($result))
            return $result; //new WP_Error(__('Could not retrieve remote image file','amazon-link'));

         // Save file to media library
         $content = $result['body'];
         $size = file_put_contents ($filename_full, $content);

         if (is_readable($filename_full)) {
            // Grabbed Image successfully now add it to the media library
            $wp_filetype = wp_check_filetype(basename($filename_full), null );
            $attachment = array(
               'guid' => $filename,
               'post_mime_type' => $wp_filetype['type'],
               'post_title' => $data['artist'] . ' - ' . $data['title'],   // Title
               'post_excerpt' => $data['title'],                     // Caption
               'post_content' => '',                           // Description
               'post_status' => 'inherit');
            $attach_id = wp_insert_attachment( $attachment, $filename_full, $post_id);
            // you must first include the image.php file
            // for the function wp_generate_attachment_metadata() to work
            update_post_meta($attach_id , 'amazon-link-ASIN', $ASIN);
            require_once(ABSPATH . "wp-admin" . '/includes/image.php');
            $attach_data = wp_generate_attachment_metadata( $attach_id, $filename_full );
            //echo "<PRE>"; print_r($attach_data); echo "</PRE>";
            wp_update_attachment_metadata( $attach_id,  $attach_data );
         } else {
            return new WP_Error(__('Could not read downloaded image','amazon-link'));
         }
         return $attach_id;
      }


      function parse_template_old ($item) {
         $countries       = array_keys($this->alink->get_country_data());
         $local_info      = $this->alink->get_local_info($item);
         $local_country   = $local_info['cc'];
         $default_country = $item['default_cc'];
         $item['home_cc'] = $default_country;

         
         // 'channel' used may be different for each shortcode or post so need to refresh every template
         $data = array( $local_country => $local_info);

         if (!is_array($item['asin'])) $item['asin'] = array($default_country => $item['asin']);
         $asins = $item['asin'];

         if ($item['global_over']) {
            $this->remap_data($item, $countries, $data);
         } else {
            $this->remap_data($item, array($local_country), $data);
         }

         $input = htmlspecialchars_decode (stripslashes($item['template_content']));

         $local_settings = $item;
         foreach ($this->alink->get_keywords() as $keyword => $key_data) {
            $index=0;
            $output = '';
            $used=0;
            while (($key_start = stripos($input, '%'.$keyword.'%' , $index)) !== FALSE) {
               $key_end = $key_start + 2 + strlen ($keyword);
               $used=1;

               $country = $local_country;
               $localised = true;
               $escaped = false;
               $local_settings['multi_cc'] = $item['multi_cc'];
               $local_settings['localise'] = $item['localise'];
               $local_settings['default_cc'] = $item['default_cc'];

               // Check for Modifiers
               $modifiers = substr($input, $key_end,4);
               $mod_end = strpos($modifiers, '#');
               if ($mod_end !== FALSE) {
                  $modifier_cc = NULL;
                  $modifier_s = NULL;
                  if ($mod_end >= 2) {
                     $modifier_cc = strtolower(substr($modifiers, $mod_end-2, 2));
                     if (!in_array($modifier_cc, $countries)) {
                        $modifier_cc = False;
                     }
                  }
                  if (($mod_end == 1) || ($mod_end == 3)) {
                     $modifier_s = strtolower(substr($modifiers, 0,1));
                     if ($modifier_s != 's') {
                        $modifier_s = False;
                     }
                  }

                  /* If no error in parsing the modifiers then process them */
                  if (($modifier_cc !== False) && ($modifier_s !== False)) {
                     $key_end +=$mod_end+1;
                     if ($modifier_cc != NULL) {
                        $country= $modifier_cc;
                        $local_settings['multi_cc'] = 0;
                        $local_settings['localise'] = 0;
                        $local_settings['default_cc'] = $country;
                        $localised = false;
                     }
                     if ($modifier_s != NULL) {
                        $escaped = 1;
                     }
                  }
               }

               if (!isset($this->$data[$country][$keyword])) {
                  $local_info = $this->alink->get_local_info($local_settings);
                  if (!isset($data[$country]['asin'])) {
                     $data[$country]['asin'] = $data[$default_country]['asin'];
                  }
                  $asin = $data[$country]['asin'];
                  
                  if ($key_data['Link']) {
                     $this->get_links($asins, $local_settings, $local_info, $data);
                  }
                  if ($key_data['Image']) {
                     /* First try and get uploaded image info */
                     $this->get_images($asin, $data[$country]);
                  }
                  if ($key_data['Live'] && !isset($data[$country][$keyword]) ) {
                     if ($local_settings['live']) {
                        $item_data = array_shift($this->alink->cached_query($asin, $local_settings));
                        if ($localised && !$item_data['found'] && $item['localise'] && ($country != $item['default_cc'])) {
                           $local_settings['default_cc'] = $item['default_cc'];
                           $local_settings['localise']   = 0;
                           $item_data = array_shift($this->alink->cached_query($asin, $local_settings));
//echo "<PRE>DATA: "; print_r($item_data); echo "</pRE>";
                        }
                        if ($item['debug'] && isset($item_data['Error'])) {
                           echo "<!-- amazon-link ERROR: "; print_r($item_data); echo "-->";
                        }
//echo "<PRE>DATA: "; print_r($item_data); echo "</pRE>";
                          $data[$country] = array_merge($item_data, (array)$data[$country]);
//echo "<PRE>DATA: "; print_r($data); echo "</pRE>";
                     } else {
                        $data[$country][$keyword] = 'Undefined';
                        $data[$country]['found'] = 1;
                     }
                  } else if (($keyword == 'found') && !isset($data[$country][$keyword])) {
                     $data[$country][$keyword] = 1;
                  } else {
                     $data[$country] = array_merge($local_info, $data[$country]);
                     if (!isset($data[$country][$keyword])) $data[$country][$keyword] = 'NL';
                  }
               }
               $phrase = $data[$country][$keyword];

               if ($item['multi_cc'] && $key_data['Link']) unset ($data[$country][$keyword]); // Only use links once
               /*
                * We urlencode the "'","\r" and "\n" so the javascript parses correctly.
                * We encode the "&" so the parse_args & html_entity_decode do not see it as a field separator. (data inserted into shortcode from helper)
                * urlencode works for 'multisite' javascript
                * ''' & '\n' also causes problems for insertForm results form javascript
                * Don't do full urlencode as it makes the shortcode data unreadable in the post
                */
               if ($escaped) $phrase = addslashes(htmlspecialchars (str_ireplace(array( '&', "'", "\r", "\n"), array('%26', '&#39;','%0D','%0A'), $phrase),ENT_COMPAT | ENT_HTML401,'UTF-8')); //urlencode

               $output .= substr($input, $index, ($key_start-$index)) . $phrase;
               $index  = $key_end;
            }
            if ($used && !empty($data[$default_country]['unused_args'])) {
               $data[$default_country]['unused_args'] = preg_replace('!(&?)'.$keyword.'=[^&]*(\1?)&?!','\2', $data[$default_country]['unused_args']);
            }
            $input = $output . substr($input, $index);
         }
         $this->alink->Settings['default_cc'] = $item['default_cc'];
         $this->alink->Settings['multi_cc'] = $item['multi_cc'];
         $this->alink->Settings['localise'] = $item['localise'];
         return $input;
      }



   }
}
?>