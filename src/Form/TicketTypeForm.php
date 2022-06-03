<?php

namespace Drupal\event_ticket\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TicketTypeForm.
 */
class TicketTypeForm extends EntityForm {

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

    /* You will need additional form elements for your custom properties. */

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
