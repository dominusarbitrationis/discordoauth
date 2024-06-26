<?php

class SteamAuthPlugin extends MantisPlugin {

	var $cmv_pages;
	var $current_page;

	function register() {
		$this->name = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->page        = 'config_page';

		$this->version  = '2.0';
		$this->requires = array(
			'MantisCore' => '2.0.0',
		);

		$this->author  = 'Quinn Beltramo';
		$this->contact = 'dominusarbitrationis@users.noreply.github.com';
		$this->url     = 'arcengames.com';
	}

	function init() {
		$this->cmv_pages    = array(
			'login_page.php'
		);
		$this->current_page = basename( $_SERVER['PHP_SELF'] );
	}

	function hooks() {
		return array(
			'EVENT_LAYOUT_RESOURCES' => 'resources',
			'EVENT_PLUGIN_INIT'=> 'doLogin',
			'EVENT_AUTH_USER_FLAGS' => 'auth_user_flags',
		);
	}

	function config() {
		return array(
			'steamAPIKeyId' => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
			'domainName'    => $_SERVER['SERVER_NAME'],
		);
	}
	function doLogin(){
		if ( ! in_array( $this->current_page, $this->cmv_pages ) ) {
			return '';
		}else{
		if ( isset($_GET['steam'] ) )
    {
        try
        {
			require_once( 'pages/openid.php' );
			$openid = new LightOpenID( plugin_config_get( 'domainName' ) );

            if ( !$openid->mode) 
			{
				$openid->identity = 'https://steamcommunity.com/openid';
                header( 'Location: '. $openid->authUrl() );
            }
            elseif($openid->mode == 'cancel' ) 
			{
                plugin_log_event('User has canceled authentication!');
            } else
            {
				if ($openid->validate()) 
				{ 
					$id = $openid->identity;
					$ptn = "/^https?:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
					preg_match($ptn, $id, $matches);
					$_SESSION['steamid'] = $matches[1];
					$url = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=".plugin_config_get( 'steamAPIKeyId' )."&steamids=".$_SESSION['steamid'];
					$context = stream_context_create( array( 'http' => array( 'header'=>'Connection: close\r\n' ) ) );
					$json_source = file_get_contents( $url. "&format=json",false, $context );
					$json_output = json_decode($json_source, false );
					$steamid = $_SESSION['steamid'];
					foreach ($json_output->response->players as $player)
					{
						$regOptions = array(
							'username' => 'steamuser-'.$player->personaname,
							'email' => 'steamuser-' . $player->steamid . '@' . "steamcommunity.com",
							'member_name' => 'steamuser-' . $player->steamid,
							'real_name' => $player->personaname,
						);
						var_dump($regOptions);
						$t_username = $regOptions['username'];
						$t_email = $regOptions['email'];
						$t_user_id_email = empty($t_email) ? false : user_get_id_by_email( $t_email );
						$t_realname = $regOptions['real_name'];
						if (!$t_user_id_email) 
						{
							if (!empty($t_username)) 
							{
								$number = 1;
								while(user_get_id_by_name( $t_username )){
									$t_username .= "_" . $number;
									$number++;
								}
								user_create($t_username, auth_generate_random_password(), $t_email, auth_signup_access_level(), false, true, $t_realname);
								plugin_log_event("User creation has run.");
								$t_user_id = user_get_id_by_email( $t_email );
								auth_login_user( $t_user_id );
								header("Location:".__FILE__);
								return;
							}
							plugin_log_event("Either no username was passed, or autocreation is turned off.");
							return;
						}
						else 
						{
							if ($t_user_id_email && auth_get_current_user_id() !== $t_user_id_email)
							{
								plugin_log_event("User found from email, login now");
								auth_login_user( $t_user_id_email );
								header("Location:".__FILE__);
								return;
							}
							else
								plugin_log_event("User already logged in!");
						}
					}
				}
			}
		}
        catch ( ErrorException $e) 
		{
            echo $e->getMessage();
        }
    }
		}	
	}

	function resources() {
		if ( ! in_array( $this->current_page, $this->cmv_pages ) ) {
			return '';
		}

		return '
			<meta name="steamAPIKeyId" content="' . plugin_config_get( 'steamAPIKeyId' ) . '" />
			<style>
			#plugin_steamauth {
				margin-top:20px;
				padding-top:20px;
				border-top: 1px solid #CCC;
				text-align:right;
				}
				#plugin_steamauth a {
						background: url('.plugin_file("steam_signin.png").');
						background-size:contain;
						background-repeat:no-repeat;
						text-indent: 100%;
						white-space: nowrap;
						overflow: hidden;
						display: inline-block;
						height: 46px;
						width: 191px;
						margin-right:25px;
				}
			</style>
			<script type="text/javascript" src="'.plugin_file("plugin.js").'"></script>
		';
	}
	function auth_user_flags( $p_event_name, $p_args ) {
		$t_username = $p_args['username'];

		$t_user_id = $p_args['user_id'];
		
		# Don't access DB if db_is_connected() is false.
		# If user is unknown, don't handle them. Even anonymous users have an ID
		if( !$t_user_id ) {
			return null;
		}

		# use the custom authentication
		if (strpos($t_username, 'steamuser-') === 0) {
		$t_flags = new AuthFlags();

		# Passwords managed externally for all Steam users
		$t_flags->setCanUseStandardLogin( false );

		$t_flags->setPasswordManagedExternallyMessage( 'Passwords are managed by Steam. Go to Steam to change!' );

		return $t_flags;
	}
	}
}
