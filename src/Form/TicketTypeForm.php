<?php

namespace Drupal\event_ticket\Form;

use Drupal\commerce\EntityHelper;
use Drupal\Core\Entity\EntityForm;
use Drupal\entity\Form\EntityDuplicateFormTrait;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TicketType Entity Form.
 */
class TicketTypeForm extends EntityForm {

  use EntityDuplicateFormTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $event_ticket_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $event_ticket_type->label(),
      '#description' => $this->t("Label for the Ticket type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $event_ticket_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\event_ticket\Entity\TicketType::load',
      ],
      '#disabled' => !$event_ticket_type->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#default_value' => $this->entity->getDescription(),
    ];

    if ($this->moduleHandler->moduleExists('commerce_order')) {
      // Prepare a list of order item types used to purchase product variations.
      $order_item_type_storage = $this->entityTypeManager->getStorage('commerce_order_item_type');
      $order_item_types = $order_item_type_storage->loadMultiple();
      $order_item_types = array_filter($order_item_types, function ($order_item_type) {
        /** @var \Drupal\commerce_order\Entity\OrderItemTypeInterface $order_item_type */
        return $order_item_type->getPurchasableEntityTypeId() == 'event_ticket';
      });

      $form['orderItemType'] = [
        '#type' => 'select',
        '#title' => $this->t('Order item type'),
        '#default_value' => $this->entity->getOrderItemTypeId(),
        '#options' => EntityHelper::extractLabels($order_item_types),
        '#empty_value' => '',
        '#required' => TRUE,
      ];
    }

    // Prepare a list of order item types used to purchase product variations.
    $registration_type_storage = $this->entityTypeManager->getStorage('event_registration_type');
    $registration_types = $registration_type_storage->loadMultiple();
    $form['registrationType'] = [
      '#type' => 'select',
      '#title' => $this->t('Registration type'),
      '#default_value' => $this->entity->getRegistrationTypeId(),
      '#options' => EntityHelper::extractLabels($registration_types),
      '#empty_value' => '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $event_ticket_type = $this->entity;
    $status = $event_ticket_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Ticket type.', [
          '%label' => $event_ticket_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Ticket type.', [
          '%label' => $event_ticket_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($event_ticket_type->toUrl('collection'));
  }

}
