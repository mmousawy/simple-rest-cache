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

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class SimpleRestCache
{
  private $server;
  const CACHE_DIR = __DIR__ . '/cache';

  function __construct()
  {
    if (!is_dir(self::CACHE_DIR)) {
      if (!mkdir(self::CACHE_DIR)) {
        return new WP_Error('simple_rest_cache_error', 'Cannot create cache folder', ['status' => 404]);
      }
    }

    // Filter all REST requests
    add_filter('rest_pre_dispatch', [ $this, 'passThroughCache' ]);
  }

  function passThroughCache($request)
  {
    $requestUri = $_SERVER['REQUEST_URI'];

    if (strpos($requestUri, '/wp-json/wp/v2/') === false) {
      return false;
    }

    $restEndpoint = preg_replace('/.+?wp-json\/wp\/v2\//', '', $requestUri);
    $restEndpoint = preg_replace('/[^a-z\d-_]/', '-', $restEndpoint);

    $cacheFile = self::CACHE_DIR . '/' . $restEndpoint;

    if (file_exists($cacheFile)) {
      // Found cache, get the result from cache
      $result = new WP_REST_Response();
      $result->set_data(json_decode(file_get_contents($cacheFile), true));
      return $result;

    } else {
      // Manually dispatch the request, cache it and return results
      remove_filter('rest_pre_dispatch', [ $this, 'passThroughCache' ]);
      $result = rest_do_request(new WP_REST_Request('GET', '/wp/v2/' . $restEndpoint));

      // Result did not have a successful response, don't cache
      if ($result->get_status() !== 200) {
        return $result;
      }

      if (!file_put_contents($cacheFile, json_encode($result->get_data()))) {
        return new WP_Error('simple_rest_cache_error', 'Cannot save result to cache', ['status' => 404]);
      }

      add_filter('rest_pre_dispatch', [ $this, 'passThroughCache' ]);
      return $result;
    }
  }
}

add_action('rest_api_init', function() {
  return new SimpleRestCache();
}, 4);
