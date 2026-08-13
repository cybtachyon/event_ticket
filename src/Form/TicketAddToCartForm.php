<?php

namespace Drupal\event_ticket\Form;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_store\SelectStoreTrait;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines form for adding items to the cart.
 *
 * @ingroup event_ticket
 */
class TicketAddToCartForm extends FormBase {

  use SelectStoreTrait;

  /**
   * The Commerce Cart Manager service.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected CartManagerInterface $cartManager;

  /**
   * The Commerce Cart Provider service.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected CartProviderInterface $cartProvider;

  /**
   * The current user Account entity.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Commerce Order Type resolver.
   *
   * @var \Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface
   */
  protected ChainOrderTypeResolverInterface $orderTypeResolver;

  /**
   * The Commerce Price resolver.
   *
   * @var \Drupal\commerce_price\Resolver\ChainPriceResolverInterface
   */
  protected ChainPriceResolverInterface $priceResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->cartManager = $container->get('commerce_cart.cart_manager');
    $instance->cartProvider = $container->get('commerce_cart.cart_provider');
    $instance->orderTypeResolver = $container->get('commerce_order.chain_order_type_resolver');
    $instance->currentStore = $container->get('commerce_store.current_store');
    $instance->priceResolver = $container->get('commerce_price.chain_price_resolver');
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId(): string {
    return 'event_ticket_add_to_cart';
  }

  /**
   * Defines the settings form for Ticket entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();
    $event = $build_info['event'];

    // Load related tickets.
    $ticket_view_builder = $this->entityTypeManager->getViewBuilder('event_ticket');
    $ticket_storage = $this->entityTypeManager->getStorage('event_ticket');
    $query = $ticket_storage->getQuery();
    $query->condition('event', $event->id());
    $query->accessCheck(TRUE);
    $ticket_ids = $query->execute();
    $tickets = $ticket_storage->loadMultiple($ticket_ids);
    $form_state->addBuildInfo('event_tickets', $tickets);

    $form['tickets'] = [
      '#type' => 'container',
    ];
    foreach ($tickets as $ticket) {
      $ticket_id = $ticket->id();
      $form['tickets'][$ticket_id] = [
        '#type' => 'container',
        'summary' => $ticket_view_builder->view($ticket, 'summary'),
        'quantity' => [
          '#type' => 'number',
          '#label' => $this->t('Quantity'),
          '#parents' => [
            $ticket_id,
            'quantity',
          ],
          '#min' => 0,
          '#step' => 1,
        ],
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to cart'),
    ];
    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /*
     * At the end of the form have a "Add to cart" button. When clicked, the
     * tickets should be added to the users cart, and they should be sent to
     * checkout.
     */
    $build_info = $form_state->getBuildInfo();
    $event = $build_info['event'];
    $tickets = $build_info['event_tickets'];

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');

    foreach ($tickets as $ticket) {
      $quantity = $form_state->getValue([$ticket->id(), 'quantity']);
      $order_item = $order_item_storage->createFromPurchasableEntity($ticket, [
        'quantity' => $quantity,
      ]);

      if (empty($quantity)) {
        continue;
      }

      $cart = $order_item->getOrder();
      if (!$cart) {
        $order_type_id = $this->orderTypeResolver->resolve($order_item);
        $store = $this->selectStore($ticket);
        $cart = $this->cartProvider->getCart($order_type_id, $store);
        if (!$cart) {
          $cart = $this->cartProvider->createCart($order_type_id, $store);
        }
      }

      $order_item = $this->cartManager->addOrderItem($cart, $order_item, TRUE);

    }

    $this->redirect('commerce_cart.page');
  }

}
