<?php

class PostsController extends AppController {

  public function getPosts ($time = null) {
    if (is_null($time)) {
      return $this->db['posts'];
    }

    $time = (int) $time;

    return array_values(array_filter(
      $this->db['posts'],
      function ($post) use ($time) { // PHP 5.3+ closure
        return (int) $post['time'] > $time;
      }
    ));
  }

  public function addNewPost ($message, $name) {
    $name = trim((string) $name);
    $message = trim((string) $message);

    if ($message === '') {
      throw new Exception('Empty message argument', 400);
    }

    if ($name === '') {
      $name = 'Anonymous';
    }

    array_push($this->db['posts'], array(
      'uuid' => md5($name . time() . $_SERVER['REMOTE_ADDR'] . mt_rand()),
      'name' => $name,
      'message' => $message,
      'time' => time()
    ));

    return $this->db['posts'][count($this->db['posts']) - 1];
  }

}
