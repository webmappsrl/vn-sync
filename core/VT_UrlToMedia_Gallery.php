<?php
/**
 *
 * L'oggetto prende un url da un metafield e carica l'immagine nei media associandola ad un post id
 *
 *
 *
 */

class VT_UrlToMedia_Gallery
{

    public $post_id;
    public $meta_key;

    public $urls_to_import;

    public $post;



    function __construct( $post , $urls , $meta_key )
    {


        if ( is_numeric( $post ) )
            $post = get_post( $post );


        $this->post = $post;
        $this->post_id = $post->ID;

        $attachments = array();

        foreach ( $urls as $url )
        {
            $attachment = new VT_UrlToMedia( $post , $url , '' );
            $attachment_id = $attachment->get_attachment();
            if ( is_numeric( $attachment_id ) )
                $attachments[] = $attachment_id;

        }

        if ( ! empty( $attachments ) )
            update_field( $meta_key, $attachments, $this->post_id );



    }




}


