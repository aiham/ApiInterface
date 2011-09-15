<?php

class AppController extends Controller {

  protected
    $db_path = 'data.db',
    $db = array();

  public function __construct () {
    if (file_exists(self::$directory . $this->db_path)) {
      $this->db = unserialize(file_get_contents(self::$directory . $this->db_path));
    } else {
      $this->db['users'] = array();
    }
  }

  public function __destruct () {
    file_put_contents(self::$directory . $this->db_path, serialize($this->db));
  }

}
