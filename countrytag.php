<?php

/*
  Plugin Name: WP Country Tag
  Plugin URI: 
  Description: This plugin allows you to add country tag which shows only user access form specific country. Text between [country_text] and [/country_text]. You can use "in" and "not_in" property for set countries.
  Version: 1.0.0
  Author: shipweb
  Author URI: https://www.shipweb.jp/
*/

// WordPressの読み込みが完了してヘッダーが送信される前に実行するアクションに、
// Countrytagクラスのインスタンスを生成するStatic関数をフック
//add_action('init', 'Countrytag::instance');
require_once (plugin_dir_path(__FILE__ ).'include/setting.php');
require_once (plugin_dir_path(__FILE__ ).'include/updatedb.php');

//プラグインactivate時にcron登録
register_activation_hook( __FILE__, 'sct_cron_activate' );
function sct_cron_activate(){
    if ( !wp_next_scheduled( 'countrytag_update_database' ) ) {
        wp_schedule_event(time(), 'daily', 'countrytag_update_database');  //daily
    }
}

//プラグインdeactivate時にcron解除
register_deactivation_hook( __FILE__, 'sct_cron_deactivate' ); 
function sct_cron_deactivate() {
    $timestamp = wp_next_scheduled( 'countrytag_update_database' );
    wp_unschedule_event( $timestamp, 'countrytag_update_database' );
}


class Countrytag {

    /**
     * このプラグインのバージョン
     */
    const VERSION = '1.0.0';

    /**
     * このプラグインのID：shipweb Country Tag
     */
    const PLUGIN_ID = 'sct';

    /**
     * 国データベースファイルパス
     */
    const COUNTRY_DB_FILE = 'Country.mmdb';

    //ログファイル
    const COUNTRY_LOG_FILE = 'log/log.php';
    
    /**
     * PREFIX
     */
    const PLUGIN_PREFIX = self::PLUGIN_ID . '_';

    /**
     * CredentialAction：設定
     */
    const CREDENTIAL_ACTION__SETTINGS_FORM = self::PLUGIN_ID . '-nonce-action_settings-form';
    
    /**
     * CredentialName：設定
     */
    const CREDENTIAL_NAME__SETTINGS_FORM = self::PLUGIN_ID . '-nonce-name_settings-form';

    /**
     * OPTIONSテーブルのキー：Setting
     */
    const OPTION_KEY__SETTINGS = self::PLUGIN_PREFIX . 'settings';
    
    /**
     * 画面のslug：トップ
     */
    const SLUG__SETTINGS_FORM = self::PLUGIN_ID . '-settings-form';

    const SETTINGS_OPTIONS = array(
        'other' => array(
            'prefix' => '1',
            'name' => 'Database',
            'fields' => array(
                'plan' => array(
                    'type' => 'select',
                    'label' => 'Plan',
                    'required' => true,
                    'list' => array('free' => 'Free','paid' => 'Paid'),
                    'default' => 'free',
                    'hint' => 'Select your plan, free or paid for.',
                ), 
                'license_key' => array(
                    'type' => 'text',
                    'label' => 'License key',
                    'required' => false,
                    'default' => '',
                    'hint' => 'Your license key.',
                    'size' => 30,
                ),
                'free_country_db_url' => array(
                    'type' => 'text',
                    'label' => 'GeoLite2 Country Database URL',
                    'required' => false,
                    'default' => 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=YOUR_LICENSE_KEY&suffix=tar.gz',
                    'hint' => 'Database URL for Free',
                    'size' => 90,
                ),
                'paid_country_db_url' => array(
                    'type' => 'text',
                    'label' => 'GeoIP2 Country Database URL',
                    'required' => false,
                    'default' => 'https://download.maxmind.com/app/geoip_download?edition_id=GeoIP2-Country&license_key=YOUR_LICENSE_KEY&suffix=tar.gz',
                    'hint' => 'Database URL for Paid',
                    'size' => 90,
                ),
                'schedule' => array(
                    'type' => 'select',
                    'label' => 'Auto update database',
                    'required' => true,
                    'list' => array('never' => 'Never', 'hourly' => 'Hourly', 'twicedaily' => 'Twice daily', 'daily' => 'Daily', 'weekly' => 'Weekly'),
                    'default' => 'daily',
                    'hint' => 'Intervals to check for updates to the database.',
                ),
                'next_time' => array(
                    'type' => 'hidden',
                    'label' => 'Next check timestamp',
                    'required' => false,
                    'default' => '',
                    'hint' => 'Timestamp next check.',
                    'size' => 90,
                ),                
            ),
        ),
        'info' => array(
            'prefix' => '2',
            'name' => 'Info',
            'fields' => array(),
        ),
        'log' => array(
            'prefix' => '3',
            'name' => 'Log',
            'fields' => array(),
        ),
    );

