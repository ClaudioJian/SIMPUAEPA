<?php 
echo 'path: ' . __DIR__ . '/../vendor/autoload.php'. PHP_EOL;


require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__. "/../");
$dotenv->load();

// . PHP_EOL is a constant in PHP that represents the end of a line.
echo $_ENV['ENVIRONMENT']??"NOT FIND ENVIROMENT VARIABLE" . PHP_EOL;
echo "TEST" . PHP_EOL;

return 0;
?>