<?php 
echo __DIR__ . '../vendor/autoload.php';


require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__. "/../");
$dotenv->load();

echo $_ENV['ENVIROMENT'];

return 0;
?>