    /**
     * パラメーターのPREFIX
     */
    const PARAMETER_PREFIX = self::PLUGIN_PREFIX;

    /**
     * 一時入力値保持用のPREFIX
     */
    const TRANSIENT_PREFIX = self::PLUGIN_PREFIX. 'temp-';

    /**
     * 不正入力値エラー表示のPREFIX
     */
    const INVALID_PREFIX = self::PLUGIN_PREFIX. 'invalid-';
    
    /**
     * TRANSIENTキー(保存完了メッセージ)：設定
     */
    const TRANSIENT_KEY__SAVE_SETTINGS = self::PLUGIN_PREFIX . 'save-settings';

    /**
     * TRANSIENTのタイムリミット：5秒
     */
    const TRANSIENT_TIME_LIMIT = 5;

    /**
     * 通知タイプ：エラー
     */
    const NOTICE_TYPE__ERROR = 'error';

    /**
     * 通知タイプ：警告
     */
    const NOTICE_TYPE__WARNING = 'warning';

    /**
     * 通知タイプ：成功
     */
    const NOTICE_TYPE__SUCCESS = 'success';

    /**
     * 通知タイプ：情報
     */
    const NOTICE_TYPE__INFO = 'info';
    
    /**
     * 設定データ
     */
    public $ini;  
    
    /**
     * WordPressの読み込みが完了してヘッダーが送信される前に実行するアクションにフックする、
     *　Countrytagクラスのインスタンスを生成するStatic関数
     */
    static function instance() {
        return new self();
    }
    
    /**
     * HTMLのOPTIONタグを生成・取得
     */
    static function makeHtmlSelectOptions($list, $selected, $label = null) {
        $html = '';
        foreach ($list as $key => $value) {
            $html .= '<option class="level-0" value="' . $key . '"';
            if ($key == $selected || (is_array($selected) && in_array($key, $selected))) {
                $html .= ' selected="selected"';
            }
            $html .= '>' . (is_null($label) ? $value : $value[$label]) . '</option>';
        }
        return $html;
    }

    /**
     * 通知タグを生成・取得
     * @param message 通知するメッセージ
     * @param type 通知タイプ(error/warning/success/info)
     * @retern 通知タグ(HTML)
     */
    static function getNotice($message, $type) {
        return 
            '<div class="notice notice-' . $type . ' is-dismissible">' .
            '<p><strong>' . esc_html($message) . '</strong></p>' .
            '<button type="button" class="notice-dismiss">' .
            '<span class="screen-reader-text">Dismiss this notice.</span>' .
            '</button>' .
            '</div>';
    }

    static function getErrorBar($message, $type){
        return '<div class="error">' .esc_html($message).'</div>';
    }


    /**
     * コンストラクタ
     */
    function __construct() {
        add_action( 'plugins_loaded', [ $this, 'register_stream_connector' ], 99, 1  );

        add_action('init', function(){
            add_shortcode( 'country_text',  [$this,'shortcode_handler_function'] ); //ショートコードのフック
            add_shortcode( 'country_info',  [$this,'shortcode_handler_function_info'] ); //ショートコードのフック

            // 管理画面を表示中、且つ、ログイン済、且つ、特権管理者or管理者の場合
            if (is_admin() && is_user_logged_in() && (is_super_admin() || current_user_can('administrator'))) {
                // 管理画面のトップメニューページを追加
                add_action('admin_menu', ['countrytagSetting', 'set_plugin_menu']);
                // 管理画面各ページの最初、ページがレンダリングされる前に実行するアクションに、
                // 初期設定を保存する関数をフック
                add_action('admin_init', ['countrytagSetting', 'save_settings']);
            }
            //定期実行の登録
            //add_action('wp', [$this,'update_activation']);

            //データベース更新イベント
            add_action('countrytag_update_database', [$this, 'update_activation']);

            // オプションの読み込み
            $this->ini = $this->get_all_options();
        });
    }

