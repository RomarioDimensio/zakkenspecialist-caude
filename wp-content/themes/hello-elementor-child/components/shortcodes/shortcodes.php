<?php

// require once what is in the components folder
foreach (glob(__DIR__ . '/*/*.php') as $php) {
    require_once $php;
}