<?php


// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
    exit;


new VtAjaxHandler(
    'mb_import_export' ,
    'mb_import_export' ,
    true,
    false,
    array(),
    'jquery'//generic handle, loaded everywhere
);

function mb_import_export( $params )
{

    if ( $params['type'] == 'mb-import' )
        $test = new MB_ImportPostsWithAcfAndPods();
    elseif ( $params['type'] == 'mb-export' )
        $test = new MB_ExportPostsWithAcfAndPods( 'viaggio' );
    else
        echo "Wrong type provided";

   wp_die();
}


