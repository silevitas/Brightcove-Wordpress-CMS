<?php
/*
Plugin Name: VideoCloud
Plugin URI: http://www.brightcove.com
Description: Brightcove CMS API and new player
Author: Ben Clifford
Author URI: http://www.brightcove.com
*/

// Hook for adding admin menus
add_action('admin_menu', 'bc_add_pages');

// action function for above hook
function bc_add_pages() {
  // Add a new submenu under Settings:
  add_options_page(__('Brightcove Video Cloud Settings','brightcove-settings'), __('Video Cloud','brightcove-settings'), 'manage_options', 'videocloudsettings', 'bc_settings_page');
}

//Add to media tab
add_filter('media_upload_tabs', 'brightcove_video_cloud_media_menu');
add_action('media_upload_brightcove_video_cloud', 'brightcove_video_cloud_menu_handle');

add_shortcode('brightcove_player', 'handle_brightcove_shortcode');

function handle_brightcove_shortcode($atts, $content = null) {
  // insert player iframe
  extract(shortcode_atts(array('video' => null), $atts));
  $p_id = get_option('bc_player_id');
  $acc = get_option( 'bc_account_id' );
  $player = "<iframe width='640px' height='360px' frameborder=0 ";
  $player .= "src='//players.brightcove.net/" . $acc . "/" . $p_id . "_default/index.html";
  $player .= "?videoId=" . $video . "' ";
  $player .= "allowfullscreen webkitallowfullscreen mozallowfullscreen></iframe>";
  return $player;
}

function brightcove_video_cloud_media_menu($tabs) {
  $tabs['brightcove_video_cloud']='Brightcove Video Cloud';
  return $tabs;
}

function brightcove_video_cloud_tab() {
  // video cloud tab in media dialog
  // has upload from dropbox and one page of results
  // cms search needs to be ajax
  wp_enqueue_script( 'jquery' );
  echo media_upload_header();
  echo brightcove_upload_form();
  $video_json = get_cms_response();
  ?><style type="text/css">
  .brightcove_thumb { width: 120px ;}
</style>
<table>
  <?php
  foreach ($video_json as $video) {
    echo '<tr><td><a id="' . $video->id . '" class="insert_video">';
    echo '<img class="brightcove_thumb" src="' . $video->images->thumbnail->src . '"/>' . $video->name . '</a></td></tr>';
  }
  ?></table>
  <script>
    jQuery(".insert_video").click(function(){
      var shortcode = '[brightcove_player video=' + jQuery(this).attr('id') + ']';
      (window.dialogArguments || opener || parent || top).send_to_editor(shortcode);
    });
  </script><?php
}

function brightcove_upload_form() {
?><script type="text/javascript" src="https://www.dropbox.com/static/api/2/dropins.js" id="dropboxjs" data-app-key="haonjc8rf0ugfrj"></script>

<p>Create Video Cloud video from Dropbox: <div id="dropbox_container"></div></p>

<script type="text/javascript">
var oauth_token;

    // Set up Dropbox button
    options = {
      success: function(files) {
        jQuery.post(
          ajaxurl, 
            {
              'action': 'brightcove_upload',
              'url':   files[0].link
            }, 
          function(response){
            var shortcode = '[brightcove_player video=' + response.id + ']';
            (window.dialogArguments || opener || parent || top).send_to_editor(shortcode);
          }
        );
      },
      cancel: function() {},
      linkType: "direct",
      multiselect: false,
      extensions: ['video']
    };
    var button = Dropbox.createChooseButton(options);
    document.getElementById("dropbox_container").appendChild(button);
</script>
<hr />
<?php
}

