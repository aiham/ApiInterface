<?php

include_once dirname(__FILE__) . '/controller.php';

class ApiInterfaceDispatcher {

  protected static $directory;

  public function __construct ($directory) {
    self::$directory = rtrim((string) $directory, '/') . '/';
  }

  public function dispatch () {

    if (empty(self::$directory) || !is_dir(self::$directory)) {
      return $this->error(500, 'Internal Server Error');
    }

    if (strtolower($_SERVER['REQUEST_METHOD']) !== 'post') {
      return $this->error(405, 'Method Not Allowed');
    }

    if (empty($_POST['requests'])) {
      return $this->error(400, 'Bad Request');
    }

    if (!self::load('app')) {
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

        if (!self::load($request['controller'])) {
          throw new Exception('Invalid controller', 404);
        }

        $class = $this->getClass($request['controller']);

        $action = $request['action'];

        if (!in_array($action, get_class_methods($class))) {
          throw new Exception('Invalid action', 404);
        }

        $obj = new $class();

        $result = $obj->$action(isset($request['args']) && is_array($request['args']) ? $request['args'] : array());

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

    $this->respond(200, array('status' => 200, 'results' => $responses));

  }

  protected function error ($status, $message) {
    $this->respond($status, array('status' => $status, 'error' => $message));
  }

  public static function toCamelCase ($input) {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($input))));
  }

  protected function getClass ($controller) {
    return self::toCamelCase(strtolower($controller) . '_controller');
  }

  public static function load ($controller) {
    $controller = strtolower($controller) . '_controller';

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
    header('Content-Type: application/json', true, (int) $status);

    echo json_encode($data);
    exit;
  }

}
