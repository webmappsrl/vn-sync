<?php
# Register a custom 'foo' command to output a supplied positional param.
#
# $ wp foo bar --append=qux
# Success: bar qux

/**
 * My awesome import command for Verde Natura
 *
 *
 * @when after_wp_load
 */
$vn_sync = function( $args, $assoc_args )
{

    WP_CLI::line( 'Start importing routes ...' );
    #new MB_ImportPostsWithAcfAndPods();
    WP_CLI::line( 'Routes imported!' );

    WP_CLI::line( 'Start importing comments ...' );
    new MB_ImportCommentsWithAcfAndPods();
    WP_CLI::line( 'Comments imported!' );

    WP_CLI::success( 'Done!' );
};

WP_CLI::add_command( 'vn-sync', $vn_sync );