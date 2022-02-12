<?php
/**
 * Country Tag
 * 管理画面でのプラグイン設定画面
 */
class updateCountryDB{
    static function updatedb($forceupdate = false){
        $ini = Countrytag::get_all_options();
        
        $dbfile = plugin_dir_path(dirname(__FILE__)).Countrytag::COUNTRY_DB_FILE;
        //$extractfilename = $ini['plan']=='paid' ? 'GeoIP2-Country.mmdb':'GeoLite2-Country.mmdb';

        $tmpdir = plugin_dir_path(dirname(__FILE__))."tmp";
        if(file_exists($tmpdir)){
            self::rmrf($tmpdir);
        }

        if(!file_exists($tmpdir) && !mkdir($tmpdir)){
            return ["isSuccess"=>false, "message"=>"Couldn't create directory ".$tmpdir." Please check permission."];
        }

        if(!$forceupdate && !Countrytag::isNeedUpdate()){
            return ["isSuccess"=>true, "message"=>"No need to update. Database is up to date."];//, "isNoLogging"=>true
        }

        $downloadfile = $tmpdir."/download.tar.gz";


        $isSuccess = false;
        $url = str_replace('YOUR_LICENSE_KEY', $ini['license_key'], $ini[$ini['plan'].'_country_db_url']);
        $result = "";
        if($url){
            $url_hash = str_replace('suffix=tar.gz', 'suffix=tar.gz.sha256', $url);
            $hash_content = file_get_contents($url_hash);
            if(preg_match('/^([a-z0-9]+)/', $hash_content, $match_hash)){
                $hash_sha256 = trim($match_hash[1]);
                
                if (file_put_contents($downloadfile, file_get_contents($url))){//file_exists($downloadfile) || 
                    if(hash_file('sha256', $downloadfile) == $hash_sha256){
                        
                        try {
                            $phar = new PharData($downloadfile);
                            $phar->extractTo($tmpdir, null, true); // 展開
                            $files = glob(rtrim($tmpdir, '/') . '/*/*.mmdb');
                            $sourcefile = $files[0];
                            if($sourcefile && file_exists($sourcefile)){
                                if(copy($sourcefile, $dbfile)){
                                    $result .= "Database file was updated successfully";
                                    $isSuccess = true;
                                }else{
                                    $result .= "Faild to copy database file.";
                                }
                            }else{
                                $result .= "Faild extract archive. can't find ".($sourcefile ? $sourcefile : "*.mmdb")."";
                            }

                        } catch (Exception $e) {
                            // エラー処理
                            $result .= "Faild extract archive. can't extract ".$downloadfile." ".$e->getMessage();
                        }
                    }else{
                        $result .= "Hash does not match. hash: ".hash_file('sha256', $downloadfile) ." expected:". $hash_sha256;
                    }
                }else{
                    $result .= "Failed download database file.";
                }
            }else{
                $result .= "Failed download hash file.".$hash_content;
            }
        }else{
            $result .= "Database URL not found.";
        }

        //tmpディレクトリを削除
        self::rmrf($tmpdir);

        return ["isSuccess"=>$isSuccess, "message"=>$result];
    }

    static function rmrf($dir) {
        if (is_dir($dir) and !is_link($dir)) {
            array_map('self::rmrf',   glob($dir.'/*', GLOB_ONLYDIR));
            array_map('unlink', glob($dir.'/*'));
            rmdir($dir);
        }
    }
}