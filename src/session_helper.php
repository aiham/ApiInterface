<?php

class SessionHelper {

  protected $_key, $_token;

  public function __construct ($key, $token, $lifetime, $path, $domain, $secure, $httponly) {

    $this->_key = $key;
    $this->_token = $token;

    session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
    session_start();

  }

  public function __get ($key) {
    return isset($_SESSION[$this->_key]['values'][$key]) ?
      $_SESSION[$this->_key]['values'][$key] : null;
  }

  public function __set ($key, $value) {
    $_SESSION[$this->_key]['values'][$key] = $value;
  }

  public function keyExists ($key) {
    return isset($_SESSION[$key]);
  }

  public function isValid () {
    return isset($_SESSION[$this->_key]) &&
      isset($_SESSION[$this->_key]['token']) &&
      $_SESSION[$this->_key]['token'] === $this->_token;
  }

  public function newKey ($key, $token) {
    $_SESSION[$key] = array(
      'last_request' => time(),
      'token' => $token,
      'values' => array()
    );
  }

  public function replaceToken ($token) {
    $this->_token = $token;
    $_SESSION[$this->_key]['token'] = $this->_token;
    $_SESSION[$this->_key]['last_request'] = time();
  }

}
