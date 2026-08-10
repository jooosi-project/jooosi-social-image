<?php

declare(strict_types=1);

namespace JooosiEgami\Cli;

use JooosiEgami\Processing\TemplateInvalidator;
use JooosiEgami\Template\TemplateRepository;
use WP_CLI;

use function WP_CLI\Utils\format_items;

/**
 * Inspects and removes saved image designs.
 *
 * @since 0.1.0
 */
final class TemplateCommand
{
    public function __construct(
        private TemplateRepository $templateRepository,
        private TemplateInvalidator $templateInvalidator,
    ) {
    }

    public function list(array $args, array $assocArgs): void
    {
        $items = array_map(
            static fn (array $template): array => [
                'id' => $template['id'],
                'title' => $template['title'],
                'status' => $template['status'],
                'outputs' => implode(',', $template['rules']['outputs']),
                'query' => strtoupper((string) $template['rules']['query']['relation']),
                'conditions' => self::countConditions($template['rules']['query']),
                'revision' => $template['revision'],
            ],
            $this->templateRepository->all(),
        );

        format_items($assocArgs['format'] ?? 'table', $items, ['id', 'title', 'status', 'outputs', 'query', 'conditions', 'revision']);
    }

    public function get(array $args, array $assocArgs): void
    {
        $template = $this->templateRepository->get(absint($args[0] ?? 0));

        if (is_wp_error($template)) {
            WP_CLI::error($template->get_error_message());
        }

        WP_CLI::line((string) wp_json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function delete(array $args, array $assocArgs): void
    {
        $id = absint($args[0] ?? 0);
        $template = $this->templateRepository->get($id);

        if (is_wp_error($template)) {
            WP_CLI::error($template->get_error_message());
        }

        WP_CLI::confirm(sprintf('Delete design “%s” and its generated cache?', $template['title']), $assocArgs);
        $deleted = $this->templateRepository->delete($id);

        if (is_wp_error($deleted)) {
            WP_CLI::error($deleted->get_error_message());
        }

        if (! $deleted) {
            WP_CLI::error('The design could not be deleted.');
        }

        $flushed = $this->templateInvalidator->invalidate($id);

        if (is_wp_error($flushed)) {
            WP_CLI::warning($flushed->get_error_message());
        }

        WP_CLI::success('Design deleted.');
    }

    private static function countConditions(array $node): int
    {
        if (($node['type'] ?? '') === 'condition') {
            return 1;
        }

        return array_sum(array_map(
            static fn (mixed $child): int => is_array($child) ? self::countConditions($child) : 0,
            is_array($node['children'] ?? null) ? $node['children'] : [],
        ));
    }
}
