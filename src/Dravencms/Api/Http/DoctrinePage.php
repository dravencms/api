<?php declare(strict_types=1);

namespace Dravencms\Api\Http;

use Doctrine\ORM\QueryBuilder;

final class DoctrinePage
{
    public static function fetch(QueryBuilder $query, Query $parameters, callable $serialize): array
    {
        $limit = $parameters->integer('limit', 50, 1, 100);
        $offset = $parameters->integer('offset', 0, 0, 1000000);
        $rows = $query->setMaxResults($limit + 1)->setFirstResult($offset)->getQuery()->getResult();
        $hasNext = count($rows) > $limit;
        return [
            'data' => array_map($serialize, array_slice($rows, 0, $limit)),
            'pagination' => new Page($limit, $offset, $hasNext ? $offset + $limit : null),
        ];
    }
}
