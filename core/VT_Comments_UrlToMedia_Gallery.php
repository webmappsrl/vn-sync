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
            $this->writeLog( 'Resource is already on this domain. Try to insert image only in db. Check comment with id: ' . $this->comment_id );

            $this->insert_attachment();

            return;
        }




        $this->start_url_to_media();


    }


    function start_url_to_media()
    {

        $check = $this->url_to_media();
        if ( $check )
            $this->writeLog( "Url imported correctly in comment with id: $this->comment_id. See the file imported : $this->attachment_url" );
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
            $this->writeLog( 'Impossible import image of url provided: ' . $this->url_to_import . '. Check in comment with id: ' . $this->comment_id );
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
            $this->writeLog( 'Impossible create attachment in media library for comment id: ' . $this->comment_id );
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
        $log_folder = "url_to_media_import_comments";
        $basePath = __DIR__;
        $dirpath = $basePath . '/' . $log_folder;

        if ( ! file_exists($dirpath ) )
        {
            // create directory/folder uploads.
            mkdir($dirpath, 0755 , true );
        }
        $log_file_data = $dirpath.'/url_to_media.log';
        $check = file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
    }

    function get_attachment()
    {
        return $this->attachment_id;
    }



}


