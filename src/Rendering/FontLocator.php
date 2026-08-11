<?php

declare (strict_types=1);
namespace JooosiEgami\Rendering;

use JooosiEgami\Integration\YabeWebfont;
defined('ABSPATH') || exit;
final class FontLocator
{
    public function __construct(private YabeWebfont $webfonts)
    {
    }
    public function locateForElement(array $element): string
    {
        $family = sanitize_text_field((string) ($element['fontFamily'] ?? 'system'));
        $weight = (int) ($element['fontWeight'] ?? 400);
        if (!empty($element['fontAttachmentId'])) {
            $path = get_attached_file((int) $element['fontAttachmentId']);
            if ($this->isFont($path)) {
                return $path;
            }
        }
        if (!$this->isBundledFamily($family)) {
            $path = $this->webfonts->locate($family, $weight, 'normal');
            if ($this->isFont($path)) {
                return $path;
            }
        }
        $candidates = apply_filters('jooosi-egami/rendering:system_font_paths', $this->systemCandidates($family, $weight), $family, $weight, $element);
        foreach ((array) $candidates as $path) {
            if ($this->isFont($path)) {
                return $path;
            }
        }
        return '';
    }
    /**
     * Report whether the host provides the fallback faces used by server renders.
     * Absolute host paths stay private; diagnostics expose only family and weight.
     *
     * @return array{available: bool, families: list<array{family: string, regular: bool, bold: bool}>, reason: string, notice: string}
     */
    public function capabilities(): array
    {
        $families = array();
        $missing = array();
        foreach (array('Arial', 'Georgia', 'Verdana') as $family) {
            $regular = '' !== $this->locateForElement(array('fontFamily' => $family, 'fontWeight' => 400));
            $bold = '' !== $this->locateForElement(array('fontFamily' => $family, 'fontWeight' => 700));
            $families[] = array('family' => $family, 'regular' => $regular, 'bold' => $bold);
            if (!$regular) {
                $missing[] = $family;
            }
        }
        $available = (bool) ($families[0]['regular'] ?? \false);
        $reason = $available ? '' : __('No readable Arial-compatible TTF or OTF fallback was found. Install Liberation Sans or DejaVu Sans, or provide a local font through Yabe Webfont.', 'jooosi-egami');
        $notice = '';
        if ($available && $missing) {
            /* translators: %s: Comma-separated list of missing fallback font families. */
            $notice = sprintf(__('Some fallback font families are unavailable: %s. Their text layers will use no server-rendered glyphs until compatible local fonts are installed.', 'jooosi-egami'), implode(', ', $missing));
        }
        return array('available' => $available, 'families' => $families, 'reason' => $reason, 'notice' => $notice);
    }
    private function isBundledFamily(string $family): bool
    {
        return in_array(strtolower($family), array('', 'system', 'arial', 'georgia', 'verdana'), \true);
    }
    /**
     * Browser previews use equivalent ordered fallback stacks in element-styles.ts.
     *
     * @return list<string>
     */
    private function systemCandidates(string $family, int $weight): array
    {
        $bold = $weight >= 600;
        if (0 === strcasecmp($family, 'Georgia')) {
            $weighted = $bold ? array('/Library/Fonts/Georgia Bold.ttf', '/System/Library/Fonts/Supplemental/Georgia Bold.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSerif-Bold.ttf', '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf') : array();
            return array_merge($weighted, array('/Library/Fonts/Georgia.ttf', '/System/Library/Fonts/Supplemental/Georgia.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSerif-Regular.ttf', '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf'));
        }
        if (0 === strcasecmp($family, 'Verdana')) {
            $weighted = $bold ? array('/Library/Fonts/Verdana Bold.ttf', '/System/Library/Fonts/Supplemental/Verdana Bold.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf') : array();
            return array_merge($weighted, array('/Library/Fonts/Verdana.ttf', '/System/Library/Fonts/Supplemental/Verdana.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'));
        }
        $weighted = $bold ? array('/Library/Fonts/Arial Bold.ttf', '/System/Library/Fonts/Supplemental/Arial Bold.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf') : array();
        return array_merge($weighted, array('/Library/Fonts/Arial.ttf', '/System/Library/Fonts/Supplemental/Arial.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'));
    }
    private function isFont(mixed $path): bool
    {
        return is_string($path) && is_readable($path) && in_array(strtolower(pathinfo($path, \PATHINFO_EXTENSION)), array('ttf', 'otf'), \true);
    }
}
