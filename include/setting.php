<?php
/**
 * Country Tag
 * 管理画面でのプラグイン設定画面
 */
class countrytagSetting{
    /**
     * 管理画面メニューの基本構造が配置された後に実行するアクションにフックする、
     * 管理画面のトップメニューページを追加する関数
     */
    static function set_plugin_menu() {
        // 設定のサブメニュー「Country Tag」を追加
        $page_hook_suffix = add_options_page(
            // ページタイトル：
            'Country Tag Setting',
            // メニュータイトル：
            'Country Tag',
            // 権限：
            // manage_optionsは以下の管理画面設定へのアクセスを許可
            // ・設定 > 一般設定
            // ・設定 > 投稿設定
            // ・設定 > 表示設定
            // ・設定 > ディスカッション
            // ・設定 > パーマリンク設定
            'manage_options',
            // ページを開いたときのURL(slug)：
            Countrytag::SLUG__SETTINGS_FORM,
            // メニューに紐づく画面を描画するcallback関数：
            ['countrytagSetting', 'show_settings']
        );
        add_action( "admin_print_styles-{$page_hook_suffix}", ['countrytagSetting', 'wpdocs_plugin_admin_styles']);
        add_action( "admin_print_scripts-{$page_hook_suffix}", ['countrytagSetting', 'wpdocs_plugin_admin_scripts']);
    }
    
