<?php
/**
 * Created by PhpStorm.
 * User: marco
 * Date: 29/10/18
 * Time: 12:08
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
    exit;

/**
 * Class VtAjaxHandler
 *
 * This object is useful to automatize ajax register actions.
 * Improved with security check with wordpress nonce and request parameters sanitize.
 * Autoload parameters for client side.
 *
 */
class VtAjaxHandler
{

    /**
     * Ajax action
     * used also in nonce action
     * @var string
     */
    protected $action;

    /**
     * Ajax callback
     * for server side
     * @var string
     */
    protected $callback_name;

    /**
     * Nonce
     * https://developer.wordpress.org/reference/functions/wp_create_nonce/
     * used for security reasons
     * @var string
     */
    protected $nonce;

    /**
     * Parameters to load in head with wp_localize_script
     * https://developer.wordpress.org/reference/functions/wp_localize_script/
     * @var array
     */
    private $js_params;

    /**
     * Script handle the data will be attached to.
     * see first parameter of wp_localize_script
     * https://developer.wordpress.org/reference/functions/wp_localize_script/
     * @var string
     */
    private $js_handle;

    /**
     * Path of optional file to enqueue
     * @var string
     */
    private $js_path;


    /**
     * VtAjaxHandler constructor.
     * @param $action - action for js ajax call on wp-admin/admin-ajax.php
     * @param $callback_name - callback of php function for server side
     * @param bool $private - is this ajax for logged in users ?
     * @param bool $public - is this ajax for not logged in users ?
     * @param array $js_params - params to print in an js object after script handle provided
     * @param $js_handle - script handle ( registered with wp_enqueue_scripts ) to attach previous params parameter
     * @param string $js_path - path of js to enqueue
     */

    function __construct(
        $action,
        $callback_name ,
        $private = true,
        $public = false ,
        $js_params = array() ,
        $js_handle = '',
        $js_path = ''
    )
    {

        if ( empty( $action ) || empty( $callback_name ) )
            return false;

        if ( ! $private && ! $public )
            return false;

        if ( ! function_exists( $callback_name ) )
            return false;


        $this->action = $action;
        $this->callback_name = $callback_name;


        //create a nonce that client must send via ajax

        add_action( 'init' , array( $this , 'create_nonce') );

        if ( $private )
        {
            //logged in users
            add_action( "wp_ajax_$this->action", array( $this , 'call_callback' ) );
            add_action( "admin_enqueue_scripts" , array( $this ,'localize_js_object' ) );
        }

        if ( $public )
        {
            //no logged in users
            add_action( "wp_ajax_nopriv_$this->action", array( $this , 'call_callback' ) );
            add_action( "wp_enqueue_scripts" , array( $this ,'localize_js_object' ) );
        }

        $this->js_handle = $js_handle;
        $this->js_params = $js_params;
        $this->js_path = $js_path;

        if ( ! empty( $js_path ) )
            wp_register_script( $this->js_handle , $this->js_path , array() ,false,true );

        return $this;
    }


    /**
     * Call php function provided by user
     * Sanitize parameters in $_REQUEST global variable
     * Check nonce
     * @return mixed
     */
    public function call_callback()
    {

        $params = $this->sanitize_params();

        $nonce = isset( $params['nonce'] ) ? $params['nonce'] : false;

        if ( ! $nonce )
            wp_die('Forbidden');

        $check_nonce = wp_verify_nonce($nonce, $this->action );

        //check nonce validity ad its generation was 0-12 hours ago
        if ( ! $check_nonce || $check_nonce > 1 )//$check_nonce should be equal to 1
            wp_die('Forbidden');

        $return_val = call_user_func( $this->callback_name , $params );

        return $return_val;
    }


    /**
     * Sanitize an array of strings
     * Strings can be json
     * @return array
     */
    private function sanitize_params()
    {
        $params = $_REQUEST;
        $sanitized_params = array();

        foreach ( $params as $key => $param )
        {
            if ( ! is_array( $param ) && is_string( $param ) )
            {
                if ( ! $this->isJson($param ) )
                    $param = filter_var($param,FILTER_SANITIZE_STRING );

                $sanitized_params[ $key ] = $param;
            }

        }

        return $sanitized_params;
    }


    /**
     * Check if string provided is in json format
     * @param $string
     * @return bool
     */
    public function isJson($string) {
        json_decode($string);
        return ( json_last_error() == JSON_ERROR_NONE );
    }


    /**
     * Create a record in wp db for security reasons
     * https://developer.wordpress.org/reference/functions/wp_create_nonce/
     */
    public function create_nonce()
    {
        $this->nonce = wp_create_nonce($this->action );
    }

    /**
     * Enqueue scripts and if is necessary enqueue too a window js var
     * @return bool
     */
    public function localize_js_object()
    {
        $check = false;

        if ( ! empty( $this->js_handle ) )
        {

           $js_params = array_merge($this->js_params, array(
                    'nonce' => $this->nonce
                ));

           $check = wp_localize_script($this->js_handle, $this->action, $js_params);


            if ( ! empty( $this->js_path ) )
            {
                wp_enqueue_script( $this->js_handle );
            }



        }

        return $check;
    }



}