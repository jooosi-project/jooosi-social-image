<?php

declare(strict_types=1);

namespace JooosiEgami\Tests\Unit;

use JooosiEgami\Preset\PresetRepositoryManager;
use JooosiEgami\Preset\PresetSchema;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class PresetRepositoryTest extends TestCase
{
    private string $repositoryFile;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['egami_test_options'] = [];
        $this->repositoryFile = dirname(__DIR__, 2) . '/presets/repository.json';
    }

    public function testBundledRepositoryProducesSixtyFiveDecoratedPresets(): void
    {
        $manager = new PresetRepositoryManager($this->repositoryFile, 'https://example.test/schemas');
        $catalog = $manager->catalog();

        self::assertNotInstanceOf(WP_Error::class, $catalog);
        self::assertCount(65, $catalog['presets']);
        self::assertCount(1, $catalog['repositories']);
        self::assertSame('egami-essentials/editorial-gradient', $catalog['presets'][0]['key']);
        self::assertSame('egami-essentials', $catalog['presets'][0]['source']['repositoryId']);
        self::assertTrue($catalog['presets'][0]['source']['bundled']);
        self::assertSame('https://example.test/schemas/repository.schema.json', $catalog['schemas']['repository']);
    }

    public function testRepositorySchemaRejectsDuplicatePresetIds(): void
    {
        $repository = $this->fixture();
        $repository['presets'][] = $repository['presets'][0];
        $result = PresetSchema::repository($repository);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('egami_preset_duplicate_preset', $result->get_error_code());
        self::assertSame(422, $result->get_error_data()['status']);
    }

    public function testUnsupportedDocumentVersionIsRejected(): void
    {
        $preset = $this->fixture()['presets'][0];
        $preset['document']['version'] = 4;
        $result = PresetSchema::preset($preset);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('egami_preset_document', $result->get_error_code());
    }

    public function testUnsupportedRectElementIsRejected(): void
    {
        $preset = $this->fixture()['presets'][0];
        $preset['document']['elements'][0]['type'] = 'rect';
        $result = PresetSchema::preset($preset);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('egami_preset_document', $result->get_error_code());
    }

    public function testBundledRepositoryCanBeDisabledButNotDeleted(): void
    {
        $manager = new PresetRepositoryManager($this->repositoryFile, 'https://example.test/schemas');
        $disabled = $manager->setEnabled('egami-essentials', false);

        self::assertNotInstanceOf(WP_Error::class, $disabled);
        self::assertCount(0, $disabled['presets']);
        self::assertFalse($disabled['repositories'][0]['enabled']);
        self::assertSame('0', $GLOBALS['egami_test_options'][PresetRepositoryManager::OPTION_BUNDLED_ENABLED]);
        self::assertTrue($disabled['repositories'][0]['capabilities']['toggle']);
        self::assertFalse($disabled['repositories'][0]['capabilities']['delete']);

        $deleted = $manager->remove('egami-essentials');
        self::assertInstanceOf(WP_Error::class, $deleted);
        self::assertSame('egami_bundled_repository_protected', $deleted->get_error_code());

        $enabled = $manager->setEnabled('egami-essentials', true);
        self::assertNotInstanceOf(WP_Error::class, $enabled);
        self::assertCount(65, $enabled['presets']);
        self::assertSame('1', $GLOBALS['egami_test_options'][PresetRepositoryManager::OPTION_BUNDLED_ENABLED]);
    }

    public function testBundledRealWorldRepositoryConformsToTheApplicationSchema(): void
    {
        $repository = $this->fixture();
        $result = PresetSchema::repository($repository);

        self::assertNotInstanceOf(WP_Error::class, $result);
        self::assertSame('egami-essentials', $result['id']);
        self::assertCount(65, $result['presets']);
    }

    public function testCachedExternalRepositoryCanBeDisabledAndRemoved(): void
    {
        $external = $this->fixture();
        $external['id'] = 'remote-studio';
        $external['title'] = 'Remote Studio';
        $external['version'] = '2.0.0';
        $external['presets'] = [$external['presets'][0]];
        $external['presets'][0]['id'] = 'remote-editorial';

        $GLOBALS['egami_test_options'][PresetRepositoryManager::OPTION_REPOSITORIES] = [
            'remote-studio' => [
                'id' => 'remote-studio',
                'url' => 'https://example.test/repository.json',
                'enabled' => true,
                'addedAt' => '2026-08-07T00:00:00Z',
                'lastError' => '',
            ],
        ];
        $GLOBALS['egami_test_options'][PresetRepositoryManager::OPTION_CACHE] = [
            'remote-studio' => [
                'repository' => $external,
                'syncedAt' => '2026-08-07T01:00:00Z',
                'etag' => 'test',
                'lastModified' => '',
            ],
        ];

        $manager = new PresetRepositoryManager($this->repositoryFile, 'https://example.test/schemas/');
        $catalog = $manager->catalog();

        self::assertNotInstanceOf(WP_Error::class, $catalog);
        self::assertCount(66, $catalog['presets']);
        self::assertSame('remote-studio/remote-editorial', $catalog['presets'][65]['key']);
        self::assertFalse($catalog['presets'][65]['source']['bundled']);

        $disabled = $manager->setEnabled('remote-studio', false);
        self::assertNotInstanceOf(WP_Error::class, $disabled);
        self::assertCount(65, $disabled['presets']);
        self::assertFalse($disabled['repositories'][1]['enabled']);

        $removed = $manager->remove('remote-studio');
        self::assertNotInstanceOf(WP_Error::class, $removed);
        self::assertCount(1, $removed['repositories']);
        self::assertSame([], $GLOBALS['egami_test_options'][PresetRepositoryManager::OPTION_CACHE]);
    }

    private function fixture(): array
    {
        return json_decode((string) file_get_contents($this->repositoryFile), true, 512, JSON_THROW_ON_ERROR);
    }
}
