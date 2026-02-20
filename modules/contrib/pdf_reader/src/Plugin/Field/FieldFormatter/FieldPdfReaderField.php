<?php

namespace Drupal\pdf_reader\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'FieldPdfReaderFields' formatter.
 *
 * @FieldFormatter(
 *  id = "FieldPdfReaderFields",
 *  label = @Translation("PDF Reader"),
 *  field_types = {"string","file","uri"}
 * )
 */
class FieldPdfReaderField extends FormatterBase {

  const GOOGLE_VIEWER = '//docs.google.com/viewer';
  const MICROSOFT_VIEWER = 'https://view.officeapps.live.com/op/embed.aspx';

  public $displayOptions = [];
  public $isColorboxInstalled = FALSE;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $extensionListModule;

  /**
   * FieldPdfReaderField class constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file url generator.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The module extension list.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, FileUrlGeneratorInterface $file_url_generator, ModuleExtensionList $extension_list_module ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
    $this->extensionListModule = $extension_list_module;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
      $container->get('extension.list.module')
    );
  }

  /**
   * Checks if the colorbox and the libraries module are enabled.
   *
   * @return bool
   *   TRUE, if colorbox and libraries module are enabled,
   *   FALSE otherwise.
   */
  public function isColorboxInstalled() {
    if ($this->moduleHandler->moduleExists('colorbox') && $this->moduleHandler->moduleExists('libraries')) {
      $this->isColorboxInstalled = TRUE;
      return $this->isColorboxInstalled;
    }
  }

