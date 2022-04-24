<?php
namespace Uganda;

class Helpers {
    public function fetch($file) {
        $json = file_get_contents(dirname(__FILE__).'/geo/'.$file);
        $data = json_decode($json, true);

        return $data;
    }

    public function format($results) {
        $info['count'] = (int) count($results);
        $info['data'] = (array) $results;
        
        return $info;
    }
    
    public function filter($name, $data) {
        return function ($obj) use ($name, $data) {
            if (isset($obj[$name])) {
                if ($obj[$name] == $data) return true;
            }
            return false;
        };
    }
}
