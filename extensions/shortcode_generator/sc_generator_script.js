jQuery(document).ready( function() 
{
	updateAfview();
});

//this function is used to update the shortcode generated from the options the user has chosen in the shortcode generator
function updateAfview()
{
	var updatedAfviewTag = '[afview';

	//first grab all the values 
	var selectedNumLinks = jQuery('#af_selectLinkNum').val();
	updatedAfviewTag =  updatedAfviewTag.concat(' limit=');
	updatedAfviewTag =  updatedAfviewTag.concat(selectedNumLinks);


	if ( jQuery('#af_cb_display_title').prop( "checked" ) )
	{
		updatedAfviewTag =  updatedAfviewTag.concat(' title="');
		var displayTitle = jQuery('#af_title').val();
		if (displayTitle.length < 1)
		{	//grab the placeholder text 
			displayTitle = jQuery('#af_title').attr("placeholder");
		}
		updatedAfviewTag =  updatedAfviewTag.concat(displayTitle);
		updatedAfviewTag =  updatedAfviewTag.concat('"');
	}
	else
	{
		updatedAfviewTag =  updatedAfviewTag.concat(' display_title="false"');			
	}


	var categoryFilter = jQuery('#af_categories_to_filter').val();
	if (categoryFilter.length > 0)
	{
		updatedAfviewTag =  updatedAfviewTag.concat(' category_filter="');
		updatedAfviewTag =  updatedAfviewTag.concat(categoryFilter);
		updatedAfviewTag =  updatedAfviewTag.concat('"');

	}


	updatedAfviewTag =  updatedAfviewTag.concat(']');
	jQuery('#af_generated_shortcode').text(updatedAfviewTag);
}