// ajax callback for create video
add_action( 'wp_ajax_brightcove_upload', 'brightcove_upload_callback' );
function brightcove_upload_callback() {
  global $wpdb; // this is how you get access to the database
  $url = $_POST['url'];
  $token = get_brightcove_token();
  $acc = get_option( 'bc_account_id' );
  $cms_response = wp_remote_post( 'https://cms.api.brightcove.com/v1beta1/accounts/' . $acc . '/videos', array(
    'blocking' => true,
    'headers' => array( 'Authorization' => 'Bearer ' . $token->response, 'Content-Type' => 'application/json' ),
    'body' => '{"name":"wordpress dropbox"}'
  ));
  $new_video = json_decode($cms_response['body']);
  $di_response = wp_remote_post( 'https://ingest.api.brightcove.com/v1/accounts/' . $acc . '/videos/' . $new_video->id . '/ingest-requests', array(
    'blocking' => true,
    'headers' => array( 'Authorization' => 'Bearer ' . $token->response, 'Content-Type' => 'application/json' ),
    'body' => '{"profile":"balanced-high-definition", "master": {"url":"' . $url . '"}}'
  ));
  wp_send_json($new_video);
  die(); // this is required to terminate immediately and return a proper response
}

function brightcove_video_cloud_menu_handle() {
  return wp_iframe( 'brightcove_video_cloud_tab' );
}

// bc_settings_page() displays the page content for the Test settings submenu
function bc_settings_page() {
  //must check that the user has the required capability 
  if (!current_user_can('manage_options'))
  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }
  $cid_display_name = 'Client ID';
  $cid_data_field_name = 'bc_client_id';

  $cs_display_name = 'Client Secret';
  $cs_data_field_name = 'bc_client_secret';

  $acc_opt_name = 'bc_account_id';
  $acc_display_name = 'Video Cloud Account ID';
  $acc_data_field_name = 'bc_account_id';

  $pid_opt_name = 'bc_player_id';
  $pid_display_name = 'Brightcove Player ID';
  $pid_data_field_name = 'bc_player_id';

  $hidden_field_name = 'bc_submit_hidden';

  $acc_opt_val = get_option( $acc_opt_name );
  $pid_opt_val = get_option( $pid_opt_name ) ?: 'default';

  if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
    // Read the posted values
    $cid_opt_val = $_POST[ $cid_data_field_name ];
    $cs_opt_val = $_POST[ $cs_data_field_name ];
    $acc_opt_val = $_POST[ $acc_data_field_name ];
    $pid_opt_val = $_POST[ $pid_data_field_name ];

    // Save the posted values in the database
    if (($cid_opt_val != '') && ($cs_opt_val != '')) {
      update_option('bc_client_id', $cid_opt_val);
      update_option('bc_credentials', base64_encode($cid_opt_val . ':' . $cs_opt_val));
    }

    update_option( $acc_opt_name, $acc_opt_val );
    update_option( $pid_opt_name, $pid_opt_val );

    // Put an settings updated message on the screen

    ?>
    <div class="updated">
      <p><strong><?php _e('Settings saved.', 'brightcove-settings' ); ?></strong></p>
    </div>
    <?php
    $token = get_brightcove_token(true);
    if ($token->state == 'ok') {
      echo('<div class="updated"><p>Token retrieved.</p></div>');
    }
    else if ($token->state == 'cached') {
      // this shouldn't happen -- get_brightcove_token(true) forces a new token 
      echo('<div class="updated"><p>Token still valid.</p></div>');
    }
    else {
      echo('<div class="error"><p><strong>Unable to get token. Re-enter client ID and secret.</strong></p>' . $token->response . '</p></div>');
    }
  }
  $bc_client_id = get_option('bc_client_id');
  $bc_credentials = get_option('bc_credentials');
  echo '<div class="wrap">';
  echo "<h2>" . __( 'Brightcove Video Cloud Settings', 'brightcove-settings' ) . "</h2>";
  ?>

  <form name="form1" method="post" action="">
    <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
    <table class="form-table">
      <tbody>
        <tr>
          <th scope="row">
            <label for="<?php echo $cid_data_field_name; ?>"><?php _e( $cid_display_name, 'brightcove-settings' ); ?></label>
          </th>
          <td>  
            <input type="text" name="<?php echo $cid_data_field_name; ?>"
            value="" size="50"<?php echo ($bc_credentials!==false ? "" : " required" ) ?>
            placeholder="<?php echo ($bc_credentials!==false ? $bc_client_id : "Enter client ID" ) ?>">
          </td>
        </tr>    
        <tr>
          <th scope="row">
            <label for="<?php echo $cs_data_field_name; ?>"><?php _e( $cs_display_name, 'brightcove-settings' ); ?></label>
          </th>
          <td>
            <input type="password" name="<?php echo $cs_data_field_name; ?>"
            value="" size="50"<?php echo ($bc_credentials!==false ? "" : " required" ) ?>
            placeholder="<?php echo ($bc_credentials!==false ? "Saved" : "Enter client secret" ) ?>">
            <?php
            if ($bc_credentials!==false) {
              echo '<p class="description">Input both a client ID and client secret to update the credentials used.<br/>Leave blank to keep the current settings.</p>';
            }
            else {
              echo '<p class="description">Input both a client ID and client secret to set the credentials used.</p>';
            }
            ?>
          </td>
        </tr>    
        <tr>
          <th scope="row">
            <label for="<?php echo $acc_data_field_name; ?>"><?php _e( $acc_display_name, 'brightcove-settings' ); ?></label>
          </th>
          <td>
            <input type="text" name="<?php echo $acc_data_field_name; ?>"
            value="<?php echo $acc_opt_val; ?>" size="50" required>
          </td>
        </tr>    
        <tr>
          <th scope="row">
            <label for="<?php echo $pid_data_field_name; ?>"><?php _e( $pid_display_name, 'brightcove-settings' ); ?></label>
          </th>
          <td>
            <input type="text" name="<?php echo $pid_data_field_name; ?>"
            value="<?php echo $pid_opt_val; ?>" size="50" required>
            <p class="description">Enter the ID of a &ldquo;next generation&ldquo; Brightcove player.</p>
          </td>
        </tr>
      </tbody>
    </table>
    <p class="submit">
      <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save changes and test credentials') ?>" />
    </p>

  </form>