    /**
     * 登録されているオプション情報を全て返す
     */
    static function get_all_options(){
        $options = get_option(self::OPTION_KEY__SETTINGS); //オプションを取得
        foreach(self::SETTINGS_OPTIONS as $tab_name => $tab_details){
            //flatten
            foreach($tab_details['fields'] as $option_key => $option_details){
                if(!isset($options[$option_key])){
                    $options[$option_key] = $option_details['default'];
                }
            }
        }
        return $options;
    }

    /**
     * 登録されているオプションの値を返す
     */
    static function get_option($option_name){
        $options = get_option(self::OPTION_KEY__SETTINGS); //オプションを取得
        if(isset($options[$option_name])){
            return $options[$option_name];
        }
        foreach(self::SETTINGS_OPTIONS as $tab_name => $tab_details){
            //flatten
            foreach($tab_details['fields'] as $option_key => $option_details){
                if($option_name == $option_key){
                    return $option_details['default'];
                }
            }
        }
        return null;
    }


    /**
     * オプションの値を登録
     */
    static function set_option($option_name, $option_value){
        $options = get_option(self::OPTION_KEY__SETTINGS); //オプションを取得
        $options[$option_name] = $option_value;
        update_option(Countrytag::OPTION_KEY__SETTINGS, $options);
        return null;
    }
    

    /**
     * ショートコード実行
     */
	function shortcode_handler_function($atts, $content = null, $tag = ''){
        global $sct_countrycode;
        $atts = wp_parse_args($atts, array(
            'in'  => '',
            'not_in' => '',
            'altsc' => '',
        ));
        $output="";

        if(!isset($sct_countrycode)){
            require dirname(__FILE__).'/vendor/autoload.php';

            $dbfile = dirname(__FILE__)."/".self::COUNTRY_DB_FILE;
            if(!file_exists($dbfile)){
                return "<!--Countrytag:ERROR The DB file does not exist.//-->";
            }

            try{
                $reader = new GeoIp2\Database\Reader($dbfile);
            }catch(\MaxMind\Db\InvalidDatabaseException $e){
                return "<!--Countrytag:ERROR Invalid Database.//-->";
            }
            
            $remote_ip = self::getClientIpAddress();    //
            //$remote_ip = '31.24.80.1';//FR 31.24.80.1 //JP 115.36.163.201 //US 23.133.48.100

            try{
                $record = $reader->country($remote_ip);
                $country = strtoupper($record->country->isoCode); // 'US'
                $sct_countrycode = $country;
            }catch(\GeoIp2\Exception\AddressNotFoundException $e){
                $country = NULL;
            }
        }else{
            $country = $sct_countrycode;
        }
        

        $ary_country_in = $atts['in'] ? array_map('trim', explode(',', strtoupper($atts['in']))) : array();
        $ary_country_not_in = $atts['not_in'] ? array_map('trim', explode(',', strtoupper($atts['not_in']))) : array();

        if($country != "" && !empty($ary_country_in) && in_array($country, $ary_country_in)){
            $output = do_shortcode( $content );
        }else if($country != "" && !empty($ary_country_not_in) && !in_array($country, $ary_country_not_in)){
            $output = do_shortcode( $content );
        }else if(!empty($atts['altsc'])){
            $output = do_shortcode( '[sc name="'.$atts['altsc'].'"]' );
        }

		return $output;
	}

    //テスト用ショートコード
    //アクセス元の IP Addressと判定された国を出力
    function shortcode_handler_function_info($atts, $content = null, $tag = ''){
        global $sct_countrycode;
        $atts = wp_parse_args($atts, array(
            'in'  => '',
            'not_in' => '',
            'altsc' => '',
        ));
        $output="";
        require dirname(__FILE__).'/vendor/autoload.php';

        $dbfile = dirname(__FILE__)."/".self::COUNTRY_DB_FILE;
        if(!file_exists($dbfile)){
            return "Countrytag:ERROR The DB file does not exist.";
        }

        try{
            $reader = new GeoIp2\Database\Reader($dbfile);
        }catch(\MaxMind\Db\InvalidDatabaseException $e){
            return "Countrytag:ERROR Invalid Database.";
        }
        
        $remote_ip = self::getClientIpAddress();    //

        try{
            $record = $reader->country($remote_ip);
            $country = strtoupper($record->country->isoCode); // 'US'
            $sct_countrycode = $country;
        }catch(\GeoIp2\Exception\AddressNotFoundException $e){
            $country = "Could not detect.";
        }
        $output .="<p>IP Address: ".$remote_ip."</p><p>Country: ".$country."</p>";

        return $output;
    }

