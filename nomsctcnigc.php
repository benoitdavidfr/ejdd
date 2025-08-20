<?php
/** Jeu de données des noms des CT définis par le CNIG.
 * @package Dataset
 */
require_once 'spreadsheetdataset.inc.php';

/** JdD  des noms des CT définis par le CNIG (NomsCtCnigC) stockées dans un fichier ODS. */
class NomsCtCnigC extends SpreadSheetDataset {
  /** Nom du fichier ODS. */
  const FILE_PATH = 'nomsctcnigc.ods';
  
  function __construct(string $name) { parent::__construct($name, self::FILE_PATH); }
    
  static function main(): void {
    switch($_GET['action'] ?? null) {
      case null: {
        $objet = new NomsCtCnigC('NomsCtCnigC');
        echo "<a href='?action=print_r'>Afficher l'objet NomsCtCnigC</a><br>\n";
        echo "<a href='?action=schema'>Afficher le schéma</a><br>\n";
        foreach (array_keys($objet->docCollections) as $cname) {
          echo "<a href='?action=collection&collection=$cname'>Afficher la collection $cname</a><br>\n";
        }
        break;
      }
      case 'print_r': {
        $objet = new NomsCtCnigC('NomsCtCnigC');
        echo '<pre>$nomsCtCnigC = '; print_r($objet);
        break;
      }
      case 'schema': {
        $objet = new NomsCtCnigC('NomsCtCnigC');
        echo '<pre>'; print_r([
          'title'=> $objet->title,
          'description'=> $objet->description,
          '$schema'=> $objet->jsonSchema(),
        ]);
        break;
      }
      case 'collection': {
        $objet = new NomsCtCnigC('NomsCtCnigC');
        echo "<pre>collection=";
        foreach ($objet->getItems($_GET['collection']) as $key => $tuple)
          print_r([$key => $tuple]);
        break;
      }
    }
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


NomsCtCnigC::main();
