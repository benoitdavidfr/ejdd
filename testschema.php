<?php
require_once 'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use JsonSchema\Validator;

$data = Yaml::parseFile('testschema.yaml');
//print_r($data);

$validator = new Validator;
$stdObject = RecArray::toStdObject($data['data']);
$validator->validate($stdObject, $data['schema']);

if ($validator->isValid()) {
  echo "Valeur conforme au schéma<br>\n";
}
else {
  echo "<pre>La data n'est pas conforme au schéma. Violations:<br>\n";
  foreach ($validator->getErrors() as $error) {
    printf("[%s] %s\n", $error['property'], $error['message']);
  }
  echo "</pre>\n";
}


if (!isset($data['schema2'])) return;


$validator = new Validator;
$stdObject = RecArray::toStdObject($data['schema']);
$validator->validate($stdObject, $data['schema2']);

if ($validator->isValid()) {
  echo "Schéma conforme au schéma2<br>\n";
}
else {
  echo "<pre>Le schéma n'est pas conforme au schéma2. Violations:<br>\n";
  foreach ($validator->getErrors() as $error) {
    printf("[%s] %s\n", $error['property'], $error['message']);
  }
  echo "</pre>\n";
}
