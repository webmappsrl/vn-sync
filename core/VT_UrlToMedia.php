<?php
/**
 *
 * L'oggetto prende un url da un metafield e carica l'immagine nei media associandola ad un post id
 *
 *
 *
 */

class VT_UrlToMedia
{

    public $post_id;
    public $meta_key;

    public $url_to_import;

    public $post;

    private $upload_dir;
    public $attachment_id;
    public $attachment_url;
    public $attachment_filename;
    public $attachment_filepath;


    function __construct( $post , $url , $meta_key )
    {

        if ( is_numeric( $post ) )
            $post = get_post( $post );


        $this->post = $post;
        $this->post_id = $post->ID;


        if ( filter_var($url, FILTER_VALIDATE_URL) === false )
        {
            $this->writeLog( 'Value stored in meta key provided isn\'t a url : ' . $url . '. Check post with id: ' . $this->post_id);
            return;
        }

        $this->url_to_import = $url;

        if ( strpos( $this->url_to_import,home_url() ) !== false )
        {
            $this->writeLog( 'Resource is already on this domain. Check post with id: ' . $this->post_id);
            return;
        }


        $this->meta_key = $meta_key;

        $this->start_url_to_media();


    }


    function start_url_to_media()
    {

        $this->upload_dir = wp_upload_dir();

        $check = $this->url_to_media();
        if ( $check )
            $this->writeLog( "Url imported correctly in post with id: $this->post_id. See the file imported : $this->attachment_url" );
        return $check;
    }

    function url_to_media()
    {
        $this->attachment_filename = filter_var( urldecode( basename( $this->url_to_import ) ) , FILTER_SANITIZE_URL );
        $this->attachment_filepath = $this->upload_dir['path'] . '/' . $this->attachment_filename;

        $contents= file_get_contents($this->url_to_import );

        if ( $contents === false )
        {
            //$this->url_to_import = str_replace( 'http' , 'https' , $this->url_to_import );
            $contents = file_get_contents( $this->url_to_import );
            if ( $contents === false )
            {
                $this->writeLog( 'Impossible get contents in url provided ( http or in https ): ' . $this->url_to_import . '. Set empty value. Check in post with id: ' . $this->post_id );
                return false;
            }

        }

        try {

            $savefile = fopen($this->attachment_filepath, 'w');
            fwrite($savefile, $contents);
            fclose($savefile);

        }
        catch( Exception $e )
        {
            $this->writeLog( $e->getMessage() );
            return false;
        }

        $check = $this->insert_attachment();

        return $check;


    }


    function insert_attachment()
    {
        $wp_filetype = wp_check_filetype($this->attachment_filename, null );

        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => $this->attachment_filename,
            'post_content' => '',
            'post_status' => 'inherit'
        );


        $attach_id = wp_insert_attachment( $attachment, $this->attachment_filepath , $this->post_id);

        if ( $attach_id === 0 )
        {
            $this->writeLog( 'Impossible create attachment in media library for post id: ' . $this->post_id );
            return false;
        }


        $this->attachment_id = $attach_id;
        $imagenew = get_post( $attach_id );
        $fullsizepath = get_attached_file( $imagenew->ID );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        $this->attachment_url = wp_get_attachment_url( $this->attachment_id );
        $this->update_old_meta();


        return true;

    }

    function update_old_meta()
    {
        if ( $this->meta_key)
            $test = update_post_meta( $this->post_id ,  $this->meta_key ,$this->attachment_id );
    }

    function writeLog( $log_msg )
    {
        $log_folder = "url_to_media_import";
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


