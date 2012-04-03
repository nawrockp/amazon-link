<?php

$wishlist_template = htmlspecialchars ('
<div class="al_found%FOUND%">
 <div class="amazon_prod">
  <div class="amazon_img_container">
   %LINK_OPEN%<img class="%IMAGE_CLASS%" src="%THUMB%">%LINK_CLOSE%
  </div>
  <div class="amazon_text_container">
   <p>%LINK_OPEN%%TITLE%%LINK_CLOSE%</p>
   <div class="amazon_details">
     <p>by %ARTIST% [%MANUFACTURER%]<br />
     Rank/Rating: %RANK%/%RATING%<br />
     <b>Price: <span class="amazon_price">%PRICE%</span></b>
    </p>
   </div>
  </div>
 </div>
<img src="http://www.assoc-amazon.%TLD%/e/ir?t=%TAG%&l=as2&o=%MPLACE_ID%&a=%ASIN%" width="1" height="1" border="0" alt="" style="border:none !important; margin:0px !important;" />
</div>');


$carousel_template = htmlspecialchars ('
<script type=\'text/javascript\'>
var amzn_wdgt={widget:\'Carousel\'};
amzn_wdgt.tag=\'%TAG%\';
amzn_wdgt.widgetType=\'ASINList\';
amzn_wdgt.ASIN=\'%ASINs%\';
amzn_wdgt.title=\'%TEXT%\';
amzn_wdgt.marketPlace=\'%MPLACE%\';
amzn_wdgt.width=\'600\';
amzn_wdgt.height=\'200\';
</script>
<script type=\'text/javascript\' src=\'http://wms.assoc-amazon.%TLD%/20070822/%MPLACE%/js/swfobject_1_5.js\'>
</script>');


$iframe_template = htmlspecialchars ('
<iframe src="http://%RCM%/e/cm?lt1=_blank&bc1=000000&IS2=1&bg1=FFFFFF&fc1=000000&lc1=0000FF&t=%TAG%&o=%MPLACE_ID%&p=8&l=as4&m=amazon&f=ifr&ref=ss_til&asins=%ASIN%" style="width:120px;height:240px;" scrolling="no" marginwidth="0" marginheight="0" frameborder="0"></iframe>
');


$image_template = htmlspecialchars ('
<div class="al_found%FOUND%">
 %LINK_OPEN%<img alt="%TITLE%" title="%TITLE%" src="%IMAGE%" class="%IMAGE_CLASS%">%LINK_CLOSE%
<img src="http://www.assoc-amazon.%TLD%/e/ir?t=%TAG%&l=as2&o=%MPLACE_ID%&a=%ASIN%" width="1" height="1" border="0" alt="" style="border:none !important; margin:0px !important;" />
</div>
');


$mp3_clips_template = htmlspecialchars ('
<script type=\'text/javascript\'>
var amzn_wdgt={widget:\'MP3Clips\'};
amzn_wdgt.tag=\'%TAG%\';
amzn_wdgt.widgetType=\'ASINList\';
amzn_wdgt.ASIN=\'%ASINS%\';
amzn_wdgt.title=\'%TEXT%\';
amzn_wdgt.width=\'250\';
amzn_wdgt.height=\'250\';
amzn_wdgt.shuffleTracks=\'True\';
amzn_wdgt.marketPlace=\'%MPLACE%\';
</script>
<script type=\'text/javascript\' src=\'http://wms.assoc-amazon.%TLD%/20070822/%MPLACE%/js/swfobject_1_5.js\'>
</script>');


$my_favourites_template = htmlspecialchars ('
<script type=\'text/javascript\'>
var amzn_wdgt={widget:\'MyFavorites\'};
amzn_wdgt.tag=\'%TAG%\';
amzn_wdgt.columns=\'1\';
amzn_wdgt.rows=\'3\';
amzn_wdgt.title=\'%TEXT%\';
amzn_wdgt.width=\'250\';
amzn_wdgt.ASIN=\'%ASINS%\';
amzn_wdgt.showImage=\'True\';
amzn_wdgt.showPrice=\'True\';
amzn_wdgt.showRating=\'True\';
amzn_wdgt.design=\'5\';
amzn_wdgt.colorTheme=\'White\';
amzn_wdgt.headerTextColor=\'#FFFFFF\';
amzn_wdgt.marketPlace=\'%MPLACE%\';
</script>
<script type=\'text/javascript\' src=\'http://wms.assoc-amazon.%TLD%/20070822/%MPLACE%/js/AmazonWidgets.js\'>
</script>');


$thumbnail_template = htmlspecialchars ('
<div class="al_found%FOUND%">
 %LINK_OPEN%<img alt="%TITLE%" title="%TITLE%" src="%THUMB%" class="%IMAGE_CLASS%">%LINK_CLOSE%
<img src="http://www.assoc-amazon.%TLD%/e/ir?t=%TAG%&l=as2&o=%MPLACE_ID%&a=%ASIN%" width="1" height="1" border="0" alt="" style="border:none !important; margin:0px !important;" />
</div>');

$preview_script_template = ('
<script type="text/javascript" src="http://wms.assoc-amazon.%TLD%/20070822/%MPLACE%/js/link-enhancer-common.js?tag=%TAG%">
</script>
<noscript>
    <img src="http://wms.assoc-amazon.%TLD%/20070822/%MPLACE%/img/noscript.gif?tag=%TAG%" alt="" />
</noscript>');

$easy_banner_template = htmlspecialchars ('
<iframe src="http://%RCM%/e/cm?t=%TAG%&o=%MPLACE_ID%&p=26&l=ez&f=ifr&f=ifr" width="468" height="60" scrolling="no" marginwidth="0" marginheight="0" border="0" frameborder="0" style="border:none;">
</iframe>');

         $this->default_templates = array (
            'banner easy' => array ( 'Name' => 'Banner Easy', 'Description' => __('Easy Banner (468x60)', 'amazon-link'), 
                                 'Content' => $easy_banner_template, 'Version' => '1', 'Notice' => '', 'Type' => 'No ASIN', 'Preview_Off' => 0 ),
            'carousel' => array ( 'Name' => 'Carousel', 'Description' => __('Amazon Carousel Widget (limited locales)', 'amazon-link'), 
                                  'Content' => $carousel_template, 'Version' => '1', 'Notice' => '', 'Type' => 'Multi', 'Preview_Off' => 0 ),
            'iframe image' => array ( 'Name' => 'Iframe Image', 'Description' => __('Standard Amazon Image Link', 'amazon-link'), 
                                  'Content' => $iframe_template, 'Type' => 'Product', 'Version' => '1', 'Notice' => '', 'Preview_Off' => 0 ),
            'image' => array ( 'Name' => 'Image', 'Description' => __('Localised Image Link', 'amazon-link'), 
                                  'Content' => $image_template, 'Type' => 'Product', 'Version' => '2', 'Notice' => 'Add impression tracking', 'Preview_Off' => 0 ),
            'mp3 clips' => array ( 'Name' => 'MP3 Clips', 'Description' => __('Amazon MP3 Clips Widget (limited locales)', 'amazon-link'), 
                                  'Content' => $mp3_clips_template, 'Version' => '1', 'Notice' => '', 'Type' => 'Multi', 'Preview_Off' => 0 ),
            'my favourites' => array ( 'Name' => 'My Favourites', 'Description' => __('Amazon My Favourites Widget (limited locales)', 'amazon-link'), 
                                  'Content' => $my_favourites_template, 'Version' => '1', 'Notice' => '', 'Type' => 'Multi', 'Preview_Off' => 0 ),
            'preview script' => array ( 'Name' => 'Preview Script', 'Description' => __('Add Amazon Preview Pop-up script (limited locales)', 'amazon-link'),
                                  'Content' => $preview_script_template, 'Version' => '1', 'Notice' => '', 'Type' => 'No ASIN', 'Preview_Off' => 1 ),
            'thumbnail' => array ( 'Name' => 'Thumbnail', 'Description' => __('Localised Thumb Link', 'amazon-link'), 
                                  'Content' => $thumbnail_template, 'Type' => 'Product', 'Version' => '2', 'Notice' => 'Add impression tracking', 'Preview_Off' => 0 ),
            'wishlist' => array ( 'Name' => 'Wishlist', 'Description' => __('Used to generate the wishlist', 'amazon-link'), 
                                  'Content' => $wishlist_template, 'Type' => 'Product', 'Version' => '2', 'Notice' => 'Add impression tracking', 'Preview_Off' => 0)
         );

?>