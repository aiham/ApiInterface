<?php

class UsersController extends AppController {

  public function getAllUsers () {
    return $this->db['users'];
  }

  public function addNewUser ($args) {
    if (empty($args) || empty($args['name'])) {
      throw new Exception('Invalid arguments', 400);
    }
    array_push($this->db['users'], $args['name']);
    return array('id' => count($this->db['users']) - 1);
  }

}
