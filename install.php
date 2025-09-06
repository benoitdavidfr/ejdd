<?php
/** Vérifie la configuration d'installation */
if (!is_dir(__DIR__.'/vendor')) {
  echo "Des composants extérieurs sont nécessaires. Pour cela utiliser composer avec les modules suivants:<br>\n";
  echo "<pre>
  composer require --dev phpstan/phpstan
  composer require justinrainbow/json-schema
  composer require symfony/yaml
  composer require phpoffice/phpspreadsheet</pre>\n";
  die();
}