<?php

include 'custom_dispatcher.php';

$dispatcher = new CustomDispatcher(dirname(__FILE__));
$dispatcher->dispatch();
