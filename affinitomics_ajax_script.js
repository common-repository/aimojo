jQuery(document).ready( function() {
  request_counter = 0;

  jQuery( "[name='af_view_placeholder']" ).each(function()
  {
    shouldRun = this.alt;
    request_counter += 1;
    url = this.value;
    jQuery.ajax({
      type : "get",
      dataType : "json",
      url : 'http://' + url + '&request_counter=' + request_counter,
      data : { },
      success: function(response) 
      {
        if (response['status'] == 'success')
        {
          this_response_number = response['request_counter']
          my_ul_id = 'af_view_list_' + this_response_number;
          jQuery('<ul class="aflist" id="af_view_list_' + this_response_number + '"></ul>').insertAfter(
            jQuery( "[name='af_view_placeholder']" )[this_response_number - 1]);
          jQuery.each(response['related'], function()
          {
            if (shouldRun == 0)
            {
              jQuery('#' + my_ul_id).append('<li> Register for free at Affinitomics.com to view related posts! </li>');      
            }
            else
            {
              jQuery('#' + my_ul_id).append('<li><a href="' + this.element.url + '">' + this.element.title + ' (' + this.score + ')' + '</a></li>');
            }
          });
        } 
        else 
        {
          console.log('Error, response["status"] != "success"');
        }
      }
    });
  });

  var placeholderCount = jQuery( "[name='af_cloud_sync_placeholder']" ).length;

  jQuery( "[name='af_cloud_sync_placeholder']" ).each(function(){
    okToGo = jQuery('#af_cloud_sync_go').val();


    jQuery( "[name='aimojo_export_progress']" ).attr("max", placeholderCount);

    if (okToGo == 'yes'){

      jQuery('#aimojo-progress-div').css({'display': 'block'});
      jQuery('#aimojo-export-form').css({'display': 'none'});


      url = this.value;
      jQuery.ajax({
        type : "get",
        dataType : "json",
        url : 'http://' + url,
        data : { },
        success: function(response) {
          if (response['status'] == 'success')
          {
      //      jQuery('.cloud_sync_ol').append('<li>' + JSON.stringify(response) + '</li>');
       
            updatePostUrl = 'admin.php?page=aimojo-export-tab&quietUpdate=1&postID=' +  response['params']['uid'] + '&afid=' + response['data']['objectId'];
            postRequest(updatePostUrl);

            updateProgressBar(1, placeholderCount);
          } 
          else 
          {
            console.log('Error, response["status"] != "success"');
          }
        }
      });
    }
  });

});

function updateProgressBar(interval, max)
{
    progress =  jQuery( "[name='aimojo_export_progress']" );
    progress.val(progress.val()+interval);
    var percentDone = (progress.val() / max) * 100;
    percentDone = Math.floor(percentDone);
    jQuery('#aimojo-export-status').text(percentDone + '%');
    if ( progress.val()+interval < progress.attr('max'))
    {

    }
    else 
    { 
        jQuery('#aimojo-export-status').text('Done!');
        progress.val(progress.attr('max'));
    }
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

