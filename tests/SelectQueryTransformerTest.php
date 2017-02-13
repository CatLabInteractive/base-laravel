<?php

use CatLab\Base\Enum\Operator;
use CatLab\Base\Models\Database\DB;
use CatLab\Base\Models\Database\SelectQueryParameters;
use CatLab\Base\Models\Database\WhereParameter;
use CatLab\Laravel\Database\SelectQueryTransformer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Connection;

/**
 * Class SelectQueryTransformerTest
 */
class SelectQueryTransformerTest extends PHPUnit_Framework_TestCase
{
    private function getQueryBuilder()
    {
        return (new Builder(new Connection(new MockPDO())))->from('table');
    }

    /**
     * @test
     */
    public function testLaravelTransformer()
    {
        $selectQuery = new SelectQueryParameters();

        $selectQuery->where(
            (new WhereParameter('foo', Operator::EQ, 'bar'))
            ->or(
                (new WhereParameter('bar', Operator::LT, 15))
                ->and(new WhereParameter('cat', Operator::NEQ, 'catlab'))
            )
        );

        $builder = $this->getQueryBuilder();
        SelectQueryTransformer::toLaravel($builder, $selectQuery);

        $this->assertEquals(
            'select * from "table" where "foo" = ? or ("bar" < ? and ("cat" != ?))',
            $builder->toSql()
        );

        $bindings = $builder->getBindings();
        $this->assertEquals('bar', $bindings[0]);
        $this->assertEquals(15, $bindings[1]);
        $this->assertEquals('catlab', $bindings[2]);
    }

    /**
     * @test
     */
    public function testSearch()
    {
        $selectQuery = new SelectQueryParameters();

        $selectQuery->where(
            new WhereParameter('foo', Operator::SEARCH, 'bar')
        );

        $builder = $this->getQueryBuilder();
        SelectQueryTransformer::toLaravel($builder, $selectQuery);

        $this->assertEquals(
            'select * from "table" where "foo" LIKE ?',
            $builder->toSql()
        );

        $bindings = $builder->getBindings();
        $this->assertEquals('%bar%', $bindings[0]);
    }

    /**
     * @test
     */

    /*
    public function testRawContent()
    {
        $selectQuery = new SelectQueryParameters();

        $selectQuery->where(
            (new WhereParameter('foo', Operator::EQ, 'bar'))
                ->or(
                    (new WhereParameter(DB::raw('COUNT(bar)'), Operator::LT, 15))
                        ->and(new WhereParameter('cat', Operator::NEQ, 'catlab'))
                )
        );

        $builder = $this->getQueryBuilder();
        SelectQueryTransformer::toLaravel($builder, $selectQuery);

        $this->assertEquals(
            'select * from "table" where "foo" = ? or (COUNT(bar) < ? and ("cat" != ?))',
            $builder->toSql()
        );
    }*/
}

class MockPDO extends PDO
{
    public function __construct ()
    {}

}