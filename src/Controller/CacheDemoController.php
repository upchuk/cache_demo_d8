<?php

/**
 * @file
 * Contains \Drupal\cache_demo\Controller\CacheDemoController.
 */

namespace Drupal\cache_demo\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use \GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Cache demo main page.
 */
class CacheDemoController extends ControllerBase {

  /**
   * @var CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Class constructor.
   */
  public function __construct(CacheBackendInterface $cacheBackend) {
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default')
    );
  }

  /**
   * Renders the page for the cache demo.
   */
  public function index(Request $request) {
    $output = array();

    $clear = $request->query->get('clear');
    if ($clear) {
      $this->clearPosts();
    }

    if (!$clear) {
      $start_time = microtime(TRUE);
      $data = $this->loadPosts();
      $end_time = microtime(TRUE);

      $duration = $end_time - $start_time;
      $reload = $data['means'] == 'API' ? 'Reload the page to retrieve the posts from cache and see the difference.' : '';
      $output['duration'] = array(
        '#type' => 'markup',
        '#prefix' => '<div>',
        '#suffix' => '</div>',
        '#markup' => t('The duration for loading the posts has been @duration ms using the @means. @reload',
          array(
            '@duration' => number_format($duration * 1000, 2),
            '@means' => $data['means'],
            '@reload' => $reload
          )),
      );
    }

    if ($cache = $this->cacheBackend->get('cache_demo_posts') && $data['means'] == 'cache') {
      $url = new Url('cache_demo_page', array(), array('query' => array('clear' => true)));
      $output['clear'] = array(
        '#type' => 'markup',
        '#markup' => $this->l('Clear the cache and try again', $url),
      );
    }

    if (!$cache = $this->cacheBackend->get('cache_demo_posts')) {
      $url = new Url('cache_demo_page');
      $output['populate'] = array(
        '#type' => 'markup',
        '#markup' => $this->l('Try loading again to query the API and re-populate the cache', $url),
      );
    }

    return $output;
  }

  /**
   * Loads a bunch of dummy posts from cache or API
   * @return array
   */
  private function loadPosts() {
    if ($cache = $this->cacheBackend->get('cache_demo_posts')) {
      return array(
        'data' => $cache->data,
        'means' => 'cache',
      );
    }
    else {
      $guzzle = new Client();
      $response = $guzzle->get('http://jsonplaceholder.typicode.com/posts');
      $posts = \GuzzleHttp\json_decode($response->getBody());
      $this->cacheBackend->set('cache_demo_posts', $posts, CacheBackendInterface::CACHE_PERMANENT);
      return array(
        'data' => $posts,
        'means' => 'API',
      );
    }
  }

  /**
   * Clears the posts from the cache.
   */
  function clearPosts() {
    if ($cache = $this->cacheBackend->get('cache_demo_posts')) {
      $this->cacheBackend->delete('cache_demo_posts');
      drupal_set_message('Posts have been removed from cache.', 'status');
    }
    else {
      drupal_set_message('No posts in cache.', 'error');
    }
  }

}
