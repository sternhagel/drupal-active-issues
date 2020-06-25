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
 *   id = "project_issues_block",
 *   admin_label = @Translation("Active issues on drupal.org"),
 *   category = @Translation("Custom"),
 * )
 */ 
class ProjectIssuesBlock extends BlockBase implements BlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build() {

    // define HTTP connection to drupal.org using Guzzle
    /** 
     * @var \GuzzleHttp\Client $client 
     */
    $client = \Drupal::service('http_client_factory')->fromOptions([
      'base_uri' => 'https://www.drupal.org/api-d7/',
    ]);
        
    // read configuration of block instance
    $config = $this->getConfiguration();
    
    if (!empty($config['project_machine_name'])) {
      $machine_name = trim($config['project_machine_name']);
    } else {
      // if config value empty, set some default value
      $machine_name = 'image_field_caption';
    }
    
    if (!empty($config['maximum_number'])) {
      $max_items = $config['maximum_number'];
    } else {
      // if config value empty, set some default value
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
    
    /** 
     * get list of issues for project
     * 
     * NOTE: original challenge is to get "most active" issues -
     * which is a bit ambiguous.
     * 
     * For now the implementation only shows active issues,
     * sorting by "last changed" - sorting by "comment_count" might
     * be preferrable, but is not support by drupal.org
     * 
     */
    $issue_response = $client->get('node.json', [
      'query' => [
        'type' => 'project_issue',
        'field_project' => $nid,
        'limit' => $max_items,
        // sorting by 'comment_count' obviously not supported - throws 503 error
        // 'sort' => 'comment_count'
        'sort' => 'changed',
        'direction' => 'DESC',
        'field_issue_status' => 1, // only show active issues
      ]
    ]);
    $issue_list = Json::decode($issue_response->getBody());

    // output block headline
    $markup = '<h2>Currently active issues for project "' 
      . $project_info['list'][0]['title'] . '"</h2>';
    
    // output HTML list
    $markup .= "<ol>";
    foreach($issue_list["list"] AS $issue) {
      
      // convert changed date to readable format
      $changed = date('d.m.Y H:i', $issue['changed']);
      
      $markup .= '<li><a href="' . $issue['url'] . '" target="_blank" >'
        . $issue['title'] . '</a> - last changed: ' . $changed . '</li>';
    }
    $markup .= "</ol>";
    
    // return HTML markup
    return [
      '#markup' => $markup
    ];
  }

  /**
   * {@inheritdoc}
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
   */
  public function blockSubmit($input_form, FormStateInterface $form_state) {
    $this->setConfigurationValue('project_machine_name', $form_state->getValue('project_machine_name'));
    $this->setConfigurationValue('maximum_number', $form_state->getValue('maximum_number'));
  }

}


