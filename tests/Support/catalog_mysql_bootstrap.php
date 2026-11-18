<?php

require dirname(__DIR__,2).'/vendor/autoload.php';
$catalogMysqlStage=\Tests\Support\CatalogMysqlEnvironment::settings();
\Tests\Support\CatalogMysqlEnvironment::verify($catalogMysqlStage);
\Tests\Support\CatalogMysqlEnvironment::environment($catalogMysqlStage);
