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

  function __construct($result)
  {
    $this->server = rest_get_server();

    if (!is_dir(self::CACHE_DIR)) {
      mkdir(self::CACHE_DIR);
    }

    add_filter('rest_post_dispatch', [ $this, 'passThroughCache' ]);
  }

  function passThroughCache($result)
  {
    $requestUri = $_SERVER['REQUEST_URI'];

    if (strpos($requestUri, '/wp-json/wp/v2/') === false) {
      return false;
    }

    $restEndpoint = str_replace('/wp-json/wp/v2/', '', $requestUri);
    $cacheFile = self::CACHE_DIR . '/' . $restEndpoint;

    if (file_exists($cacheFile)) {
      // Found cache, get the response from cache
      $response = new \WP_REST_Response();
      $response->set_data(json_decode(file_get_contents($cacheFile), true));
      return $response;

    } else {
      // Cache the result and return
      file_put_contents($cacheFile, json_encode($result->get_data()));
      return $result;
    }
  }
}

add_action('rest_api_init', function($result) {
  return new SimpleRestCache($result);
}, 4);
