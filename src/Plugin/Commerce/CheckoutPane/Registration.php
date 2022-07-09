<?php

namespace Drupal\event_ticket\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormStateInterface;
use Drupal\event_ticket\Entity\TicketInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the review pane.
 *
 * @CommerceCheckoutPane(
 *   id = "event_ticket_information",
 *   label = @Translation("Registration Infromation"),
 *   default_step = "event_ticket_information",
 * )
 */
class Registration extends CheckoutPaneBase {

  /**
   * The display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $displayRepository;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->displayRepository = $container->get('entity_display.repository');
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'form_mode' => 'register',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    return $this->t('Form Mode: %form_mode', [
      '%form_mode' => $this->configuration['form_mode'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form_modes = $this->displayRepository->getFormModeOptions('event_registration');

    $form['form_mode'] = [
      '#type' => 'options',
      '#title' => $this->t('Form Mode'),
      '#options' => $form_modes,
      '#description' => $this->t('Registration information form mode.'),
      '#default_value' => $this->configuration['form_mode'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['form_mode'] = !empty($values['form_mode']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    $visible = FALSE;
    foreach ($this->order->getItems() as $item) {
      if ($item->getPurchasedEntity() instanceof TicketInterface) {
        $visible = TRUE;
        break;
      }
    }
    // Show the pane only when there is a registration that needs info.
    return $visible;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $storage = $this->entityTypeManager->getStorage('event_registration');

    $summary = [];
    foreach ($this->order->getItems() as $item) {
      $registrations_query = $storage->getQuery();
      $registrations_query->condition('order_item', $item->id());
      $registration_ids = $registration_query->execute();
      $registrations = $storage->loadMultiple($registration_ids);
      foreach ($registrations as $registration) {
        $summary[$registration->id()] = [
          '#plain_text' => $registration->getLabel(),
        ];
      }
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {

    $pane_form['#submit'] = [
      ['Drupal\inline_entity_form\ElementSubmit', 'trigger'],
    ];
    $pane_form['tickets'] = [
      '#type' => 'container',
    ];
    foreach ($this->order->getItems() as $item) {
      $ticket = $item->getPurchasedEntity();

      $pane_form['tickets'][$item->id()]['registrations'] = [
        '#type' => 'item',
        '#title' => $this->t('%ticket_label for %event_label', [
          '%ticket_label' => $ticket->label(),
          '%event_label' => $ticket->getEvent()->label(),
        ]),
      ];

      $ticket_type = $ticket->getTicketType();
      $registration_type = $ticket_type->getRegistrationTypeId();
      $registrations = $this->getOrderItemRegistrations($item);
      $bundle = $ticket->getTicketType();
      foreach ($registrations as $registration) {
        $inline_form = $this->inlineFormManager->createInstance('content_entity', [
          'form_mode' => 'register',
        ], $registration);
        $index = count($pane_form['tickets'][$item->id()]['registrations']);
        $pane_form['tickets'][$item->id()]['registrations'][$index] = [
          '#type' => 'details',
          '#title' => $registration->label(),
          'form' => [
            '#parents' => array_merge($pane_form['#parents'], [
              'tickets',
              $item->id(),
              'registrations',
              $index,
              'form',
            ]),
            '#inline_form' => $inline_form,
          ],
        ];
        $pane_form['tickets'][$item->id()]['registrations'][$index]['form'] = $inline_form->buildInlineForm($pane_form['tickets'][$item->id()]['registrations'][$index]['form'], $form_state);
      }

      $new = $item->getQuantity() - count($registrations);
      if ($new < 0) {
        $form_state->setError($pane_form, $this->t('There are more registrations then tickets being purchased.'));
      }
      elseif ($new > 0) {
        $storage = $this->entityTypeManager->getStorage('event_registration');
        for ($i = 0; $i < $new; $i++) {
          $new_registration = $storage->create([
            'type' => $registration_type,
            'event' => $ticket->getEvent()->id(),
            'order_item' => $item->id(),
            'event_ticket' => $ticket->id(),
          ]);
          $inline_form = $this->inlineFormManager->createInstance('content_entity', [
            'form_mode' => 'register',
          ], $registration);
          $index = count($pane_form['tickets'][$item->id()]['registrations']);
          $pane_form['tickets'][$item->id()]['registrations'][$index] = [
            '#type' => 'details',
            '#open' => TRUE,
            '#title' => $this->t('New registration'),
            'form' => [
              '#parents' => array_merge($pane_form['#parents'], [
                'tickets',
                $item->id(),
                'registrations',
                $index,
                'form',
              ]),
              '#inline_form' => $inline_form,
            ],
          ];
          $pane_form['tickets'][$item->id()]['registrations'][$index]['form'] = $inline_form->buildInlineForm($pane_form['tickets'][$item->id()]['registrations'][$index]['form'], $form_state);
        }
      }
    }

    return $pane_form;
  }

  /**
   * Gets registrations for an order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return \Drupal\event_registration\Entity\RegistrationInterface[]
   *   The registrations.
   */
  public function getOrderItemRegistrations(OrderItemInterface $order_item) {
    $storage = $this->entityTypeManager->getStorage('event_registration');
    $registrations_query = $storage->getQuery();
    $registrations_query->condition('order_item', $order_item->id());
    $registration_ids = $registrations_query->execute();
    return $storage->loadMultiple($registration_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // @todo ensure there aren't extra registrations.
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);

    foreach ($this->order->getItems() as $item) {
      $indexes = Element::children($pane_form['tickets'][$item->id()]['registrations']);

      foreach ($indexes as $index) {
        $ticket = $item->getPurchasedEntity();
        $event = $ticket->getEvent();

        $registration = $pane_form['tickets'][$item->id()]['registrations'][$index]['form']['#inline_form']->getEntity();

        $registration->get('event')->appendItem(['entity' => $event]);
        $registration->get('event_ticket')->appendItem(['entity' => $ticket]);
        $registration->get('order_item')->appendItem(['entity' => $item]);
        $registration->save();
      };
    }
  }

}
