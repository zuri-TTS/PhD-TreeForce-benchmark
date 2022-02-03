<?php
\spl_autoload_register(function (string $className) {
    $className = \str_replace('\\', '/', $className);

    $path = __DIR__ . "/$className.php";

    if (\is_file($path))
        include $path;
});