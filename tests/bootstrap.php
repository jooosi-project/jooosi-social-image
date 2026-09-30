<?php

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/wordpress/');

if (! class_exists('WP_Error')) {
    final class WP_Error
    {
        public function __construct(
            private string $code = '',
            private string $message = '',
            private mixed $data = null,
        ) {
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data(): mixed
        {
            return $this->data;
        }
    }
}

if (! class_exists('WP_Post')) {
    final class WP_Post
    {
        public int $ID = 0;
        public string $post_type = 'post';
        public string $post_status = 'publish';
        public int $post_author = 0;
        public int $post_parent = 0;
        public string $post_title = '';
        public string $post_name = '';
        public string $post_excerpt = '';
        public string $post_content = '';
        public string $post_date = '';
        public string $post_date_gmt = '';
        public string $post_modified = '';
        public string $post_modified_gmt = '';

        public function __construct(array $values = [])
        {
            foreach ($values as $key => $value) {
                if (property_exists($this, (string) $key)) {
                    $this->{$key} = $value;
                }
            }
        }
    }
}

function is_wp_error(mixed $value): bool
{
    return $value instanceof WP_Error;
}

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function esc_html__(string $text, string $domain = 'default'): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function apply_filters(string $hookName, mixed $value, mixed ...$args): mixed
{
    $callbacks = array_values(array_filter(
        $GLOBALS['social_image_test_hooks']['filters'] ?? [],
        static fn (array $registered): bool => $registered['hookName'] === $hookName,
    ));
    usort($callbacks, static fn (array $left, array $right): int => $left['priority'] <=> $right['priority']);

    foreach ($callbacks as $registered) {
        $acceptedArgs = max(1, (int) $registered['acceptedArgs']);
        $value = ($registered['callback'])(...array_slice([$value, ...$args], 0, $acceptedArgs));
    }

    return $value;
}

function sanitize_key(string $key): string
{
    return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $key));
}

function sanitize_text_field(string $value): string
{
    return trim(strip_tags($value));
}

function sanitize_textarea_field(string $value): string
{
    return trim(strip_tags($value));
}

function absint(mixed $value): int
{
    return abs((int) $value);
}

function esc_url_raw(string $value, array $protocols = []): string
{
    return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
}

function trailingslashit(string $value): string
{
    return rtrim($value, '/\\') . '/';
}

function wp_parse_url(string $url, int $component = -1): mixed
{
    return parse_url($url, $component);
}

function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
{
    return json_encode($value, $flags, $depth);
}

function wp_generate_uuid4(): string
{
    return sprintf(
        '%08x-%04x-4%03x-a%03x-%012x',
        random_int(0, 0xffffffff),
        random_int(0, 0xffff),
        random_int(0, 0xfff),
        random_int(0, 0xfff),
        random_int(0, 0xffffffffffff),
    );
}

$GLOBALS['social_image_test_hooks'] = [
    'actions' => [],
    'filters' => [],
    'shortcodes' => [],
    'activation' => [],
    'deactivation' => [],
];

$GLOBALS['social_image_test_scheduled_events'] = [];
$GLOBALS['social_image_test_schedule_result'] = true;

$GLOBALS['social_image_test_options'] = [];

function get_option(string $option, mixed $default = false): mixed
{
    return $GLOBALS['social_image_test_options'][$option] ?? $default;
}

function update_option(string $option, mixed $value, mixed $autoload = null): bool
{
    $GLOBALS['social_image_test_options'][$option] = $value;

    return true;
}

function delete_option(string $option): bool
{
    unset($GLOBALS['social_image_test_options'][$option]);

    return true;
}

function wp_next_scheduled(string $hook, array $args = []): int|false
{
    foreach ($GLOBALS['social_image_test_scheduled_events'] as $event) {
        if ($event['hook'] === $hook && $event['args'] === $args) {
            return $event['timestamp'];
        }
    }

    return false;
}

function wp_schedule_single_event(int $timestamp, string $hook, array $args = [], bool $wpError = false): bool|WP_Error
{
    unset($wpError);
    $result = $GLOBALS['social_image_test_schedule_result'] ?? true;

    if ($result instanceof WP_Error || $result === false) {
        return $result;
    }

    $GLOBALS['social_image_test_scheduled_events'][] = compact('timestamp', 'hook', 'args');

    return true;
}

