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
        ),
        'activity' => array(
            'field_type' => 'pods_fields',
            'field_name' => 'tipo'
        ),
        'who' => array(
            'field_type' => 'pods_fields',
            'field_name' => 'tipo'
        ),
        'where' => array(
            'field_type' => 'pods_fields',
            'field_name' => 'dest'
        )
    );
    public $terms_imported = array();

    public $taxonomies_to_import = array();

    //METAFIELDS
    //new key => old key
    public $meta_fields = array(
        "vn_sih" => "sih",
        "vn_fdn" => "fdn",
        "vn_new" => "new",
        "vn_diff" => "diff",
        "vn_mezza_pensione" => "mezza_pensione",
        "vn_sopraponte" => "sopraponte",
        "vn_durata" => "durata",
        "vn_note_dur" => "note_dur",
        "vn_partenze" => "partenze",
        "vn_part_sum" => "part_sum",
        "vn_desc_min" => "desc_min",
        "vn_note" => "note",
        "vn_desc" => "desc",
        "vn_prog" => "prog",
        "vn_scheda_tecnica" => "scheda_tecnica",
        "vn_part_pr" => "part_pr",
        "vn_come_arrivare" => "come_arrivare",
        "vn_latitude" => "latitude",
        "vn_longitude" => "longitude",
        "vn_prezzo" => "prezzo",
        "vn_prezzo_sc" => "prezzo_sc",
        "vn_ordine" => "ordine"
    );

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
                                $slug = $term['slug'];

                                $real_taxonomy = $webMapp_tax;

                                //filter TIPOLOGIA
                                if ( $field_name == 'tipo' )
                                {
                                    //filter TIPOLOGIA -> activity
                                    if ( in_array($slug, array(
                                        'in-bicicletta',
                                        'bici-e-barca',
                                        'a-piedi',
                                    ) ) )
                                    {
                                        $real_taxonomy = 'activity';
                                    }
                                    //filter TIPOLOGIA -> who
                                    elseif( in_array($slug, array(
                                        'in-famiglia',
                                        'esplorazione'
                                    ) ) )
                                    {
                                        $real_taxonomy = 'who';
                                    }
                                }

                                $search = array_search($name, $this->terms_imported[$real_taxonomy] );


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

                //METAFIELDS
                $meta_fields = array(
                    $this->meta_key_post_identifier => $old_post_cod
                );
                foreach( $this->meta_fields as $new_key => $old_key )
                {
                    if ( isset( $post['pods_fields'][$old_key] ) )
                        $meta_fields[$new_key] = $post['pods_fields'][$old_key];
                }


                if ( count($posts ) == 0 )
                {
                    $this->writeLog( "Impossible find a post with cod: $old_post_cod. A new one will be create." , 'SUCCESS');

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
                if ( $check instanceof WP_Error )//probably term already exists
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
                            $old_term_slug = $term[ 'slug' ];
                            $write = true;

                            //filter TIPOLOGIA -> activity
                            if ( $field_name == 'tipo' && $webMapp_tax == 'activity' )
                            {
                                if ( ! in_array($old_term_slug, array(
                                    'in-bicicletta',
                                    'bici-e-barca',
                                    'a-piedi',
                                ) ) )
                                $write = false;
                            }
                            //filter TIPOLOGIA -> who
                            elseif( $field_name == 'tipo' && $webMapp_tax == 'who' )
                            {
                                if ( ! in_array($old_term_slug, array(
                                    'in-famiglia',
                                    'esplorazione'
                                ) ) )
                                    $write = false;
                            }

                            //DEFAULT
                            if ( $write && ! isset( $this->taxonomies_to_import[$webMapp_tax][$old_term_id] ) )
                            {
                                $this->taxonomies_to_import[$webMapp_tax][$old_term_id] = array(
                                    'name' => $term[ 'name' ],
                                    'slug' => $old_term_slug
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