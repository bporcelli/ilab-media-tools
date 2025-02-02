<?php

// Copyright (c) 2016 Interfacelab LLC. All rights reserved.
//
// Released under the GPLv3 license
// http://www.gnu.org/licenses/gpl-3.0.html
//
// Uses code from:
// Persist Admin Notices Dismissal
// by Agbonghama Collins and Andy Fragen
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************
namespace ILAB\MediaCloud\Tools\Storage\CLI;

use  GuzzleHttp\Client ;
use  GuzzleHttp\Exception\BadResponseException ;
use  GuzzleHttp\Exception\ClientException ;
use  GuzzleHttp\Exception\RequestException ;
use  ILAB\MediaCloud\CLI\Command ;
use  ILAB\MediaCloud\Storage\StorageSettings ;
use  ILAB\MediaCloud\Tasks\BatchManager ;
use  ILAB\MediaCloud\Tools\Storage\DefaultProgressDelegate ;
use  ILAB\MediaCloud\Tools\Storage\StorageTool ;
use  ILAB\MediaCloud\Tools\ToolsManager ;
use  ILAB\MediaCloud\Utilities\Environment ;
use  ILAB\MediaCloud\Utilities\Logging\Logger ;
use  Illuminate\Support\Facades\Storage ;

if ( !defined( 'ABSPATH' ) ) {
    header( 'Location: /' );
    die;
}

/**
 * Import to Cloud Storage, rebuild thumbnails, etc.
 * @package ILAB\MediaCloud\CLI\Storage
 */
class StorageCommands extends Command
{
    private  $debugMode = false ;
    /**
     * Imports the media library to the cloud.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : The maximum number of items to process, default is infinity.
     *
     * [--offset=<number>]
     * : The starting offset to process.  Cannot be used with page.
     *
     * [--page=<number>]
     * : The starting offset to process.  Page numbers start at 1.  Cannot be used with offset.
     *
     * [--paths=<string>]
     * : Controls the upload path.  'preserve' will preserve the files current path, 'replace' will replace it with the custom prefix defined in cloud storage settings.  'prepend' will prepend the custom prefix with the existing upload directory.
     * ---
     * default: preserve
     * options:
     *   - preserve
     *   - replace
     *   - prepend
     * ---
     *
     * [--skip-existing]
     * : Skips images that have already been migrated to storage.
     *
     * [--skip-thumbnails]
     * : Skips uploading thumbnails.  Requires Imgix or Dynamic Images.
     *
     * [--order-by=<string>]
     * : The field to sort the items to be imported by. Valid values are 'date', 'title' and 'filename'.
     * ---
     * options:
     *   - date
     *   - title
     *   - filename
     * ---
     *
     * [--order=<string>]
     * : The sort order. Valid values are 'asc' and 'desc'.
     * ---
     * default: asc
     * options:
     *   - asc
     *   - desc
     * ---
     *
     * @when after_wp_load
     *
     * @param $args
     * @param $assoc_args
     */
    public function import( $args, $assoc_args )
    {
        /** @var \Freemius $media_cloud_licensing */
        global  $media_cloud_licensing ;
        self::Error( "Only available in the Premium version.  To upgrade: https://mediacloud.press/pricing/" );
    }
    
    /**
     * Regenerate thumbnails
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : The maximum number of items to process, default is infinity.
     *
     * [--offset=<number>]
     * : The starting offset to process.  Cannot be used with page.
     *
     * [--page=<number>]
     * : The starting offset to process.  Page numbers start at 1.  Cannot be used with offset.
     *
     * @when after_wp_load
     *
     * @param $args
     * @param $assoc_args
     */
    public function regenerate( $args, $assoc_args )
    {
        /** @var \Freemius $media_cloud_licensing */
        global  $media_cloud_licensing ;
        self::Error( "Only available in the Premium version.  To upgrade: https://mediacloud.press/pricing/" );
    }
    
