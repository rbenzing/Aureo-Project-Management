<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Enums\TemplateType;
use App\Models\BaseModel;
use App\Models\Setting;
use App\Models\Template;
use App\Services\SecurityService;
use App\Services\SettingsService;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Behavioural tests for the Template model: available/default template
 * lookups (including the company-specific-then-global fallback chain),
 * paginated listing with dynamic filter/where construction, default-flag
 * toggling, sprint-template enrichment from SettingsService, and the
 * create-with-configuration transaction.
 *
 * The Database singleton is always swapped for a mock via reflection so no
 * real MySQL connection is opened (mirroring BaseModelTest/MilestoneTest).
 * SettingsService's process-wide static cache is pre-seeded (mirroring
 * TaskTest/ProjectTest) so getSprintTemplates()/getSprintTemplateConfiguration()
 * resolve deterministically without touching the Setting model. Template does
 * not use the Searchable trait, so create() never reaches SearchIndex.
 */
// Config is declared because Database consults Config::isProduction(); it is a
// process-wide singleton reached via Database, so which test first executes
// its body moves with execution order across the whole suite (see MilestoneTest).
#[CoversClass(Template::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(SecurityService::class)]
#[UsesClass(Setting::class)]
#[UsesClass(SettingsService::class)]
#[UsesClass(TemplateType::class)]
final class TemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDatabase(null);
        $this->seedSecurityService(null);
        $this->seedSettingsServiceInstance(null);
        $this->seedSettingsCache([]);
    }

    protected function tearDown(): void
    {
        $this->seedDatabase(null);
        $this->seedSecurityService(null);
        $this->seedSettingsServiceInstance(null);
        $this->seedSettingsCache(null);

        parent::tearDown();
    }

    private function seedDatabase(?Database $db): void
    {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);
    }

    private function seedSecurityService(?SecurityService $service): void
    {
        (new ReflectionClass(SecurityService::class))->getProperty('instance')->setValue(null, $service);
    }

    private function seedSettingsServiceInstance(?SettingsService $service): void
    {
        (new ReflectionClass(SettingsService::class))->getProperty('instance')->setValue(null, $service);
    }

    private function seedSettingsCache(?array $cache): void
    {
        (new ReflectionClass(SettingsService::class))->getProperty('cache')->setValue(null, $cache);
    }

    private function statement(array $methodReturns): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);

        foreach ($methodReturns as $method => $value) {
            $stmt->method($method)->willReturn($value);
        }

        return $stmt;
    }

    /**
     * Database mock whose executeQuery()/executeInsertUpdate() each replay
     * their own queue of steps in order (a PDOStatement/bool to return, or a
     * Throwable to throw), recording every call's SQL/params, repeating the
     * last queued step for calls beyond the list.
     */
    private function newDb(
        array $queryQueue = [],
        array $insertUpdateQueue = [],
        ?array &$queryCalls = null,
        ?array &$insertCalls = null
    ): Database {
        $queryCalls = [];
        $insertCalls = [];
        $db = $this->createMock(Database::class);

        $qi = 0;
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$qi, $queryQueue, &$queryCalls) {
                $queryCalls[] = ['sql' => $sql, 'params' => $params];
                $step = $queryQueue[$qi] ?? end($queryQueue);
                $qi++;

                if ($step instanceof \Throwable) {
                    throw $step;
                }

                return $step;
            }
        );

        $ii = 0;
        $db->method('executeInsertUpdate')->willReturnCallback(
            function (string $sql, array $params = []) use (&$ii, $insertUpdateQueue, &$insertCalls) {
                $insertCalls[] = ['sql' => $sql, 'params' => $params];
                $step = $insertUpdateQueue[$ii] ?? end($insertUpdateQueue);
                $ii++;

                if ($step instanceof \Throwable) {
                    throw $step;
                }

                return $step;
            }
        );

        return $db;
    }

    // ---------------------------------------------------- getAvailableTemplates()

    public function testGetAvailableTemplatesReturnsRowsForTypeAndCompany(): void
    {
        $rows = [(object)['id' => 1, 'name' => 'Standard Sprint']];
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => $rows])], [], $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->getAvailableTemplates('sprint', 7);

        $this->assertSame($rows, $result);
        $this->assertSame('sprint', $calls[0]['params'][':template_type']);
        $this->assertSame(7, $calls[0]['params'][':company_id']);
    }

    public function testGetAvailableTemplatesWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Template();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get available templates:');

        $model->getAvailableTemplates();
    }

    // --------------------------------------------------------- getAllTemplates()

    public function testGetAllTemplatesWithNoFiltersOnlyExcludesDeleted(): void
    {
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => []])], [], $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $model->getAllTemplates([], 5, 2);

        // The trailing ORDER BY always mentions template_type, so assert on the
        // WHERE clause specifically rather than the presence of that substring.
        $this->assertStringContainsString('WHERE is_deleted = 0', $calls[0]['sql']);
        $this->assertStringNotContainsString('template_type = :template_type', $calls[0]['sql']);
        $this->assertSame(5, $calls[0]['params'][':limit']);
        $this->assertSame(5, $calls[0]['params'][':offset']); // (page 2 - 1) * limit 5
    }

    public function testGetAllTemplatesWithTemplateTypeFilterAddsCondition(): void
    {
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => []])], [], $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $model->getAllTemplates(['template_type' => 'task']);

        $this->assertStringContainsString('template_type = :template_type', $calls[0]['sql']);
        $this->assertSame('task', $calls[0]['params'][':template_type']);
    }

    /**
     * Regression: the company_id block was guarded by isset(), which is false for
     * a present-but-null value, so ['company_id' => null] never entered it and the
     * IS NULL branch was dead code. A caller asking for global templates got no
     * company filter at all and silently received every company's templates. The
     * guard is now array_key_exists(), so an explicit null is distinguishable from
     * an absent key.
     */
    public function testGetAllTemplatesWithNullCompanyIdFiltersToGlobalTemplates(): void
    {
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => []])], [], $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $model->getAllTemplates(['company_id' => null]);

        $this->assertStringContainsString('company_id IS NULL', $calls[0]['sql']);
        $this->assertArrayNotHasKey(':company_id', $calls[0]['params']);
    }

    public function testGetAllTemplatesWithoutCompanyIdKeyAppliesNoCompanyFilter(): void
    {
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => []])], [], $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $model->getAllTemplates([]);

        $this->assertStringNotContainsString('company_id', $calls[0]['sql']);
        $this->assertArrayNotHasKey(':company_id', $calls[0]['params']);
    }

    public function testGetAllTemplatesWithCompanyIdUsesEqualityClause(): void
    {
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => []])], [], $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $model->getAllTemplates(['company_id' => 4]);

        $this->assertStringContainsString('company_id = :company_id', $calls[0]['sql']);
        $this->assertSame(4, $calls[0]['params'][':company_id']);
    }

    public function testGetAllTemplatesWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Template();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get templates:');

        $model->getAllTemplates();
    }

    // -------------------------------------------------------- getDefaultTemplate()

    public function testGetDefaultTemplateReturnsCompanySpecificDefaultWithoutFallback(): void
    {
        $companyDefault = (object)['id' => 1, 'name' => 'Company Default'];
        $calls = [];
        $db = $this->newDb([$this->statement(['fetch' => $companyDefault])], [], $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->getDefaultTemplate('project', 3);

        $this->assertSame($companyDefault, $result);
        $this->assertCount(1, $calls); // no fallback query needed
        $this->assertStringContainsString('company_id = :company_id', $calls[0]['sql']);
    }

    public function testGetDefaultTemplateFallsBackToGlobalWhenNoCompanySpecificDefault(): void
    {
        $globalDefault = (object)['id' => 2, 'name' => 'Global Default'];
        $calls = [];
        $db = $this->newDb([
            $this->statement(['fetch' => false]),
            $this->statement(['fetch' => $globalDefault]),
        ], [], $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->getDefaultTemplate('project', 3);

        $this->assertSame($globalDefault, $result);
        $this->assertCount(2, $calls);
        $this->assertStringContainsString('company_id IS NULL', $calls[1]['sql']);
    }

    public function testGetDefaultTemplateWithNullCompanyIdSkipsCompanyQuery(): void
    {
        $globalDefault = (object)['id' => 2, 'name' => 'Global Default'];
        $calls = [];
        $db = $this->newDb([$this->statement(['fetch' => $globalDefault])], [], $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->getDefaultTemplate('project', null);

        $this->assertSame($globalDefault, $result);
        $this->assertCount(1, $calls);
    }

    public function testGetDefaultTemplateReturnsNullWhenNoDefaultExistsAnywhere(): void
    {
        $db = $this->newDb([$this->statement(['fetch' => false])]);
        $this->seedDatabase($db);

        $model = new Template();

        $this->assertNull($model->getDefaultTemplate('project', null));
    }

    public function testGetDefaultTemplateWrapsExceptionInRuntimeException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Template();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get default template:');

        $model->getDefaultTemplate();
    }

    // ---------------------------------------------------------- setDefaultTemplate()

    public function testSetDefaultTemplateWithCompanyIdScopesClearToThatCompany(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true, true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->setDefaultTemplate(9, 'sprint', 4);

        $this->assertTrue($result);
        $this->assertStringContainsString('company_id = :company_id', $calls[0]['sql']);
        $this->assertSame(4, $calls[0]['params'][':company_id']);
        $this->assertSame('sprint', $calls[0]['params'][':template_type']);
        $this->assertStringContainsString('SET is_default = 1', $calls[1]['sql']);
        $this->assertSame(9, $calls[1]['params'][':template_id']);
    }

    public function testSetDefaultTemplateWithoutCompanyIdClearsGlobalDefaults(): void
    {
        $calls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true, true], $unusedQueryCalls, $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->setDefaultTemplate(9, 'project', null);

        $this->assertTrue($result);
        $this->assertStringContainsString('company_id IS NULL', $calls[0]['sql']);
        $this->assertArrayNotHasKey(':company_id', $calls[0]['params']);
    }

    public function testSetDefaultTemplateRollsBackAndThrowsOnFailure(): void
    {
        $db = $this->newDb([], [new RuntimeException('update failed')]);
        $db->method('beginTransaction');
        $db->expects($this->once())->method('rollBack');
        $db->expects($this->never())->method('commit');
        $this->seedDatabase($db);

        $model = new Template();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to set default template:');

        $model->setDefaultTemplate(9);
    }

    // ---------------------------------------------------------- getSprintTemplates()

    public function testGetSprintTemplatesEnrichesRowsWithDefaultSettingsWhenCacheEmpty(): void
    {
        $row = (object)['id' => 1, 'name' => 'Standard Sprint', 'template_type' => 'sprint'];
        $calls = [];
        $db = $this->newDb([$this->statement(['fetchAll' => [$row]])], [], $calls);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->getSprintTemplates(5);

        $this->assertSame(5, $calls[0]['params'][':company_id']);
        $this->assertSame(2, $result[0]->sprint_length);
        $this->assertSame('story_points', $result[0]->estimation_method);
        $this->assertSame(40, $result[0]->default_capacity);
        $this->assertFalse($result[0]->include_weekends);
        $this->assertTrue($result[0]->auto_assign_subtasks);
        $this->assertTrue($result[0]->ceremony_settings['sprint_planning']['enabled']);
    }

    public function testGetSprintTemplatesReadsValuesFromSettingsCacheNotHardcodedDefaults(): void
    {
        $this->seedSettingsCache([
            'sprints' => [
                'default_length' => '5',
                'estimation_method' => 'hours',
                'default_capacity' => '25',
                'include_weekends' => '1',
                'auto_assign_subtasks' => '0',
            ],
        ]);
        $row = (object)['id' => 1, 'name' => 'Custom Sprint', 'template_type' => 'sprint'];
        $db = $this->newDb([$this->statement(['fetchAll' => [$row]])]);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->getSprintTemplates();

        $this->assertSame(5, $result[0]->sprint_length);
        $this->assertSame('hours', $result[0]->estimation_method);
        $this->assertSame(25, $result[0]->default_capacity);
        $this->assertTrue($result[0]->include_weekends);
        $this->assertFalse($result[0]->auto_assign_subtasks);
    }

    public function testGetSprintTemplatesReturnsEmptyArrayOnException(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Template();

        $this->assertSame([], $model->getSprintTemplates());
    }

    // -------------------------------------------------------- createSprintTemplate()

    public function testCreateSprintTemplateWithoutConfigDataSkipsConfigurationStep(): void
    {
        $insertCalls = [];
        $unusedQueryCalls = [];
        $db = $this->newDb([], [true], $unusedQueryCalls, $insertCalls);
        $db->method('lastInsertId')->willReturn(15);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->createSprintTemplate([
            'name' => 'Basic',
            'description' => 'A basic template',
            'template_type' => TemplateType::SPRINT->value,
        ]);

        $this->assertSame(15, $result);
        $this->assertCount(1, $insertCalls); // only the templates INSERT ran
    }

    public function testCreateSprintTemplateWithConfigDataAlsoPersistsConfiguration(): void
    {
        $existsCheck = $this->statement(['fetch' => false]);
        $queryCalls = [];
        $insertCalls = [];
        $db = $this->newDb([$existsCheck], [true, true], $queryCalls, $insertCalls);
        $db->method('lastInsertId')->willReturn(21);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->createSprintTemplate(
            ['name' => 'With Config', 'description' => 'x', 'template_type' => TemplateType::SPRINT->value],
            ['sprint_length' => 3]
        );

        $this->assertSame(21, $result);
        // 1 INSERT for the template itself + 1 for the Setting upsert.
        $this->assertCount(2, $insertCalls);
        $this->assertStringContainsString('INSERT INTO settings', $insertCalls[1]['sql']);
        $this->assertSame('3', $insertCalls[1]['params'][':value']);
    }

    public function testCreateSprintTemplateRollsBackAndThrowsWhenCreateFails(): void
    {
        $db = $this->newDb([], [new RuntimeException('insert failed')]);
        $db->method('beginTransaction');
        $db->expects($this->once())->method('rollBack');
        $db->expects($this->never())->method('commit');
        $this->seedDatabase($db);

        $security = $this->createMock(SecurityService::class);
        $security->method('getSafeErrorMessage')->willReturn('safe message');
        $this->seedSecurityService($security);

        $model = new Template();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create sprint template:');

        $model->createSprintTemplate(['name' => 'x', 'description' => 'y', 'template_type' => 'sprint']);
    }

    // --------------------------------------------- createSprintTemplateConfiguration()

    public function testCreateSprintTemplateConfigurationPersistsEachProvidedKey(): void
    {
        $queryCalls = [];
        $insertCalls = [];
        $db = $this->newDb(
            [$this->statement(['fetch' => false])],
            [true, true, true, true, true],
            $queryCalls,
            $insertCalls
        );
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->createSprintTemplateConfiguration(1, [
            'sprint_length' => 4,
            'estimation_method' => 'hours',
            'default_capacity' => 30,
            'include_weekends' => true,
            'auto_assign_subtasks' => false,
        ]);

        $this->assertTrue($result);
        $this->assertCount(5, $insertCalls);
        $this->assertSame('4', $insertCalls[0]['params'][':value']);
        $this->assertSame('hours', $insertCalls[1]['params'][':value']);
        $this->assertSame('30', $insertCalls[2]['params'][':value']);
        $this->assertSame('1', $insertCalls[3]['params'][':value']);
        $this->assertSame('0', $insertCalls[4]['params'][':value']);
    }

    public function testCreateSprintTemplateConfigurationIgnoresKeysNotPresent(): void
    {
        $queryCalls = [];
        $insertCalls = [];
        $db = $this->newDb([$this->statement(['fetch' => false])], [true], $queryCalls, $insertCalls);
        $this->seedDatabase($db);

        $model = new Template();
        $result = $model->createSprintTemplateConfiguration(1, ['estimation_method' => 'story_points']);

        $this->assertTrue($result);
        $this->assertCount(1, $insertCalls);
        $this->assertSame('story_points', $insertCalls[0]['params'][':value']);
    }

    public function testCreateSprintTemplateConfigurationCatchesExceptionAndReturnsFalse(): void
    {
        $db = $this->newDb([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Template();

        $this->assertFalse($model->createSprintTemplateConfiguration(1, ['sprint_length' => 2]));
    }

    // -------------------------------------------- getSprintTemplateConfiguration()

    public function testGetSprintTemplateConfigurationReturnsDefaultsFromEmptyCache(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Template();
        $config = $model->getSprintTemplateConfiguration(7);

        $this->assertSame(7, $config->template_id);
        $this->assertSame(2, $config->sprint_length);
        $this->assertSame('story_points', $config->estimation_method);
        $this->assertSame(40, $config->default_capacity);
        $this->assertFalse($config->include_weekends);
        $this->assertTrue($config->auto_assign_subtasks);
        $this->assertTrue($config->ceremony_settings['daily_standup']['enabled']);
    }

    public function testGetSprintTemplateConfigurationReadsValuesFromSettingsCache(): void
    {
        $this->seedSettingsCache([
            'sprints' => [
                'default_length' => '3',
                'estimation_method' => 'hours',
                'default_capacity' => '30',
                'include_weekends' => '1',
                'auto_assign_subtasks' => '0',
            ],
        ]);
        $this->seedDatabase($this->createMock(Database::class));

        $model = new Template();
        $config = $model->getSprintTemplateConfiguration(7);

        $this->assertSame(3, $config->sprint_length);
        $this->assertSame('hours', $config->estimation_method);
        $this->assertSame(30, $config->default_capacity);
        $this->assertTrue($config->include_weekends);
        $this->assertFalse($config->auto_assign_subtasks);
    }

    public function testGetSprintTemplateConfigurationReturnsNullOnException(): void
    {
        $this->seedDatabase($this->createMock(Database::class));

        $settings = $this->createMock(SettingsService::class);
        $settings->method('getSettingInt')->willThrowException(new RuntimeException('boom'));
        $this->seedSettingsServiceInstance($settings);

        $model = new Template();

        $this->assertNull($model->getSprintTemplateConfiguration(7));
    }
}
