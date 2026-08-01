<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Config;
use App\Core\Database;
use App\Models\BaseModel;
use App\Models\Favorite;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Behavioural tests for the Favorite model: user favorite listing, add/remove
 * with the optional item-id/url branches that shape the generated SQL,
 * existence checks, sort-order allocation (including its swallow-and-default
 * failure path) and the reorder transaction.
 *
 * The Database singleton is always swapped for a mock via reflection so no
 * real MySQL connection is opened (mirroring BaseModelTest/MilestoneTest).
 * Favorite does not use the Searchable trait and does not call
 * BaseModel::create()/update(), so no SearchIndex/SecurityService wiring is
 * needed here.
 */
// Config is declared because Database consults Config::isProduction(). It is a
// process-wide singleton, so only the first test in a run to reach it executes
// its body — which test that is moves with execution order (see MilestoneTest).
#[CoversClass(Favorite::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Config::class)]
#[UsesClass(Database::class)]
final class FavoriteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDatabase(null);
    }

    protected function tearDown(): void
    {
        $this->seedDatabase(null);

        parent::tearDown();
    }

    private function seedDatabase(?Database $db): void
    {
        (new ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $db);
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
     * Database mock whose executeQuery() replays $steps in order (each either
     * a PDOStatement to return or a Throwable to throw), recording every call's
     * SQL/params into $calls, repeating the last step for calls beyond the list.
     */
    private function dbSequenced(array $steps, ?array &$calls = null): Database
    {
        $calls = [];
        $db = $this->createMock(Database::class);
        $call = 0;
        $db->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = []) use (&$call, $steps, &$calls) {
                $calls[] = ['sql' => $sql, 'params' => $params];
                $step = $steps[$call] ?? end($steps);
                $call++;

                if ($step instanceof \Throwable) {
                    throw $step;
                }

                return $step;
            }
        );

        return $db;
    }

    // ------------------------------------------------------- getUserFavorites()

    public function testGetUserFavoritesReturnsRowsOrderedBySortOrder(): void
    {
        $rows = [(object)['id' => 1, 'page_title' => 'Home'], (object)['id' => 2, 'page_title' => 'Reports']];
        $calls = [];
        $db = $this->dbSequenced([$this->statement(['fetchAll' => $rows])], $calls);
        $this->seedDatabase($db);

        $model = new Favorite();
        $result = $model->getUserFavorites(7);

        $this->assertSame($rows, $result);
        $this->assertStringContainsString('WHERE user_id = :user_id', $calls[0]['sql']);
        $this->assertStringContainsString('ORDER BY sort_order ASC, created_at ASC', $calls[0]['sql']);
        $this->assertSame(7, $calls[0]['params'][':user_id']);
    }

    public function testGetUserFavoritesWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('db down')]);
        $this->seedDatabase($db);

        $model = new Favorite();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get user favorites: db down');

        $model->getUserFavorites(7);
    }

    // ------------------------------------------------------------ addFavorite()

    public function testAddFavoriteReturnsFalseWhenAlreadyExists(): void
    {
        // favoriteExists() query returns count > 0
        $calls = [];
        $db = $this->dbSequenced([
            $this->statement(['fetch' => (object)['count' => 1]]),
        ], $calls);
        $this->seedDatabase($db);

        $model = new Favorite();
        $result = $model->addFavorite(1, 'project', 'My Project', 5, '/projects/5', 'folder');

        $this->assertFalse($result);
        $this->assertCount(1, $calls); // only the existence check ran
    }

    public function testAddFavoriteInsertsWithNextSortOrderWhenNotExists(): void
    {
        $calls = [];
        $db = $this->dbSequenced([
            $this->statement(['fetch' => (object)['count' => 0]]), // favoriteExists -> false
            $this->statement(['fetch' => (object)['next_order' => 3]]), // getNextSortOrder
            $this->statement(['rowCount' => 1]), // insert
        ], $calls);
        $this->seedDatabase($db);

        $model = new Favorite();
        $result = $model->addFavorite(1, 'project', 'My Project', 5, '/projects/5', 'folder');

        $this->assertTrue($result);
        $this->assertStringContainsString('INSERT INTO user_favorites', $calls[2]['sql']);
        $this->assertSame(3, $calls[2]['params'][':sort_order']);
        $this->assertSame(1, $calls[2]['params'][':user_id']);
        $this->assertSame('project', $calls[2]['params'][':type']);
        $this->assertSame(5, $calls[2]['params'][':item_id']);
        $this->assertSame('/projects/5', $calls[2]['params'][':url']);
        $this->assertSame('My Project', $calls[2]['params'][':title']);
        $this->assertSame('folder', $calls[2]['params'][':icon']);
    }

    public function testAddFavoriteReturnsFalseWhenInsertAffectsNoRows(): void
    {
        $db = $this->dbSequenced([
            $this->statement(['fetch' => (object)['count' => 0]]),
            $this->statement(['fetch' => (object)['next_order' => 1]]),
            $this->statement(['rowCount' => 0]),
        ]);
        $this->seedDatabase($db);

        $model = new Favorite();

        $this->assertFalse($model->addFavorite(1, 'page', 'Dashboard'));
    }

    public function testAddFavoriteGetsDefaultSortOrderWhenSortOrderQueryFails(): void
    {
        $calls = [];
        $db = $this->dbSequenced([
            $this->statement(['fetch' => (object)['count' => 0]]), // favoriteExists -> false
            new RuntimeException('lookup failed'), // getNextSortOrder -> caught, defaults to 1
            $this->statement(['rowCount' => 1]), // insert
        ], $calls);
        $this->seedDatabase($db);

        $model = new Favorite();
        $result = $model->addFavorite(1, 'page', 'Dashboard');

        $this->assertTrue($result);
        $this->assertSame(1, $calls[2]['params'][':sort_order']);
    }

    public function testAddFavoriteWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Favorite();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add favorite:');

        $model->addFavorite(1, 'page', 'Dashboard');
    }

    // --------------------------------------------------------- removeFavorite()

    public function testRemoveFavoriteWithItemIdAndUrlBuildsBothClauses(): void
    {
        $calls = [];
        $db = $this->dbSequenced([$this->statement(['rowCount' => 1])], $calls);
        $this->seedDatabase($db);

        $model = new Favorite();
        $result = $model->removeFavorite(1, 'project', 5, '/projects/5');

        $this->assertTrue($result);
        $this->assertStringContainsString('AND favorite_id = :item_id', $calls[0]['sql']);
        $this->assertStringContainsString('AND page_url = :url', $calls[0]['sql']);
        $this->assertSame(5, $calls[0]['params'][':item_id']);
        $this->assertSame('/projects/5', $calls[0]['params'][':url']);
    }

    public function testRemoveFavoriteWithoutItemIdOrUrlUsesIsNullClauses(): void
    {
        $calls = [];
        $db = $this->dbSequenced([$this->statement(['rowCount' => 0])], $calls);
        $this->seedDatabase($db);

        $model = new Favorite();
        $result = $model->removeFavorite(1, 'page');

        $this->assertFalse($result);
        $this->assertStringContainsString('AND favorite_id IS NULL', $calls[0]['sql']);
        $this->assertStringContainsString('AND page_url IS NULL', $calls[0]['sql']);
        $this->assertArrayNotHasKey(':item_id', $calls[0]['params']);
        $this->assertArrayNotHasKey(':url', $calls[0]['params']);
    }

    public function testRemoveFavoriteWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Favorite();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to remove favorite:');

        $model->removeFavorite(1, 'page');
    }

    // -------------------------------------------------------- favoriteExists()

    public function testFavoriteExistsReturnsTrueWhenCountPositive(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetch' => (object)['count' => 2]])]);
        $this->seedDatabase($db);

        $model = new Favorite();

        $this->assertTrue($model->favoriteExists(1, 'project', 5, '/projects/5'));
    }

    public function testFavoriteExistsReturnsFalseWhenCountZero(): void
    {
        $db = $this->dbSequenced([$this->statement(['fetch' => (object)['count' => 0]])]);
        $this->seedDatabase($db);

        $model = new Favorite();

        $this->assertFalse($model->favoriteExists(1, 'project', 5, '/projects/5'));
    }

    public function testFavoriteExistsWrapsExceptionInRuntimeException(): void
    {
        $db = $this->dbSequenced([new RuntimeException('boom')]);
        $this->seedDatabase($db);

        $model = new Favorite();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to check favorite existence:');

        $model->favoriteExists(1, 'project');
    }

    // ------------------------------------------------------- updateSortOrder()

    public function testUpdateSortOrderIssuesOneUpdatePerFavoriteAndCommits(): void
    {
        $calls = [];
        $db = $this->dbSequenced([
            $this->statement(['rowCount' => 1]),
            $this->statement(['rowCount' => 1]),
        ], $calls);
        $db->expects($this->once())->method('beginTransaction')->willReturn(true);
        $db->expects($this->once())->method('commit')->willReturn(true);
        $db->expects($this->never())->method('rollBack');
        $this->seedDatabase($db);

        $model = new Favorite();
        $result = $model->updateSortOrder(9, [101, 102]);

        $this->assertTrue($result);
        $this->assertCount(2, $calls);
        $this->assertSame(1, $calls[0]['params'][':sort_order']);
        $this->assertSame(101, $calls[0]['params'][':id']);
        $this->assertSame(9, $calls[0]['params'][':user_id']);
        $this->assertSame(2, $calls[1]['params'][':sort_order']);
        $this->assertSame(102, $calls[1]['params'][':id']);
    }

    public function testUpdateSortOrderRollsBackAndThrowsOnFailure(): void
    {
        $db = $this->dbSequenced([new RuntimeException('update failed')]);
        $db->method('beginTransaction')->willReturn(true);
        $db->expects($this->once())->method('rollBack')->willReturn(true);
        $db->expects($this->never())->method('commit');
        $this->seedDatabase($db);

        $model = new Favorite();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update sort order:');

        $model->updateSortOrder(9, [101]);
    }
}
