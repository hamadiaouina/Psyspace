<?php
echo "<pre>";
var_dump(getenv('TURNSTILE_SECRET'));
echo "\n";
var_dump($_ENV['TURNSTILE_SECRET'] ?? '$_ENV NON SET');
echo "\n";
var_dump($_SERVER['TURNSTILE_SECRET'] ?? '$_SERVER NON SET');
echo "</pre>";
?>