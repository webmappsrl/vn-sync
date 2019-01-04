<?php
/**
 *
 * L'oggetto prende un url da un metafield e carica l'immagine nei media associandola ad un comment id
 *
 *
 *
 */

class VT_Comments_UrlToMedia_Gallery
{

    public $comment_id;
    public $meta_key;

    public $url_to_import;

    public $comment;

    private $upload_dir;
    public $attachment_id;
    public $attachment_url;
    public $attachment_filename;
    public $attachment_filepath;
    public $upload_folder;

    public $image_details;


    function __construct( $comment , $url , $details = array() )
    {

        if ( is_numeric( $comment ) )
            $comment = get_comment( $comment );


        $this->comment = $comment;
        $this->comment_id = $comment->comment_ID;




        $this->url_to_import = $url;

        $now_folder = date('Y/m');
        $this->upload_dir = wp_upload_dir();
        $this->attachment_filename = filter_var( urldecode( basename( $this->url_to_import ) ) , FILTER_SANITIZE_URL );

        $this->upload_folder = $this->upload_dir['path'] . '/' . $now_folder . '/';
        if ( ! file_exists($this->upload_folder ) )
        {
            // create directory/folder uploads.
            mkdir($this->upload_folder, 0755 , true );
            $this->writeLog( 'Create the directory: ' . $this->upload_folder );

        }



        $this->attachment_filepath =  $this->upload_folder . $this->attachment_filename;
        $this->image_details = $details;


        if ( strpos( $this->url_to_import,home_url() ) !== false )
        {
            $this->writeLog( "WAIT : this resource is already on this server ( $url ). Try to insert image only in db." );

            $check = $this->insert_attachment();

            if ( $check )
                $this->writeLog( "SUCCESS : $url resource added in db. Check comment with id: $this->comment_id" );
            else
                $this->writeLog( "ERROR : impossible add $url resource in db for comment with id: $this->comment_id" );


            return;
        }




        $check = $this->start_url_to_media();

        if ( $check )
            $this->writeLog( "SUCCESS : url imported correctly in comment with id: $this->comment_id. See the file imported : $this->attachment_url" );
        else
            $this->writeLog( "ERROR : impossible import $this->url_to_import resource for comment with id: $this->comment_id" );



    }


    function start_url_to_media()
    {

        $check = $this->url_to_media();

        return $check;
    }

    function url_to_media()
    {

        $check = false;
        try
        {
            $contents= file_get_contents($this->url_to_import );
            $savefile = fopen($this->attachment_filepath, 'w');
            fwrite($savefile, $contents);
            fclose($savefile);
            $check = $this->insert_attachment();
        }
        catch( Exception $e )
        {
            $this->writeLog( 'ERROR : impossible import image of url provided: ' . $this->url_to_import . '. Check in comment with id: ' . $this->comment_id );
        }

        return $check;
    }


    function insert_attachment()
    {
        $wp_filetype = wp_check_filetype($this->attachment_filename, null );

        $attachment = array_merge( $this->image_details ,array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => $this->attachment_filename,
            'post_content' => '',
            'post_status' => 'inherit'
        ));


        $attach_id = wp_insert_attachment( $attachment, $this->attachment_filepath );

        if ( $attach_id === 0 )
        {
            $this->writeLog( "ERROR : wp_insert_attachment function, impossible create attachment in media library with resource $this->url_to_import for comment with id: $this->comment_id" );
            return false;
        }


        $this->attachment_id = $attach_id;
        $imagenew = get_post( $attach_id );
        $fullsizepath = get_attached_file( $imagenew->ID );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        $this->attachment_url = wp_get_attachment_url( $this->attachment_id );



        return true;

    }


    function writeLog( $log_msg )
    {
        $basePath = MB_IMPORT_EXPORT_DIR;
        $dirpath = $basePath . '/import_logs';

        if ( ! file_exists($dirpath ) )
        {
            // create directory/folder uploads.
            mkdir($dirpath, 0755 , true );
        }
        $log_file_data = $dirpath.'/url_to_media_comments.log';
        $check = file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
    }

    function get_attachment()
    {
        return $this->attachment_id;
    }



}


