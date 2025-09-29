<?php
/** Vérifie la configuration d'installation.
 * L'idée est d'aider qqun qui fait tourner l'appli après l'avoir clonée de GitHub.
 * Chaque JdD doit gérer lui-même cet aspect avec isAvailable()
 */

if (!preg_match('!^8\.4!', phpversion())) {
  echo "Erreur, Cette application nécessite Php 8.4, la version de Php utilisée est ",phpversion(),"<br>\n";
  die();
}

if (!is_dir(__DIR__.'/vendor')) {
  echo "Des composants extérieurs sont nécessaires. Pour cela utiliser composer avec les modules suivants:<br>\n";
  echo "<pre>
  composer require --dev phpstan/phpstan
  composer require justinrainbow/json-schema
  composer require symfony/yaml
  composer require phpoffice/phpspreadsheet</pre>\n";
  die();
}
