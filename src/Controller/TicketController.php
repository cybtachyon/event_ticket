<?php

namespace Drupal\event_ticket\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\event_ticket\Entity\TicketInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TicketController.
 *
 *  Returns responses for Ticket routes.
 */
class TicketController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a Ticket revision.
   *
   * @param int $event_ticket_revision
   *   The Ticket revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($event_ticket_revision) {
    $event_ticket = $this->entityTypeManager()->getStorage('event_ticket')
      ->loadRevision($event_ticket_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('event_ticket');

    return $view_builder->view($event_ticket);
  }

  /**
   * Page title callback for a Ticket revision.
   *
   * @param int $event_ticket_revision
   *   The Ticket revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($event_ticket_revision) {
    $event_ticket = $this->entityTypeManager()->getStorage('event_ticket')
      ->loadRevision($event_ticket_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $event_ticket->label(),
      '%date' => $this->dateFormatter->format($event_ticket->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Ticket.
   *
   * @param \Drupal\event_ticket\Entity\TicketInterface $event_ticket
   *   A Ticket object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(TicketInterface $event_ticket) {
    $account = $this->currentUser();
    $event_ticket_storage = $this->entityTypeManager()->getStorage('event_ticket');

    $langcode = $event_ticket->language()->getId();
    $langname = $event_ticket->language()->getName();
    $languages = $event_ticket->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $event_ticket->label()]) : $this->t('Revisions for %title', ['%title' => $event_ticket->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all ticket revisions") || $account->hasPermission('administer ticket entities')));
    $delete_permission = (($account->hasPermission("delete all ticket revisions") || $account->hasPermission('administer ticket entities')));

    $rows = [];

    $vids = $event_ticket_storage->revisionIds($event_ticket);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\event_ticket\TicketInterface $revision */
      $revision = $event_ticket_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $event_ticket->getRevisionId()) {
          $link = $this->l($date, new Url('entity.event_ticket.revision', [
            'event_ticket' => $event_ticket->id(),
            'event_ticket_revision' => $vid,
          ]));
        }
        else {
          $link = $event_ticket->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.event_ticket.translation_revert', [
                'event_ticket' => $event_ticket->id(),
                'event_ticket_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.event_ticket.revision_revert', [
                'event_ticket' => $event_ticket->id(),
                'event_ticket_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.event_ticket.revision_delete', [
                'event_ticket' => $event_ticket->id(),
                'event_ticket_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['event_ticket_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
