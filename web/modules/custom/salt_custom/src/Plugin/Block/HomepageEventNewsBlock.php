<?php

namespace Drupal\salt_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Block(
 *   id = "salt_homepage_event_news_block",
 *   admin_label = @Translation("Salt - Homepage Event & News")
 * )
 */
class HomepageEventNewsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'salt_homepage_event_news_block',
      '#event_items' => $this->getEventItems(),
      '#news_items' => $this->getNewsItems(),
      '#cache' => [
        'max-age' => 300,
        'contexts' => ['url'],
        'tags' => Cache::mergeTags(['node_list:event', 'node_list:news'], $this->getCacheTags()),
      ],
    ];
  }

  /**
   * Ongoing events first, then future events, capped at 2.
   */
  protected function getEventItems() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $now_str = date('Y-m-d', \Drupal::time()->getCurrentTime());

    $ids = $storage->getQuery()
      ->condition('type', 'event')
      ->condition('status', NodeInterface::PUBLISHED)
      ->accessCheck(TRUE)
      ->sort('field_event_date.value', 'ASC')
      ->range(0, 50)
      ->execute();

    $ongoing = [];
    $future = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if ($node->get('field_event_date')->isEmpty()) {
        continue;
      }
      $start = $node->get('field_event_date')->value;
      $end = $node->get('field_event_date')->end_value ?: $start;
      if ($start <= $now_str && $now_str <= $end) {
        $ongoing[] = $node;
      }
      elseif ($start > $now_str) {
        $future[] = $node;
      }
    }

    $selected = array_slice(array_merge($ongoing, $future), 0, 2);
    $items = [];
    foreach ($selected as $node) {
      $items[] = _salt_custom_build_card_item($node, 'field_event_image', 'field_event_date');
    }
    return $items;
  }

  /**
   * Single latest published News item.
   */
  protected function getNewsItems() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->condition('type', 'news')
      ->condition('status', NodeInterface::PUBLISHED)
      ->accessCheck(TRUE)
      ->sort('field_publish_date', 'DESC')
      ->range(0, 1)
      ->execute();

    $items = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      $items[] = _salt_custom_build_card_item($node, 'field_image', 'field_publish_date');
    }
    return $items;
  }

}
