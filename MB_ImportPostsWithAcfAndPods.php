<?php


class MB_ImportPostsWithAcfAndPods
{

    //PATH VARS
    private $plugin_dir = MB_IMPORT_EXPORT_DIR;
    public $main_file_path = MB_IMPORT_EXPORT_DIR . '/exports/';
    private $log_file_path = null;


    //FILE CONTENT VARS
    public $main_file_php;
    public $main_file_json;

    /**
    public $translated_files_paths;
    public $translated_files_phps;
     **/

    //ENVIROMENT VARS
    public $post_type;
    public $post_type_from;
    public $lang;
    private $today;

    //TAXONOMIES VARS
    public $taxonomies_fields = array(
        'when' => array(
            'field_type' => 'pods_fields',
            'field_name' => 'periodo'
        )
    );
    public $terms_imported = array();

    public $taxonomies_to_import = array();

    //ENVIROMENT ELEMENTS
    public $taxonomies;


    //UPDATE POSTS VARS
    public $meta_key_post_identifier = 'n7webmapp_route_cod';
    public $meta_key_post_identifier_old = 'cod';
    public $meta_key_post_identifier_old_type = 'pods_fields';



    function __construct( $post_type = 'route' , $post_type_from = 'viaggio' , $lang = null )
    {
        if ( ! post_type_exists( $post_type ) )
        {
            $this->writeLog("Post type provided doesn't exists: $post_type",'ERROR' );
            return 0;
        }

        $this->post_type = $post_type;
        $this->post_type_from = $post_type_from;
        $this->lang = $lang;
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
            $this->prepare_taxonomies();
            $this->insert_terms();
            $this->update_posts();



        }
        catch( Exception $e )
        {
            trigger_error($e->getMessage() );
        }

    }

    function update_posts()
    {

        /**
         * Wpml
         * switch language before query
         */
        $lang = $this->lang ? $this->lang : 'it';

        if ( $lang )
        {
            global $sitepress;
            $sitepress->switch_lang($lang, true);
        }


        foreach ( $this->main_file_php as $id => $post )
        {
            if ( isset( $post[ $this->meta_key_post_identifier_old_type ][ $this->meta_key_post_identifier_old ] )
                && $post[ $this->meta_key_post_identifier_old_type ][ $this->meta_key_post_identifier_old ]
            )
            {
                //get cod
                $old_post_cod = $post[ $this->meta_key_post_identifier_old_type ][ $this->meta_key_post_identifier_old ];

                //get terms
                $post_terms = array();
                foreach ( $this->taxonomies_fields as $webMapp_tax => $details ) {
                    $field_type = $details['field_type'];
                    $field_name = $details['field_name'];

                    if ( isset($post[$field_type][$field_name]) )//periodo
                    {
                        if ( is_array($post[$field_type][$field_name]) ) {
                            $old_terms = $post[$field_type][$field_name];

                            //fix pods format if only 1 term is found
                            if ( isset( $old_terms['term_id'] ) )
                                $old_terms = array( $old_terms );

                            foreach ($old_terms as $term)
                            {
                                $name = $term['name'];
                                $search = array_search($name, $this->terms_imported[$webMapp_tax] );
                                if ( ! $search )
                                {
                                    $this->writeLog( "Impossible find in db a term in taxonomy: $webMapp_tax with name $name" , 'ERROR' );
                                }
                                else
                                {
                                    $post_terms[$webMapp_tax][] = $search;
                                }

                            }
                        }
                    }
                }

                //search posts with this cod
                $posts = get_posts(
                    array(
                        'posts_per_page' => -1,
                        'post_type' => $this->post_type,
                        'meta_key' => $this->meta_key_post_identifier,
                        'meta_value' => $old_post_cod,
                        'post_status' => 'any'
                    )
                );


                if ( count($posts ) == 0 )
                {
                    $this->writeLog( "Impossible find a post with cod: $old_post_cod. A new one will be create." );

                    $meta_fields = array(
                        $this->meta_key_post_identifier => $old_post_cod
                    );

                    //create new post
                    $post_id = $this->update_create_post( $post['pods_fields']['titolo'] ,$meta_fields );
                    $this->add_post_terms($post_id ,$post_terms);
                }
                elseif ( count($posts ) == 1 )
                {
                    $this->writeLog("Post with cod: $old_post_cod already exists.");
                }
                elseif ( count($posts ) > 1 )
                {
                    $this->writeLog("There are more than 1 posts with cod: $old_post_cod." , "ERROR" );
                }
            }
            else
            {
                $this->writeLog("Post with id: " . $post['ID'] . " doesn't have a meta 'cod' identifier");
            }

        }
    }

    function add_post_terms( $post_id , $terms_to_add , $append = false )
    {
        foreach ( $terms_to_add as $taxonomy => $terms )
            $check = wp_set_object_terms( $post_id, $terms, $taxonomy, $append );

    }

    function update_create_post( $title, $meta_fields = array() , $post_id = false )
    {

        $title = wp_strip_all_tags( $title );

        $my_post = array(
            'post_title'   => $title
        );



        if ( $post_id )
        {
            $my_post['ID'] = $post_id;
            $check = wp_update_post($my_post );
        }
        else
        {
            $other_post_args = array(
                'post_content'  => '',
                'post_status'   => 'publish',
                'post_author'   => 1,
                'post_type'     => $this->post_type

            );

            $my_post = array_merge($other_post_args, $my_post );

            $check = wp_insert_post( $my_post );
        }



        if ( $check instanceof WP_Error || ! $check )
        {
            $msg = $post_id ? 'add a' : 'update the';
            $this->writeLog("Impossible $msg post with id: " . $post_id ,'ERROR' );
            return false;
        }
        else
        {
            $post_id = $check;
        }


        foreach ( $meta_fields as $meta_key => $meta_value )
        {
            update_post_meta( $post_id , $meta_key, $meta_value );
        }


        return $check;


    }


    /**
     * Create new terms in db
     */
    function insert_terms()
    {
        if ( empty( $this->taxonomies_to_import ) )
        {
            $this->writeLog("No terms to insert" );
            return;
        }

        foreach ( $this->taxonomies_to_import as $taxonomy_name => $terms )
        {
            if ( ! isset( $this->terms_imported[$taxonomy_name] ) )
                $this->terms_imported[$taxonomy_name] = array();

            foreach ( $terms as $term )
            {
                $check = wp_insert_term($term['name'] ,$taxonomy_name );
                if ( $check instanceof WP_Error )
                {
                    $this->writeLog("Impossible create term with name: " . $term['name'] . " in $taxonomy_name taxonomy. Error: " . $check->get_error_message() );
                    if ( isset( $check->error_data['term_exists'] ) )
                        $this->terms_imported[$taxonomy_name][$check->error_data['term_exists']] = $term['name'];
                }
                else
                {
                    $this->writeLog("Term created: " . $term['name'] . " in $taxonomy_name taxonomy." , "SUCCESS" );
                    $new_term_id = $check['term_id'];
                    $this->terms_imported[$taxonomy_name][$new_term_id] = $term['name'];
                }

            }

        }
    }

    /**
     * Load all taxonomies
     */
    function prepare_taxonomies()
    {
        foreach ( $this->main_file_php as $id => $post )
        {
            foreach ( $this->taxonomies_fields as $webMapp_tax => $details )
            {
                $field_type = $details['field_type'];
                $field_name = $details['field_name'];

                if ( isset( $post[ $field_type ][ $field_name ] ) )//periodo
                {
                    if ( is_array( $post[ $field_type ][ $field_name ] ) )
                    {
                        $old_terms = $post[ $field_type ][ $field_name ];

                        //fix pods format if only 1 term is found
                        if ( isset( $old_terms['term_id'] ) )
                            $old_terms = array( $old_terms );

                        foreach ( $old_terms as $term )
                        {
                            $old_term_id = $term[ 'term_id' ];

                            if ( ! isset( $this->taxonomies_to_import[$webMapp_tax][$old_term_id] ) )
                            {
                                $this->taxonomies_to_import[$webMapp_tax][$old_term_id] = array(
                                    'name' => $term[ 'name' ],
                                    'slug' => $term[ 'slug' ]
                                );
                            }

                        }
                    }
                    else
                    {
                        $this->writeLog( "Impossible read terms of taxonomy: $webMapp_tax, from post with id (old): " . $post['ID'] );
                    }

                }
            }
        }

    }


    /**
     * @throws Exception
     */
    function init()
    {
        $this->main_file_json = (string) $this->get_content();
        $this->main_file_php = (array) json_decode( $this->main_file_json , true );

        if ( ! $this->main_file_php )
        {
            $error = "Impossible convert json in php";
            $this->writeLog($error , 'ERROR' );
            throw new Exception($error );
        }

    }


    function get_content()
    {
        $filepath = $this->main_file_path . 'fields_export_last_' . $this->post_type_from . '_';
        if ( $this->lang )
            $filepath .= $this->lang;
        else
            $filepath .= 'it';

        $filepath .= '.json';

        if ( ! file_exists( $filepath) )
        {
            $this->writeLog("Json file doesn't exists: " . $filepath , 'ERROR' );
            return '{}';
        }

        $content = file_get_contents( $filepath );
        if ( ! $content )
        {
            $this->writeLog('Impossible load json: ' . $filepath , 'ERROR' );
            return '{}';
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

            $this->log_file_path = $dirpath.'/import_log_' . $this->today . '.log';
            $check1 = file_put_contents($this->log_file_path,"##################### START IMPORT A JSON FILE [ $this->today | $now ]"  . "\n", FILE_APPEND);
        }

        $msg = "$kind [$now]: $log_msg ";
        $check2 = file_put_contents($this->log_file_path,$msg  . "\n", FILE_APPEND);

        return $check1 && $check2;
    }

}