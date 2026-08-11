<?php

declare (strict_types=1);
namespace JooosiEgami\Admin\Controller;

use JooosiEgami\Assignment\TemplateMatcher;
use JooosiEgami\Content\DynamicDataResolver;
use JooosiEgami\Diagnostics\SystemDiagnostics;
use JooosiEgami\Integration\OmniIcon;
use JooosiEgami\Rendering\ImageGenerator;
use JooosiEgami\Rendering\SvgSupport;
use JooosiEgami\Processing\TemplateInvalidator;
use JooosiEgami\Preset\PresetRepositoryManager;
use JooosiEgami\Settings\PluginSettings;
use JooosiEgami\Template\TemplateRepository;
use JooosiEgami\Template\TemplateSchema;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
/**
 * REST API for the Egami administration app.
 *
 * @since 0.1.0
 */
final class EgamiController
{
    public const NAMESPACE = 'egami/v1';
    public function __construct(private TemplateRepository $templateRepository, private ImageGenerator $imageGenerator, private TemplateInvalidator $templateInvalidator, private PresetRepositoryManager $presetRepositories, private OmniIcon $icons, private SvgSupport $svgSupport, private SystemDiagnostics $diagnostics, private DynamicDataResolver $dynamicData, private TemplateMatcher $templateMatcher)
    {
    }
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/designs', [['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'templates'], 'permission_callback' => [$this, 'canRead']], ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'createTemplate'], 'permission_callback' => [$this, 'canManage']]]);
        register_rest_route(self::NAMESPACE, '/designs/(?P<id>\d+)', [['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'template'], 'permission_callback' => [$this, 'canRead']], ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'updateTemplate'], 'permission_callback' => [$this, 'canManage']], ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'deleteTemplate'], 'permission_callback' => [$this, 'canManage']]]);
        $this->registerManageRoute('/designs/(?P<id>\d+)/duplicate', 'duplicateTemplate');
        $this->registerManageRoute('/designs/(?P<id>\d+)/preview', 'preview');
        $this->registerManageRoute('/designs/(?P<id>\d+)/render', 'render');
        $this->registerManageRoute('/cache/flush', 'flush');
        $this->registerManageRoute('/cache/warm', 'warm');
        register_rest_route(self::NAMESPACE, '/settings', [['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'getSettings'], 'permission_callback' => [$this, 'canRead']], ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'updateSettings'], 'permission_callback' => [$this, 'canManage']]]);
        register_rest_route(self::NAMESPACE, '/presets', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'presetCatalog'], 'permission_callback' => [$this, 'canRead']]);
        register_rest_route(self::NAMESPACE, '/preset-repositories', [['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'presetCatalog'], 'permission_callback' => [$this, 'canRead']], ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'addPresetRepository'], 'permission_callback' => [$this, 'canManage']]]);
        register_rest_route(self::NAMESPACE, '/preset-repositories/(?P<id>[a-z0-9-]+)', [['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'updatePresetRepository'], 'permission_callback' => [$this, 'canManage']], ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'removePresetRepository'], 'permission_callback' => [$this, 'canManage']]]);
        $this->registerManageRoute('/preset-repositories/(?P<id>[a-z0-9-]+)/refresh', 'refreshPresetRepository');
        register_rest_route(self::NAMESPACE, '/matching-posts', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'matchingPosts'], 'permission_callback' => [$this, 'canRead']]);
        register_rest_route(self::NAMESPACE, '/placeholders', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'placeholders'], 'permission_callback' => [$this, 'canRead'], 'args' => ['post_id' => ['default' => 0, 'sanitize_callback' => 'absint']]]);
        register_rest_route(self::NAMESPACE, '/status', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'status'], 'permission_callback' => [$this, 'canRead']]);
        register_rest_route(self::NAMESPACE, '/icons/search', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'searchIcons'], 'permission_callback' => [$this, 'canRead'], 'args' => ['query' => ['required' => \true, 'sanitize_callback' => 'sanitize_text_field']]]);
    }
    public function canRead(): bool
    {
        return current_user_can('edit_posts');
    }
    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }
    public function templates(): WP_REST_Response
    {
        return rest_ensure_response($this->templateRepository->all());
    }
    public function template(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return rest_ensure_response($this->templateRepository->get(absint($request['id'])));
    }
    public function createTemplate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = (array) $request->get_json_params();
        return rest_ensure_response($this->templateRepository->create((string) ($payload['title'] ?? ''), isset($payload['document']) && is_array($payload['document']) ? $payload['document'] : null, isset($payload['rules']) && is_array($payload['rules']) ? $payload['rules'] : null));
    }
    public function updateTemplate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = (array) $request->get_json_params();
        $templateId = absint($request['id']);
        $result = $this->templateRepository->update($templateId, $payload);
        if (!is_wp_error($result)) {
            $processing = $this->templateInvalidator->invalidate($templateId);
            if (is_wp_error($processing)) {
                return $processing;
            }
            $result['processing'] = $processing;
        }
        return rest_ensure_response($result);
    }
    public function deleteTemplate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $templateId = absint($request['id']);
        $result = $this->templateRepository->delete($templateId);
        if (is_wp_error($result)) {
            return $result;
        }
        if ($result) {
            $processing = $this->templateInvalidator->invalidate($templateId);
            if (is_wp_error($processing)) {
                return $processing;
            }
        }
        return rest_ensure_response(['deleted' => $result, 'processing' => $processing ?? null]);
    }
    public function duplicateTemplate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return rest_ensure_response($this->templateRepository->duplicate(absint($request['id'])));
    }
    public function preview(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = (array) $request->get_json_params();
        $document = isset($payload['document']) && is_array($payload['document']) ? $payload['document'] : null;
        if ($document === null) {
            $template = $this->templateRepository->get(absint($request['id']));
            if (is_wp_error($template)) {
                return $template;
            }
            $document = $template['document'];
        }
        return rest_ensure_response($this->imageGenerator->preview($document, absint($payload['post_id'] ?? 0)));
    }
    public function render(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = (array) $request->get_json_params();
        return rest_ensure_response($this->imageGenerator->generate(absint($request['id']), absint($payload['post_id'] ?? 0), sanitize_key((string) ($payload['output'] ?? 'manual')), [], !empty($payload['force'])));
    }
    public function flush(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = (array) $request->get_json_params();
        return rest_ensure_response($this->imageGenerator->flushCache(absint($payload['design_id'] ?? 0)));
    }
    public function warm(WP_REST_Request $request): WP_REST_Response
    {
        $payload = (array) $request->get_json_params();
        return rest_ensure_response($this->imageGenerator->warm(absint($payload['design_id'] ?? 0), sanitize_key((string) ($payload['post_type'] ?? ''))));
    }
    public function getSettings(): WP_REST_Response
    {
        return rest_ensure_response(PluginSettings::all());
    }
    public function updateSettings(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response(PluginSettings::update((array) $request->get_json_params()));
    }
    public function presetCatalog(): WP_REST_Response|WP_Error
    {
        return rest_ensure_response($this->presetRepositories->catalog());
    }
    public function addPresetRepository(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = (array) $request->get_json_params();
        return rest_ensure_response($this->presetRepositories->add((string) ($payload['url'] ?? '')));
    }
    public function updatePresetRepository(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = (array) $request->get_json_params();
        return rest_ensure_response($this->presetRepositories->setEnabled(sanitize_key((string) $request['id']), !empty($payload['enabled'])));
    }
    public function removePresetRepository(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return rest_ensure_response($this->presetRepositories->remove(sanitize_key((string) $request['id'])));
    }
    public function refreshPresetRepository(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return rest_ensure_response($this->presetRepositories->refresh(sanitize_key((string) $request['id'])));
    }
    public function matchingPosts(WP_REST_Request $request): WP_REST_Response
    {
        $payload = (array) $request->get_json_params();
        $rules = TemplateSchema::normalizeRules($payload['rules'] ?? []);
        $search = sanitize_text_field((string) ($payload['search'] ?? ''));
        $resultLimit = max(10, min(200, (int) apply_filters('jooosi-egami/admin:preview_post_limit', 100)));
        $scanLimit = max($resultLimit, min(20000, (int) apply_filters('jooosi-egami/admin:preview_post_scan_limit', 5000)));
        $postTypes = array_values(array_diff(get_post_types(['show_ui' => \true], 'names'), [TemplateRepository::POST_TYPE]));
        $items = [];
        $scanned = 0;
        $page = 1;
        $truncated = \false;
        do {
            $query = new WP_Query(['post_type' => $postTypes, 'post_status' => ['publish', 'draft', 'pending', 'future', 'private'], 'posts_per_page' => min(250, $scanLimit - $scanned), 'paged' => $page, 's' => $search, 'orderby' => 'modified', 'order' => 'DESC']);
            foreach ($query->posts as $post) {
                ++$scanned;
                if (current_user_can('edit_post', $post->ID) && $this->templateMatcher->matches($rules, $post)) {
                    $items[] = ['id' => (int) $post->ID, 'title' => get_the_title($post) ?: __('(no title)', 'jooosi-egami'), 'type' => $post->post_type, 'status' => $post->post_status];
                }
                if (count($items) >= $resultLimit || $scanned >= $scanLimit) {
                    $truncated = $page < (int) $query->max_num_pages || count($items) >= $resultLimit;
                    break 2;
                }
            }
            ++$page;
        } while ($page <= (int) $query->max_num_pages);
        return rest_ensure_response(['items' => $items, 'scanned' => $scanned, 'truncated' => $truncated]);
    }
    public function placeholders(WP_REST_Request $request): WP_REST_Response
    {
        $postId = absint($request->get_param('post_id'));
        if ($postId > 0 && !current_user_can('edit_post', $postId)) {
            $postId = 0;
        }
        return rest_ensure_response(['definitions' => $this->dynamicData->definitions($postId), 'values' => $this->dynamicData->resolve($postId)]);
    }
    public function status(): WP_REST_Response
    {
        return rest_ensure_response($this->diagnostics->status());
    }
    public function searchIcons(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!$this->svgSupport->available()) {
            return new WP_Error('egami_svg_unavailable', $this->svgSupport->capabilities()['reason'], ['status' => 503]);
        }
        $query = trim(sanitize_text_field((string) $request->get_param('query')));
        if ($query === '') {
            return rest_ensure_response(['results' => []]);
        }
        return rest_ensure_response(['results' => $this->icons->search(substr($query, 0, 100), 128)]);
    }
    private function registerManageRoute(string $route, string $callback): void
    {
        register_rest_route(self::NAMESPACE, $route, ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, $callback], 'permission_callback' => [$this, 'canManage']]);
    }
}