function wp_upload_dir(): array
{
    $basedir = $GLOBALS['social_image_test_upload_basedir'] ?? sys_get_temp_dir() . '/jooosi-social-image-tests';

    return [
        'path' => $basedir,
        'url' => 'https://example.test/wp-content/uploads',
        'subdir' => '',
        'basedir' => $basedir,
        'baseurl' => 'https://example.test/wp-content/uploads',
        'error' => false,
    ];
}

function wp_mkdir_p(string $path): bool
{
    return is_dir($path) || mkdir($path, 0777, true);
}

function wp_is_writable(string $path): bool
{
    return is_writable($path);
}

function wp_normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

function wp_delete_file(string $file): void
{
    if (is_file($file)) {
        unlink($file);
    }
}

function add_action(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
    $GLOBALS['social_image_test_hooks']['actions'][] = compact('hookName', 'callback', 'priority', 'acceptedArgs');

    return true;
}

function add_filter(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
    $GLOBALS['social_image_test_hooks']['filters'][] = compact('hookName', 'callback', 'priority', 'acceptedArgs');

    return true;
}

function remove_all_filters(string $hookName): bool
{
    $GLOBALS['social_image_test_hooks']['filters'] = array_values(array_filter(
        $GLOBALS['social_image_test_hooks']['filters'],
        static fn (array $registered): bool => $registered['hookName'] !== $hookName,
    ));

    return true;
}

$GLOBALS['social_image_test_post_meta'] = [];
$GLOBALS['social_image_test_terms'] = [];
$GLOBALS['social_image_test_page_templates'] = [];

function get_page_template_slug(WP_Post|int|null $post = null): string|false
{
    $postId = $post instanceof WP_Post ? $post->ID : (int) $post;
    return $GLOBALS['social_image_test_page_templates'][$postId] ?? false;
}

function has_term(string|int|array $term, string $taxonomy = '', WP_Post|int|null $post = null): bool
{
    $postId = $post instanceof WP_Post ? $post->ID : (int) $post;
    $expected = array_map('strval', (array) $term);
    return array_intersect($expected, array_map('strval', $GLOBALS['social_image_test_terms'][$postId][$taxonomy] ?? [])) !== [];
}

function metadata_exists(string $metaType, int $objectId, string $metaKey): bool
{
    return array_key_exists($metaKey, $GLOBALS['social_image_test_post_meta'][$objectId] ?? []);
}

function get_post_meta(int $postId, string $key = '', bool $single = false): mixed
{
    $metadata = $GLOBALS['social_image_test_post_meta'][$postId] ?? [];
    if ($key === '') return $metadata;
    if (! array_key_exists($key, $metadata)) return $single ? '' : [];
    $values = is_array($metadata[$key]) ? $metadata[$key] : [$metadata[$key]];
    return $single ? ($values[0] ?? '') : $values;
}

function maybe_unserialize(mixed $value): mixed
{
    if (! is_string($value)) return $value;
    $unserialized = @unserialize($value);
    return $unserialized === false && $value !== 'b:0;' ? $value : $unserialized;
}

function add_shortcode(string $tag, callable $callback): void
{
    $GLOBALS['social_image_test_hooks']['shortcodes'][] = compact('tag', 'callback');
}

function register_activation_hook(string $file, callable $callback): void
{
    $GLOBALS['social_image_test_hooks']['activation'][] = compact('file', 'callback');
}

function register_deactivation_hook(string $file, callable $callback): void
{
    $GLOBALS['social_image_test_hooks']['deactivation'][] = compact('file', 'callback');
}

function load_plugin_textdomain(string $domain, bool $deprecated = false, string $pluginRelativePath = ''): bool
{
    return true;
}

function plugin_basename(string $file): string
{
    return basename(dirname($file)) . '/' . basename($file);
}

function plugin_dir_url(string $file): string
{
    return 'https://example.test/wp-content/plugins/' . basename(dirname($file)) . '/';
}

require dirname(__DIR__) . '/vendor/autoload.php';