  /**
   * Callback function to return PDF display options.
   */
  public function getPdfDisplayOptions() {
    $this->displayOptions = [
      'google' => $this->t('Google Viewer'),
      'ms' => $this->t('MS Viewer'),
      'embed' => $this->t('Direct Embed'),
      'pdf-js' => $this->t('pdf.js'),
    ];
    if ($this->isColorboxInstalled()) {
      $this->displayOptions['colorbox'] = $this->t('Colorbox');
    }
    return $this->displayOptions;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'pdf_width' => 600,
      'pdf_height' => 780,
      'renderer' => 'google',
      'embed_view_fit' => 'Fit',
      'embed_hide_toolbar' => FALSE,
      'download' => FALSE,
      'link_placement' => 'top',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['pdf_width'] = [
      '#title' => $this->t('Width'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('pdf_width'),
    ];

    $element['pdf_height'] = [
      '#title' => $this->t('Height'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('pdf_height'),
    ];

    $element['renderer'] = [
      '#title' => $this->t('Renderer'),
      '#type' => 'select',
      '#options' => $this->getPdfDisplayOptions(),
      '#default_value' => $this->getSetting('renderer'),
    ];

    $element['embed_view_fit'] = [
      '#title' => $this->t('Direct Embed fit option'),
      '#description' => $this->t('Only applies for Direct Embed renderer'),
      '#type' => 'select',
      // See https://www.adobe.com/content/dam/acom/en/devnet/acrobat/pdfs/pdf_open_parameters.pdf
      '#options' => [
        'Fit' => $this->t('Fit'),
        'FitH' => $this->t('Fit horizontally'),
        'FitV' => $this->t('Fit vertically'),
      ],
      '#default_value' => $this->getSetting('embed_view_fit'),
    ];

    $element['embed_hide_toolbar'] = [
      '#title' => $this->t('Direct Embed Hide toolbar'),
      '#description' => $this->t('Only applies for Direct Embed renderer'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('embed_hide_toolbar'),
    ];

    $element['download'] = [
      '#title' => $this->t('Show download link'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('download'),
    ];

    $element['link_placement'] = [
      '#title' => $this->t('Show Link'),
      '#type' => 'select',
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => $this->getSetting('link_placement'),
      '#states' => [
        'invisible' => [
          'input[name="fields[field_file_to_test_ha][settings_edit_form][settings][download]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $displayoptions = $this->getPdfDisplayOptions();
    $summary[] = $this->t('Size:') . $this->getSetting('pdf_width') . 'x' . $this->getSetting('pdf_height');
    $summary[] = $this->t('Direct Embed fit option:') . $this->getSetting('embed_view_fit');
    $summary[] = $this->t('Using:') . $displayoptions[$this->getSetting('renderer')];
    $is_downloadable = $this->getSetting('download') ? $this->t('YES') : $this->t('NO');
    $summary[] = $this->t('Download Link:') . $is_downloadable;
    $summary[] = $this->t('Direct Embed Hide toolbar:') . $this->getSetting('embed_hide_toolbar');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $field_display_type = $this->getSetting('renderer');
    $width = $this->getSetting('pdf_width');
    $height = $this->getSetting('pdf_height');
    $download_placement = $this->getSetting('link_placement');
    $fit = $this->getSetting('embed_view_fit');
    $toolbar = $this->getSetting('embed_hide_toolbar');
    $fragment = http_build_query([
      'view' => $fit,
      'toolbar' => ($toolbar ? 0 : 1),
    ]);
    foreach ($items as $delta => $item) {
      if ($values = $item->getValue('values')) {
        if (isset($values['target_id']) && !empty($values['target_id']) && is_numeric($values['target_id'])) {
          $file = $this->entityTypeManager->getStorage('file')->load($values['target_id']);
          if (isset($file) and is_object($file)) {
            $file_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
            $file_name = $file->getFileName();
          }
        }
        elseif (isset($values['value']) && !empty($values['value'])) {
          if (UrlHelper::isValid($values['value'])) {
            $file_url = $this->fileUrlGenerator->generateAbsoluteString($values['value']);
            $file_name = $file_url;
          }
        }
        if (isset($file_url)) {
          switch ($field_display_type) {
            case 'google':
            case 'ms':
              ($field_display_type == 'google') ? $file_path = self::GOOGLE_VIEWER . '?embedded=true&url=' . urlencode($file_url) : $file_path = self::MICROSOFT_VIEWER . '?src=' . urlencode($file_url);
              $elements[$delta] = [
                '#theme' => 'pdf_reader',
                '#service' => $field_display_type,
                '#file_url' => $file_path,
                '#width' => $width,
                '#height' => $height,
              ];
              break;

            case 'embed':
              $elements[$delta] = [
                '#theme' => 'pdf_reader_embed',
                '#service' => $field_display_type,
                '#width' => $width,
                '#height' => $height,
                '#file_url' => Url::fromUri($file_url, ['fragment' => $fragment])->toUriString(),
                '#text' => $this->t('It appears your Web browser is not configured to display PDF files.')
                . Link::fromTextAndUrl($this->t('Download adobe Acrobat'), Url::fromUri('http://www.adobe.com/products/reader.html'))->toString()
                . ' ' . $this->t('or') . ' ' . Link::fromTextAndUrl($this->t('Click here to download the PDF file.'), Url::fromUri($file_url))->toString() ,
              ];
              break;

            case 'pdf-js':
              $module_path = base_path() . $this->extensionListModule->getPath('pdf_reader');
              $elements[$delta] = [
                '#theme' => 'pdf_reader_js',
                '#service' => $field_display_type,
                '#width' => $width,
                '#height' => $height,
                '#attached' => [
                  'drupalSettings' => [
                    'pdf_reader' => [
                      'file_url' => $file_url,
                      'path_pdf_reader' => "$module_path/js/pdf.js",
                    ],
                  ],
                  'library' => [
                    "pdf_reader/global-styling",
                  ],
                ],
              ];
              break;

            case 'colorbox':
              $elements[$delta] = [
                '#theme' => 'pdf_reader_colorbox',
                '#service' => $field_display_type,
                '#file_url' => $file_url,
                '#file_name' => $file_name,
                '#width' => $width,
                '#height' => $height,
              ];
              break;
          }
          if ($this->getSetting('download')) {
            $elements[$delta]['#download_link'] = $file_url;
            if (!empty($download_placement) && $download_placement == 'top') {
              $elements[$delta]['#top'] = 'top';
            }
            else {
              $elements[$delta]['#bottom'] = 'bottom';
            }
            $elements[$delta]['#attached']['library'][] = 'pdf_reader/download-link-css';
          }
          if ($this->isColorboxInstalled()) {
            $elements[$delta]['#attached']['library'][] = 'pdf_reader/colorbox';
          }
        }
      }
    }
    return $elements;
  }

}
