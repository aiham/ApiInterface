<?php

include_once dirname(__FILE__) . '/controller.php';

class ApiInterfaceDispatcher {

  protected static $directory;

  public function __construct ($directory) {
    self::$directory = rtrim((string) $directory, '/') . '/';
  }

  public function dispatch () {

    if (method_exists($this, 'init')) {
      try {
        $this->init();
      } catch (Exception $e) {
        return $this->error($e->getCode(), $e->getMessage());
      }
    }

    if (empty(self::$directory) || !is_dir(self::$directory)) {
      return $this->error(500, 'Internal Server Error');
    }

    if (strtolower($_SERVER['REQUEST_METHOD']) !== 'post') {
      return $this->error(405, 'Method Not Allowed');
    }

    if (
      empty($_POST['requests']) ||
      empty($_POST['key']) ||
      empty($_POST['token']) ||
      mb_strlen($_POST['key']) !== 32
    ) {
      return $this->error(400, 'Bad Request');
    }

    $this->session = self::startSession($_POST['key'], $_POST['token']);

    if (!$this->session->isValid()) {
      return $this->error(401, 'Unauthorised');
    }

    if (!self::loadController('app')) {
      include_once dirname(__FILE__) . '/app_controller.php';
    }

    AppController::$directory = self::$directory;

    include_once dirname(__FILE__) . '/main_controller.php';

    $requests = json_decode($_POST['requests'], true);

    if (!is_array($requests)) {
      return $this->error(400, 'Bad Request');
    }

    $responses = array();

    foreach ($requests as $request) {

      if (!is_array($request)) {
        continue;
      }

      try {

        if (empty($request['controller']) || empty($request['action']) || empty($request['label'])) {
          throw new Exception('Bad Request', 400);
        }

        if (!self::loadController($request['controller'])) {
          throw new Exception('Invalid controller', 404);
        }

        $class = $this->getClass($request['controller']);

        $action = $request['action'];

        if (!in_array($action, get_class_methods($class))) {
          throw new Exception('Invalid action', 404);
        }

        $args = isset($request['args']) ? $request['args'] : array();

        $method = new ReflectionMethod($class, $action);
        $parameters = $method->getParameters();
        $ordered_args = array();
        foreach ($parameters as $parameter) {
          $name = $parameter->getName();
          if (isset($args[$name])) {
            $value = $args[$name];
          } else {
            if (!$parameter->isOptional()) {
              throw new Exception('Missing required argument ' . $name, 400);
            }
            $value = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
          }
          $ordered_args[$parameter->getPosition()] = $value;
        }

        $obj = new $class();

        if (method_exists($this, 'beforeRequest')) {
          $obj = $this->beforeRequest($obj);
        }

        $result = call_user_func_array(array($obj, $action), $ordered_args);

        if (method_exists($this, 'beforeResponse')) {
          $result = $this->beforeResponse($result);
        }

        array_push($responses, array(
          'status' =>  200,
          'label' =>  $request['label'],
          'result' => $result
        ));

      } catch (Exception $e) {
        array_push($responses, array(
          'status' =>  $e->getCode(),
          'label' =>  $request['label'],
          'error' => $e->getMessage()
        ));
      }

    }

    if (method_exists($this, 'clean')) {
      try {
        $this->clean();
      } catch (Exception $e) {
        $this->error($e->getCode(), $e->getMessage(), false);
      }
    }

    $token = self::generateRandomToken();
    $this->session->replaceToken($token);

    $this->respond(
      200,
      array(
        'status' => 200,
        'token' => $token,
        'results' => $responses
      )
    );

  }

  protected function error ($status, $message, $respond = true) {
    if (method_exists($this, 'onError')) {
      $this->onError($status, $message);
    }
    if ($respond) {
      $this->respond($status, array('status' => $status, 'error' => $message));
    }
  }

  public static function toCamelCase ($input) {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($input))));
  }

  protected function getClass ($controller) {
    return self::toCamelCase(strtolower($controller) . '_controller');
  }

  public static function loadController ($controller) {
    return self::load($controller . '_controller');
  }

  public static function loadHelper ($helper) {
    return self::load($helper . '_helper');
  }

  public static function load ($name) {
    $name = strtolower($name);

    $class = self::toCamelCase($name);
    if (class_exists($class)) {
      return true;
    }

    $file = self::$directory . $name . '.php';
 
    if (!is_file($file) || !is_readable($file)) {
      return false;
    }

    include_once $file;

    return class_exists($class);
  }

  protected function respond ($status, $data) {
    header('Content-Type: application/json', true, (int) $status);

    echo json_encode($data);
    exit;
  }

  public static function requestKeyAndToken () {
    $session = self::startSession(null, null);

    do {
      $key = self::generateRandomToken();
    } while ($session->keyExists($key));

    $token = self::generateRandomToken();

    $session->newKey($key, $token);

    return array('key' => $key, 'token' => $token);
  }

  public static function generateRandomToken () {
    return md5(
      microtime(true) .
      mt_rand() .
      $_SERVER['REMOTE_ADDR']
    );
  }

  public static function startSession ($key, $token) {
    $session_path = $_SERVER['SCRIPT_NAME'];
    $session_path_length = mb_strlen($session_path);

    if ($session_path_length === 0 || $session_path === '/') {
      $session_path = '/';
    } else if ($session_path[$session_path_length - 1] !== '/') {
      $session_path = dirname($session_path) . '/';
    } else {
      $session_path = dirname($session_path . '/.') . '/';
    }

    if (!self::loadHelper('session')) {
      include_once dirname(__FILE__) . '/session_helper.php';
    }

    return new SessionHelper(
      $key,
      $token,
      0, // cookie lifetime
      // FIXME - currently the path and domain of the index page
      // need to be the same as the api page. should be
      // configurable so the pages can be placed in different
      // directories
      $session_path, // cookie path
      $_SERVER['HTTP_HOST'], // cookie domain
      false, // https only
      true // http only (no js)
    );
  }

}
