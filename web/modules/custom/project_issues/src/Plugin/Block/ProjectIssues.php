<?php

namespace Drupal\project_issues\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Json;

/**
 * Provides a configurable block for displaying active issues of
 * projects on drupal.org.
 *
 * @Block(
 *   id = "project_issues",
 *   admin_label = @Translation("Active issues on drupal.org"),
 *   category = @Translation("Custom"),
 * )
 */
class ProjectIssues extends BlockBase implements BlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build() {

    /**
     * Define HTTP connection to drupal.org using Guzzle
     *
     * @var \GuzzleHttp\Client The HTTP client.
     */
    $client = \Drupal::service('http_client_factory')->fromOptions([
      'base_uri' => 'https://www.drupal.org/api-d7/',
    ]);

    // read configuration of block instance
    $config = $this->getConfiguration();

    if (!empty($config['project_machine_name'])) {
      $machine_name = trim($config['project_machine_name']);
    } else {
      $machine_name = '';
    }

    if (!empty($config['maximum_number'])) {
      $max_items = $config['maximum_number'];
    } else {
      // if config value empty, set default value
      $max_items = 20;
    }

    // API calls to drupal.org

    // get node ID of project
    $project_response = $client->get('node.json', [
      'query' => [
        'field_project_machine_name' => $machine_name,
      ]
    ]);
    $project_info = Json::decode($project_response->getBody());
    $nid = $project_info['list'][0]['nid'];

    if (!empty($nid)) {

      /**
       * Get list of issues for project
       *
       * NOTE: original challenge was to get "most active" issues -
       * which is a bit ambiguous.
       *
       * For now the implementation only shows active issues,
       * sorting by "last changed" - sorting by "comment_count" would
       * be my preferred option, but is not supported by drupal.org
       */
      $issue_response = $client->get('node.json', [
        'query' => [
          'type' => 'project_issue',
          'field_project' => $nid,
          'limit' => $max_items,
          // sorting by 'comment_count' apparently not supported - throws 503 error
          // 'sort' => 'comment_count',
          'sort' => 'changed', // sort by "last changed" instead...
          'direction' => 'DESC',
          'field_issue_status' => 1 // only show active issues
        ]
      ]);
      $issue_list = Json::decode($issue_response->getBody());

      // output block headline
      $markup = '<h3>' . $max_items .
        ' most recently updated active issues for project <a href="' .
        $project_info['list'][0]['url'] . '" target="_blank" >' .
        $project_info['list'][0]['title'] . '"</a></h3>';

      // output HTML list
      $markup .= "<ol>";
      foreach($issue_list["list"] AS $issue) {

        // convert "last changed" date to readable format
        $changed = date('d.m.Y H:i', $issue['changed']);

        $markup .= '<li><a href="' . $issue['url'] . '" target="_blank" >'
          . $issue['title'] . '</a> - last changed: ' . $changed . '</li>';
      }
      $markup .= "</ol>";

    } else { // if node ID in response is empty..
      $markup = '<h3>Sorry, no project found on drupal.org for machine name "' . $machine_name . '".</h3>';
    }

    // return HTML markup
    return [
      '#markup' => $markup
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Defines the form elements for configuration of block instances.
   *
   * Currently implemented:
   *  - project_machine_name: machine name of the project of interest
   *  - maximum_number: maximum number of issues to be shown in the block
   */
  public function blockForm($input_form, FormStateInterface $form_state) {
    $input_form = parent::blockForm($input_form, $form_state);

    $config = $this->getConfiguration();

    $input_form['project_machine_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project machine name'),
      '#description' => $this->t('Please fill in the machine name of the project (e.g. "ctools").'),
      '#default_value' => isset($config['project_machine_name']) ? $config['project_machine_name'] : '',
    ];

    $input_form['maximum_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of items'),
      '#description' => $this->t('Please specify the number of issues to show in the block.'),
      '#default_value' => isset($config['maximum_number']) ? $config['maximum_number'] : 20,
    ];

    return $input_form;
  }

  /**
   * {@inheritdoc}
   *
   * Processes the configuration values when submitting the form.
   */
  public function blockSubmit($input_form, FormStateInterface $form_state) {
    $this->setConfigurationValue('project_machine_name', $form_state->getValue('project_machine_name'));
    $this->setConfigurationValue('maximum_number', $form_state->getValue('maximum_number'));
  }

}
