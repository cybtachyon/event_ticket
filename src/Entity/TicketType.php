<?php

namespace Drupal\event_ticket\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityBase;

/**
 * Defines the Ticket type entity.
 *
 * @ConfigEntityType(
 *   id = "event_ticket_type",
 *   label = @Translation("Ticket type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\event_ticket\TicketTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\event_ticket\Form\TicketTypeForm",
 *       "edit" = "Drupal\event_ticket\Form\TicketTypeForm",
 *       "delete" = "Drupal\event_ticket\Form\TicketTypeDeleteForm"
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\event_ticket\TicketTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "event_ticket_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "event_ticket",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "orderItemType",
 *     "registrationType",
 *     "eventTypes",
 *     "traits",
 *     "locked",
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/config/event_ticket/type/{event_ticket_type}",
 *     "add-form" = "/admin/commerce/config/event_ticket/type/add",
 *     "edit-form" = "/admin/commerce/config/event_ticket/type/{event_ticket_type}/edit",
 *     "delete-form" = "/admin/commerce/config/event_ticket/type/{event_ticket_type}/delete",
 *     "collection" = "/admin/commerce/config/event_ticket/type"
 *   }
 * )
 */
class TicketType extends CommerceBundleEntityBase implements TicketTypeInterface {

  /**
   * The Ticket type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Ticket type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The type description.
   *
   * @var string
   */
  protected $description = '';

  /**
   * The order item type ID.
   *
   * @var string
   */
  protected $orderItemType;

  /**
   * The Registration bundle.
   *
   * @var string
   */
  protected $registrationType;

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderItemTypeId() {
    return $this->orderItemType;
  }

  /**
   * {@inheritdoc}
   */
  public function setOrderItemTypeId($order_item_type_id) {
    $this->orderItemType = $order_item_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationTypeId(): ?string {
    return $this->registrationType;
  }

  /**
   * {@inheritdoc}
   */
  public function setRegistrationTypeId($registration_type_id) {
    $this->registrationType = $registration_type_id;
    return $this;
  }

}
