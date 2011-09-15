<?php

include '../../src/dispatcher.php';

$dispatcher = new ApiInterfaceDispatcher(dirname(__FILE__));
$dispatcher->dispatch();
