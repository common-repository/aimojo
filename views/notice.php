<?php
		$icon_url    = $bannerImage;
//		$icon_url    = AI_MOJO__PLUGIN_URL . 'images/'. $bannerImage;
		$icon_url_2x = AI_MOJO__PLUGIN_URL . 'images/'. $bannerImage;

		$banner_button_close_text = 'Later';
?>

<div id="aimojo-notice" class="updated" href="<?php  echo $bannerLink; ?>">
	<div class="aimojo-banner"></div>

	<p><a id="aimojo-banner-link-button"  onclick="return clickLinkBanner( '<?php  echo $bannerLink; ?>', '<?php  echo $postOptionsUrl; ?>' );" ></a></p>
	<p><a id="aimojo-banner-dismiss-button" onclick="return dismissBanner( '<?php  echo $postOptionsUrl; ?>');"><?php echo $banner_button_close_text; ?></a></p>

</div>




<style>
		.aimojo-banner {
		/*	position:absolute; left:0px; top:0px;   */
			display:block; width:960px; height:120px;
			background:url(<?php echo $icon_url; ?>) no-repeat;
		}

		/* Retina */
		@media
			only screen and (-webkit-min-device-pixel-ratio: 1.5),
			only screen and (-moz-min-device-pixel-ratio: 1.5),
			only screen and (-o-min-device-pixel-ratio: 3/2),
			only screen and (min-device-pixel-ratio: 1.5) {
				.aimojo-banner {
					background-image: url(<?php echo $icon_url_2x; ?>);
					background-size:  960px 120px;
				}
		}

		#aimojo-notice
		{
			position:relative;
			height:120px;
			width:960px;
			background: #fff; /* Old browsers */
			color: #666;
			border-radius:3px;
			border:1px solid #BCE8F1;
			font-size: 1.45em;
			padding: 0em 0em 0em 0px;
			text-shadow:0 1px 0 rgba(255, 255, 255, 0.5);			
		}

		#aimojo-banner-link-button
		{
			position: absolute;
		    width: 100%;
		    height: 100%;
		    top: 0;
		    left: 0;
		    text-decoration: none;
		    /* Makes sure the link doesn't get underlined */
		    z-index: 10;
		    /* raises anchor tag above everything else in div */
		    background-color: white;
		    /*workaround to make clickable in IE */
		    opacity: 0;
		    /*workaround to make clickable in IE */
		    filter: alpha(opacity=0);
		    /*workaround to make clickable in IE */
		}

		#aimojo-banner-dismiss-button
		{
			display: block;
		    position: absolute;
		    top: 4px;
		    left: 899px;
		    background: gray;
		    z-index: 11;
		    border-color: #0073aa;
		    margin: 0px 0px 0px 0;
		    padding: 1px 13px;
		    height: auto;
		    line-height: 1.4285714;
		    white-space: normal;
		    font-size: 12px;
		    box-shadow: inset 0 1px 0 rgba(120,200,230,.5),0 1px 0 rgba(0,0,0,.15);
		    color: #fff;
		    text-decoration: none;
		    vertical-align: top;
		    cursor: pointer;
		    border-width: 1px;
		    border-style: solid;
		    -webkit-appearance: none;
		    box-sizing: border-box;
		    border-radius: 3px;
		    -webkit-font-smoothing: subpixel-antialiased;
		}


</style>





<script type="text/javascript">

function dismissBanner(postOptionsUrl) 
{
  postRequest( postOptionsUrl );

  document.getElementById("aimojo-notice").style.display = "none";

  return false;
}  

function clickLinkBanner(linkUrl, postOptionsUrl) 
{
  postRequest( postOptionsUrl );

  window.open(linkUrl);

  document.getElementById("aimojo-notice").style.display = "none";

  return false;
}  

// helper function for cross-browser request object
function postRequest(url) 
{
    var req = false;
    try
    {
        // most browsers
        req = new XMLHttpRequest();
    }
    catch (e)
    {
        // IE
        try
        {
            req = new ActiveXObject("Msxml2.XMLHTTP");
        } 
        catch(e) 
        {
            // try an older version
            try
            {
                req = new ActiveXObject("Microsoft.XMLHTTP");
            } 
            catch(e) 
            {
                return false;
            }
        }
    }
    if (!req) 
    	return false;
    if (typeof success != 'function') 
    	success = function () {};
    if (typeof error!= 'function') 
    	error = function () {};
    req.onreadystatechange = function()
    {
        if(req.readyState == 4) 
        {
            return req.status === 200 ? success(req.responseText) : error(req.status);
        }
    }
    req.open("POST", url, true);
    req.send(null);
    return req;
}

</script>