</div>

<?php
}
function get_brightcove_token($force_refresh=false) {
  // Returns an object with the token and its expiry time, or an error
  // Unless $force_refresh=true a token is not requested from the API
  // if the cached token is still valid
  // $token->response = token or error message
  // $token->state = ok|cached|error
  // $token->expires = expiry (set to 90% of the actual expiry time)

  $token;

  // Check for cached token if force-refresh not requested
  if (!$force_refresh && get_option( 'bc_oauth_token' )) {
    $token->response = get_option( 'bc_oauth_token' );
    $token->expires = get_option( 'bc_oauth_token_expires' );
    if (time() < $token->expires) {
      $token->state = 'cached';
      return $token;
    }
  } 

  $bc_credentials = get_option('bc_credentials');
  if (!isset($bc_credentials)) {
    $token->state = 'error';
    $token->response = 'Client ID and secret not set.';
    return $token;
  }

  $response = wp_remote_post( 'https://oauth.brightcove.com/v3/access_token', array(
    'method' => 'POST',
    'blocking' => true,
    'headers' => array( 'Authorization' => 'Basic ' . $bc_credentials ),
    'body' => array( 'grant_type' => 'client_credentials')
   ));

  if ( is_wp_error( $response ) ) {
    $token->state = 'error';
    $token->response = $response->get_error_message();
    return $token;
  }
  $token_response = json_decode($response['body']);
  if (!isset($token_response->access_token)) {
    $token->state = 'error';
    $token->response = $output;
    return $token;
  }
  else {
    $token->state = 'ok';
    $token->expires = time() + ($token_response->expires_in * 0.9 );
    $token->response = $token_response->access_token;
    update_option( 'bc_oauth_token', $token_response->access_token );
    update_option( 'bc_oauth_token_expires', $token->expires );
    return $token;
  }
}

function get_cms_response() {
  // Just gets first page of videos
  $token = get_brightcove_token();
  $acc = get_option( 'bc_account_id' );
  if ($token->state == 'ok' || $token->state == 'cached') {
    $response = wp_remote_get( 'https://cms.api.brightcove.com/v1beta1/accounts/' . $acc . '/videos', array(
    'blocking' => true,
    'headers' => array( 'Authorization' => 'Bearer ' . $token->response )
   ));
   return json_decode($response['body']);
  }
}

?>