    /**
     * 初期設定画面を表示
     */
    static function show_settings() {
        // プラグインのオプション
        $plugin_options = Countrytag::get_all_options();

        // 初期設定の保存完了メッセージ
        if (false !== ($complete_message = get_transient(Countrytag::TRANSIENT_KEY__SAVE_SETTINGS))) {
            $complete_message = Countrytag::getNotice($complete_message, Countrytag::NOTICE_TYPE__SUCCESS);
        }
        
        // nonceフィールドを生成・取得
        $nonce_field = wp_nonce_field(Countrytag::CREDENTIAL_ACTION__SETTINGS_FORM, Countrytag::CREDENTIAL_NAME__SETTINGS_FORM, true, false);

        // 開いておくタブ
        $active_tab = 0;
        echo <<< EOM
        {$complete_message}
        <form action="" method='post' id="country-tag-settings-form">
        {$nonce_field}
        <div class="wrap ui-tabs ui-corner-all ui-widget ui-widget-content" id="stabs">
            <ul class="ui-tabs-nav ui-corner-all ui-helper-reset ui-helper-clearfix ui-widget-header">
EOM;
        foreach(Countrytag::SETTINGS_OPTIONS as $tab_name => $tab_details){    
                echo "<li class='ui-tabs-tab ui-corner-top ui-state-default ui-tab'><a href='#stabs-{$tab_details['prefix']}'>{$tab_details['name']}</a></li>";
        }
        echo <<< EOM
                </ul>
EOM;
        foreach(Countrytag::SETTINGS_OPTIONS as $tab_name => $tab_details){
            switch($tab_name){   
                case "info":
                    //タブ
                    $dbfile = plugin_dir_path(dirname(__FILE__)).Countrytag::COUNTRY_DB_FILE;
                    echo <<< EOM
                    <div id="stabs-{$tab_details['prefix']}"  class="ui-tabs-panel ui-corner-bottom ui-widget-content">
                        <h3>{$tab_details['name']}</h3>
                        <p>
                            <label>Country DB File: </label>
EOM;
                    echo $dbfile;
                    echo <<< EOM
                        </p>
                        <p>
                            <label>File size: </label>
EOM;
                    if(file_exists($dbfile)){
                        echo countrytagSetting::size_convert(filesize($dbfile));

                    }else{
                        echo "No DB file";
                    }
                    
                    echo <<< EOM
                        </p>
                        <p>
                            <label>Last modified:</label>
EOM;
                    if(file_exists($dbfile)){
                        $dbmtime_local = filemtime($dbfile);
                        echo date("Y-m-d H:i:s",$dbmtime_local);
                        if(Countrytag::isNeedUpdate()){
                            echo " Updates are available";
                        }else{
                            echo " DB is up to date.";
                        }  
                        
                    }

                    echo <<< EOM
                        </p>
                        <p>
                            <label>Auto update: </label>
EOM;
                    echo $plugin_options['schedule']!="never"?"Enabled":"Disabled";
echo <<< EOM
                        </p>
                        <p>
                            <label>Next schedule: </label>
EOM;
                    if($plugin_options['schedule']!="never"){
                        /*
                        if($plugin_options['next_time']){
                            echo date("Y-m-d H:i:s", intval($plugin_options['next_time']));
                            $next_time = new DateTime('@'.$plugin_options['next_time']);
                            $now_time = new DateTime();
                            $interval = $next_time->diff($now_time);
                            echo " ( in ".$interval->format('%a days %h hours %i minutes').")";
                        }else{
                            echo "Not set";
                        }
                        */
                        $timestamp = wp_next_scheduled( 'countrytag_update_database' );
                        if($timestamp){
                            $next_time = new DateTime('@'.$timestamp);
                            $now_time = new DateTime();
                            $interval = $next_time->diff($now_time);
                            echo date("Y-m-d H:i:s", $timestamp);
                            if($interval->invert){
                                echo " ( in ".$interval->format('%a days %h hours %i minutes').")";
                            }else{
                                echo " ( ".$interval->format('%a days %h hours %i minutes')." ago)";
                            } 
                        }else{
                            echo "Not set";
                        }
                        
                    }else{
                        echo "Never";
                    }
                    echo <<< EOM
                        </p>
                            <button type=submit name=updatedb value=1 class="button">Update DB now</button>
                        <p>
                        </p>
                    </div>
EOM;
                    break;
                case "log":
                    echo <<< EOM
                    <div id="stabs-{$tab_details['prefix']}"  class="ui-tabs-panel ui-corner-bottom ui-widget-content">
                        <h3>{$tab_details['name']}</h3>
                        <p>
                            You can install <a href='https://wordpress.org/plugins/stream/'>Stream plugin</a> to view logs.
                        </p>
                    </div>
EOM;
                    /*
                    //$logfile = plugin_dir_path(dirname(__FILE__)).Countrytag::COUNTRY_LOG_FILE;
                    echo <<< EOM
                    <div id="stabs-{$tab_details['prefix']}"  class="ui-tabs-panel ui-corner-bottom ui-widget-content">
                        <h3>{$tab_details['name']}</h3>
                        <p>
                            <div style="width:100%; max-height:1000px; overflow: scroll; border:1px solid #333">
EOM;
                    $log_content = file_get_contents($logfile);
                    if($log_content){
                        foreach(explode("\n",$log_content) as $line){
                            echo $line."<br>";
                        }
                    }
                    echo <<< EOM
                            </div>
                        </p>
                    </div>
EOM;
*/
                    break;
                default:
                    //タブ
                    echo <<< EOM
                    <div id="stabs-{$tab_details['prefix']}"  class="ui-tabs-panel ui-corner-bottom ui-widget-content">
                        <h3>{$tab_details['name']}</h3>
EOM;
                    $ary_option = array();
                    foreach($tab_details['fields'] as $option_key => $option_details){
                    
                        $options = array();
                        
                        // 不正メッセージ
                        if (false !== ($invalid = get_transient(Countrytag::INVALID_PREFIX.$option_key))) {
                            $options['invalid'] = Countrytag::getErrorBar($invalid, Countrytag::NOTICE_TYPE__ERROR);
                            $active_tab = intval($tab_details['prefix']) - 1;
                        }else{
                            $options['invalid'] = "";
                        }
                        //パラメータ名
                        $options['param'] = Countrytag::PARAMETER_PREFIX.$option_key.(isset($option_details['isMulti'])&&$option_details['isMulti']==true?"[]":"");

                        //設定値
                        if (false === ($value = get_transient(Countrytag::TRANSIENT_PREFIX.$option_key))) {
                            // 無ければoptionsテーブルから取得
                            $value = $plugin_options[$option_key];
                            // それでもなければデフォルト値
                        }
                        $options['value'] = is_array($value) ? $value : esc_html($value);

                        $error_class = $options['invalid'] ? 'class="error-message" ':'';
                        $required = isset($option_details['required'])&&$option_details['required'] ? "required" : "";
                        $hint =  isset($option_details['hint']) ? "<a href=# title='".$option_details['hint']."'><span class='ui-icon ui-icon-info'></span></a>" : "";
                        $size =  isset($option_details['size'])&&$option_details['size'] ? 'size="'.$option_details['size'].'" ':'';
                        switch($option_details['type']){
                            case 'hidden':
                                echo <<< EOM
                                <p>
EOM;
                                break;
                            default:
                        echo <<< EOM
                        <p>
                            <label for="{$options['param']}" {$error_class}>{$option_details['label']}：</label>
EOM;
                        }
                        switch($option_details['type']){
                            case 'select':
                            case 'multiselect':
                                // セレクトボックスを出力
                                $select = "<select name='{$options['param']}' ".($option_details['type']=='multiselect'?"multiple class='sct-multi-select' ":"").">";
                                $select .= Countrytag::makeHtmlSelectOptions($option_details['list'], $options['value']);
                                $select .= "</select>{$hint}";
                                echo $select;
                                break;
                            case 'color':
                                // カラーピッカーを出力
                                echo "<input type='text' name='{$options['param']}' value='{$options['value']}' class='sct-color-picker' data-default-color='{$option_details['default']}' {$required} {$size}/>{$hint}";
                                break;                            
                            case 'spinner':
                                // スピナーを出力
                                echo "<input type='number' name='{$options['param']}' value='{$options['value']}' {$required} {$size} />{$hint}";
                                break;
                            case 'hidden':
                                echo "<input type='hidden' name='{$options['param']}' value='{$options['value']}' />";
                                break;
                            default:
                                //テキストボックス出力
                                echo "<input type='text' name='{$options['param']}' value='{$options['value']}' {$required} {$size} />{$hint}";
                        }                    
                        echo <<< EOM
                                {$options['invalid']}
                        </p>
EOM;

                    }
                    echo <<< EOM
                    </div>
EOM;
                    break;
            }
        }
        $sct_json = json_encode(array(
                            "active_tab" => $active_tab,
                        ));
        // 送信ボタンを生成・取得
        $submit_button = get_submit_button('Save');
        echo <<< EOM
                </div><!-- stabs -->
                {$submit_button}
            </form>
            <script>
                var sct_json = JSON.parse('{$sct_json}');
            </script>
EOM;
    }

