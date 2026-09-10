<?php

namespace Drupal\event_ticket\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;


/**
 * Adds Commerce Order Item references to Event Registration.
 */
class CommerceOrderEntityHooks {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'module_handler')] private readonly ModuleHandlerInterface $module_handler
  ) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    if (($entity_type->id() === 'event_registration') && $this->moduleHandler->moduleExists('commerce_order')) {
      $fields['order_item'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Order Item'))
        ->setDescription(t('The Order item the registration is linked to.'))
        ->setRevisionable(TRUE)
        ->setSetting('target_type', 'commerce_order_item')
        ->setSetting('handler', 'default')
        ->setDisplayConfigurable('form', FALSE)
        ->setDisplayConfigurable('view', TRUE);
    }
    return $fields;
  }

  /**
   * The Drupal Module Handler.
   */
  private readonly ModuleHandlerInterface $moduleHandler;

}