    /**
     * Unlinks media from the cloud.  Important: This will not attempt to download any media from the cloud before it unlinks it.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : The maximum number of items to process, default is infinity.
     *
     * [--offset=<number>]
     * : The starting offset to process.  Cannot be used with page.
     *
     * [--page=<number>]
     * : The starting offset to process.  Page numbers start at 1.  Cannot be used with offset.
     *
     * @when after_wp_load
     *
     * @param $args
     * @param $assoc_args
     */
    public function unlink( $args, $assoc_args )
    {
        $postArgs = [
            'post_type'   => 'attachment',
            'post_status' => 'inherit',
        ];
        
        if ( isset( $assoc_args['limit'] ) ) {
            $postArgs['posts_per_page'] = $assoc_args['limit'];
            
            if ( isset( $assoc_args['offset'] ) ) {
                $postArgs['offset'] = $assoc_args['offset'];
            } else {
                if ( isset( $assoc_args['page'] ) ) {
                    $postArgs['offset'] = max( 0, ($assoc_args['page'] - 1) * $assoc_args['limit'] );
                }
            }
        
        } else {
            $postArgs['nopaging'] = true;
        }
        
        $q = new \WP_Query( $postArgs );
        Command::Out( "", true );
        Command::Warn( "%WThis command only unlinks media attachments from cloud storage, \nit will not download any media from cloud storage. If the attachments \nyou are unlinking do not exist on your server, you will have broken \nimages on your site.%n" );
        Command::Out( "", true );
        \WP_CLI::confirm( "Are you sure you want to continue?", $assoc_args );
        Command::Out( "", true );
        Command::Info( "Found %W{$q->post_count}%n attachments.", true );
        Command::Info( "Processing ..." );
        foreach ( $q->posts as $post ) {
            $meta = wp_get_attachment_metadata( $post->ID );
            
            if ( isset( $meta['s3'] ) ) {
                unset( $meta['s3'] );
                
                if ( isset( $meta['sizes'] ) ) {
                    $sizes = $meta['sizes'];
                    foreach ( $sizes as $size => $sizeData ) {
                        if ( isset( $sizeData['s3'] ) ) {
                            unset( $sizeData['s3'] );
                        }
                        $sizes[$size] = $sizeData;
                    }
                    $meta['sizes'] = $sizes;
                }
                
                update_post_meta( $post->ID, '_wp_attachment_metadata', $meta );
            }
            
            Command::Info( '.' );
        }
        Command::Info( ' %GDone.%n', true );
        Command::Out( "", true );
    }
    
