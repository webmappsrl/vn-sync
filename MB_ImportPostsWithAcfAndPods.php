<?php


class MB_ImportPostsWithAcfAndPods
{

    //PATH VARS
    private $plugin_dir = MB_IMPORT_EXPORT_DIR;
    public $main_file_path = MB_IMPORT_EXPORT_DIR . '/exports/';
    private $log_file_path = null;


    //FILE CONTENT VARS
    public $main_file_php;//italian
    public $main_file_json;//italian

    public $eng_file_php;
    public $eng_file_json;

    /**
    public $translated_files_paths;
    public $translated_files_phps;
     **/

    //ENVIROMENT VARS
    public $post_type;
    public $post_type_from;
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
        'featured_image' => "image",
        "n7webmap_track_media_gallery" => 'gallery'
    );

    public $new_meta_fields = array(
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
        "vn_ordine" => "ordine",
        "vn_meta_dog" => "meta_dog",
        "vn_hide" => "hide",
        //immagini
        "vn_immagine_mappa" => "immagine_mappa"
    );

    //ENVIROMENT ELEMENTS
    public $taxonomies;


    //UPDATE POSTS VARS
    public $meta_key_post_identifier = 'n7webmapp_route_cod';
    public $meta_key_post_identifier_old = 'cod';
    public $meta_key_post_identifier_old_type = 'pods_fields';

    // posts imported
    // old_id => new_id
    public $posts_imported = array();



    function __construct( $post_type = 'route' , $post_type_from = 'viaggio' , $lang = null )
    {
        if ( ! post_type_exists( $post_type ) )
        {
            $this->writeLog("Post type provided doesn't exists: $post_type",'ERROR' );
            return 0;
        }

        $this->post_type = $post_type;
        $this->post_type_from = $post_type_from;

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

            //prepare an array of taxonomies
            $this->prepare_taxonomies( $this->main_file_php );

            //impossible to
            //$this->prepare_taxonomies( $this->eng_file_php );

            $this->insert_terms();

            $this->update_posts( $this->main_file_php ,'it' );
            $this->writeJsonFromPhp( $this->posts_imported , 'it' );

            $this->update_posts( $this->eng_file_php , 'en' );


            $this->writeLog( 'END IMPORT' , 'SUCCESS' );

        }
        catch( Exception $e )
        {
            trigger_error($e->getMessage() );
        }

    }

    function update_posts( $file , $lang )
    {
        $this->posts_iteration( $file , $lang );
    }

    function posts_iteration( $php_file , $lang )
    {
        foreach ( $php_file as $id => $post )
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

                                $search = false;
                                if ( isset( $this->terms_imported[$real_taxonomy] ) )
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


                /**
                 * Wpml
                 * switch language before query
                 */
                global $sitepress;
                if ( $lang != 'it' )
                    $sitepress->switch_lang($lang, true);




                //search posts with this cod
                $posts = get_posts(
                    array(
                        'posts_per_page' => -1,
                        'post_type' => $this->post_type,
                        'meta_key' => $this->meta_key_post_identifier,
                        'meta_value' => $old_post_cod,
                        'post_status' => 'any',
                        'suppress_filters' => false
                    )
                );


                $sitepress->switch_lang('it', true);


                //METAFIELDS
                $meta_fields = array(
                    $this->meta_key_post_identifier => $old_post_cod
                );
                foreach( $this->meta_fields as $new_key => $old_key )
                {
                    if ( isset( $post['pods_fields'][$old_key] ) )
                        $meta_fields[$new_key] = $post['pods_fields'][$old_key];
                }

                $new_meta_fields = array();
                foreach( $this->new_meta_fields as $new_key => $old_key )
                {
                    if ( isset( $post['pods_fields'][$old_key] ) )
                        $new_meta_fields[$new_key] = $post['pods_fields'][$old_key];
                }

                /**
                 * POST ELEMENTS
                 */

                $post_elements = array(
                    'post_content' => isset($post['post_content']) ? $post['post_content'] : '',
                    'post_excerpt' => isset($post['post_excerpt']) ? $post['post_excerpt'] : '',
                    'post_status' => isset($post['post_status']) ? $post['post_status'] : ''
                );

                $post_id = false;


                /**
                 * POST TRANSLATIONS
                 */
                $translations = array();
                if ( $lang != 'it' )
                    $translations = isset( $post['translations'] ) && is_array($post['translations'] ) ? $post['translations'] : array();

                if ( count($posts ) == 0 )
                {
                    $this->writeLog( "Impossible find a post with cod: $old_post_cod. A new one will be create." , 'SUCCESS');

                    //
                    $meta_fields_to_update = array_merge( $meta_fields , $new_meta_fields );
                    //create new post
                    $post_id = $this->update_create_post( $post['pods_fields']['titolo'] , $post_elements, $meta_fields_to_update );
                    $this->add_post_terms($post_id ,$post_terms);
                }
                elseif ( count($posts ) == 1 )
                {
                    $post_id = $posts[0];
                    $post_id = $post_id->ID;

                    $this->writeLog("Post with cod: $old_post_cod already exists, only new metafields will be update on post with id $post_id" , 'SUCCESS');

                    $meta_fields_to_update = $new_meta_fields;
                    $post_id = $this->update_create_post( $post['pods_fields']['titolo'] , array(), $meta_fields_to_update , $post_id );


                }
                elseif ( count($posts ) > 1 )
                {
                    $this->writeLog("There are more than 1 posts with cod: $old_post_cod." , "ERROR" );
                }


                if ( $post_id )
                {
                    $this->posts_imported[$id] = $post_id;


                    //set translations
                    //translations can be empty
                    //used only if lang != 'it'
                    foreach ( $translations as $lang_code => $tr_post_id )
                    {
                        $italian_post_id = isset( $this->posts_imported[$tr_post_id] ) ? $this->posts_imported[$tr_post_id] : false;
                        if ( $italian_post_id )
                            $this->vt_wpml_set_post_translation( $italian_post_id , $post_id , $lang );
                    }
                }





            }
            else
            {
                $this->writeLog("Post with id: " . $post['ID'] . " doesn't have a meta 'cod' identifier" , 'ERROR');
            }

        }
    }

    function add_post_terms( $post_id , $terms_to_add , $append = false )
    {
        foreach ( $terms_to_add as $taxonomy => $terms )
            $check = wp_set_object_terms( $post_id, $terms, $taxonomy, $append );

    }

    function update_create_post( $title, $post_elements , $meta_fields = array() , $post_id = false )
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
                'post_author'   => 1,
                'post_type'     => $this->post_type,
            );

            $my_post = array_merge( $post_elements , $my_post , $other_post_args );

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
            if ( $meta_key == 'featured_image' )
            {
                if ( isset( $meta_value['guid'] ) && $meta_value['guid'] )
                {
                    $details = $this::get_image_details( $meta_value );
                    $actual_url = MB_ImportPostsWithAcfAndPods::get_image_url( $meta_value['guid'] );
                    new VT_UrlToMedia_FeaturedImage($post_id,$actual_url, $details );
                }

            }
            elseif( $meta_key == 'vn_immagine_mappa' )
            {
                if ( isset( $meta_value['guid'] ) && $meta_value['guid'] )
                {
                    $details = $this::get_image_details( $meta_value );
                    $actual_url = $actual_url = MB_ImportPostsWithAcfAndPods::get_image_url( $meta_value['guid'] );
                    new VT_UrlToMedia( $post_id , $actual_url ,$meta_key , $details );
                }

            }
            elseif( $meta_key == 'n7webmap_track_media_gallery' )
            {

                $urls = array_map(function($i)
                {
                    if ( isset( $i['guid'] ) )
                        return $i['guid'];
                    else return false;
                    },
                    $meta_value );

                $urls = MB_ImportPostsWithAcfAndPods::get_image_urls( $urls );

                $detailss = array_map(function($i)
                {
                    return MB_ImportPostsWithAcfAndPods::get_image_details( $i );

                },
                    $meta_value );

                new VT_UrlToMedia_Gallery( $post_id , $urls , $meta_key , $detailss );
            }
            else
                update_post_meta( $post_id , $meta_key, $meta_value );
        }



        return $post_id;


    }

    static function get_image_url( $url )
    {
        $pos = strpos($url , '/wp-content/');
        $file_relative_path = substr( $url , $pos );
        $verde_natura_base_url = 'https://www.verde-natura.it/wpsite';
        $filename = basename( $url );
        $last_slash_pos = strrpos( $file_relative_path , '/');
        $file_sub_relative_path = substr( $file_relative_path ,0, $last_slash_pos );

        $upload_dir = wp_upload_dir(); // Array of key => value pairs
        /*
            $upload_dir now contains something like the following (if successful)
            Array (
                [path] => C:\path\to\wordpress\wp-content\uploads\2010\05
                [url] => http://example.com/wp-content/uploads/2010/05
                [subdir] => /2010/05
                [basedir] => C:\path\to\wordpress\wp-content\uploads
                [baseurl] => http://example.com/wp-content/uploads
                [error] =>
            )
            // Descriptions
            [path] - base directory and sub directory or full path to upload directory.
            [url] - base url and sub directory or absolute URL to upload directory.
            [subdir] - sub directory if uploads use year/month folders option is on.
            [basedir] - path without subdir.
            [baseurl] - URL path without subdir.
            [error] - set to false.
        */


        $now_folder = date('Y/m');
        $actual_path = $upload_dir['path'] . '/' . $now_folder . '/' . $filename;

        if ( file_exists( $actual_path ) )
            $actual_url = $upload_dir['url'] . '/' . $filename;
        else
            $actual_url = $verde_natura_base_url . $file_sub_relative_path . '/' . urlencode( $filename );

        return $actual_url;
    }

    static function get_image_urls( $urls )
    {
        $new_urls = array();
        foreach ( $urls as $url )
            if ( $url )
                $new_urls[] = MB_ImportPostsWithAcfAndPods::get_image_url($url );

        return $new_urls;
    }

    static function get_image_details( $image )
    {
        $r = array();

        $post_title = isset( $image['post_title'] ) ? $image['post_title'] : false;
        if ( $post_title )
            $r['post_title'] = $post_title ;

        $post_excerpt = isset( $image['post_excerpt'] ) ? $image['post_excerpt'] : false;
        if ( $post_excerpt )
            $r['post_excerpt'] = $post_excerpt ;

        $post_content = isset( $image['post_content'] ) ? $image['post_content'] : false;
        if ( $post_content )
            $r['post_content'] = $post_content;

        return $r;


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
     * @param $file
     */
    function prepare_taxonomies( $file )
    {
        foreach ( $file as $id => $post )
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
        //italian
        $this->main_file_json = (string) $this->get_content();
        $this->main_file_php = (array) json_decode( $this->main_file_json , true );

        //english
        $this->eng_file_json = (string) $this->get_content( 'en' );
        $this->eng_file_php = (array) json_decode( $this->eng_file_json , true );

        if ( ! $this->main_file_php || ! $this->eng_file_php )
        {
            $error = "Impossible convert json in php";
            $this->writeLog($error , 'ERROR' );
            throw new Exception($error );
        }

    }


    function get_content( $lang = 'it' )
    {
        $filepath = $this->main_file_path . 'fields_export_last_' . $this->post_type_from . '_';

        $filepath .= $lang;

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



    /**
     * @param $post_id
     * @param $translated_post_id
     * @param $language_code
     * @return bool
     */
    function vt_wpml_set_post_translation( $post_id , $translated_post_id , $language_code )
    {
        // get the language info of the original post
        // https://wpml.org/wpml-hook/wpml_element_language_details/

        $post = get_post( $post_id );
        if ( ! $post )
            return false;

        $post_type = $post->post_type;

        $get_language_args = array( 'element_id' => $post_id , 'element_type' => $post_type );
        $original_post_language_info = apply_filters( 'wpml_element_language_details', null, $get_language_args );

        $set_language_args = array(
            'element_id' => $translated_post_id,
            'element_type' => "post_$post_type",
            'trid' => $original_post_language_info->trid,
            'language_code' => $language_code,
            'source_language_code' => $original_post_language_info->language_code
        );


        do_action( 'wpml_set_element_language_details', $set_language_args );

        return true;
    }


    function writeJsonFromPhp( $msg_php , $lang = null )
    {
        $folder = "import_logs";
        $basePath = __DIR__;
        $dirpath = $basePath . '/' . $folder;

        if ( ! file_exists($dirpath ) )
        {
            // create directory/folder uploads.
            mkdir($dirpath, 0755 , true );
        }

        $file_path = "$dirpath/last_imported_{$this->post_type}";

        if ( $lang )
            $file_path .= "_$lang";

        $file_path .= '.json';

        if ( file_exists($file_path ) )
        {
            $time = time();
            $new_filepath = substr( $file_path , 0,-5 );
            rename($file_path,"{$new_filepath}_before_{$time}.json");
        }

        $check = false;
        $msg_json = json_encode( $msg_php );
        if ( json_last_error() == JSON_ERROR_NONE )
        {
            $check = file_put_contents($file_path, $msg_json);
        }

        return $check;

    }

}