    /**
     * 初期設定を保存するcallback関数
     */
    static function save_settings() {
        // nonceで設定したcredentialをPOST受信した場合
        if (isset($_POST[Countrytag::CREDENTIAL_NAME__SETTINGS_FORM]) && $_POST[Countrytag::CREDENTIAL_NAME__SETTINGS_FORM]) {
            // nonceで設定したcredentialのチェック結果が問題ない場合
            if (check_admin_referer(Countrytag::CREDENTIAL_ACTION__SETTINGS_FORM, Countrytag::CREDENTIAL_NAME__SETTINGS_FORM)) {
                $valid = true;

                $current_plugin_options = Countrytag::get_all_options();
                //チャンネル以外のオプション値チェック
                $plugin_options = array();
                foreach(Countrytag::SETTINGS_OPTIONS as $tab_name => $tab_details){
                    foreach($tab_details['fields'] as $option_key => $option_details){
                        if(isset($option_details['isMulti']) && $option_details['isMulti']){
                            $value = $_POST[Countrytag::PARAMETER_PREFIX.$option_key];
                            foreach($value as $key => $tmp){
                                $value[$key] = trim(sanitize_text_field($tmp));
                            }
                        }else{
                            $value = trim(sanitize_text_field($_POST[Countrytag::PARAMETER_PREFIX.$option_key]));
                        }
                        if(empty($value) && isset($option_details['required']) && $option_details['required']){
                            set_transient(Countrytag::INVALID_PREFIX.$option_key,$option_details['label']." is required.", Countrytag::TRANSIENT_TIME_LIMIT);
                            $valid = false;
                        }else if(isset($option_details['regex']) && !empty($value) && !preg_match($option_details['regex'], $value)){
                            set_transient(Countrytag::INVALID_PREFIX.$option_key,$option_details['label']." is not valid.", Countrytag::TRANSIENT_TIME_LIMIT);
                            $valid = false;
                        }
                        $plugin_options[$option_key] = $value;
                    }
                }

                

                // すべてのチャンネルの値をチェックして、なお有効フラグがTrueの場合
                if ($valid) {
                    if($current_plugin_options['schedule'] != $plugin_options['schedule'] || !wp_next_scheduled( 'countrytag_update_database' )){
                        //自動アップデート設定に変更がある場合
                        //一旦スケジュール解除
                        $next_time = wp_next_scheduled( 'countrytag_update_database' );
                        if($next_time){
                            wp_unschedule_event( $next_time, 'countrytag_update_database' );
                        }
                        
                        //改めてスケジュール登録
                        if($plugin_options['schedule'] != 'never'){
                            wp_schedule_event(time(), $plugin_options['schedule'], 'countrytag_update_database');
                        }
                        /*
                        if($plugin_options['schedule'] != 'never'){
                            $plugin_options['next_time'] = time();
                        }
                        */
                    }

                    if(isset($_POST["updatedb"]) && $_POST["updatedb"]=="1"){
                        //データベース更新
                        $update_result = updateCountryDB::updatedb(true);
                        Countrytag::logging($update_result);
                        $complete_message = $update_result['message'];
                    }else{
                        $complete_message = "Saved settings.";
                    }
                    
                    //プラグインオプションを保存
                    update_option(Countrytag::OPTION_KEY__SETTINGS, $plugin_options);
                    // 保存が完了したら、完了メッセージをTRANSIENTに5秒間保持
                    set_transient(Countrytag::TRANSIENT_KEY__SAVE_SETTINGS, $complete_message, Countrytag::TRANSIENT_TIME_LIMIT);
                }else {
                    // 有効フラグがFalseの場合
                    
                    foreach(Countrytag::SETTINGS_OPTIONS as $tab_name => $tab_details){
                        foreach($tab_details['fields'] as $option_key => $option_details){
                            if($option_details['isMulti']){
                                $value = $_POST[Countrytag::PARAMETER_PREFIX.$option_key];
                                foreach($value as $key => $tmp){
                                    $value[$key] = trim(sanitize_text_field($tmp));
                                }
                            }else{
                                $value = trim(sanitize_text_field($_POST[Countrytag::PARAMETER_PREFIX.$option_key]));
                            }
                            set_transient(Countrytag::TRANSIENT_PREFIX.$option_key, $value, Countrytag::TRANSIENT_TIME_LIMIT);
                        }
                    }
                    // (一応)初期設定の保存完了メッセージを削除
                    delete_transient(Countrytag::TRANSIENT_KEY__SAVE_SETTINGS);
                }
                // 設定画面にリダイレクト
                wp_safe_redirect(menu_page_url(Countrytag::SLUG__SETTINGS_FORM), 303);
            }
        }
    }