    /**
     * Migrates any media that was uploaded with Human Made S3 Uploads plugin
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : The maximum number of items to process, default is infinity.
     *
     * [--offset=<number>]
     * : The starting offset to process.  Cannot be used with page.
     *
     * [--page=<number>]
     * : The starting offset to process.  Page numbers start at 1.  Cannot be used with offset.
     *
     * [--order-by=<string>]
     * : The field to sort the items to be imported by. Valid values are 'date', 'title' and 'filename'.
     * ---
     * options:
     *   - date
     *   - title
     *   - filename
     * ---
     *
     * [--order=<string>]
     * : The sort order. Valid values are 'asc' and 'desc'.
     * ---
     * default: asc
     * options:
     *   - asc
     *   - desc
     * ---
     *
     * @when after_wp_load
     *
     * @param $args
     * @param $assoc_args
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function migrateS3Uploads( $args, $assoc_args )
    {
        
        if ( !is_plugin_active( 's3-uploads/s3-uploads.php' ) ) {
            self::Error( "S3 Uploads must be installed and activated." );
            exit( 1 );
        }
        
        Command::Out( "", true );
        Command::Warn( "%WThis command will make some changes to your database that are totally reversible.  However, it's always a good idea to backup your database first.%n" );
        Command::Out( "", true );
        $result = \WP_CLI::confirm( "Are you sure you want to continue?", $assoc_args );
        self::Info( $result, true );
        $postArgs = [
            'post_type'   => 'attachment',
            'post_status' => 'inherit',
            'meta_query'  => [
            'relation' => 'AND',
            [
            'key'     => '_wp_attachment_metadata',
            'value'   => '"s3"',
            'compare' => 'NOT LIKE',
            'type'    => 'CHAR',
        ],
            [
            'key'     => 'ilab_s3_info',
            'compare' => 'NOT EXISTS',
        ],
        ],
        ];
        
        if ( isset( $assoc_args['limit'] ) ) {
            $postArgs['posts_per_page'] = $assoc_args['limit'];
            
            if ( isset( $assoc_args['offset'] ) ) {
                $postArgs['offset'] = $assoc_args['offset'];
            } else {
                if ( isset( $assoc_args['page'] ) ) {
                    $postArgs['offset'] = max( 0, ($assoc_args['page'] - 1) * $assoc_args['limit'] );
                }
            }
        
        } else {
            $postArgs['nopaging'] = true;
        }
        
        
        if ( isset( $assoc_args['order-by'] ) && in_array( $assoc_args['order-by'], [ 'date', 'title', 'filename' ] ) ) {
            
            if ( $assoc_args['order-by'] == 'filename' ) {
                $postArgs['meta_key'] = '_wp_attached_file';
                $postArgs['orderby'] = 'meta_value';
            } else {
                $postArgs['orderby'] = $assoc_args['order-by'];
            }
            
            $postArgs['order'] = ( isset( $assoc_args['order'] ) && $assoc_args['order'] == 'desc' ? 'DESC' : 'ASC' );
        }
        
        $q = new \WP_Query( $postArgs );
        $postCount = count( $q->posts );
        
        if ( $postCount == 0 ) {
            self::Error( "No posts found." );
            exit( 0 );
        }
        
        $currentIndex = 1;
        self::Info( "Found {$postCount} posts.", true );
        /** @var \S3_Uploads $s3Uploads */
        $s3Uploads = \S3_Uploads::get_instance();
        $s3Base = trailingslashit( $s3Uploads->get_s3_url() );
        $s3Acl = ( defined( 'S3_UPLOADS_OBJECT_ACL' ) ? S3_UPLOADS_OBJECT_ACL : 'public-read' );
        $host = get_home_url( '/' );
        $guzzle = new Client();
        /** @var \WP_Post $post */
        foreach ( $q->posts as $post ) {
            
            if ( strpos( $post->guid, $host ) === 0 ) {
                self::Info( "[{$currentIndex} of {$postCount}] Skipping ({$post->ID}) {$post->post_title} ... ", true );
                $currentIndex++;
                continue;
            }
            
            self::Info( "[{$currentIndex} of {$postCount}] Processing ({$post->ID}) {$post->post_title} ... ", false );
            $currentIndex++;
            try {
                $res = $guzzle->request( 'HEAD', $post->guid, [
                    'allow_redirects' => true,
                ] );
            } catch ( ClientException $ex ) {
                self::Info( "Error " . $ex->getMessage() . " skipping.", true );
                continue;
            }
            
            if ( $res->getStatusCode() == 200 ) {
                self::Info( "Exists ... ", false );
                $basename = basename( $post->guid );
                $postBaseUrl = str_replace( $basename, '', $post->guid );
                $key = str_replace( $s3Base, '', $post->guid );
                $baseKey = ltrim( trailingslashit( str_replace( $basename, '', $key ) ), '/' );
                $s3Info = [
                    'url'       => $post->guid,
                    'bucket'    => $s3Uploads->get_s3_bucket(),
                    'provider'  => 's3',
                    'privacy'   => $s3Acl,
                    'v'         => MEDIA_CLOUD_INFO_VERSION,
                    'key'       => $key,
                    'options'   => [],
                    'mime-type' => $post->post_mime_type,
                ];
                $meta = wp_get_attachment_metadata( $post->ID );
                $meta['file'] = $key;
                $meta['s3'] = $s3Info;
                $sizes = $meta['sizes'];
                
                if ( empty($sizes) ) {
                    self::Info( "Missing size data ... ", false );
                } else {
                    $newSizes = [];
                    foreach ( $sizes as $size => $sizeData ) {
                        $sizeUrl = trailingslashit( $postBaseUrl ) . $sizeData['file'];
                        try {
                            $res = $guzzle->request( 'HEAD', $sizeUrl, [
                                'allow_redirects' => true,
                            ] );
                        } catch ( ClientException $ex ) {
                            continue;
                        }
                        
                        if ( $res->getStatusCode() == 200 ) {
                            $s3Info = [
                                'url'       => $sizeUrl,
                                'bucket'    => $s3Uploads->get_s3_bucket(),
                                'provider'  => 's3',
                                'privacy'   => $s3Acl,
                                'v'         => MEDIA_CLOUD_INFO_VERSION,
                                'key'       => $baseKey . $sizeData['file'],
                                'options'   => [],
                                'mime-type' => $sizeData['mime-type'],
                            ];
                            $sizeData['s3'] = $s3Info;
                            $newSizes[$size] = $sizeData;
                        }
                    
                    }
                    $meta['sizes'] = $newSizes;
                }
                
                update_post_meta( $post->ID, '_wp_attachment_metadata', $meta );
                self::Info( "Done.", true );
            } else {
                self::Info( "Skipping, does not exist.", true );
            }
        
        }
    }
    
    public static function Register()
    {
        \WP_CLI::add_command( 'mediacloud', __CLASS__ );
    }

}