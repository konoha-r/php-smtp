<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
namespace App;

use Net_DNS2_Resolver;

class Dns{

    protected $dns;
    private static $name_servers = [];
    private static $cache = [];
    
    public function __construct(Net_DNS2 $dns){/*{{{*/
        $this->dns = $dns;
        $opts = [
            'cache_type' => 'file',
            'cache_file' => '/tmp/net_dns2.cache',
            'cache_size' => 100000,
            'cache_serializer' => 'json'
        ];
    }/*}}}*/

    public static function newDnsResolver($opts=[]){/*{{{*/
        $opts = array_merge(['name_servers' => self::get_name_servers()]
            , $opts);
        return $r = new Net_DNS2_Resolver($opts);
    }/*}}}*/

    public static function get_record($hostname, $type='ALL'){/*{{{*/
        $now_time = time();
        if( isset(self::$cache[$hostname]) && isset(self::$cache[$hostname][$type]) ){
            return self::$cache[$hostname][$type];
        }

        $dns = new Net_DNS2_Resolver(['nameservers' => self::get_name_servers()]);
        try{
            $record = $dns->query($hostname, $type, 'IN');
        }catch(Net_DNS2_Exception $e){
            echo "Net_DNS2_Exception::query ", $e->getMessage(), "\n";
        }

        $answer = [];
        foreach($record->answer as $v){
            $tmp = get_object_vars($v);
            $tmp['query_time'] = $now_time;//問い合わせた時間を記録
            $tmp['expired_time'] = $now_time + $tmp['ttl'];
            $answer[] = $tmp;
        }
        return self::$cache[$hostname][$type] = $answer;
    }/*}}}*/

    public static function set_name_servers($servers=array()){/*{{{*/
        if( !empty($servers) ){ self::$name_servers = $servers; }
        return self::$name_servers;
    }/*}}}*/

    public static function query($hostname, $type){/*{{{*/
        $dns = new Net_DNS2_Resolver(['nameservers' => Dns::get_name_servers()]);
        return $record = $dns->query($hostname, $type, 'IN');
    }/*}}}*/

    public static function get_name_servers(){ return self::$name_servers; }

}

