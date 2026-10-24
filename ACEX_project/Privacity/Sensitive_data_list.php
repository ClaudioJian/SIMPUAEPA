<?php
namespace ACEX_project\Privacity;

/**
 * array{name=>bool}
 */
define('SENSITIVE_INFO_LIST',[
    'ip'=>true,
]);

function Is_sensitive_data(string $name) : bool{
    return isset(SENSITIVE_INFO_LIST[$name]) && SENSITIVE_INFO_LIST[$name];
}