    //管理画面用にスクリプト読み込み
    static function wpdocs_plugin_admin_scripts(){
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core',false,array('jquery'));
        wp_enqueue_script('jquery-ui-tabs',false,array('jquery-ui-core'));
        wp_enqueue_script('jquery-ui-tooltip',false,array('jquery-ui-core'));
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('jquery-ui-multiselect-widget',plugins_url("js/jquery.multiselect.min.js", dirname(__FILE__)),array('jquery-ui-core'),"3.0.1",true);
        $setting_js = "js/sct_setting.js";
        wp_enqueue_script(Countrytag::PLUGIN_PREFIX.'admin', plugins_url($setting_js, dirname(__FILE__)),array('jquery-ui-tabs','wp-color-picker','jquery-ui-multiselect-widget'),filemtime(plugin_dir_path(dirname(__FILE__)).$setting_js),true);
    }

    //管理画面用にスタイル読み込み
    static function wpdocs_plugin_admin_styles(){
        $jquery_ui_css = "css/jquery-ui.css";
        wp_enqueue_style(Countrytag::PLUGIN_ID. '-admin-ui-css',plugins_url($jquery_ui_css, dirname(__FILE__)),array(),filemtime(plugin_dir_path(dirname(__FILE__)).$jquery_ui_css));
        wp_enqueue_style('wp-color-picker');
        $setting_css = "css/sct_setting.css";
        wp_enqueue_style(Countrytag::PLUGIN_PREFIX.'admin-css', plugins_url($setting_css, dirname(__FILE__)),array(),filemtime(plugin_dir_path(dirname(__FILE__)).$setting_css));
        $multiselect_css = "css/jquery.multiselect.css";
        wp_enqueue_style(Countrytag::PLUGIN_PREFIX.'multiselect-css', plugins_url($multiselect_css, dirname(__FILE__)),array(),filemtime(plugin_dir_path(dirname(__FILE__)).$multiselect_css));
    }

    static function size_convert($bite , $decimal=1){
        if(!$bite){return 0;}
        $decimal = ($decimal) ? pow(10,$decimal) : 10;
        $kiro = 1024;
        $size = $bite;
        $unit = "B";
        $units = ["B" , "KB" , "MB" , "GB" , "TB"];
        for($i=count($units)-1; $i>0; $i--){
            if($bite / pow($kiro,$i) > 1){
            $size = round($bite / pow($kiro,$i) * $decimal) / $decimal ;
            $unit = $units[$i];
            break;
            }
        }
        return (string)$size ." ". $unit;
    }
}