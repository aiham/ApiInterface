<?php

include_once dirname(__FILE__) . '/controller.php';

class ApiInterfaceDispatcher {

  protected static $directory;

  public function __construct ($directory) {
    self::$directory = rtrim((string) $directory, '/') . '/';
  }

  public function dispatch () {

    if (empty(self::$directory) || !is_dir(self::$directory)) {
      return $this->error();
    }

    if (strtolower($_SERVER['REQUEST_METHOD']) !== 'post') {
      return $this->error();
    }

    if (empty($_POST['requests'])) {
      return $this->error();
    }

    if (!self::load('app')) {
      include_once dirname(__FILE__) . '/app_controller.php';
    }

    AppController::$directory = self::$directory;

    include_once dirname(__FILE__) . '/main_controller.php';

    $requests = json_decode($_POST['requests'], true);

    $responses = array();

    foreach ($requests as $request) {

      $result;

      try {

        if (!self::load($request['controller'])) {
          throw new Exception('invalid controller'); // TODO
        }

        $class = $this->getClass($request['controller']);

        $action = $request['action'];

        if (!in_array($action, get_class_methods($class))) {
          throw new Exception('invalid action'); // TODO
        }

        $obj = new $class();

        $result = $obj->$action(isset($request['args']) ? $request['args'] : array());

      } catch (Exception $e) {
        $result = $e->getMessage(); // TODO
      }

      array_push($responses, array(
        'label' =>  $request['label'],
        'result' => $result
      ));

    }

    $this->respond('OK', $responses); // TODO - Fix that status

  }

  protected function error () {
    $this->respond('error', 'wrong');
  }

  public static function toCamelCase ($input) {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($input)))); // FIXME - mb_strtolower
  }

  protected function getClass ($controller) {
    return self::toCamelCase(strtolower($controller) . '_controller'); // FIXME - mb_strtolower
  }

  public static function load ($controller) {
    $controller = strtolower($controller) . '_controller'; // FIXME - mb_strtolower

    $class = self::toCamelCase($controller);
    if (class_exists($class)) {
      return true;
    }

    $file = self::$directory . $controller . '.php';
 
    if (!is_file($file) || !is_readable($file)) {
      return false;
    }

    include_once $file;

    return class_exists($class);
  }

  protected function respond ($status, $data) {

    header('Content-Type: application/json');

    echo json_encode(array(
      'status' => $status,
      'data' => $data
    ));

    exit;

  }

}
