<?php

namespace Drupal\wmcontroller\ViewBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\wmcontroller\Service\Cache\Dispatcher;

class ViewBuilder
{
    /** @var Dispatcher */
    private $dispatcher;

    /**  @var EntityTypeManagerInterface */
    private $entityTypeManager;

    protected $viewMode = 'full';

    protected $langCode = null;

    protected $templateDir;

    protected $template;

    /** @var EntityInterface */
    protected $entity;

    protected $data = [];

    protected $hooks = [];

    protected $headElements = [];

    protected $cache = [
        'tags' => [],
        'contexts' => [],
    ];

    public function __construct(
        Dispatcher $dispatcher,
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->dispatcher = $dispatcher;
        $this->entityTypeManager = $entityTypeManager;
    }

    public function setTemplateDir($templateDir)
    {
        $this->templateDir = $templateDir;

        return $this;
    }

    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    public function setEntity(EntityInterface $entity)
    {
        $this->entity = $entity;

        return $this;
    }

    /**
     * @param  array $headElements
     * @return $this
     */
    public function setHeadElements(array $headElements)
    {
        $this->headElements = $headElements;

        return $this;
    }

    /**
     * @param  array  $headElement
     * @param  string $key
     * @return $this
     */
    public function addHeadElement(array $headElement, $key = '')
    {
        $key = $key ?: bin2hex(random_bytes(20));
        $this->headElements[] = [$headElement, $key];
        return $this;
    }

    /**
     * Set the data passed to the view
     * Has to be an associative array
     *
     * When passed [myVariable => 'I am a teapot'], the view will
     * have access to the variable 'myVariable'
     *
     * This is done by wmcontroller_theme_set_variables
     *
     * @param  array $data
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    public function setViewMode($viewMode)
    {
        $this->viewMode = $viewMode;

        return $this;
    }

    public function setLangCode($langCode)
    {
        $this->langCode = $langCode;

        return $this;
    }

    /**
     * @return array
     */
    public function getHooks()
    {
        return $this->hooks;
    }

    /**
     * @param  array $hooks
     * @return $this
     */
    public function setHooks(array $hooks)
    {
        $this->hooks = $hooks;

        return $this;
    }

    /**
     * @param  array $cache
     * @return $this
     */
    public function setCache(array $cache)
    {
        $this->cache = $cache;

        return $this;
    }

    public function addCacheTag(string $tag)
    {
        $this->cache['tags'][] = $tag;

        return $this;
    }

    public function addCacheTags(array $tag)
    {
        $this->cache['tags'] = array_merge($this->cache['tags'], $tag);

        return $this;
    }

    public function addCacheContexts(string $context)
    {
        $this->cache['contexts'][] = $context;

        return $this;
    }

    public function setCacheMaxAge(int $context)
    {
        $this->cache['max-age'] = $context;

        return $this;
    }

    public function render()
    {
        $view = [];
        if ($this->entity) {
            $view = $this->createOriginalRenderArrayFromEntity($this->entity);
        }

        $this->addThemeToRenderArray($view);
        $this->addHeadElementsToRenderArray($view);
        $this->addCustomHooksToRenderArray($view);
        $this->addCacheTagsToRenderArray($view);
        $this->dispatchCacheTags($view);

        $view['#_data'] = $this->data;

        return $view;
    }

    private function createOriginalRenderArrayFromEntity(EntityInterface $entity)
    {
        $render_controller = $this->entityTypeManager->getViewBuilder(
            $entity->getEntityTypeId()
        );

        return $render_controller->view(
            $entity,
            $this->viewMode,
            $this->langCode
        );
    }

    private function addThemeToRenderArray(&$view)
    {
        if ($this->template) {
            $view['#theme'] =
                ($this->templateDir ? $this->templateDir . '.' : '') .
                $this->template;
        }
    }

    private function addHeadElementsToRenderArray(&$view)
    {
        if (count($this->headElements) > 0) {
            if (!isset($view['#attached']['html_head'])) {
                $view['#attached']['html_head'] = [];
            }

            $view['#attached']['html_head'] = array_merge(
                $view['#attached']['html_head'],
                $this->headElements
            );
        }

        return $view;
    }

    private function addCustomHooksToRenderArray(&$view)
    {
        $view['#pre_render'] = array_merge(
            $view['#pre_render'] ?? [],
            $this->getHooks()
        );

        return $view;
    }

    private function addCacheTagsToRenderArray(&$view)
    {
        // Add cache tags
        if (empty($view['#cache'])) {
            $view['#cache'] = $this->cache;
            return;
        }

        foreach (['tags', 'contexts', 'max-age'] as $key) {
            if (!isset($this->cache[$key])) {
                continue;
            }

            if (!is_array($this->cache[$key])) {
                $view['#cache'][$key] = $this->cache[$key];
                continue;
            }

            $view['#cache'] += [$key => []];
            $view['#cache'][$key] = array_unique(
                array_merge(
                    $view['#cache'][$key],
                    $this->cache[$key]
                )
            );
        }
    }

    private function dispatchCacheTags($view)
    {
        if ($view['#cache']['tags']) {
            $this->dispatcher->dispatchTags($view['#cache']['tags']);
        }
    }
}

