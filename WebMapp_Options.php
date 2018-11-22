<?php

function mb_get_import_export_button( $import = false )
{

    $selector = $import ? 'mb-import' : 'mb-export';
    $button_text = $import ? 'Import' : 'Export';
    $url = $import ? MB_IMPORT_LOG_DIR_URL : MB_EXPORT_JSONS_URL;

    ob_start();
    ?>

    <a id="<?php echo $selector?>" class="button button-primary button-hero"><?php echo $button_text ?></a>
    <div></div>

    <script>
        (function($){

            $('#<?php echo $selector?>').on( 'click' , function(event){

                event.preventDefault();
                event.stopPropagation();

                let $this = $(this);
                let $response_container = $this.next();

                $response_container.html('Waiting ...');

                $.post(
                    ajaxurl,
                    {
                        action: 'mb_import_export',
                        nonce : mb_import_export.nonce,
                        type: '<?php echo $selector?>'
                    }
                ).done( function( response )
                {
                    $response_container.html('Done! See <a href="<?php echo $url ?>" target="_blank">here</a>');
                } ).error( function(){
                    $response_container.html('Something goes wrong. Open console to see the kind of error.');
                });
            } );

        })(jQuery);
    </script>

    <?php

    $html = ob_get_clean();

    return $html;
}


$html_export = mb_get_import_export_button();

$html_import = mb_get_import_export_button( true );


$tabs = array(
    'export' => 'Export',
    'import' => 'Import'
);
$generalOptionsPage = array(
    'export_button' => array(
        'label' => '',
        'info' => "Export Viaggi in Json Format",
        'tab' => 'export',
        'html' => $html_export
    ),
    'import_button' => array(
        'label' => '',
        'info' => "Import Viaggi from Json Format",
        'tab' => 'import',
        'html' => $html_import
    )
);


/**
 * Create admin page
 */
$WEBMAPP_GeneralOptionsPage = new MB_AdminOptionsPage(
    'MB Import Export',
    'MB Import Export',
    'manage_options',
    'mb_options',
    $generalOptionsPage,
    $tabs
);