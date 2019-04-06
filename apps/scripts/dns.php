<?php
// @see https://netdns2.com/documentation/examples/

require_once dirname(__DIR__).'/config/config.php';
use App\Dns;

Dns::set_name_servers(['192.168.1.1']);
$record = Dns::query('www.yahoo.co.jp', 'A');
if( $record ){
    $answer = [];
    $now_time = time();
    foreach($record->answer as $v){
        $tmp = get_object_vars($v);
        $tmp['query_time'] = $now_time;//問い合わせた時間を記録
        $answer[] = $tmp;
    }
    var_dump($answer);
}

