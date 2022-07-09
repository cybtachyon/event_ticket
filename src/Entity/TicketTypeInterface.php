<?php

namespace Drupal\event_ticket\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityInterface;
use Drupal\Core\Entity\EntityDescriptionInterface;

/**
 * Provides an interface for defining Ticket type entities.
 */
interface TicketTypeInterface extends CommerceBundleEntityInterface, EntityDescriptionInterface {

  /**
   * {@inheritdoc}
   */
  public function getOrderItemTypeId();

  /**
   * {@inheritdoc}
   */
  public function setOrderItemTypeId($order_item_type_id);

  /**
   * {@inheritdoc}
   */
  public function getRegistrationTypeId(): ?string;

  /**
   * {@inheritdoc}
   */
  public function setRegistrationTypeId($registration_type_id);

}
