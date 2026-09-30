<?php

declare(strict_types=1);

namespace JooosiSocialImage\Processing;

use JooosiSocialImage\Assignment\TemplateMatcher;
use JooosiSocialImage\Rendering\ImageGenerator;
use JooosiSocialImage\Template\TemplateRepository;
use WP_Error;

/**
 * Removes stale template render state and queues affected posts for regeneration.
 *
 * @since 0.1.0
 */
final class TemplateInvalidator
{
    public function __construct(
        private TemplateMatcher $matcher,
        private ImageGenerator $imageGenerator,
    ) {
    }

    /**
     * @return array{posts_scanned: int, posts_invalidated: int, attachments_deleted: int, files_deleted: int, scheduled: int}|WP_Error
     */
    public function invalidate(int $templateId): array|WP_Error
    {
        $templateId = absint($templateId);

        if ($templateId === 0) {
            return new WP_Error('social_image_invalid_template', __('A design ID is required.', 'jooosi-social-image'));
        }

        $postTypes = get_post_types(['show_ui' => true], 'names');
        unset($postTypes[TemplateRepository::POST_TYPE], $postTypes['attachment']);

        $postIds = get_posts([
            'post_type' => array_values($postTypes),
            'post_status' => array_values(get_post_stati([], 'names')),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $postsInvalidated = 0;
        $attachmentsDeleted = 0;
        $scheduled = 0;

        foreach ($postIds as $postId) {
            $postId = (int) $postId;
            $invalidated = false;
            $map = get_post_meta($postId, ImageGenerator::META_MAP, true);
            $map = is_array($map) ? $map : [];

            foreach ($map as $output => $entry) {
                if (! is_array($entry) || (int) ($entry['template_id'] ?? 0) !== $templateId) {
                    continue;
                }

                $attachmentsDeleted += $this->deleteGeneratedAttachment($postId, $entry);
                $this->deleteOutputMeta($postId, (string) $output);
                unset($map[$output]);
                $invalidated = true;
            }

            if ($invalidated) {
                ++$postsInvalidated;

                if ($map === []) {
                    delete_post_meta($postId, ImageGenerator::META_MAP);
                } else {
                    update_post_meta($postId, ImageGenerator::META_MAP, $map);
                }
            }

            if ($this->matcher->forPost($postId) !== []) {
                if (true === $this->imageGenerator->schedulePost($postId, true)) {
                    ++$scheduled;
                }
            }
        }

        $flushed = $this->imageGenerator->flushCache($templateId);

        if (is_wp_error($flushed)) {
            return $flushed;
        }

        return [
            'posts_scanned' => count($postIds),
            'posts_invalidated' => $postsInvalidated,
            'attachments_deleted' => $attachmentsDeleted,
            'files_deleted' => (int) $flushed['deleted'],
            'scheduled' => $scheduled,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function deleteGeneratedAttachment(int $postId, array $entry): int
    {
        $attachmentId = absint($entry['attachment_id'] ?? 0);

        if (
            $attachmentId === 0
            || (string) get_post_meta($attachmentId, '_social_image_generated', true) !== '1'
            || (int) get_post_meta($attachmentId, '_social_image_template_id', true) !== (int) ($entry['template_id'] ?? 0)
        ) {
            return 0;
        }

        if ((int) get_post_thumbnail_id($postId) === $attachmentId) {
            delete_post_thumbnail($postId);
        }

        return wp_delete_attachment($attachmentId, true) ? 1 : 0;
    }

    private function deleteOutputMeta(int $postId, string $output): void
    {
        $metaKey = match ($output) {
            'og' => ImageGenerator::META_OG,
            'twitter' => ImageGenerator::META_TWITTER,
            'featured' => ImageGenerator::META_FEATURED,
            default => '',
        };

        if ($metaKey !== '') {
            delete_post_meta($postId, $metaKey);
        }
    }
}
