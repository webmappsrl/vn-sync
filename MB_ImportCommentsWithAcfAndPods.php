<?php


class MB_ImportCommentsWithAcfAndPods
{

    //PATH VARS
    private $plugin_dir = MB_IMPORT_EXPORT_DIR;
    public $main_file_path = MB_IMPORT_EXPORT_DIR . '/exports/';
    private $log_file_path = null;


    //FILE CONTENT VARS
    public $main_file_php_imported_viaggi;
    public $main_file_json_imported_viaggi;


    public $main_file_php;//italian
    public $main_file_json;//italian


    /**
    public $translated_files_paths;
    public $translated_files_phps;
     **/

    //ENVIROMENT VARS
    public $post_type;
    public $post_type_from;
    private $today;


    // posts imported
    // old_id => new_id
    public $comments_imported = array();



    function __construct()
    {

        $this->today = $now_days = date('d-m-Y');

        try
        {
            $this->import();
        }
        catch (Exception $e)
        {
            return 0;
        }


        return $this->today;
    }

    function import()
    {
        try
        {
            $this->init();

            $this->comments_iteration( $this->main_file_php );

            $this->writeLog( 'END IMPORT' , 'SUCCESS' );

        }
        catch( Exception $e )
        {
            trigger_error($e->getMessage() );
        }

    }


    function comments_iteration( $php_file  )
    {
        foreach ( $php_file as $id => $comment )
        {
            //create new comment and its child
            $comment_id = $this->update_create_comment( $comment );
        }
    }


    function update_create_comment( $comment )
    {

        //get fields
        $comment_date = $comment['post_date'];
        $comment_date_gmt = get_gmt_from_date( $comment['post_date'] );

        $pods_fields = isset( $comment['pods_fields'] ) ? $comment['pods_fields'] : array();

        $comment_old_route = isset( $pods_fields['viaggio'] ) ? $pods_fields['viaggio'] : '' ;
        $comment_old_route_id = $comment_old_route && isset( $comment_old_route['ID'] ) ? $comment_old_route['ID'] : false;
        $comment_route_id = $comment_old_route_id && isset( $this->main_file_php_imported_viaggi[ $comment_old_route_id ] ) ? $this->main_file_php_imported_viaggi[ $comment_old_route_id ] : 0 ;

        $comment_author_name = isset( $pods_fields['nome'] ) ? $pods_fields['nome'] : '';
        $comment_journey_date = isset( $pods_fields['data_v'] ) ? $pods_fields['data_v'] : '';
        $comment_content = isset( $pods_fields['msg'] ) ? $pods_fields['msg'] : '';

        //set fields
        $comment_metafields = array(
            'wm_comment_journey_date' => $comment_journey_date
        );

        $comment_data = array(
            'comment_author' => $comment_author_name,
            'comment_content' => $comment_content,
            'comment_date' => $comment_date,
            'comment_date_gmt' => $comment_date_gmt,
            'comment_post_ID' => $comment_route_id,
            //'comment_type' => 'comment',//static
            'comment_meta' => $comment_metafields
        );

        $parent_comment = $this->insert_comment( $comment_data );

        if ( $parent_comment )
        {
            //gallery
            $gallery_images = $pods_fields['gallery'];
            if ( $gallery_images )
            {
                $urls = array_map( function($i)
                {
                    if ( isset( $i['guid'] ) )
                        return $i['guid'];
                    else
                        return false;
                },
                    $gallery_images );

                $urls = MB_ImportPostsWithAcfAndPods::get_image_urls( $urls );

                $details = array_map( function($i)
                {
                    return MB_ImportPostsWithAcfAndPods::get_image_details( $i );
                },
                    $gallery_images );

                //update media

                foreach ( $urls as $key => $url )
                {
                    $attachment = new VT_Comments_UrlToMedia_Gallery( $parent_comment , $url , $details[$key] );
                    $attachment_id = $attachment->get_attachment();
                    if ( is_numeric( $attachment_id ) )
                        $attachments[] = $attachment_id;

                }

                if ( ! empty( $attachments ) )
                    update_field( 'wm_comment_gallery', $attachments, 'comment_' . $parent_comment );

            }

            //child comment
            if ( isset( $pods_fields['risp'] ) )
            {
                $comment_child_data = array(
                    'comment_author' => 'Verde Natura',
                    'comment_content' => $pods_fields['risp'],
                    'comment_date' => $comment_date,
                    'comment_date_gmt' => $comment_date_gmt,
                    'comment_post_ID' => $comment_route_id,
                    //'comment_type' => 'comment',
                    'comment_parent' => $parent_comment
                );
                $this->insert_comment( $comment_child_data );
            }
        }


        return $parent_comment;
    }

    function insert_comment( $comment_data )
    {
        $check = wp_insert_comment( $comment_data );

        if ( ! $check )
        {
            $this->writeLog("Impossible add comment of route with id : " . $comment_data['comment_post_ID'] ,'ERROR' );
        }
        else
        {
            $this->writeLog("Added comment with id : $check",'SUCCESS' );
        }

        return $check;
    }









    /**
     * @throws Exception
     */
    function init()
    {
        //italian viaggi
        $this->main_file_json_imported_viaggi = (string) $this->get_file_content( MB_IMPORT_EXPORT_DIR . '/import_logs/last_imported_route_it.json' );
        $this->main_file_php_imported_viaggi = (array) json_decode( $this->main_file_json_imported_viaggi , true );

        //italian comments
        $this->main_file_json = (string) $this->get_content( 'commento_it' );
        $this->main_file_php = (array) json_decode( $this->main_file_json , true );


        if ( ! $this->main_file_php || ! $this->main_file_php_imported_viaggi )
        {
            $error = "Impossible convert json in php";
            $this->writeLog($error , 'ERROR' );
            throw new Exception($error );
        }

    }


    function get_content( $file_relative_name )
    {
        $filepath = $this->main_file_path . 'fields_export_last_' . $file_relative_name . '.json';


       $content = $this->get_file_content( $filepath );
       if ( ! $content )
           $content = '{}';

        return $content;

    }

    function get_file_content( $filepath )
    {
        if ( ! file_exists( $filepath) )
        {
            $this->writeLog("Json file doesn't exists: " . $filepath , 'ERROR' );
            return false;
        }

        $content = file_get_contents( $filepath );
        if ( ! $content )
        {
            $this->writeLog('Impossible load json: ' . $filepath , 'ERROR' );
            return false;
        }

        return $content;
    }

    function writeLog( $log_msg , $kind = 'WARNING' )
    {
        $log_folder = "import_logs";
        $basePath = $this->plugin_dir;
        $dirpath = $basePath . '/' . $log_folder;

        if ( ! file_exists($dirpath ) )
        {
            // create directory/folder uploads.
            mkdir($dirpath, 0755 , true );
        }

        $now = date('G:i:s');
        $check1 = true;
        if ( ! $this->log_file_path )
        {

            $this->log_file_path = $dirpath.'/import_comments_log_' . $this->today . '.log';
            $check1 = file_put_contents($this->log_file_path,"##################### START IMPORT A JSON FILE [ $this->today | $now ]"  . "\n", FILE_APPEND);
        }

        $msg = "$kind [$now]: $log_msg ";
        $check2 = file_put_contents($this->log_file_path,$msg  . "\n", FILE_APPEND);

        return $check1 && $check2;
    }



}