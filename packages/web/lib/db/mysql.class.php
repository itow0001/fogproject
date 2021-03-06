<?php
class MySQL extends DatabaseManager {
    private static $link;
    private static $query;
    private static $queryResult;
    private static $result;
    private static $db_name;
    public function __construct() {
        if (self::$link) return;
        try {
            if (!class_exists('mysqli')) throw new Exception(sprintf('%s %s',__CLASS__,_('PHP Extentions not loaded')));
            if (!$this->connect()) throw new Exception(_('Failed to connect'));
        } catch (Exception $e) {
            $this->error(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
        }
    }
    public function __destruct() {
        self::$result = null;
        self::$queryResult = null;
        if (!self::$link) return;
        self::$link = null;
    }
    private function connect() {
        try {
            if (self::$link) return $this;
            self::$link = mysqli_init();
            self::$link->real_connect((self::$ajax || self::$post ? preg_replace('#p:#','',DATABASE_HOST) : DATABASE_HOST),DATABASE_USERNAME,DATABASE_PASSWORD);
            self::$link->set_charset('utf8');
            $this->current_db();
        } catch (Exception $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
            if (self::$link->connect_error) die($e->getMessage());
        }
        return $this;
    }
    public function current_db() {
        if (!isset(self::$db_name) || !self::$db_name) self::$db_name = self::$link->select_db(DATABASE_NAME);
        return $this;
    }
    public function query($sql, $data = array()) {
        try {
            self::$queryResult = null;
            if (isset($data) && !is_array($data)) $data = array($data);
            if (count($data)) $sql = vsprintf($sql,$data);
            $this->info($sql);
            self::$query = $sql;
            $this->current_db();
            if (!self::$query) throw new Exception(_('No query sent'));
            else if (!self::$queryResult = self::$link->query(self::$query)) throw new Exception(sprintf('%s: %s',_('Error'),$this->sqlerror()));
            if (!self::$db_name) $this->current_db();
            if (!self::$db_name) throw new Exception(_('No database to work off'));
        } catch (Exception $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
        }
        return $this;
    }
    public function fetch($type = MYSQLI_ASSOC,$fetchType = 'fetch_assoc',$params = false) {
        try {
            self::$result = array();
            if (empty($type)) $type = MYSQLI_ASSOC;
            if (empty($fetchType)) $fetchType = 'fetch_assoc';
            if (!is_object(self::$queryResult) && in_array(self::$queryResult,array(true,false),true)) self::$result = self::$queryResult;
            else if (empty(self::$queryResult)) throw new Exception(_('No query result, use query() first'));
            else {
                switch (strtolower($fetchType)) {
                case 'fetch_all':
                    if (method_exists('mysqli_result','fetch_all')) {
                        self::$result = self::$queryResult->fetch_all($type);
                    } else {
                        for (self::$result=array();$tmp = self::$queryResult->fetch_array($type);) self::$result[] = $tmp;
                    }
                    break;
                case 'fetch_assoc':
                case 'fetch_row':
                case 'fetch_field':
                case 'fetch_fields':
                case 'free':
                    self::$result = self::$queryResult->$fetchType();
                    break;
                case 'fetch_object':
                    if (isset($type) && !class_exists($type)) throw new Exception(_('No valid class sent'));
                    else self::$result = self::$queryResult->$fetchType();
                    if (isset($type) && count($params) && !is_array($params)) self::$result = self::$queryResult->$fetchType($type,array($params));
                    else if (isset($type) && $params == false) self::$result = self::$queryResult->$fetchType($type,array(null));
                    else self::$result = self::$queryResult->$fetchType($type,$params);
                    break;
                case 'data_seek':
                case 'fetch_field_direct':
                case 'field_seek':
                    if (!is_numeric($type)) throw new Exception(_('Row number not set properly'));
                default:
                    self::$result = self::$queryResult->$fetchType($type);
                    break;
                }
            }
        } catch (Exception $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
        }
        return $this;
    }
    public function get($field = '') {
        try {
            if (self::$result === false) throw new Exception(_('No data returned'));
            if (self::$result === true) return self::$result;
            $result = array();
            if ($field) {
                foreach ((array)$field AS $i => &$key) {
                    $key = trim($key);
                    if (array_key_exists($key, (array)self::$result)) {
                        return self::$result[$key];
                    }
                    foreach ((array)self::$result AS $i => &$value) {
                        if (array_key_exists($key, (array)$value)) $result[] = $value[$key];
                    }
                }
            }
            if (count($result)) return $result;
        } catch (Exception $e) {
            $this->debug(sprintf('%s %s: %s',_('Failed to'),__FUNCTION__,$e->getMessage()));
            return false;
        }
        return self::$result;
    }
    public function result() {
        return self::$result;
    }
    public function queryResult() {
        return self::$queryResult;
    }
    public function sqlerror() {
        return self::$link->connect_error ? sprintf('%s, %s: %s',self::$link->connect_error,_('Message'),_('Check that database is running')) : self::$link->error;
    }
    public function field_count() {
        return self::$link->field_count;
    }
    public function insert_id() {
        return self::$link->insert_id;
    }
    public function affected_rows() {
        return self::$link->affected_rows;
    }
    public function num_rows() {
        self::$link->num_rows;
    }
    public function escape($data) {
        return $this->sanitize($data);
    }
    private function clean($data) {
        return trim(self::$link->real_escape_string(htmlentities(html_entity_decode(mb_convert_encoding($data,'UTF-8'),ENT_QUOTES,'UTF-8'),ENT_QUOTES,'UTF-8')));
    }
    public function sanitize($data) {
        if (!is_array($data)) return $this->clean($data);
        foreach ($data AS $key => &$val) {
            if (is_array($val)) {
                foreach ($val AS $i => $v) $data[$this->clean($key)][$i] = $this->clean($v);
            } else $data[$this->clean($key)] = $this->clean($val);
        }
        return $data;
    }
    public function db_name() {
        return self::$db_name;
    }
    public function link() {
        return self::$link;
    }
}
