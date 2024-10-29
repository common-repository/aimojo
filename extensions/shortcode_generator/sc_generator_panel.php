<?php
/**
Settings for aimojo shortcode generator extension
*/

function aimojo_sc_generator_menu()
 {
	add_submenu_page(
	  'aimojo-gstarted',               //$parent_slug
	  'shortcode generator',               //$page_title
	  'shortcode generator',               //$menu_title
	  'manage_options',             //$capability
	  'aimojo-ext-sc-generator',    //$menu_slug
	  'ext_sc_generator_content'    //$function
	  );

  }

function ext_sc_generator_content()
{
	//setup the default values to be used
	$defaultTitle = "Affinitomic Relationships:";
	$defaultLinkNum = 10;

  ?>
	<link rel="stylesheet" href="<?php echo plugin_dir_url( __FILE__ ) ?>/sc_generator_styles.css">
	<link rel="stylesheet" href="http://yui.yahooapis.com/pure/0.6.0/pure-min.css">
	<script src="<?php echo plugin_dir_url( __FILE__ ) ?>/sc_generator_script.js"></script>

	  <div class="wrap">
	    <h2>ai&#8226;mojo&#0153; Shortcode Generator Extension</h2>
	    <h2 class="nav-tab-wrapper">
	      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-gstarted">Getting Started</a>
	      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-basic-settings">Settings</a>
	      <a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>admin.php?page=aimojo-extensions">Extensions</a>
	      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-export-tab">Export</a>
      	  <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-documentation">Documentation</a>
	      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-credits">Credits</a>
	    </h2>
	</div>


	<div class="pure-g">
		<div class="pure-u-1">
		<div class="aimojo-tab-header">
			<h2>Shortcode Generator <span style="font-size:0.8em;font-weight:normal">( <a href="admin.php?page=aimojo-extensions">Back to Extensions Main Panel</a> )</span> </h2>
		</div>
		<div class="pure-alert">Shortcodes in ai&#8226;mojo&#0153;
		are reletivly easy to use, but we've made generating shortcodes for smart menus even
		easier. Fill in the fields below to automatically generate a shortcode that you can
		copy and paste into your posts, pages, etc. to display the ai&#8226;mojo&#0153;
		Affinitomics&#0153; relationships.
		</div>
		</div>
		<div class="pure-u-1">
        <div class="af_control">
            <label for="name"></label>
            <input class="af_input" id="af_title" type="text"  onchange="updateAfview();"  onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange();"  placeholder="<?php echo $defaultTitle ?>">
            <label for="cb" class="af_label">
                <input id="af_cb_display_title" type="checkbox"  onchange="updateAfview()" checked="checked">
                Display Title?
            </label>
        </div>


       <div class="af_control">
            <input class="af_input" id="af_categories_to_filter"  onchange="updateAfview();"  onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange();"  type="text" placeholder="use all categories">
            <label class="af_label" for="password">Categories (slug or id) to filter by.</label>
        </div>


       <div class="af_control">
       		<label>
	       		<select id="af_selectLinkNum" onchange="updateAfview()">
				<option value="<?php echo $defaultLinkNum ?>" selected><?php echo $defaultLinkNum ?></option>

				  <option value="1">1</option>
				  <option value="2">2</option>
				  <option value="3">3</option>
				  <option value="4">4</option>
				  <option value="5">5</option>
				  <option value="6">6</option>
				  <option value="7">7</option>
				  <option value="8">8</option>
				  <option value="9">9</option>
				  <option value="10">10</option>
				  <option value="11">11</option>
				  <option value="12">12</option>
				  <option value="13">13</option>
				  <option value="14">14</option>
				  <option value="15">15</option>
				</select>
			</label>
            <label class="af_label" for="info">Number of related links to show</label>
        </div>

		<div id="af_shortcodeLabel">
	        <label id="af_generated_shortcode_label" for="foo"><span style="font-size: 16px; color: Gray;">Generated ai&#8226;mojo&#0153; Affinitomics&#0153; Shortcode - cut and past the below shortcode into your page or post:</span></label>
	        <div id="af_generated_shortcode">[afview]</div>

	    </div>
	    </div>

	</div>
</div>

<?php

}

?>
