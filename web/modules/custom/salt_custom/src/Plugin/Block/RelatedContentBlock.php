<?php

namespace Drupal\salt_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Block(
 *   id = "salt_related_content_block",
 *   admin_label = @Translation("Salt - Related Event/News"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE)
 *   }
 * )
 */
class RelatedContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(\Drupal\Core\Session\AccountInterface $account) {
    $node = $this->getContextValue('node');
    if (!$node instanceof NodeInterface || !in_array($node->bundle(), ['news', 'event'], TRUE)) {
      return \Drupal\Core\Access\AccessResult::forbidden();
    }
    return \Drupal\Core\Access\AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');
    $bundle = $node->bundle();
    $category_field = 'field_category';

    if ($node->get($category_field)->isEmpty()) {
      return [];
    }
    $term_ids = array_column($node->get($category_field)->getValue(), 'target_id');

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->condition('type', $bundle)
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('nid', $node->id(), '<>')
      ->condition($category_field, $term_ids, 'IN')
      ->accessCheck(TRUE)
      ->sort('created', 'DESC')
      ->range(0, 2)
      ->execute();

    if (empty($ids)) {
      return [];
    }

    $image_field = $bundle === 'event' ? 'field_event_image' : 'field_image';
    $date_field = $bundle === 'event' ? 'field_event_date' : 'field_publish_date';

    $items = [];
    foreach ($storage->loadMultiple($ids) as $related) {
      $items[] = _salt_custom_build_card_item($related, $image_field, $date_field);
    }

    $block_title = ($bundle === 'event') ? 'Related Event' : 'Related News';

    return [
      '#theme' => 'salt_related_content_block',
      '#title' => $block_title,
      '#items' => $items,
      '#cache' => [
        'contexts' => ['url'],
        'tags' => Cache::mergeTags(['node_list:' . $bundle], $this->getCacheTags()),
      ],
    ];
  }

}