    /**
  * HTTPプロキシやロードバランサーを通過して接続したクライアントのIPアドレスを取得する
  * @return string
  */
    static function getClientIpAddress() {
        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
            $xForwardedFor = explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']);
            if (!empty($xForwardedFor)) {
                return trim($xForwardedFor[0]);
            }
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            return (string)$_SERVER['REMOTE_ADDR'];
        }
        return "";
    }

    /*
    ログ出力
    */
    function register_stream_connector() {
        add_filter(
			'wp_stream_connectors',
			function( $classes ) {
				require_once (plugin_dir_path(__FILE__ ).'include/logging.php');
                $class = new CountrytagConnector();
				$classes[] = $class;
				return $classes;
			}
		);
    }

    static function logging($update_result){
        /*
            $logtext = date("[d/M/Y:H:i:s O] ").$text." ".self::getClientIpAddress()."\n";
            error_log($logtext, 3, plugin_dir_path(__FILE__ ).self::COUNTRY_LOG_FILE);
        */
        $text = $update_result['message'];
        $isError = !$update_result['isSuccess'];
        if(!isset($update_result['isNoLogging']) || $update_result['isNoLogging'] === false){
            if ( class_exists( 'CountrytagConnector' ) ) {
                $class = new CountrytagConnector();
                $class->callback_Countrytag_update_db(array('id'=>null,'title'=>$text) , $isError);
            }
        }
    }

    //定期実行のチェックと実行
    function update_activation(){
        /*
        $plugin_options = Countrytag::get_all_options();
        if ($plugin_options['schedule'] != "never" && (!$plugin_options['next_time'] || time() > intval($plugin_options['next_time']))) {
            $schedule_interval = array(
                'never' => 0,
                'hourly' => 3600,
                'twicedaily' => 43200,
                'daily' => 86400,
                'weekly' => 604800,
            );
            $new_next_time = time() + intval($schedule_interval[$plugin_options['schedule']]);
            Countrytag::set_option('next_time', $new_next_time);
            $update_result = updateCountryDB::updatedb();
            Countrytag::logging($update_result);
        }
        */
        $plugin_options = Countrytag::get_all_options();
        if ($plugin_options['schedule'] != "never"){
            $schedule_interval = array(
                'never' => 0,
                'hourly' => 3600,
                'twicedaily' => 43200,
                'daily' => 86400,
                'weekly' => 604800,
            );
            $new_next_time = time() + intval($schedule_interval[$plugin_options['schedule']]);
            Countrytag::set_option('next_time', $new_next_time);
            $update_result = updateCountryDB::updatedb();
            Countrytag::logging($update_result);
        }
    }

    //DBファイルの最新版が出ているかどうかチェック
    static function isNeedUpdate(){
        $dbfile = plugin_dir_path(__FILE__).self::COUNTRY_DB_FILE;
        require_once plugin_dir_path(__FILE__).'vendor/autoload.php';
        $plugin_options = self::get_all_options();
        if(file_exists($dbfile) && !empty($plugin_options['license_key'])){
            $dbmtime_local = filemtime($dbfile);
            $url = str_replace('YOUR_LICENSE_KEY', $plugin_options['license_key'], $plugin_options[$plugin_options['plan'].'_country_db_url']);
            if($url){
                $client = new GuzzleHttp\Client();
                try {
                    $response = $client->head( $url );
                    if($response->getStatusCode() === 200){
                        if ($response->hasHeader('last-modified')) {
                            $dbmtime_remote = $response->getHeader('last-modified')[0];
                            if(strtotime($dbmtime_remote) > $dbmtime_local){
                                return true;
                            }else{
                                return false;
                            }                        
                        }
                    }
                }catch(GuzzleHttp\Exception\RequestException $e){
                    return true;
                }catch(GuzzleHttp\Exception\ConnectException $e){
                    return true;
                }
            }
        }
        
        return true;
    }
    
} // end of class
$GLOBALS['Countrytag'] = new Countrytag;

?>