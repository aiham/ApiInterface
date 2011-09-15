<?php

class UsersController extends AppController {

  public function getAllUsers () {
    return $this->db['users'];
  }

  public function addNewUser ($name) {
    $name = trim($name);
    if (empty($name)) {
      throw new Exception('Empty name argument', 400);
    }
    array_push($this->db['users'], $name);
    return array('id' => count($this->db['users']) - 1);
  }

}
