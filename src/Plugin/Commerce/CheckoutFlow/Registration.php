<?php

namespace Drupal\event_ticket\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\MultistepDefault;

/**
 * Provides the a multistep checkout flow for event registration.
 *
 * @CommerceCheckoutFlow(
 *   id = "event_ticket",
 *   label = "Multistep - Registration",
 * )
 */
class Registration extends MultistepDefault {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    $steps = parent::getSteps();
    $steps['order_information']['next_label'] = $this->t('Continue');
    
    $login = $steps['login'];
    unset($steps['login']);
    
    return [
      'login' => $login,
      'event_ticket_information' => [
        'label' => $this->t('Registration Information'),
        'previous_label' => $this->t('Go back'),
        'next_label' => $this->t('Continue'),
        'has_sidebar' => FALSE,
      ],
    ] + $steps;
  }

}
