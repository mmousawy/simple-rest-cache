<?php
/**
 * Plugin Name: Simple REST Cache
 * Description: Enables caching for the built-in WordPress REST API
 * Author:      Murtada al Mousawy
 * Version:     0.0.1
 * Author URI:  https://murtada.nl
 * License:     GPLv3
 */

declare(strict_types = 1);

namespace MMousawy;

use stdClass;
use ErrorException;
use RuntimeException;
use UnexpectedValueException;

class SimpleRestCache
{
  private $server;
  const CACHE_DIR = __DIR__ . '/cache';

  function __construct($request)
  {
    $this->server = rest_get_server();

    if (!is_dir(self::CACHE_DIR)) {
      mkdir(self::CACHE_DIR);
    }

    // Filter any REST requests
    add_filter('rest_pre_dispatch', [ $this, 'passThroughCache' ]);
  }

  function passThroughCache($request)
  {
    $requestUri = $_SERVER['REQUEST_URI'];

    if (strpos($requestUri, '/wp-json/wp/v2/') === false) {
      return false;
    }

    $restEndpoint = str_replace('/wp-json/wp/v2/', '', $requestUri);
    $cacheFile = self::CACHE_DIR . '/' . $restEndpoint;

    if (file_exists($cacheFile)) {
      // Found cache, get the result from cache
      $result = new \WP_REST_Response();
      $result->set_data(json_decode(file_get_contents($cacheFile), true));
      return $result;

    } else {
      // Manually dispatch the request, cache it and return results
      remove_filter('rest_pre_dispatch', [ $this, 'passThroughCache' ]);
      $result = rest_do_request(new \WP_REST_Request('GET', '/wp/v2/' . $restEndpoint));
      file_put_contents($cacheFile, json_encode($result->get_data()));
      add_filter('rest_pre_dispatch', [ $this, 'passThroughCache' ]);
      return $result;
    }
  }
}

add_action('rest_api_init', function($request) {
  return new SimpleRestCache($request);
}, 4);
