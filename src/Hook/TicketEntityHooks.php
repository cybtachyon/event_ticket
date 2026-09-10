<?php

namespace Drupal\event_ticket\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\event\Entity\EventTypeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;


/**
 * Alters entities to support Ticket references.
 */
class TicketEntityHooks {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')] private readonly EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    if ($entity_type->id() === 'event_registration') {
      $fields['event_ticket'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Ticket'))
        ->setDescription(t('The ticket the registration was bought with.'))
        ->setRevisionable(TRUE)
        ->setSetting('target_type', 'event_ticket')
        ->setSetting('handler', 'default')
        ->setDisplayOptions('view', [
          'label' => 'above',
        ])
        ->setDisplayConfigurable('form', FALSE)
        ->setDisplayConfigurable('view', TRUE);
    }

    if ($entity_type->id() === 'event') {
      /** @var EventTypeInterface[] $event_type_entities */
      $event_type_entities = $this->entityTypeManager->getStorage('event_type')->loadMultiple();
      foreach ($event_type_entities as $event_type_entity) {
        $event_ticket_settings = $event_type_entity->getThirdPartySetting('event_ticket', 'event_ticket_type', []);
        if (empty($event_ticket_settings)) {
          continue;
        }
        $fields['event_tickets'] = BaseFieldDefinition::create('entity_reference')
          ->setLabel(t('Tickets'))
          ->setDescription(t('The event tickets.'))
          ->setRequired(TRUE)
          ->setTargetBundle($event_type_entity->bundle())
          ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
          ->setSetting('target_bundles', $event_ticket_settings)
          ->setSetting('target_type', 'event_ticket')
          ->setSetting('handler', 'default')
          ->setDisplayOptions('view', [
            'label' => 'above',
            'type' => 'event_ticket_add_to_cart',
            'weight' => 10,
          ])
          ->setDisplayConfigurable('view', TRUE);
      }
    }
    return $fields;
  }

  /**
   * The Drupal Entity Type Manager.
   */
  private readonly EntityTypeManagerInterface $entityTypeManager;

}
