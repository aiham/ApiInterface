<?php

include '../../src/dispatcher.php';

class CustomDispatcher extends ApiInterfaceDispatcher {

  protected $db_path = 'data.db', $db = null;

  public function init () {
    if (file_exists(self::$directory . $this->db_path)) {
      $this->db = json_decode(file_get_contents(self::$directory . $this->db_path), true);
    }
    if (!is_array($this->db)) {
      $this->db = array();
      $this->db['users'] = array();
    }
  }

  public function beforeRequest ($controller) {
    $controller->db =& $this->db;
    return $controller;
  }

  public function beforeResponse ($result) {
    return $result;
  }

  public function onError ($status, $error) {
    error_log(sprintf('Error %d: %s', $status, $error));
  }

  public function clean () {
    file_put_contents(self::$directory . $this->db_path, json_encode($this->db));
  }
}
