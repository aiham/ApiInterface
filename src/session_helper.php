<?php

class SessionHelper {

  protected $key, $token;

  public function __construct ($key, $token, $lifetime, $path, $domain, $secure, $httponly) {

    $this->key = $key;
    $this->token = $token;

    session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
    session_start();

    if (!isset($_SESSION['values'])) {
      $_SESSION['values'] = array();
    }

  }

  public function get ($key, $subsession = false) {
    $container = $subsession ?
      $_SESSION[$this->key]['values'] : $_SESSION['values'];

    return isset($container[$key]) ? $container[$key] : null;
  }

  public function set ($key, $value, $subsession = false) {
    $_SESSION[$this->key]['values'][$key] = $value;
  }

  public function keyExists ($key) {
    return isset($_SESSION[$key]);
  }

  public function isValid () {
    return isset($_SESSION[$this->key]) &&
      isset($_SESSION[$this->key]['token']) &&
      $_SESSION[$this->key]['token'] === $this->token;
  }

  public function newKey ($key, $token) {
    $_SESSION[$key] = array(
      'last_request' => time(),
      'token' => $token,
      'values' => array()
    );
  }

  public function replaceToken ($token) {
    $this->token = $token;
    $_SESSION[$this->key]['token'] = $this->token;
    $_SESSION[$this->key]['last_request'] = time();
  }

}
