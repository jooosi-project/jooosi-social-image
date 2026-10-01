<?php

declare (strict_types=1);
namespace JooosiSocialImage\Template;

defined('ABSPATH') || exit;
final class TemplateSchema
{
    public const DOCUMENT_VERSION = 6;
    private const MAX_ELEMENTS = 100;
    private const MAX_CONDITIONS = 30;
    private const MAX_GROUPS = 15;
    private const MAX_QUERY_DEPTH = 3;
    public static function defaultDocument(): array
    {
        return array('version' => self::DOCUMENT_VERSION, 'width' => 1200, 'height' => 630, 'background' => array('type' => 'gradient', 'color' => '#111827', 'stops' => array(array('color' => '#111827', 'position' => 0), array('color' => '#312e81', 'position' => 100)), 'direction' => 'horizontal'), 'elements' => array(array('id' => 'eyebrow-' . wp_generate_uuid4(), 'type' => 'text', 'x' => 72, 'y' => 74, 'width' => 760, 'height' => 54, 'content' => '{{site.name}}', 'fontSize' => 30, 'minFontSize' => 18, 'fontFamily' => 'system', 'fontWeight' => 700, 'color' => '#a5b4fc', 'align' => 'left', 'verticalAlign' => 'middle', 'lineHeight' => 1.1, 'opacity' => 1, 'rotation' => 0, 'locked' => \false, 'hidden' => \false), array('id' => 'title-' . wp_generate_uuid4(), 'type' => 'text', 'x' => 72, 'y' => 160, 'width' => 1030, 'height' => 330, 'content' => '{{post.title}}', 'fontSize' => 82, 'minFontSize' => 30, 'fontFamily' => 'system', 'fontWeight' => 700, 'color' => '#ffffff', 'align' => 'left', 'verticalAlign' => 'middle', 'lineHeight' => 1.08, 'opacity' => 1, 'rotation' => 0, 'locked' => \false, 'hidden' => \false), array('id' => 'date-' . wp_generate_uuid4(), 'type' => 'text', 'x' => 72, 'y' => 534, 'width' => 500, 'height' => 40, 'content' => '{{post.date}}', 'fontSize' => 24, 'minFontSize' => 16, 'fontFamily' => 'system', 'fontWeight' => 400, 'color' => '#d1d5db', 'align' => 'left', 'verticalAlign' => 'middle', 'lineHeight' => 1.2, 'opacity' => 1, 'rotation' => 0, 'locked' => \false, 'hidden' => \false)));
    }
    public static function defaultRules(): array
    {
        return array('outputs' => array('og', 'twitter'), 'query' => array('id' => 'root', 'type' => 'group', 'relation' => 'and', 'children' => array(array('id' => 'post-type', 'type' => 'condition', 'field' => 'post_type', 'operator' => 'in', 'key' => '', 'values' => array('post')), array('id' => 'post-status', 'type' => 'condition', 'field' => 'post_status', 'operator' => 'in', 'key' => '', 'values' => array('publish')))), 'priority' => 10, 'replaceFeatured' => \false);
    }
    public static function normalizeDocument(mixed $input): array
    {
        $input = is_array($input) ? $input : array();
        $width = self::integer($input['width'] ?? 1200, 200, 2400);
        $height = self::integer($input['height'] ?? 630, 200, 2400);
        $document = array('version' => self::DOCUMENT_VERSION, 'width' => $width, 'height' => $height, 'background' => self::normalizeBackground($input['background'] ?? array(), '#111827', '#312e81'), 'elements' => array());
        $ids = array();
        $count = 0;
        $document['elements'] = self::normalizeElements($input['elements'] ?? array(), $width, $height, $ids, $count);
        return $document;
    }
    private static function normalizeBackground(mixed $input, string $fallback, string $second_fallback): array
    {
        $background = is_array($input) ? $input : array();
        $type = in_array($background['type'] ?? '', array('solid', 'gradient'), \true) ? $background['type'] : 'solid';
        $color = self::color($background['color'] ?? $fallback, $fallback);
        return array('type' => $type, 'color' => $color, 'stops' => self::normalizeGradientStops($background, $color, $second_fallback), 'direction' => in_array($background['direction'] ?? '', array('horizontal', 'vertical'), \true) ? $background['direction'] : 'horizontal');
    }
    /**
     * @return list<array{color: string, position: float}>
     */
    private static function normalizeGradientStops(array $background, string $first_color, string $second_fallback): array
    {
        $input = is_array($background['stops'] ?? null) ? array_slice($background['stops'], 0, 12) : array();
        $stops = array();
        foreach ($input as $index => $stop) {
            if (!is_array($stop)) {
                continue;
            }
            $stops[] = array('color' => self::color($stop['color'] ?? $first_color, $first_color), 'position' => self::number($stop['position'] ?? 0, 0, 100), '_index' => $index);
        }
        if (count($stops) < 2) {
            return array(array('color' => $first_color, 'position' => 0.0), array('color' => $second_fallback, 'position' => 100.0));
        }
        usort($stops, static fn(array $left, array $right): int => $left['position'] <=> $right['position'] ?: $left['_index'] <=> $right['_index']);
        return array_map(static fn(array $stop): array => array('color' => $stop['color'], 'position' => $stop['position']), $stops);
    }
    /**
     * @param array<string, true> $ids
     * @return list<array<string, mixed>>
     */
    private static function normalizeElements(mixed $input, int $canvas_width, int $canvas_height, array &$ids, int &$count): array
    {
        $elements = is_array($input) ? $input : array();
        $clean_elements = array();
        foreach ($elements as $index => $element) {
            if ($count >= self::MAX_ELEMENTS) {
                break;
            }
            if (!is_array($element)) {
                continue;
            }
            $clean_elements[] = self::normalizeElement($element, $canvas_width, $canvas_height, (int) $index, $ids, $count);
        }
        return $clean_elements;
    }
    /**
     * @param array<string, true> $ids
     * @return array<string, mixed>
     */
    private static function normalizeElement(array $input, int $canvas_width, int $canvas_height, int $index, array &$ids, int &$count): array
    {
        $type = in_array($input['type'] ?? '', array('text', 'image', 'svg', 'shape'), \true) ? $input['type'] : 'text';
        $id = sanitize_key((string) ($input['id'] ?? ''));
        if ('' === $id) {
            $id = $type . '-' . $index . '-' . substr(md5(wp_json_encode($input)), 0, 8);
        }
        $base_id = $id;
        $suffix = 2;
        while (isset($ids[$id])) {
            $id = $base_id . '-' . $suffix;
            ++$suffix;
        }
        $ids[$id] = \true;
        ++$count;
        $element = array('id' => $id, 'type' => $type, 'x' => self::number($input['x'] ?? 0, -$canvas_width, $canvas_width * 2), 'y' => self::number($input['y'] ?? 0, -$canvas_height, $canvas_height * 2), 'width' => self::number($input['width'] ?? 300, 1, $canvas_width * 2), 'height' => self::number($input['height'] ?? 120, 1, $canvas_height * 2), 'opacity' => self::number($input['opacity'] ?? 1, 0, 1), 'rotation' => self::number($input['rotation'] ?? 0, -360, 360), 'locked' => !empty($input['locked']), 'hidden' => !empty($input['hidden']));
        if ('text' === $type) {
            $align = in_array($input['align'] ?? '', array('left', 'center', 'right'), \true) ? $input['align'] : 'left';
            $vertical = in_array($input['verticalAlign'] ?? '', array('top', 'middle', 'bottom'), \true) ? $input['verticalAlign'] : 'top';
            $element += array('content' => sanitize_textarea_field((string) ($input['content'] ?? 'Text')), 'fontSize' => self::number($input['fontSize'] ?? 48, 6, 400), 'minFontSize' => self::number($input['minFontSize'] ?? 16, 6, 400), 'fontFamily' => sanitize_text_field((string) ($input['fontFamily'] ?? 'system')), 'fontAttachmentId' => absint($input['fontAttachmentId'] ?? 0), 'fontWeight' => self::integer($input['fontWeight'] ?? 400, 100, 900), 'color' => self::color($input['color'] ?? '#ffffff', '#ffffff'), 'align' => $align, 'verticalAlign' => $vertical, 'lineHeight' => self::number($input['lineHeight'] ?? 1.2, 0.7, 3));
        } elseif ('image' === $type) {
            $fit = in_array($input['fit'] ?? '', array('cover', 'contain', 'fill', 'none'), \true) ? $input['fit'] : 'cover';
            $mask = in_array($input['mask'] ?? '', array('none', 'ellipse', 'triangle', 'diamond', 'hexagon'), \true) ? $input['mask'] : 'none';
            $raw_source = trim((string) ($input['source'] ?? ''));
            $source = preg_match('#^https?://#i', $raw_source) ? esc_url_raw($raw_source, array('http', 'https')) : sanitize_text_field($raw_source);
            $element += array('attachmentId' => absint($input['attachmentId'] ?? 0), 'source' => $source, 'previewUrl' => esc_url_raw((string) ($input['previewUrl'] ?? '')), 'fit' => $fit, 'radius' => self::number($input['radius'] ?? 0, 0, 1000), 'mask' => $mask);
        } elseif ('svg' === $type) {
            $name = strtolower(trim((string) ($input['icon'] ?? '')));
            $element += array('icon' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*:[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) ? $name : '', 'color' => self::color($input['color'] ?? '#ffffff', '#ffffff'));
        } elseif ('shape' === $type) {
            $shape = in_array($input['shape'] ?? '', array('rectangle', 'ellipse', 'triangle', 'diamond', 'hexagon', 'star', 'line', 'arrow'), \true) ? $input['shape'] : 'rectangle';
            $element += array('shape' => $shape, 'background' => self::normalizeBackground($input['background'] ?? array(), '#6366f1', '#6366f1'), 'strokeColor' => self::color($input['strokeColor'] ?? '#ffffff', '#ffffff'), 'strokeWidth' => self::number($input['strokeWidth'] ?? 0, 0, 100), 'radius' => self::number($input['radius'] ?? 0, 0, 1000));
        }
        return $element;
    }
    public static function normalizeRules(mixed $input): array
    {
        $input = is_array($input) ? $input : array();
        $requested_outputs = array_map('sanitize_key', (array) ($input['outputs'] ?? array()));
        $outputs = array();
        if (array_intersect(array('og', 'twitter'), $requested_outputs)) {
            $outputs = array('og', 'twitter');
        }
        if (in_array('featured', $requested_outputs, \true)) {
            $outputs[] = 'featured';
        }
        if (array() === $outputs) {
            $outputs = array('og', 'twitter');
        }
        $query_input = isset($input['query']) && is_array($input['query']) ? $input['query'] : self::defaultRules()['query'];
        $counters = array('conditions' => 0, 'groups' => 0);
        $used_ids = array();
        $query = self::normalizeGroup($query_input, 0, $counters, $used_ids, \true);
        $query ??= array('id' => 'root', 'type' => 'group', 'relation' => 'and', 'children' => array());
        return array('outputs' => $outputs, 'query' => $query, 'priority' => self::integer($input['priority'] ?? 10, -1000, 1000), 'replaceFeatured' => !empty($input['replaceFeatured']));
    }
    /**
     * @param array{conditions: int, groups: int} $counters
     * @param array<string, bool>                 $used_ids
     * @return array{id: string, type: string, relation: string, children: list<array<string, mixed>>}|null
     */
    private static function normalizeGroup(array $input, int $depth, array &$counters, array &$used_ids, bool $root = \false): ?array
    {
        if (!$root && ($depth > self::MAX_QUERY_DEPTH || $counters['groups'] >= self::MAX_GROUPS)) {
            return null;
        }
        if (!$root) {
            ++$counters['groups'];
        }
        $id = self::uniqueRuleId((string) ($input['id'] ?? ''), $root ? 'root' : 'group-' . $counters['groups'], $used_ids);
        $children = array();
        foreach (array_slice(is_array($input['children'] ?? null) ? $input['children'] : array(), 0, self::MAX_CONDITIONS + self::MAX_GROUPS) as $index => $child) {
            if (!is_array($child)) {
                continue;
            }
            $is_group = 'group' === ($child['type'] ?? '') || array_key_exists('children', $child);
            if ($is_group) {
                $normalized = self::normalizeGroup($child, $depth + 1, $counters, $used_ids);
            } elseif ($counters['conditions'] < self::MAX_CONDITIONS) {
                $normalized = self::normalizeCondition($child, (int) $index, $used_ids);
                if (null !== $normalized) {
                    ++$counters['conditions'];
                }
            } else {
                $normalized = null;
            }
            if (null !== $normalized) {
                $children[] = $normalized;
            }
        }
        if (!$root && array() === $children) {
            return null;
        }
        return array('id' => $id, 'type' => 'group', 'relation' => 'or' === strtolower((string) ($input['relation'] ?? 'and')) ? 'or' : 'and', 'children' => $children);
    }
    /**
     * @param array<string, bool> $used_ids
     * @return array{id: string, type: string, field: string, operator: string, key: string, values: list<string>}|null
     */
    private static function normalizeCondition(array $input, int $index, array &$used_ids): ?array
    {
        $field = sanitize_key((string) ($input['field'] ?? ''));
        $operators = array('post_type' => array('in', 'not_in'), 'post_status' => array('in', 'not_in'), 'post_id' => array('in', 'not_in'), 'author' => array('in', 'not_in'), 'parent' => array('in', 'not_in'), 'page_template' => array('in', 'not_in'), 'taxonomy' => array('has_any', 'has_all', 'has_none'), 'meta' => array('exists', 'not_exists', 'equals', 'not_equals', 'contains', 'not_contains', 'in', 'not_in', 'greater', 'greater_or_equal', 'less', 'less_or_equal'), 'post_title' => array('equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with'), 'post_slug' => array('equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with'), 'post_excerpt' => array('equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with'), 'date' => array('before', 'after', 'on', 'between'));
        if (!isset($operators[$field])) {
            return null;
        }
        $operator = sanitize_key((string) ($input['operator'] ?? $operators[$field][0]));
        if (!in_array($operator, $operators[$field], \true)) {
            $operator = $operators[$field][0];
        }
        $key = sanitize_key((string) ($input['key'] ?? ''));
        if ('date' === $field && !in_array($key, array('published', 'modified'), \true)) {
            $key = 'published';
        }
        if (in_array($field, array('taxonomy', 'meta'), \true) && '' === $key) {
            return null;
        }
        $values = array();
        foreach (array_slice((array) ($input['values'] ?? array()), 0, 100) as $value) {
            $value = sanitize_text_field((string) $value);
            if ('' === $value) {
                continue;
            }
            if (in_array($field, array('post_id', 'author', 'parent'), \true)) {
                $value = (string) absint($value);
                if ('0' === $value) {
                    continue;
                }
            } elseif (in_array($field, array('post_type', 'post_status'), \true)) {
                $value = sanitize_key($value);
            }
            $values[] = $value;
        }
        if (!in_array($operator, array('exists', 'not_exists'), \true) && array() === $values) {
            return null;
        }
        return array('id' => self::uniqueRuleId((string) ($input['id'] ?? ''), 'condition-' . ($index + 1), $used_ids), 'type' => 'condition', 'field' => $field, 'operator' => $operator, 'key' => $key, 'values' => array_values(array_unique($values)));
    }
    /**
     * @param array<string, bool> $used_ids
     */
    private static function uniqueRuleId(string $input, string $fallback, array &$used_ids): string
    {
        $base_id = sanitize_key($input) ?: $fallback;
        $id = $base_id;
        $suffix = 2;
        while (isset($used_ids[$id])) {
            $id = $base_id . '-' . $suffix++;
        }
        $used_ids[$id] = \true;
        return $id;
    }
    public static function color(mixed $value, string $fallback = '#000000'): string
    {
        $value = trim((string) $value);
        if (preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $value)) {
            return strtolower($value);
        }
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value)) {
            return strtolower($value);
        }
        return $fallback;
    }
    private static function integer(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }
    private static function number(mixed $value, int|float $min, int|float $max): float
    {
        $value = is_numeric($value) ? (float) $value : (float) $min;
        return round(max($min, min($max, $value)), 3);
    }
}
