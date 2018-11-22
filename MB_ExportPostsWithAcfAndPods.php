<?php
/**
 * Created by PhpStorm.
 * User: marco
 * Date: 19/11/18
 * Time: 12:48
 */

class MB_ExportPostsWithAcfAndPods
{
    public $post_type;
    public $activeLanguages;

    public $main_language = 'it';

    public $availablePods = array(
        'barca',
        'commento',
        'dest_tax',
        'extra_tax',
        'formula_tax',
        'naz_barca',
        'periodo_tax',
        'post',
        'preventivo',
        'tipo_tax',
        'viaggio'
    );

    function __construct( $post_type = 'post' )
    {

        $this->post_type = $post_type;
        $this->activeLanguages = apply_filters( 'wpml_active_languages', NULL, 'orderby=id&order=desc' );


        foreach ( $this->activeLanguages as $lang_code => $details )
            $this->mb_export_post_type( $lang_code );



    }



    //todo load all pods set with post type
    function mb_export_post_type( $lang = null )
    {

        /**
         * Wpml
         * switch language before query
         */
        if ( $lang )
        {
            global $sitepress;
            $sitepress->switch_lang($lang, true);
        }


        /**
         * Posts query
         */
        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'any',
            'nopaging' => true
        );
        $wp_query = new WP_Query( $args );

        //get only viaggio pods fields
        $pods_fields = $this->mb_get_pods_fields_by_name('viaggio');

        $r_php_array = array();

        while( $wp_query->have_posts()) : $wp_query->the_post();

            $fields = array();
            $post_id = get_the_ID();
            $post_array = get_post( $post_id , ARRAY_A );
            $taxonomies = get_taxonomies();


            /**
             * ACF
             */

            $acf_fields = get_fields($post_id) ;


            /***
             * Pods
             */

            $pods_fields_with_values = array();
            $pods = pods('viaggio' , $post_id );
            foreach( $pods_fields as $key => $pod_field )
            {
                $pod_field_value = $pods->field( $pod_field , true );
                if ( $pod_field_value )
                    $pods_fields_with_values[$key] = $pod_field_value;
            }


            /**
             * WPML
             */

            $post_array['lang'] = apply_filters( 'wpml_post_language_details', null, $post_id );

            if ( $lang && $lang != $this->main_language )
            {
                $translations = array();

                foreach ( $this->activeLanguages as $language_code => $details )
                {

                    if ( $language_code != $lang )
                    {
                        $tr = apply_filters( 'wpml_object_id', $post_id, $this->post_type , FALSE, $lang );
                        if ( $tr )
                            $translations[ $language_code ] = $tr;
                    }
                }

                if ( $translations )
                    $post_array['translations'] = $translations;
            }


            /**
             * taxonomies
             */
            if ( $taxonomies )
            {
                $tax_arra = array();
                foreach ( $taxonomies as $tax )
                {
                    $terms = get_the_terms( $post_id , $tax );
                    if ( $terms )
                    {
                        $tax_arra[$tax] = $terms;
                    }
                }

                if ( $tax_arra )
                    $post_array['taxonomies'] = $tax_arra;
            }

            /**
             * Set elements to save in json
             */

            //pods
            if ( $pods_fields_with_values )
                $fields['pods_fields'] = $pods_fields_with_values;

            //acf
            if ( $acf_fields )
                $fields['acf_fields'] = $acf_fields;

            //merge all
            $fields = array_merge( $post_array , $fields );

            $r_php_array[$post_id] = $fields;



        endwhile;

        wp_reset_query();

        $stop = '';

        $this->writeJsonFromPhp( $r_php_array , $lang );
    }


    function mb_get_pods_fields_by_name( $pods_name )
    {
        $test = $this->mb_get_pods_fields();

        if ( ! is_array( $test ) )
            return array();

        return isset( $test[$pods_name] ) ? $test[$pods_name] : array();
    }


    /**
     *
     * $pod_name => array(
    $field_name => $field_option (array )
     *      ...
     * )
     * @return array
     */
    function mb_get_pods_fields()
    {
        /**
         * Get pods fields of viaggi
         */


        $pods = array();
        $pods_fields = array();

        foreach( $this->availablePods as $pod )
        {
            $new_pod = pods( $pod );
            $pods[$pod] = $new_pod;


            $fields = $new_pod->fields;

            $pods_fields[$pod] = $fields;
        }


        return $pods_fields;
    }



    function writeJsonFromPhp( $msg_php , $lang = null )
    {
        $folder = "exports";
        $basePath = __DIR__;
        $dirpath = $basePath . '/' . $folder;

        if ( ! file_exists($dirpath ) )
        {
            // create directory/folder uploads.
            mkdir($dirpath, 0755 , true );
        }

        $file_path = "$dirpath/fields_export_last_{$this->post_type}";

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