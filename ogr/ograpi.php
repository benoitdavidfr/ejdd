<?php
/** Test de l'utilisation de l'API OGR.
 * Voir https://gdal.org/en/stable/api/index.html#c-api
 * @package Ogr
 */
namespace Ogr;


/** Reconstitution de l'eexemple OGR2Tab.
 * -ogr2tab.php:  Read an OGR dataset and copy to a new OGR MapInfo dataset.
 *
 * Example:  ./php -q ogr2tab.php 
 *          /path/to/outputfilename.tab 
 *          /path/to/sourcefilename.tab
 * modif $strFormat en "ESRI Shapefile" puis "'MapInfo File"
 */
class OGR2Tab {
  static function main(array $argv): void {
    echo "<BODY>\n<HTML>\n<PRE>\n";
    $eErr = self::OGR2Tab_main($argv);

    if ($eErr != OGRERR_NONE) {
       printf("Some errors were reported (error %d)\n", $eErr);
    }
    else {
       printf("ogr2ogr completed with no errors.\n");
    }
  }

  /************************************************************************/
  /*                                OGR2Tab_main()                        */
  /************************************************************************/

  static function OGR2Tab_main(array $argv) {
      $strFormat = "'MapInfo File"; // String du format de sortie
      $strDataSource = NULL; // nom de la source
      $strDestDataSource = NULL; // nom de la destination
      $astrLayers = NULL; // Liste des layers
    
      /* -------------------------------------------------------------------- */
      /*      Register format(s).                                             */
      /* -------------------------------------------------------------------- */
      OGRRegisterAll();

      /* -------------------------------------------------------------------- */
      /*      Processing command line arguments.                              */
      /* -------------------------------------------------------------------- */
      $numArgs = count($argv);

      for ($iArg = 1; $iArg < $numArgs; $iArg++) {
          if ($argv[$iArg][0] == '-') {
              self::Usage();
          }
          elseif ($strDestDataSource == NULL) {
              $strDestDataSource = $argv[$iArg];
              printf("DestDataSource = %s\n", $strDestDataSource);
          }
          elseif ($strDataSource == NULL) {
              $strDataSource = $argv[$iArg];
              printf("DataSource = %s\n", $strDataSource);
          }
          else{
              $astrLayers[] = $argv[$iArg];
          }
      }
      $i = 0;
      while ($astrLayers[$i] ?? null){
          printf("Layers [%d] = %s\n", $i, $astrLayers[$i] );
          $i++;
      }

      if ($strDataSource == NULL)
          self::Usage();

      /* -------------------------------------------------------------------- */
      /*      Open data source.                                               */
      /* -------------------------------------------------------------------- */
      $hSFDriver = NULL;
      $hDS = OGROpen($strDataSource, FALSE, $hSFDriver); // $hDS est le JdD source

      /* -------------------------------------------------------------------- */
      /*      Report failure                                                  */
      /* -------------------------------------------------------------------- */
      if ($hDS == NULL) {
          printf( "FAILURE:\nUnable to open datasource `%s' with the following drivers:\n", $strDataSource );

          for ($iDriver = 0; $iDriver < OGRGetDriverCount(); $iDriver++ ) {
              printf( "  -> %s\n", OGR_DR_GetName(OGRGetDriver($iDriver)) );
          }
          return OGRERR_FAILURE;
      }

      /* -------------------------------------------------------------------- */
      /*      Find the output driver.                                         */
      /* -------------------------------------------------------------------- */
      for( $iDriver = 0;
           $iDriver < OGRGetDriverCount() && $hSFDriver == NULL;
           $iDriver++ ) {
          if( !strcasecmp(OGR_DR_GetName(OGRGetDriver($iDriver)) , $strFormat) ) {
              $hSFDriver = OGRGetDriver($iDriver);
          }
      }
      
      // $hSFDriver est le driver correspondant au JdD de destination
      if ($hSFDriver == NULL) {
          printf( "Unable to find driver `%s'.\n", $strFormat );
          printf( "The following drivers are available:\n" );
        
          for( $iDriver = 0; $iDriver < OGRGetDriverCount(); $iDriver++ ) {
              printf( "  -> %s\n", OGR_DR_GetName(OGRGetDriver($iDriver)) );
          }

          return OGRERR_FAILURE;
      }

      if (!OGR_Dr_TestCapability($hSFDriver, ODrCCreateDataSource) ) {
          printf( "%s driver does not support data source creation.\n", $strFormat );
          return OGRERR_FAILURE;
      }

      /* -------------------------------------------------------------------- */
      /*      Create the output data source.                                  */
      /* -------------------------------------------------------------------- */

     /*Uncomment and add options here. */
       /* $aoptions[0] = 'option1';
          $aoptions[1] = 'option2';
          $hODS = OGR_Dr_CreateDataSource( $hSFDriver, $strDestDataSource, $aoptions );
      */

      /* Or use no option.*/
     $hODS = OGR_Dr_CreateDataSource($hSFDriver, $strDestDataSource, NULL);
 
      if ($hODS == NULL) {
        echo "Erreur sur OGR_Dr_CreateDataSource\n";
        return OGRERR_FAILURE;
      }
      // $hODS est le JdD de destination
      
      /* -------------------------------------------------------------------- */
      /*      Process only first layer in source dataset                      */
      /* -------------------------------------------------------------------- */
      if (OGR_DS_GetLayerCount($hDS) > 0) {
          $hLayer = OGR_DS_GetLayer($hDS, 0);

          if ($hLayer == NULL ) {
              printf ("FAILURE: Couldn't fetch advertised layer 0!\n");
              return OGRERR_FAILURE;
          }

          if (!self::TranslateLayer($hDS, $hLayer, $hODS)) {
            echo "Erreur sur TranslateLayer\n";
            return OGRERR_FAILURE;
          }
      }

      /* -------------------------------------------------------------------- */
      /*      Close down.                                                     */
      /* -------------------------------------------------------------------- */
      OGR_DS_Destroy($hDS);
      OGR_DS_Destroy($hODS);
    
      return OGRERR_NONE;
  }
  /************************************************************************/
  /*                               Usage()                                */
  /************************************************************************/
  static function Usage(): void {
      printf( "Usage: ogr2ogr [-f format_name] dst_datasource_name\n
               src_datasource_name\n");
  }

  /************************************************************************/
  /*                           TranslateLayer()                           */
  /************************************************************************/

  // $hSrcDS - le JdD source
  // $hSrcLayer - couche du dataset source
  // $hDstDS - le JdD de destination
  static function TranslateLayer($hSrcDS, $hSrcLayer, $hDstDS) {
    /* -------------------------------------------------------------------- */
    /*      Create the layer.                                               */
    /* -------------------------------------------------------------------- */
    if( !OGR_DS_TestCapability($hDstDS, ODsCCreateLayer ) ) {
        printf( "%s data source does not support layer creation.\n",
                OGR_DS_GetName($hDstDS) );
        return OGRERR_FAILURE;
    }

    $hFDefn = OGR_L_GetLayerDefn($hSrcLayer);

    /* MapInfo data sources are created with one empty layer corresponding 
       to the $strFname that was passed to the OGR_Dr_CreateDataSource() call.
       Fetch this layer handle now. */

    $hDstLayer = OGR_DS_GetLayer($hDstDS, 0 /*layer number*/);

    if ($hDstLayer == NULL) {
        echo "La couche de destination hDstLayer est null\n";
        return FALSE;
    }

    /* -------------------------------------------------------------------- */
    /*      Add fields.                                                     */
    /* -------------------------------------------------------------------- */
    for ($iField = 0; $iField < OGR_FD_GetFieldCount($hFDefn); $iField++) {
        if (OGR_L_CreateField( $hDstLayer, OGR_FD_GetFieldDefn( $hFDefn, $iField), 0 /*bApproOK*/ ) != OGRERR_NONE )
            return FALSE;
    }
    /* -------------------------------------------------------------------- */
    /*      Transfer features.                                              */
    /* -------------------------------------------------------------------- */
    OGR_L_ResetReading($hSrcLayer);

    while( ($hFeature = OGR_L_GetNextFeature($hSrcLayer)) != NULL ) {

        $hDstFeature = OGR_F_Create( OGR_L_GetLayerDefn($hDstLayer) );

        if( OGR_F_SetFrom( $hDstFeature, $hFeature, FALSE /*bForgiving*/ ) != OGRERR_NONE ) {
            OGR_F_Destroy($hFeature);
          
            printf("Unable to translate feature %d from layer %s.\n", 
                   OGR_F_GetFID($hFeature), OGR_FD_GetName($hFDefn) );
            return FALSE;
        }
      
        OGR_F_Destroy($hFeature);
      
        if( OGR_L_CreateFeature( $hDstLayer, $hDstFeature ) != OGRERR_NONE ) {
            OGR_F_Destroy($hDstFeature);
            return FALSE;
        }

        OGR_F_Destroy($hDstFeature);
    }
    return TRUE;
  }
};
//$ne110m = __DIR__.'/../../data/naturalearth/110m_physical/ne_110m_coastline.shp';
//$cmdeLine = "ogr2ogr -f GeoJSON ne_110m_coastline.geojson $ne110m";
$cmdeLine = "ogr2ogr ne_110m_coastline.tab ne_110m/ne_110m.tab";
OGR2Tab::main($argv ?? explode(' ', $cmdeLine));    
//OGR2Tab::Usage();
