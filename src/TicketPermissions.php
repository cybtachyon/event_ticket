<?php

namespace Drupal\event_ticket;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\event\Entity\EventType;
use Drupal\event\Entity\EventTypeInterface;
use Drupal\event_ticket\Entity\TicketType;
use Drupal\event_ticket\Entity\TicketTypeInterface;

/**
 * Provides dynamic permissions for Ticket of different types.
 *
 * @ingroup event_ticket
 */
class TicketPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of event_ticket permissions.
   *
   * @return array
   *   The Ticket by bundle permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function generatePermissions() {
    $perms = [];

    $ticket_types = TicketType::loadMultiple();
    foreach ($ticket_types as $type) {
      $perms += $this->buildPermissions($type);
    }

    $event_types = EventType::loadMultiple();
    foreach ($event_types as $event_type) {
      $perms += $this->buildEventPermissions($event_type, $ticket_types);
    }

    return $perms;
  }

  /**
   * Returns a list of node permissions for a given node type.
   *
   * @param \Drupal\event_ticket\Entity\TicketTypeInterface $type
   *   The Ticket type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(TicketTypeInterface $type) {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "$type_id create entities" => [
        'title' => $this->t('Create new %type_name entities', $type_params),
      ],
      "$type_id edit own entities" => [
        'title' => $this->t('Edit own %type_name entities', $type_params),
      ],
      "$type_id edit any entities" => [
        'title' => $this->t('Edit any %type_name entities', $type_params),
      ],
      "$type_id delete own entities" => [
        'title' => $this->t('Delete own %type_name entities', $type_params),
      ],
      "$type_id delete any entities" => [
        'title' => $this->t('Delete any %type_name entities', $type_params),
      ],
      "$type_id view revisions" => [
        'title' => $this->t('View %type_name revisions', $type_params),
        'description' => t('To view a revision, you also need permission to view the entity item.'),
      ],
      "$type_id revert revisions" => [
        'title' => $this->t('Revert %type_name revisions', $type_params),
        'description' => t('To revert a revision, you also need permission to edit the entity item.'),
      ],
      "$type_id delete revisions" => [
        'title' => $this->t('Delete %type_name revisions', $type_params),
        'description' => $this->t('To delete a revision, you also need permission to delete the entity item.'),
      ],
      "order $type_id event_ticket for any event" => [
        'title' => $this->t('Order %type_name Tickets for any events', $type_params),
      ],
    ];
  }

  /**
   * Returns a list of ticket permissions for a given event type.
   *
   * @param \Drupal\event\Entity\EventTypeInterface $event_type
   *   The Event Type.
   * @param \Drupal\event_ticket\Entity\TicketTypeInterface[] $ticket_types
   *   The Ticket Types.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildEventPermissions(EventTypeInterface $event_type, array $ticket_types) {

    $event_type_id = $event_type->id();
    $event_type_params = [
      '%event_type_name' => $event_type->label(),
    ];

    $perms = [
      "order event_ticket for $event_type_id event" => [
        'title' => $this->t('Order Tickets for %event_type_name events', $event_type_params),
      ],
      "access event_ticket overview for $event_type_id event" => [
        'title' => $this->t('Access Ticket Overview page for %event_type_name Event', $event_type_params),
      ],
      "access event_ticket list for $event_type_id event" => [
        'title' => $this->t('Access Ticket List for %event_type_name Event', $event_type_params),
      ],
    ];

    foreach ($ticket_types as $ticket_type) {
      $ticket_type_id = $ticket_type->id();
      $ticket_type_param = $event_type_params + [
        '%ticket_type_name' => $ticket_type->label(),
      ];
      $perms += [
        "order $ticket_type_id event_ticket for $event_type_id event" => [
          'title' => $this->t('Order %ticket_type_name Tickets for %event_type_name events', $ticket_type_param),
        ],
      ];
    }

    return $perms;
  }

}
