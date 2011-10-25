<?php

class MainController extends AppController {

  public function getControllers () {
    $controllers = array();
    foreach (glob(self::$directory . '*_controller.php') as $path) {
      $file = basename($path);
      $name = substr($file, 0, strrpos($file, '_controller.php'));
      if ($name === 'app' || in_array($name, self::$private_controllers)) {
        continue;
      }
      ApiInterfaceDispatcher::loadController($name);
      $class = ApiInterfaceDispatcher::toCamelCase($name);
      array_push($controllers, array(
        'name' => $name,
        'class' => $class,
        'actions' => array_filter(get_class_methods($class . 'Controller'), array($this, 'filterAction'))
      ));
    }
    return $controllers;
  }

  public function filterAction($action) {
    return !in_array($action, self::$private_actions) && $action[0] !== '_';
  }

}
